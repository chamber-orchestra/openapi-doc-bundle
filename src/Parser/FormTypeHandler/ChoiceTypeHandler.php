<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Validator\Constraints\Count;

class ChoiceTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'choice' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $multiple = $config->getOption('multiple');
        $property->type = $multiple ? 'array' : 'string';

        if ($multiple) {
            $constraints = $config->getOption('constraints') ?? [];
            if ($constraint = $this->findConstraint($constraints, Count::class)) {
                if (null !== $constraint->min) {
                    $property->attributes['minItems'] = $constraint->min;
                }
                if (null !== $constraint->max) {
                    $property->attributes['maxItems'] = $constraint->max;
                }
            }
        }

        if (($choices = $config->getOption('choices')) && is_array($choices) && count($choices)) {
            $enums = array_values(array_map(
                static fn($c) => $c instanceof \BackedEnum ? $c->value : $c,
                $choices,
            ));
            if ($this->isNumbersArray($enums)) {
                $type = 'number';
            } elseif ($this->isBooleansArray($enums)) {
                $type = 'boolean';
            } else {
                $type = 'string';
            }

            if ($multiple) {
                $subProperty = Property::factory('items', $type);
                $subProperty->enum = $enums;
                $property->items = $subProperty;
            } else {
                $property->type = $type;
                $property->enum = $enums;
            }
        }
    }
}
