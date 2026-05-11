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

/**
 * Class PhpSourceFile.
 */
final class PhpSourceFile
{
    /**
     * @var Node[]
     */
    public array $originalNonTransformedNodes = [];
    /**
     * @var Node[]
     */
    public array $nodes = [];

    public function __construct(
        public readonly string $path,
        public readonly VirtualPhpSourceFileCollection $virtualFiles,
        private readonly ?FileWriterInterface $fileWriter = null,
        private readonly ParserInterface $parser = new UserLandParser(),
        private readonly Standard $printer = new NopPrinter(),
        private readonly Standard $standardPrinter = new Standard(),
        private readonly VirtualPhpSourceFileAssembler $virtualFileAssembler = new VirtualPhpSourceFileAssembler(),
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->originalNonTransformedNodes = $this->parser->simpleParseFile($path);
        $this->nodes = $this->parser->parseFile($path);
    }

    public function getVirtualFile(string $virtualFilePath): ?VirtualPhpSourceFile
    {
        return $this->virtualFiles->getByPath($virtualFilePath);
    }

    /**
     * @return Node[]
     */
    public function getAst(bool $reloadIfNotUpdated = false): array
    {
        $useOriginalNodes = (true === $reloadIfNotUpdated && !$this->isUpdated());

        return $this->virtualFileAssembler->assemble($this->virtualFiles, $useOriginalNodes);
    }

    public function isUpdated(): bool
    {
        foreach ($this->virtualFiles as $virtualFile) {
            if ($virtualFile->isUpdated()) {
                return true;
            }
        }

        return false;
    }

    public function save(): string
    {
        $this->log("Saving $this->path");
        $code = $this->standardPrint($this->getAst());

        foreach ($this->virtualFiles as $virtualFile) {
            $virtualFile->reboot();
        }

        $this->fileWriter?->writeContent($this->path, $code);

        return $code;
    }

    /**
     * @param Node[] $nodes
     */
    public function print(array $nodes): string
    {
        return $this->printer->prettyPrintFile($nodes);
    }

    /**
     * @param Node[] $nodes
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
