<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use function in_array;

class ComponentRegistry extends Registry
{
    public function getAll(array $excludedIds = []): array
    {
        $data = [];
        $components = $this->models;

        /* @var Component $component */
        /* @var Property $property */
        foreach ($components as $component) {
            if (in_array($component->id, $excludedIds, true)) {
                continue;
            }
            if ('array' === $component->type) {
                $componentData = ['type' => 'array'];
                if (null !== $component->items) {
                    $componentData['items'] = $this->propertyToArray($component->items);
                }
            } else {
                $componentData = ['properties' => []];
                $properties = $component->properties;
                foreach ($properties as $property) {
                    $componentData['properties'][$property->name] = $this->propertyToArray($property);
                }
                if (!empty($required = $component->required)) {
                    $componentData['required'] = $required;
                }
            }
            $data[$component->id] = $componentData;
        }

        return [
            'schemas' => $data,
        ];
    }

    private function propertyToArray(Property $property): array
    {
        $data = [];

        if (null !== $ref = $property->ref) {
            $data['$ref'] = '#/components/schemas/'.$ref->id;
        } else {
            $data['type'] = $property->type;
        }

        if (null !== $format = $property->format) {
            $data['format'] = $format;
        }

        if (!empty($items = $property->items)) {
            $data['items'] = $this->propertyToArray($items);
        } elseif (($data['type'] ?? null) === 'array') {
            $data['items'] = ['type' => 'object'];
        }

        if (!empty($enum = $property->enum)) {
            $data['enum'] = $enum;
        }

        if (!empty($property->properties)) {
            foreach ($property->properties as $subProperty) {
                $data['properties'][$subProperty->name] = $this->propertyToArray($subProperty);
            }
            if (!empty($property->requiredProperties)) {
                $data['required'] = $property->requiredProperties;
            }
        }

        $data += $property->attributes;

        return $data;
    }
}
