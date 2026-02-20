<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Dto;

class NestedDto
{
    public string $title;
    public SimpleDto $nested;
}
