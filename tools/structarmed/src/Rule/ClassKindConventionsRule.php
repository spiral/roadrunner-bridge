<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Support\Ancestry;

/**
 * A kind of class (exception, console command, ...) carries the kind's suffix and
 * extends one of its bases. Only classes are checked: an interface of the kind (e.g.
 * a marker extending Throwable) is left alone.
 *
 * A class is of the kind when it lives under the given namespace segment or, without
 * a segment, when it has the suffix or extends a base. Kinds are matched here rather
 * than as layers: a `layerPattern()` would take precedence over the path layers and
 * move the matched classes out of their module's ruleset.
 */
final readonly class ClassKindConventionsRule implements RuleInterface, MultipleRuleViolationInterface
{
    /**
     * @param non-empty-list<class-string> $bases
     */
    public function __construct(
        private string $layer,
        private string $suffix,
        private array $bases,
        private ?string $segment = null,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        if (! $classNode->isClass() || ! $classNode->isInLayer($this->layer)) {
            return false;
        }

        return $this->segment === null
            ? $classNode->nameEndsWith($this->suffix) || Ancestry::extendsAny($classNode, $this->bases)
            : \str_contains($classNode->className, '\\' . $this->segment . '\\');
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return $this->evaluateAll($classNode)[0] ?? null;
    }

    /**
     * @return list<RuleViolation>
     */
    public function evaluateAll(ClassNode $classNode): array
    {
        $messages = [];

        $classNode->nameEndsWith($this->suffix) or $messages[] = \sprintf(
            'Class [%s] must have suffix [%s]',
            $classNode->className,
            $this->suffix,
        );

        Ancestry::extendsAny($classNode, $this->bases) or $messages[] = \sprintf(
            'Class [%s] must extend one of [%s]',
            $classNode->className,
            \implode(', ', $this->bases),
        );

        return \array_map(
            static fn(string $message): RuleViolation => new RuleViolation(
                message: $message,
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            ),
            $messages,
        );
    }
}
