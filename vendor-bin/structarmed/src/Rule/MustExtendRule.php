<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Support\Ancestry;

/**
 * Every class in the layer must extend one of the given parents, directly or through
 * an ancestor. A vendor intermediate (e.g. the scaffolder's AbstractCommand) has to be
 * listed itself, see Ancestry.
 */
final readonly class MustExtendRule implements RuleInterface
{
    /**
     * @param non-empty-list<class-string> $parents
     */
    public function __construct(
        private string $layer,
        private array $parents,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isClass() && $classNode->isInLayer($this->layer);
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return Ancestry::extendsAny($classNode, $this->parents) ? null : new RuleViolation(
            message: \sprintf('Class [%s] must extend one of [%s]', $classNode->className, \implode(', ', $this->parents)),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
