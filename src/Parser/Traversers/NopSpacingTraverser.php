<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Traversers;

use BabelForge\PhpSource\Parser\AbstractNodeTraverser;
use BabelForge\PhpSource\Parser\Traversers\Visitors\NopSpacingVisitor;

class NopSpacingTraverser extends AbstractNodeTraverser
{
    public function __construct()
    {
        parent::__construct(new NopSpacingVisitor());
    }
}
