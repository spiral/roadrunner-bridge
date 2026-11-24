<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\AbstractLayerAwareRule;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * An Internal namespace is private to its module: `Queue\Internal\*` is reachable
 * from anywhere in `Queue\`, but not from `GRPC\` or `Centrifugo\`. The owning module
 * is the namespace prefix in front of the `Internal` segment, so a new module is
 * covered without being listed.
 *
 * The bridge is a single package, so its internals still have to be wired somewhere:
 * the composition-root layers (bootloaders, console commands, the fallback dispatcher)
 * may reach into any module. Vendor `Internal` namespaces (not scanned) are out of scope.
 */
final class InternalIsUsableOnlyWithinItsModuleRule extends AbstractLayerAwareRule implements RuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    /**
     * @param list<string> $compositionRoots layers allowed to use any module's internals
     */
    public function __construct(
        private readonly string $layer,
        private readonly array $compositionRoots,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        if (! $classNode->isInLayer($this->layer)) {
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
        return $this->evaluateAll($classNode)[0] ?? null;
    }

    /**
     * @return list<RuleViolation>
     */
    public function evaluateAll(ClassNode $classNode): array
    {
        $violations = [];

        foreach ($classNode->dependencies as $dependency) {
            $position = \strpos($dependency, self::SEGMENT);

            if ($position === false || $this->getDependencyNode($dependency) === null) {
                continue;
            }

            $owner = \substr($dependency, 0, $position);

            if (\str_starts_with($classNode->className, $owner . '\\')) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] must not use internal [%s] — it is private to module [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $dependency,
                    $owner,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }
}
