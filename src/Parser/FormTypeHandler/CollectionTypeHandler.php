<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Constraints\Count;

class CollectionTypeHandler extends AbstractFormTypeHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private iterable $handlers,
        private DescriberInterface $describer,
    ) {
    }

    public function supports(string $blockPrefix): bool
    {
        return 'collection' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $subType = $config->getOption('entry_type');
        $subOptions = $config->getOption('entry_options');
        $subForm = $this->formFactory->create($subType, null, $subOptions);

        $property->type = 'array';
        $subProperty = Property::factory('items', $subType);
        $property->items = $subProperty;

        $constraints = $config->getOption('constraints');
        if ($constraint = $this->findConstraint($constraints, Count::class)) {
            if (null !== $constraint->min) {
                $property->attributes['minItems'] = $constraint->min;
            }
            if (null !== $constraint->max) {
                $property->attributes['maxItems'] = $constraint->max;
            }
        }

        $this->dispatchSubProperty($subProperty, $subForm->getConfig(), $this->handlers, $this->describer);
    }
}
