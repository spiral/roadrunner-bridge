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
 * native and from the `@param` / `@return` / `@var` tags of their docblocks.
 * Protected members of a final class are not surface.
 *
 * StructArmed's MethodNode records only counts (paramCount, hasReturnType), not
 * type names, so a signature-level rule has to read them from the AST. Names are
 * resolved to FQCN with NameResolver so callers can match against a namespace.
 * Parsed once per file and cached.
 */
final class ClassSignatures
{
    private const DOC_TAG = '/@(?:phpstan-|psalm-)?(?:param|return|var)\s+/';

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

            foreach ($class->getMethods() as $method) {
                $exposed = self::isExposed($method->isPrivate(), $method->isProtected(), $final);

                foreach ($method->params as $param) {
                    // A promoted param is a property: its own visibility decides, not the method's.
                    $paramExposed = $param->isPromoted()
                        ? self::isExposed($param->isPrivate(), $param->isProtected(), $final)
                        : $exposed;

                    $paramExposed and self::collect($param->type, $types);
                }

                if ($exposed) {
                    self::collect($method->returnType, $types);
                    self::collectDoc($method->getDocComment(), $context, $types);
                }
            }

            foreach ($class->getProperties() as $property) {
                if (self::isExposed($property->isPrivate(), $property->isProtected(), $final)) {
                    self::collect($property->type, $types);
                    self::collectDoc($property->getDocComment(), $context, $types);
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
     * @param list<string> $types
     */
    private static function collectDoc(?Doc $doc, NameContext $context, array &$types): void
    {
        $text = $doc?->getText() ?? '';
        \preg_match_all(self::DOC_TAG, $text, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$tag, $offset]) {
            $expression = self::typeExpression($text, $offset + \strlen($tag));
            \preg_match_all('/(?<![\w\\\\$-])\\\\?[A-Za-z_]\w*(?:\\\\\w+)*/', $expression, $names);

            foreach ($names[0] as $typeName) {
                $types[] = $typeName[0] === '\\'
                    ? \substr($typeName, 1)
                    : $context->getResolvedClassName(new Name($typeName))->toString();
            }
        }
    }

    /**
     * The type expression starting at $offset: up to the first whitespace outside brackets,
     * so `array<string, Foo> $foo` yields `array<string, Foo>`.
     */
    private static function typeExpression(string $text, int $offset): string
    {
        $depth = 0;

        for ($i = $offset, $length = \strlen($text); $i < $length; ++$i) {
            $char = $text[$i];

            if (\str_contains('<{(', $char)) {
                ++$depth;
            } elseif (\str_contains('>})', $char)) {
                --$depth;
            } elseif ($depth <= 0 && \ctype_space($char)) {
                break;
            }
        }

        return \substr($text, $offset, $i - $offset);
    }
}
