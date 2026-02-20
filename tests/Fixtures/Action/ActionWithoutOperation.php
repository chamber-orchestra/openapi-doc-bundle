<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/no-operation', methods: ['GET'])]
class ActionWithoutOperation
{
    public function __invoke(): void {}
}
