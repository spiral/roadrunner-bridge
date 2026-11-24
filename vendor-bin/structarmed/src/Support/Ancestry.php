<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tools\StructArmed\Support;

use Boundwize\StructArmed\Analyser\ClassNode;

/**
 * Whether a class extends one of the given parents.
 *
 * StructArmed follows the parent chain only across scanned classes, so the chain ends
 * at the first vendor or built-in parent. A loaded ancestor (any built-in, e.g.
 * `\InvalidArgumentException`) is followed further with reflection; a vendor ancestor
 * is not loaded in the analyser's process and has to be listed itself.
 */
final class Ancestry
{
    /**
     * @param list<class-string> $parents
     */
    public static function extendsAny(ClassNode $classNode, array $parents): bool
    {
        $ancestors = \array_filter([$classNode->extends, ...$classNode->parentClasses]);

        foreach ($parents as $parent) {
            if ($classNode->extendsClass($parent)) {
                return true;
            }

            foreach ($ancestors as $ancestor) {
                if (\class_exists($ancestor, false) && \is_a($ancestor, $parent, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
