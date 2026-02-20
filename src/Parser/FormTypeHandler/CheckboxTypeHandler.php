<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

class CheckboxTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'checkbox' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $property->type = 'boolean';
    }
}
