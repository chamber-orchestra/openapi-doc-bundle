<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Form\FormTypeInterface;
use function in_array;

class ObjectParser implements ComponentParserInterface
{
    public function __construct(
        private PropertyParser $parser,
        private PublicPropertyParser $publicPropertyParser,
    ) {
    }

    public function supports(object $item): bool
    {
        return $item instanceof ReflectionClass
            && !in_array(ViewInterface::class, $item->getInterfaceNames())
            && !in_array(FormTypeInterface::class, $item->getInterfaceNames())
            && $item->getName() !== ViewInterface::class;
    }

    public function parse(Model $model, object $reflection): Model
    {
        /* @var Component $model */
        /* @var ReflectionClass $reflection */
        $model->id  = $reflection->getShortName();
        $parameters = [];
        $required   = [];

        foreach ($reflection->getProperties() as $property) {
            $reflectionType = $property->getType();
            $typeName       = $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : null;

            $parameter  = $this->publicPropertyParser->build($property->getName(), $typeName, $model);
            $parameter  = $this->parser->parse($parameter, $property);
            $parameters[] = $parameter;

            $isRequired = ($reflectionType instanceof ReflectionNamedType && !$reflectionType->allowsNull())
                || $parameter->required === true;
            if ($isRequired) {
                $required[] = $property->getName();
            }
        }

        $model->properties = $parameters;
        $model->required   = $required;

        return $model;
    }
}
