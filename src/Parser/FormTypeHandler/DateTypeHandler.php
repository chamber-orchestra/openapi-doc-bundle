<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

class DateTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'date' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $property->type = 'string';
        $property->format = 'date';
    }
}
