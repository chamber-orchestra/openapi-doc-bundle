<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Model;

class Operation extends Model
{
    public string $path = '';
    public string $method = '';
    public array $parameters = [];

    public ?string $description = '';
    public ?object $request = null;
    public array $security = [];
    public array $responses = [];

}