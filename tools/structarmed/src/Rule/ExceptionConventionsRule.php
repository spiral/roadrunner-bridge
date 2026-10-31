<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Support\Ancestry;

/**
 * A class under an `Exception` namespace segment carries the `Exception` suffix and
 * extends one of the given bases. Interfaces there (e.g. a marker extending Throwable)
 * are left alone.
 *
 * Exceptions are matched by namespace rather than as a layer: a `layerPattern()` would
 * take precedence over the path layers and move every exception out of its module's
 * ruleset.
 */
final readonly class ExceptionConventionsRule implements RuleInterface, MultipleRuleViolationInterface
{
    /**
     * @param non-empty-list<class-string> $bases
     */
    public function __construct(
        private string $layer,
        private array $bases,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isClass()
            && $classNode->isInLayer($this->layer)
            && \str_contains($classNode->className, '\\Exception\\');
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

        $classNode->nameEndsWith('Exception') or $messages[] = \sprintf(
            'Class [%s] must have suffix [Exception]',
            $classNode->className,
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
