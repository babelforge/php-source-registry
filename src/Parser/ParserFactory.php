<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser;

use PhpParser\Parser;
use PhpParser\ParserFactory as PhpParserFactory;

class ParserFactory
{
    private static ?Parser $parser = null;

    public static function getParser(): Parser
    {
        if (null === self::$parser) {
            self::$parser = new PhpParserFactory()->createForNewestSupportedVersion();
        }

        return self::$parser;
    }

}
