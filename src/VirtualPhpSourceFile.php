<?php

declare(strict_types=1);

namespace BabelForge\PhpSource;

use BabelForge\PhpSource\Contracts\ParserInterface;
use BabelForge\PhpSource\Parser\UserLandParser;
use BabelForge\PhpSource\Printer\NopPrinter;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class VirtualPhpSourceFile.
 *
 * Value object representing a virtual PHP file and its AST nodes.
 */
final class VirtualPhpSourceFile
{
    public string $code;
    /**
     * @var Node[]
     */
    public array $originalNonTransformedNodes;
    /**
     * @var Node[]
     */
    public array $nodes;
    public bool $isUpdated = false;

    /**
     * Constructor.
     *
     * @param string          $fullFilePath    the full file path
     * @param string          $virtualFilePath the virtual file path
     * @param Node[]          $nodes           the AST nodes representing the virtual file content
     * @param ParserInterface $parser          the parser
     * @param Standard        $printer         the printer
     * @param Standard        $standardPrinter the standard printer
     */
    public function __construct(
        public string $fullFilePath,
        public string $virtualFilePath,
        array $nodes,
        private readonly ParserInterface $parser = new UserLandParser(),
        private readonly Standard $printer = new NopPrinter(),
        private readonly Standard $standardPrinter = new Standard(),
    ) {
        $this->code = $this->standardPrinter->prettyPrintFile($nodes);
        $this->originalNonTransformedNodes = $this->parser->simpleParseCode($this->code, $this->virtualFilePath);
        $this->nodes = $this->parser->parseCode($this->code, $this->virtualFilePath);
    }

    /**
     * @return Node[]
     */
    public function getAst(): array
    {
        return $this->nodes;
    }

    public function reboot(): void
    {
        $this->isUpdated = false;
        $code = $this->standardPrint($this->nodes);

        $this->originalNonTransformedNodes = $this->parser->simpleParseCode($code, $this->virtualFilePath);
        // TODO : maybe useless
        $this->nodes = $this->parser->parseCode($code, $this->virtualFilePath);
    }

    public function isUpdated(): bool
    {
        return $this->isUpdated;
    }

    /**
     * @param Node[] $nodes
     */
    public function update(array $nodes): void
    {
        $this->isUpdated = true;
        $this->nodes = $nodes;
    }

    /**
     * @param Node[] $nodes
     */
    public function standardPrint(array $nodes): string
    {
        return $this->standardPrinter->prettyPrintFile($nodes);
    }

    /**
     * @param Node[] $nodes
     */
    public function print(array $nodes): string
    {
        return $this->printer->prettyPrintFile($nodes);
    }
}
