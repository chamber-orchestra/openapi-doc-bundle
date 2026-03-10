<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Operation
{
    public function __construct(public ?string $description = null, public ?string $request = null, public array $responses = [], public array $security = [])
    {
    }
}