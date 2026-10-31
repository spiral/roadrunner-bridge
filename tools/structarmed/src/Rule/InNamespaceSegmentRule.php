<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * Narrows a rule to classes with the given namespace segment, e.g. every `...\Exception\*`.
 *
 * Not a `layerPattern()`: a class-name pattern takes precedence over the path layers when
 * StructArmed picks a class's primary layer, so the matched classes would drop out of their
 * module's ruleset.
 */
final readonly class InNamespaceSegmentRule implements RuleInterface
{
    public function __construct(
        private string $segment,
        private RuleInterface $rule,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return \str_contains($classNode->className, '\\' . $this->segment . '\\')
            && $this->rule->appliesTo($classNode);
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return $this->rule->evaluate($classNode);
    }
}
