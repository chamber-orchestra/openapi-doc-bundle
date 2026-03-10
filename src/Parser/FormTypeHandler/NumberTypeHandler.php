<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;

class NumberTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'number' === $blockPrefix || 'integer' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $blockPrefix = $config->getType()->getBlockPrefix();
        $property->type = 'number' === $blockPrefix ? 'number' : 'integer';
        $constraints = $config->getOption('constraints');

        // Positive/PositiveOrZero are checked before GreaterThan/GreaterThanOrEqual
        // because Positive extends GreaterThan (value=0) and would otherwise overwrite.
        if ($this->findConstraint($constraints, PositiveOrZero::class)) {
            $property->attributes['minimum'] = 0;
        } elseif ($this->findConstraint($constraints, Positive::class)) {
            $property->attributes['minimum'] = 1;
        } elseif ($constraint = $this->findConstraint($constraints, GreaterThanOrEqual::class)) {
            $property->attributes['minimum'] = $constraint->value;
        } elseif ($constraint = $this->findConstraint($constraints, GreaterThan::class)) {
            $property->attributes['minimum'] = $constraint->value;
        }

        if ($constraint = $this->findConstraint($constraints, LessThanOrEqual::class)) {
            $property->attributes['maximum'] = $constraint->value;
        } elseif ($constraint = $this->findConstraint($constraints, LessThan::class)) {
            $property->attributes['maximum'] = $constraint->value;
        }

        // Range is applied only when no more specific constraint already set the boundary.
        if ($constraint = $this->findConstraint($constraints, Range::class)) {
            if (null !== $constraint->min && !isset($property->attributes['minimum'])) {
                $property->attributes['minimum'] = $constraint->min;
            }
            if (null !== $constraint->max && !isset($property->attributes['maximum'])) {
                $property->attributes['maximum'] = $constraint->max;
            }
        }
    }
}
