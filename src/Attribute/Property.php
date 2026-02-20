<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property
{
    public function __construct(public ?bool $required = null, public array $attr = [])
    {
    }
}