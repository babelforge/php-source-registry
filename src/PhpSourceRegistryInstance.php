<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource;

use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\Contracts\ParserInterface;
use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\Printer\NopPrinter;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class PhpSourceRegistryInstance
 */
class PhpSourceRegistryInstance
{
    protected ParserInterface $parser;
    protected Standard $printer;
    protected readonly Standard $standardPrinter;
    protected readonly VirtualPhpSourceFileProducer $virtualRegistryFileProducer;
    protected readonly VirtualPhpSourceFileAssembler $virtualFileAssembler;

    /**
     * @var PhpSourceFile[]
     */
    protected array $files = [];
    /**
     * @var string[]
     */
    protected array $watchedFiles = [];

    public function __construct(
        protected ?FileWriterInterface $fileWriter = null,
        protected ?LoggerInterface $logger = null,
    )
    {
        $this->parser = new UserLandParser();
        $this->printer = new NopPrinter();
        $this->standardPrinter = new Standard();
        $this->virtualFileAssembler = new VirtualPhpSourceFileAssembler();
        $this->virtualRegistryFileProducer = new VirtualPhpSourceFileProducer(
            $this->parser,
            $this->printer,
            $this->standardPrinter,
        );
    }


    /**
     * @param string $virtualFilePath
     * @param Node[] $nodes
     * @return void
     */
    public function updateVirtualFileAst(string $virtualFilePath, array $nodes): void
    {
        $virtualFile = $this->getVirtualFile($virtualFilePath);
        $this->log("Updating $virtualFilePath");
        if ($this->isWatchedFile($virtualFilePath)) {
            $this->log("Found tested file $virtualFilePath");
            $code = $this->standardPrint($nodes);
            $code .= '';
        }
        $virtualFile->update($nodes);
    }


    public function hasFile(string $filePath): bool
    {
        return array_any($this->files, fn ($file) => $file->path === $filePath);
    }

    public function getFile(string $filePath): ?PhpSourceFile
    {
        return array_find($this->files, fn ($file) => $file->path === $filePath);
    }

    /**
     * @param string $filePath
     * @return VirtualPhpSourceFileCollection
     */
    public function getVirtualFiles(string $filePath): VirtualPhpSourceFileCollection
    {
        if (null === $file = $this->getFile($filePath)) {
            $originalNonTransformedNodes = $this->parser->simpleParseFile($filePath);
            $virtualFiles = $this->virtualRegistryFileProducer->produceVirtualPhpSourceFiles(
                $filePath,
                $originalNonTransformedNodes,
            );
            $file = $this->addVirtualFiles($filePath, $virtualFiles);
        }

        return $file->virtualFiles;
    }

    protected function addVirtualFiles(string $filePath, VirtualPhpSourceFileCollection $virtualFiles): PhpSourceFile
    {
        $file = new PhpSourceFile(
            $filePath,
            $virtualFiles,
            $this->fileWriter,
            $this->parser,
            $this->printer,
            $this->standardPrinter,
            $this->virtualFileAssembler,
            $this->logger
        );

        $this->files[] = $file;

        return $file;
    }

    public function getAllVirtualFiles(): VirtualPhpSourceFileCollection
    {
        $virtualFilesCollection = new VirtualPhpSourceFileCollection();
        foreach ($this->files as $file) {
            $virtualFilesCollection->merge($this->getVirtualFiles($file->path));
        }

        return $virtualFilesCollection;
    }

    /**
     * @return Node[]
     */
    private function getFileAst(string $filePath, bool $reloadIfNotUpdated = false): array
    {
        if (null === $file = $this->getFile($filePath)) {
            $this->getVirtualFiles($filePath);
            $file = $this->getFile($filePath);
        }

        if (null === $file) {
            throw new RuntimeException("File $filePath not found");
        }

        return $file->getAst($reloadIfNotUpdated);
    }

    public function getVirtualFile(string $virtualFilePath): VirtualPhpSourceFile
    {
        foreach ($this->files as $file) {
            if (null !== $virtualFile = $file->getVirtualFile($virtualFilePath)) {
                return $virtualFile;
            }
        }

        throw new RuntimeException("Virtual file $virtualFilePath not found");
    }

    /**
     * @return Node[]
     */
    public function getVirtualFileAst(string $virtualFilePath): array
    {
        return $this->getVirtualFile($virtualFilePath)->getAst();
    }


    public function save(): void
    {
        $this->log(str_repeat('=', 100));
        $this->log("Saving files");
        foreach ($this->files as $file) {
            if (!$file->isUpdated()) {
                continue;
            }

            $code = $file->save();

            if ($this->isWatchedFile($file->path)) {
                $this->log("Found tested file $file->path");
            }
            $this->log($code);
            $this->log(str_repeat('-', 100));
        }
    }

    public function addWatchedFile(string $file): void
    {
        if (!in_array($file, $this->watchedFiles, true)) {
            $this->watchedFiles[] = $file;
        }
    }

    public function isWatchedFile(string $testedFile): bool
    {
        return array_any($this->watchedFiles, fn ($file) => str_contains($file, $testedFile));
    }

    public function code(string $filePath): string
    {
        $ast = $this->getFileAst($filePath, true);

        return $this->standardPrint($ast);
    }


    /**
     * @param ParserInterface $parser
     * @return void
     * @api
     */
    public function setParser(ParserInterface $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * @param Standard $printer
     * @return void
     * @api
     */
    public function setPrinter(Standard $printer): void
    {
        $this->printer = $printer;
    }

    /**
     * @param Node[] $nodes
     * @return string
     */
    public function print(array $nodes): string
    {
        return $this->printer->prettyPrintFile($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return string
     */
    public function standardPrint(array $nodes): string
    {
        return $this->standardPrinter->prettyPrintFile($nodes);
    }

    private function log(string $message): void
    {
        $this->logger?->info($message);
    }
}
