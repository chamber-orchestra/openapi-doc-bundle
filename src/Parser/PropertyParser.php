<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ReflectionProperty;

class PropertyParser
{
    public function parse(Property $property, ReflectionProperty $reflection): Property
    {
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof \ChamberOrchestra\OpenApiDocBundle\Attribute\Property) {
                $property->required = $instance->required;
                $property->attributes += $instance->attr;
            }
        }

        return $property;
    }
}