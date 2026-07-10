<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Extension;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

#[Extension('x-entity', 'App\Entity\User')]
#[Extension('x-doc-group', 'users')]
#[Extension('x-internal', true)]
class ExtendedView implements ViewInterface
{
    public string $name = '';
}
