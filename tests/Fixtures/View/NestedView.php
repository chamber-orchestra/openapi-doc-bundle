<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View;

use ChamberOrchestra\ViewBundle\View\ViewInterface;

class NestedView implements ViewInterface
{
    public string $title = '';
    public SimpleView $simple;
}
