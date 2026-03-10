<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;

class RepeatedTypeHandler extends AbstractFormTypeHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private iterable $handlers,
        private DescriberInterface $describer,
    ) {
    }

    public function supports(string $blockPrefix): bool
    {
        return 'repeated' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $property->type = 'object';
        $property->requiredProperties = [$config->getOption('first_name'), $config->getOption('second_name')];

        $subType = $config->getOption('type');

        foreach (['first', 'second'] as $subField) {
            $subName = $config->getOption($subField.'_name');
            $subForm = $this->formFactory->create(
                $subType,
                null,
                array_merge(
                    $config->getOption('options'),
                    $config->getOption($subField.'_options')
                )
            );
            $subProperty = Property::factory($subName);
            $property->properties[] = $subProperty;
            $this->dispatchSubProperty($subProperty, $subForm->getConfig(), $this->handlers, $this->describer);
        }
    }
}
