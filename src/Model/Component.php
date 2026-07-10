<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Model;

class Component extends Model
{
    public Component|Property|null $parent = null;
    /**
     * Properties for component in associative array
     * @var array
     */
    public array $properties = [];
    /** Set to 'array' for IterableView-based schemas */
    public ?string $type = null;
    public ?Property $items = null;
    public ?int $status = null;
    public array $headers = [];
    public array $required = [];
    /** @var array<string, mixed> OpenAPI specification extensions (x-* keys) emitted on the schema. */
    public array $extensions = [];
}