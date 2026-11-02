<?php

declare(strict_types=1);

/**
 * Registers PSR-4 rules for the generated code from `GENERATED_AUTOLOAD`: a JSON object of namespace prefix
 * => directory, as in composer.json.
 */

$rules = \json_decode((string) \getenv('GENERATED_AUTOLOAD'), true, flags: \JSON_THROW_ON_ERROR);

\spl_autoload_register(static function (string $class) use ($rules): void {
    foreach ($rules as $prefix => $dir) {
        if (\str_starts_with($class, $prefix)) {
            $file = $dir . '/' . \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';
            \is_file($file) and require $file;
            return;
        }
    }
});
