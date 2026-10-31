<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * The converse of InternalNamespaceMustBeAnnotatedInternalRule: an @internal class
 * must live under an `Internal` namespace segment, because the module boundary rule
 * recognises internals by that segment alone. Composition-root layers are exempt:
 * their classes are internal, yet they wire every module and belong to none.
 */
final readonly class InternalMustLiveInInternalNamespaceRule implements RuleInterface
{
    /**
     * @param list<string> $compositionRoots layers whose @internal classes may live anywhere
     */
    public function __construct(
        private string $layer,
        private array $compositionRoots,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        if (! $classNode->isInternal || ! $classNode->isInLayer($this->layer)) {
            return false;
        }

        foreach ($this->compositionRoots as $root) {
            if ($classNode->isInLayer($root)) {
                return false;
            }
        }

        return true;
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return \str_contains($classNode->className, '\\Internal\\') ? null : new RuleViolation(
            message: \sprintf('%s [%s] is annotated @internal and must live under an Internal namespace', $classNode->getType(), $classNode->className),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
