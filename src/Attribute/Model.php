<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Model
{
    public function __construct(public ?string $model = null, public ?string $type = null)
    {
    }
}