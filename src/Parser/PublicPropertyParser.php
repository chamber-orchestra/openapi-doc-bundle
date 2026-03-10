<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Utils\TypeConverter;

/**
 * Shared logic for building a Property from a PHP type name.
 * Used by both ViewParser and ObjectParser to avoid duplication.
 */
class PublicPropertyParser
{
    public function __construct(
        private TypeConverter $typeConverter,
        private DescriberInterface $describer,
    ) {
    }

    /**
     * Build a Property from a resolved PHP type name.
     *
     * Resolution order:
     *   1. No type → type: string (fallback)
     *   2. Primitive (int, bool, …) → mapped OpenAPI scalar
     *   3. BackedEnum / Uuid / DateTime → typed property with enum/format
     *   4. Other class → ComponentDescriber → $ref
     *   5. Unresolvable class → type: string (fallback)
     */
    public function build(string $name, ?string $typeName, Component $parent): Property
    {
        if (null === $typeName) {
            return Property::factory($name, 'string');
        }

        if ($openApiType = $this->typeConverter->toOpenApiType($typeName)) {
            return Property::factory($name, $openApiType);
        }

        if ($openApiProperty = $this->typeConverter->toOpenApiProperty($typeName)) {
            $property = Property::factory($name, $openApiProperty['type']);
            $property->format = $openApiProperty['format'] ?? null;
            $property->enum   = $openApiProperty['enum'] ?? [];

            return $property;
        }

        try {
            $child         = $this->describer->describe($typeName);
            $child->parent = $parent;
            $property      = Property::factory($name, 'object');
            $property->ref = $child;

            return $property;
        } catch (\Throwable) {
            return Property::factory($name, 'string');
        }
    }
}
