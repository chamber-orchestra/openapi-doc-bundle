<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Operation;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\SimpleView;
use Symfony\Component\Routing\Attribute\Route;

#[Operation(description: 'Get something simple')]
#[Route('/simple', methods: ['GET'])]
class SimpleAction
{
    public function __invoke(): SimpleView
    {
        return new SimpleView();
    }
}
