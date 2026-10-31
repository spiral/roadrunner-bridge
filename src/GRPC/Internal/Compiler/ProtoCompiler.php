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
     */
    public function __construct(
        private string $basePath,
        string $baseNamespace,
        private FilesInterface $files,
        private BufCommandBuilder $commandBuilder,
        private ProcessRunner $runner,
        private BufTemplate $template,
    ) {
        $this->baseNamespace = \str_replace('\\', '/', \rtrim($baseNamespace, '\\'));
    }

    /**
     * @param string $input Existing input directory; imports are resolved from it.
     * @param list<string> $protoFiles Existing proto files inside the input. Generation covers the whole
     *        directory of each file, including subdirectories.
     *
     * @throws CompileException
     */
    public function compile(string $input, array $protoFiles): CompileResult
    {
        $paths = self::paths($input, $protoFiles);

        return $this->template->file === null
            ? $this->compileWithLocalTemplate($input, $paths)
            : $this->compileWithUserTemplate($input, $paths, $this->template->file);
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

            $warnings = $this->runner->run($this->commandBuilder->build($input, $templateFile, $paths));

            $result = [];
            foreach ($this->files->getFiles($outDir) as $file) {
                $result[] = $this->copy($outDir, $file);
            }
        } finally {
            $this->files->deleteDirectory($workDir);
        }

        return new CompileResult($result, $warnings);
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

        $warnings = $this->runner->run($this->commandBuilder->build($input, $templateFile, $paths));

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

    private function copy(string $tmpDir, string $file): string
    {
        $source = \ltrim($this->files->relativePath($file, $tmpDir), '\\/');
        if (\str_starts_with($source, $this->baseNamespace)) {
            $source = \ltrim(\substr($source, \strlen($this->baseNamespace)), '\\/');
        }

        $target = $this->files->normalizePath($this->basePath . '/' . $source);

        $this->files->ensureDirectory(\dirname($target));
        $this->files->copy($file, $target);

        return $target;
    }
}
