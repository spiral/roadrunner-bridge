<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Compiler;

use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;

/**
 * Compiles GRPC protobuf declarations with buf and moves files into proper location.
 *
 * @internal
 */
final readonly class ProtoCompiler
{
    private string $baseNamespace;

    /**
     * @param non-empty-string $basePath Directory for the generated files.
     * @param string $baseNamespace Namespace prefix cut from the generated file paths.
     * @param string $workingDir Directory buf runs in: relative paths of a user template resolve from it.
     */
    public function __construct(
        private string $basePath,
        string $baseNamespace,
        private FilesInterface $files,
        private BufCommandBuilder $commandBuilder,
        private ProcessRunner $runner,
        private BufTemplate $template,
        private string $workingDir,
    ) {
        $this->baseNamespace = \str_replace('\\', '/', \rtrim($baseNamespace, '\\'));
    }

    /**
     * With the local template, the proto files imported from the input are generated as well: the generated
     * code references their classes. A user template decides that itself (`include_imports`).
     *
     * @param string $input Existing input directory; imports are resolved from it.
     * @param list<string> $protoFiles Existing proto files inside the input. Generation covers the whole
     *        directory of each file, including subdirectories.
     *
     * @throws CompileException
     */
    public function compile(string $input, array $protoFiles): CompileResult
    {
        $paths = self::paths($input, $protoFiles);

        if ($this->template->file !== null) {
            return $this->compileWithUserTemplate($input, $paths, $this->template->file);
        }

        // An empty list already covers the whole input.
        $paths === [] or $paths = [...$paths, ...$this->importsOutside($input, $paths)];

        return $this->compileWithLocalTemplate($input, $paths);
    }

    /**
     * A commented-out import must not pull in a file that may not even compile. Strings are matched in the same
     * pass, so `//` or `/*` inside one (`go_package = "example.com/x/*"`) does not start a comment. A comment
     * becomes a space: protoc accepts one between `import` and the path with no whitespace around it.
     *
     * @psalm-pure
     */
    private static function stripComments(string $code): string
    {
        // Branches are unrolled with possessive quantifiers: a per-character alternation exhausts the PCRE stack
        // on a literal of a few kilobytes, and a failed match would silently lose every import.
        $result = \preg_replace_callback(
            '#"[^"\\\\\n]*+(?:\\\\.[^"\\\\\n]*+)*+"|\'[^\'\\\\\n]*+(?:\\\\.[^\'\\\\\n]*+)*+\'|//[^\n]*|/\*[^*]*+(?:\*(?!/)[^*]*+)*+\*/#',
            static fn(array $match): string => $match[0][0] === '/' ? ' ' : $match[0],
            $code,
        );

        return $result ?? throw new CompileException(
            \sprintf('Unable to read the imports of a proto file: %s.', \preg_last_error_msg()),
        );
    }

    /**
     * @param list<string> $dirs
     *
     * @psalm-pure
     */
    private static function isInside(string $file, array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if (\str_starts_with($file, $dir . \DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * buf rejects a `--path` equal to the input and needs none to cover the whole input.
     *
     * @param list<string> $protoFiles
     *
     * @return list<string>
     *
     * @psalm-pure
     */
    private static function paths(string $input, array $protoFiles): array
    {
        $input = \rtrim($input, '\\/');
        $dirs = \array_unique(\array_map(static fn(string $file): string => \dirname($file), $protoFiles));
        \sort($dirs);

        $result = [];
        foreach ($dirs as $dir) {
            if ($dir === $input) {
                return [];
            }

            foreach ($result as $parent) {
                if (\str_starts_with($dir, $parent . \DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $result[] = $dir;
        }

        return $result;
    }

    /**
     * Proto files that the files under the given directories import, directly or transitively, and that lie
     * in the input outside those directories.
     *
     * Not buf's `--include-imports`: it also generates buf.yaml dependencies (e.g. googleapis, which ship with
     * google/common-protos). An import that does not resolve inside the input (a dependency or a Well-Known
     * Type) is left to buf, as is a `google/...` one: a vendored copy of googleapis or of the Well-Known Types
     * would duplicate the classes of google/common-protos and google/protobuf.
     *
     * @param non-empty-list<string> $dirs Real paths of directories inside the input.
     *
     * @return list<string> Real paths of the imported files.
     */
    private function importsOutside(string $input, array $dirs): array
    {
        $queue = [];
        foreach ($dirs as $dir) {
            foreach ($this->files->getFiles($dir) as $file) {
                \str_ends_with($file, '.proto') and $queue[] = (string) \realpath($file);
            }
        }

        $input = \rtrim($input, '\\/');
        $seen = \array_fill_keys($queue, true);
        $result = [];
        while ($queue !== []) {
            $code = self::stripComments((string) \file_get_contents(\array_shift($queue)));
            \preg_match_all('/\bimport\s*(?:(?:public|weak)\s*)?(["\'])([^"\']+)\1\s*;/', $code, $matches);

            foreach ($matches[2] as $import) {
                if (\str_starts_with($import, 'google/')) {
                    continue;
                }

                $file = \realpath($input . \DIRECTORY_SEPARATOR . $import);
                if ($file === false || isset($seen[$file]) || !self::isInside($file, [$input])) {
                    continue;
                }

                $seen[$file] = true;
                $queue[] = $file;
                self::isInside($file, $dirs) or $result[] = $file;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $paths
     */
    private function compileWithLocalTemplate(string $input, array $paths): CompileResult
    {
        $workDir = $this->files->normalizePath(\sys_get_temp_dir() . '/spiral-grpc-' . \bin2hex(\random_bytes(8)));
        $outDir = $workDir . '/out';
        $templateFile = $workDir . '/buf.gen.yaml';

        try {
            $this->files->ensureDirectory($outDir);
            $this->files->write($templateFile, $this->template->render($outDir));

            $warnings = $this->runner->run($this->commandBuilder->build($input, $templateFile, $paths), $this->workingDir);

            $result = [];
            $foreign = [];
            foreach ($this->files->getFiles($outDir) as $file) {
                $source = \ltrim($this->files->relativePath($file, $outDir), '\\/');
                if ($this->baseNamespace !== '') {
                    // Whole segments only: the prefix `App` must leave `AppExtra/...` alone.
                    if (\str_starts_with($source, $this->baseNamespace . '/')) {
                        $source = \substr($source, \strlen($this->baseNamespace) + 1);
                    } elseif (\str_contains($source, '/')) {
                        $foreign[] = \strstr($source, '/', true);
                    }
                }

                $result[] = $this->copy($file, $source);
            }
        } finally {
            $this->files->deleteDirectory($workDir);
        }

        return new CompileResult($result, $warnings, \array_values(\array_unique($foreign)));
    }

    /**
     * The template's `out` directories are not known here, so the result is every file under the base path
     * that the run created or touched.
     *
     * @param list<string> $paths
     */
    private function compileWithUserTemplate(string $input, array $paths, string $templateFile): CompileResult
    {
        $before = $this->snapshot();
        $startedAt = \time();

        $warnings = $this->runner->run($this->commandBuilder->build($input, $templateFile, $paths), $this->workingDir);

        $result = [];
        foreach ($this->snapshot() as $file => $mtime) {
            // filemtime() has a one-second resolution, so a rewrite within the second of the previous one
            // keeps the old value.
            if (!isset($before[$file]) || $before[$file] !== $mtime || $mtime >= $startedAt) {
                $result[] = $file;
            }
        }

        return new CompileResult($result, $warnings);
    }

    /**
     * @return array<string, int> File path => modification time.
     */
    private function snapshot(): array
    {
        if (!$this->files->isDirectory($this->basePath)) {
            return [];
        }

        \clearstatcache();

        $result = [];
        foreach ($this->files->getFiles($this->basePath) as $file) {
            $result[$file] = (int) \filemtime($file);
        }

        return $result;
    }

    /**
     * @param string $source Target path relative to the base path.
     */
    private function copy(string $file, string $source): string
    {
        $target = $this->files->normalizePath($this->basePath . '/' . $source);

        $this->files->ensureDirectory(\dirname($target));
        $this->files->copy($file, $target);

        return $target;
    }
}
