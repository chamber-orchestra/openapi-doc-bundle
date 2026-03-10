<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Validator\Constraint as FormConstraint;

abstract class AbstractFormTypeHandler implements FormTypeHandlerInterface
{
    protected function findConstraint(array $constraints, string $class): ?FormConstraint
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return $constraint;
            }
        }

        return null;
    }

    protected function isNumbersArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!is_numeric($item)) {
                return false;
            }
        }

        return true;
    }

    protected function isBooleansArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!is_bool($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Walks up the resolved form type chain to find the first Symfony built-in type.
     * Returns null if the root type is FormType (i.e. a custom type with no built-in parent).
     */
    public static function getBuiltinFormType(ResolvedFormTypeInterface $type): ?ResolvedFormTypeInterface
    {
        do {
            $class = get_class($type->getInnerType());

            if (FormType::class === $class) {
                return null;
            }

            if ('entity' === $type->getBlockPrefix() || 'document' === $type->getBlockPrefix()) {
                return $type;
            }

            if (str_starts_with($class, 'Symfony\Component\Form\Extension\Core\Type\\')) {
                return $type;
            }
        } while ($type = $type->getParent());

        return null;
    }

    /**
     * Dispatches a sub-property to the appropriate handler or describes it as a component $ref.
     * Shared by CollectionTypeHandler and RepeatedTypeHandler.
     */
    protected function dispatchSubProperty(
        Property $property,
        FormConfigInterface $config,
        iterable $handlers,
        DescriberInterface $describer,
    ): void {
        $type = $config->getType();

        if (!$builtinFormType = self::getBuiltinFormType($type)) {
            $child = $describer->describe(get_class($type->getInnerType()));
            $property->ref = $child;
            $child->parent = $property;

            return;
        }

        $blockPrefix = $builtinFormType->getBlockPrefix();

        foreach ($handlers as $handler) {
            if ($handler->supports($blockPrefix)) {
                $handler->handle($property, $config);

                return;
            }
        }
    }
}
