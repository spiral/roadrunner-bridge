<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Support;

use PhpParser\Comment\Doc;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Extracts the fully-qualified type names a class exposes on its public surface:
 * the parameter and return types of its public and protected methods, and the
 * types of its public and protected properties (promoted ones included), both
 * native and from their docblock tags, plus the types in the class docblock's
 * `@method`, `@property`, `@extends`, `@template` and similar tags. Protected
 * members of a final class and members annotated @internal are not surface.
 *
 * StructArmed's MethodNode records only counts (paramCount, hasReturnType), not
 * type names, so a signature-level rule has to read them from the AST. Names are
 * resolved to FQCN with NameResolver so callers can match against a namespace.
 * Parsed once per file and cached.
 */
final class ClassSignatures
{
    private const MEMBER_TAGS = 'param-out|param|return|var|self-out|this-out';
    private const CLASS_TAGS = 'property-read|property-write|property|method|extends|implements|use|mixin|template-covariant|template-contravariant|template';

    /** Tags whose types do not come first: `@method Ret name(Arg $a)`, `@template T of Bound`. */
    private const LINE_TAGS = ['method', 'template', 'template-covariant', 'template-contravariant'];

    /** @var array<string, array<string, list<string>>> file => short class name => FQ type names on its public surface */
    private static array $cache = [];

    /**
     * @return list<string>
     */
    public static function publicTypes(string $file, string $shortName): array
    {
        return (self::$cache[$file] ??= self::parse($file))[$shortName] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parse(string $file): array
    {
        $code = @\file_get_contents($file);

        if ($code === false) {
            return [];
        }

        try {
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];

            $nameResolver = new NameResolver();
            $ast = (new NodeTraverser($nameResolver))->traverse($ast);
        } catch (\Throwable) {
            return [];
        }

        // After the traversal the context holds the last namespace and its imports; with one
        // namespace per file (PSR-4) that is the context of every docblock in it.
        $context = $nameResolver->getNameContext();
        $signatures = [];

        foreach ((new NodeFinder())->findInstanceOf($ast, ClassLike::class) as $class) {
            $name = $class->name?->toString();

            if ($name === null) {
                continue;
            }

            $final = $class instanceof Class_ && $class->isFinal();
            $types = [];
            self::collectDoc($class->getDocComment(), $context, self::CLASS_TAGS, $types);

            foreach ($class->getMethods() as $method) {
                $exposed = ! self::isInternal($method->getDocComment())
                    && self::isExposed($method->isPrivate(), $method->isProtected(), $final);

                foreach ($method->params as $param) {
                    // A promoted param is also a property, exposed by either visibility.
                    $paramExposed = $exposed
                        || ($param->isPromoted() && self::isExposed($param->isPrivate(), $param->isProtected(), $final));

                    $paramExposed and self::collect($param->type, $types);
                }

                if ($exposed) {
                    self::collect($method->returnType, $types);
                    self::collectDoc($method->getDocComment(), $context, self::MEMBER_TAGS, $types);
                }
            }

            foreach ($class->getProperties() as $property) {
                $doc = $property->getDocComment();

                if (! self::isInternal($doc) && self::isExposed($property->isPrivate(), $property->isProtected(), $final)) {
                    self::collect($property->type, $types);
                    self::collectDoc($doc, $context, self::MEMBER_TAGS, $types);
                }
            }

            $signatures[$name] = \array_values(\array_unique($types));
        }

        return $signatures;
    }

    private static function isExposed(bool $private, bool $protected, bool $final): bool
    {
        return ! $private && ! ($protected && $final);
    }

    private static function isInternal(?Doc $doc): bool
    {
        return $doc !== null && \preg_match('/^[\/*\s]*@internal(?![\w-])/m', $doc->getText()) === 1;
    }

    /**
     * @param list<string> $types
     */
    private static function collect(?Node $type, array &$types): void
    {
        switch (true) {
            case $type instanceof Name:
                $types[] = $type->toString();
                break;
            case $type instanceof NullableType:
                self::collect($type->type, $types);
                break;
            case $type instanceof UnionType:
            case $type instanceof IntersectionType:
                foreach ($type->types as $inner) {
                    self::collect($inner, $types);
                }
                break;
                // A plain Identifier (int, string, void, self, static, ...) carries no namespace: ignore.
        }
    }

    /**
     * Every class-like name in the type expressions of the docblock's tags. Pseudo-types and
     * array-shape keys resolve to names that no scanned class has, so callers matching against
     * scanned classes can take them as they come.
     *
     * @param string $tags alternation of tag names, without the phpstan-/psalm- prefix
     * @param list<string> $types
     */
    private static function collectDoc(?Doc $doc, NameContext $context, string $tags, array &$types): void
    {
        $text = $doc?->getText() ?? '';
        \preg_match_all('/@(?:phpstan-|psalm-)?(' . $tags . ')\s+/', $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($matches as [[$tag, $offset], [$tagName]]) {
            $start = $offset + \strlen($tag);
            $expression = \in_array($tagName, self::LINE_TAGS, true)
                ? \strstr(\substr($text, $start) . "\n", "\n", true)
                : self::typeExpression($text, $start);
            \preg_match_all('/(?<![\w\\\\$-])\\\\?[A-Za-z_]\w*(?:\\\\\w+)*/', $expression, $names);

            foreach ($names[0] as $typeName) {
                $types[] = $typeName[0] === '\\'
                    ? \substr($typeName, 1)
                    : $context->getResolvedClassName(new Name($typeName))->toString();
            }
        }
    }

    /**
     * The type expression starting at $offset: up to the first whitespace outside brackets
     * that does not sit next to `:`, `|` or `&`, so `array<string, Foo> $foo` yields
     * `array<string, Foo>` and `\Closure(): Foo | null $f` yields `\Closure(): Foo | null`.
     */
    private static function typeExpression(string $text, int $offset): string
    {
        $depth = 0;
        $length = \strlen($text);

        for ($i = $offset; $i < $length; ++$i) {
            $char = $text[$i];

            if (\str_contains('<{(', $char)) {
                ++$depth;
            } elseif (\str_contains('>})', $char)) {
                --$depth;
            } elseif ($depth <= 0 && \ctype_space($char)) {
                $before = \rtrim(\substr($text, $offset, $i - $offset));
                $after = \ltrim(\substr($text, $i, 64));

                if (! \str_contains(':|&', \substr($before, -1) ?: ' ') && ! \str_contains(':|&', $after[0] ?? ' ')) {
                    break;
                }
            }
        }

        return \substr($text, $offset, $i - $offset);
    }
}
