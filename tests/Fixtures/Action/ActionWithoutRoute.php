<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Operation;

#[Operation(description: 'Missing Route — should not be located')]
class ActionWithoutRoute
{
    public function __invoke(): void {}
}
