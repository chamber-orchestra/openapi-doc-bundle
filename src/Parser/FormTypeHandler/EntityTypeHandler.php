<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

class EntityTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'entity' === $blockPrefix || 'document' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        if ($config->getOption('multiple')) {
            $property->type = 'array';
            $property->items = Property::factory('items', 'integer');
        } else {
            $property->type = 'integer';
        }
    }
}
