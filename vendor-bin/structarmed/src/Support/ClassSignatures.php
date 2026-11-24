<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Support;

use PhpParser\Comment\Doc;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Extracts the fully-qualified type names a class exposes in its docblocks: the
 * class docblock's `@method`, `@property`, `@extends`, `@template` and similar tags,
 * and the `@param`, `@return` and `@var` tags of its public and protected members.
 * Protected members of a final class and members annotated @internal are not surface.
 * Also gives the lines those surface members span.
 *
 * StructArmed's ClassNode::$signatures covers the native types, but neither docblocks
 * nor which member a parameter type belongs to; the lines tell the latter.
 * Names are resolved to FQCN with NameResolver so callers can match against a
 * namespace. Parsed once per file and cached.
 */
final class ClassSignatures
{
    private const MEMBER_TAGS = 'param-out|param|return|var|self-out|this-out';
    private const CLASS_TAGS = 'property-read|property-write|property|method|extends|implements|use|mixin|template-covariant|template-contravariant|template';

    /** Tags whose types do not come first: `@method Ret name(Arg $a)`, `@template T of Bound`. */
    private const LINE_TAGS = ['method', 'template', 'template-covariant', 'template-contravariant'];

    /** @var array<string, array<string, array{list<string>, array<int, true>}>> file => short class name => [FQ docblock type names, surface member lines] */
    private static array $cache = [];

    /**
     * @return array{list<string>, array<int, true>}
     */
    public static function surface(string $file, string $shortName): array
    {
        return (self::$cache[$file] ??= self::parse($file))[$shortName] ?? [[], []];
    }

    /**
     * @return array<string, array{list<string>, array<int, true>}>
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
            $lines = [];
            self::collectDoc($class->getDocComment(), $context, self::CLASS_TAGS, $types);

            foreach ([...$class->getMethods(), ...$class->getProperties()] as $member) {
                $doc = $member->getDocComment();

                if (! self::isInternal($doc) && ! $member->isPrivate() && ! ($member->isProtected() && $final)) {
                    self::collectDoc($doc, $context, self::MEMBER_TAGS, $types);
                    $lines += \array_fill_keys(\range($member->getStartLine(), $member->getEndLine()), true);
                }
            }

            $signatures[$name] = [\array_values(\array_unique($types)), $lines];
        }

        return $signatures;
    }

    private static function isInternal(?Doc $doc): bool
    {
        return $doc !== null && \preg_match('/^[\/*\s]*@internal(?![\w-])/m', $doc->getText()) === 1;
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
