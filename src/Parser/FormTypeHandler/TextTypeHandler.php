<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class TextTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'text' === $blockPrefix || 'email' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $property->type = 'string';
        $constraints = $config->getOption('constraints');

        if ($constraint = $this->findConstraint($constraints, Length::class)) {
            if (null !== $constraint->min) {
                $property->attributes['minLength'] = $constraint->min;
            }
            if (null !== $constraint->max) {
                $property->attributes['maxLength'] = $constraint->max;
            }
        }

        if ($this->findConstraint($constraints, Email::class)) {
            $property->format = 'email';
        }

        if ($this->findConstraint($constraints, Url::class)) {
            $property->format = 'uri';
        }

        if ($constraint = $this->findConstraint($constraints, Regex::class)) {
            $property->attributes['pattern'] = $constraint->pattern;
        }
    }
}
