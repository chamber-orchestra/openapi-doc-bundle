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
        $entityClass = $config->getOption('class');

        if ($config->getOption('multiple')) {
            $property->format = sprintf('[%s id]', $entityClass);
            $property->type = 'array';
            $subProperty = Property::factory('items', 'string');
            $property->items = $subProperty;
        } else {
            $property->type = 'string';
            $property->format = sprintf('%s id', $entityClass);
        }
    }
}
