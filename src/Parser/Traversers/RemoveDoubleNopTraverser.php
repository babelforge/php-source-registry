<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Traversers;

use PhpNoobs\PhpSource\Parser\AbstractNodeTraverser;
use PhpNoobs\PhpSource\Parser\Traversers\Visitors\RemoveDoubleNopVisitor;

class RemoveDoubleNopTraverser extends AbstractNodeTraverser
{
    public function __construct()
    {
        parent::__construct(new RemoveDoubleNopVisitor());
    }
}
