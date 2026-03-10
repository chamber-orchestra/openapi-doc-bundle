<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Model;

/**
 * Class Property
 * @example
 *       id:
 *          type: integer
 * @package ChamberOrchestra\OpenApiDocBundle\Model
 */
class Property
{
    public ?string $name = null;
    public ?string $type = null;
    public ?string $format = null;
    public array $properties = [];
    /** Whether this property is required in its parent component's required[] list. */
    public ?bool $required = null;
    /** Required sub-property names for inline object schemas (type: object). Set by RepeatedTypeHandler. */
    public array $requiredProperties = [];
    public ?Component $ref = null;
    public ?Property $items = null;
    public array $enum = [];
    public array $attributes = [];

    public static function factory(string $name, ?string $type = null, ?string $format = null): self
    {
        $property = new self();
        $property->name = $name;
        $property->type = $type;
        $property->format = $format;

        return $property;
    }
}
