<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\AbstractFormTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\FormTypeHandlerInterface;
use ReflectionClass;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use function in_array;

class FormParser implements ComponentParserInterface
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private DescriberInterface $describer,
        private iterable $handlers,
    ) {
    }

    public function supports(object $item): bool
    {
        return $item instanceof ReflectionClass
            && in_array(FormTypeInterface::class, $item->getInterfaceNames());
    }

    public function parse(Model $model, object $item): Model
    {
        /* @var ReflectionClass $item */
        /* @var Component $model */
        $form = $this->formFactory->create($item->getName());

        $model->id = $item->getShortName();
        $required = [];
        foreach ($form as $name => $child) {
            $config = $child->getConfig();

            if ($config->getRequired()) {
                $required[] = $name;
            }

            $property = Property::factory($name);
            $model->properties[] = $property;
            $this->findFormType($property, $config);

            if (!$config->getRequired()) {
                $constraints = $config->getOption('constraints') ?? [];
                $hasNotNull = $this->hasConstraint($constraints, NotNull::class)
                    || $this->hasConstraint($constraints, NotBlank::class);
                if (!$hasNotNull) {
                    $property->attributes['nullable'] = true;
                }
            }
        }

        $model->required = $required;

        return $model;
    }

    private function hasConstraint(array $constraints, string $class): bool
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function findFormType(Property $property, $config): void
    {
        /* @var Component $childComponent */
        $type = $config->getType();

        if (!$builtinFormType = AbstractFormTypeHandler::getBuiltinFormType($type)) {
            $childComponent = $this->describer->describe(get_class($type->getInnerType()));
            $property->ref = $childComponent;
            $childComponent->parent = $property;

            return;
        }

        $blockPrefix = $builtinFormType->getBlockPrefix();

        /* @var FormTypeHandlerInterface $handler */
        foreach ($this->handlers as $handler) {
            if ($handler->supports($blockPrefix)) {
                $handler->handle($property, $config);
                return;
            }
        }
    }

}
