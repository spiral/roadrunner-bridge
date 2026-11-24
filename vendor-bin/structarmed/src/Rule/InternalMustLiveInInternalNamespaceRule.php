<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * The converse of InternalNamespaceMustBeAnnotatedInternalRule: an @internal class
 * must live under an `Internal` namespace segment, because the module boundary rule
 * recognises internals by that segment alone.
 */
final readonly class InternalMustLiveInInternalNamespaceRule implements RuleInterface
{
    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInternal && $classNode->isInLayer($this->layer);
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
