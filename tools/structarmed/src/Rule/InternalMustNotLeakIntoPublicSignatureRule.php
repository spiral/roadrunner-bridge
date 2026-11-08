<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Analyser\ClassSignature;
use Boundwize\StructArmed\Rule\AbstractLayerAwareRule;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Boundwize\StructArmed\Rule\SignatureTypesAwareRuleInterface;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Support\ClassSignatures;

/**
 * A class on the public surface must not name an internal type in its signature:
 * a parent, interface or trait, or a public or protected parameter, return, or
 * property (native or PHPDoc type). A type is internal when it
 * lives under an `Internal` namespace or is annotated @internal — the annotation
 * counts too, because an @internal interface outside `Internal` is just as free to
 * change. Classes that are themselves internal are exempt.
 *
 * Only scanned types count: a vendor's internals (e.g. protobuf's
 * `Google\Protobuf\Internal\Message`, the base of every generated message) are that
 * vendor's business, not a leak of ours.
 */
final class InternalMustNotLeakIntoPublicSignatureRule extends AbstractLayerAwareRule implements SignatureTypesAwareRuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    public function __construct(
        private readonly string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer) && ! $this->isInternal($classNode);
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

        $supertypes = [$classNode->extends, ...$classNode->implements, ...$classNode->interfaceExtends, ...$classNode->traits];

        foreach (\array_filter($supertypes) as $type) {
            if (! $this->isInternalType($type)) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] must not extend, implement or use internal [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $type,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        [$types, $surfaceLines] = ClassSignatures::surface($classNode->file, $classNode->shortName());

        foreach ($classNode->signatures as $signature) {
            // Written in a surface member, or a promoted property exposed by its own visibility.
            $promotedExposed = $signature->kind === ClassSignature::PROPERTY && $signature->isPromoted
                && $signature->isExposed() && ! ($signature->visibility === 'protected' && $classNode->isFinal);

            if ($promotedExposed || isset($surfaceLines[$signature->line])) {
                $types[] = $signature->type;
            }
        }

        foreach (\array_unique($types) as $type) {
            if (! $this->isInternalType($type)) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] exposes internal type [%s] in its public signature',
                    $classNode->getType(),
                    $classNode->className,
                    $type,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }

    private function isInternalType(string $type): bool
    {
        $node = $this->getDependencyNode($type);

        return $node !== null && $this->isInternal($node);
    }

    private function isInternal(ClassNode $classNode): bool
    {
        return \str_contains($classNode->className, self::SEGMENT)
            || $classNode->isInternal;
    }
}
