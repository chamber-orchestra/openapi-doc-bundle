<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View;

use ChamberOrchestra\ViewBundle\View\ViewInterface;

class SimpleView implements ViewInterface
{
    public string $name = '';
    public int $age = 0;
    public bool $active = false;
    public float $score = 0.0;
}
