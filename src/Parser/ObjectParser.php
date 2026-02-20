<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Exception\DescriberException;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Utils\TypeConverter;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Form\FormTypeInterface;
use function in_array;

class ObjectParser implements ComponentParserInterface
{
    public function __construct(
        private PropertyParser $parser,
        private TypeConverter $typeConverter,
        private DescriberInterface $describer,
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
        $model->id = $reflection->getShortName();
        $parameters = [];
        $required = [];

        foreach ($reflection->getProperties() as $property) {
            $reflectionType = $property->getType();
            $typeName = $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : null;

            if (null === $typeName) {
                $parameter = Property::factory($property->getName(), 'string');
            } else {
                $openApiType = $this->typeConverter->toOpenApiType($typeName);
                if (null === $openApiType) {
                    if ($openApiProperty = $this->typeConverter->toOpenApiProperty($typeName)) {
                        $parameter = Property::factory($property->getName(), $openApiProperty['type']);
                        if (isset($openApiProperty['format'])) {
                            $parameter->format = $openApiProperty['format'];
                        }
                        if (!empty($openApiProperty['enum'])) {
                            $parameter->enum = $openApiProperty['enum'];
                        }
                    } else {
                        try {
                            $child = $this->describer->describe($typeName);
                            $parameter = Property::factory($property->getName(), 'object');
                            $parameter->ref = $child;
                        } catch (DescriberException $e) {
                            $parameter = Property::factory($property->getName(), 'string');
                        }
                    }
                } else {
                    $parameter = Property::factory($property->getName(), $openApiType);
                }
            }

            $parameter = $this->parser->parse($parameter, $property);
            $parameters[] = $parameter;

            $isRequired = ($reflectionType instanceof ReflectionNamedType && !$reflectionType->allowsNull())
                || $parameter->required === true;
            if ($isRequired) {
                $required[] = $property->getName();
            }
        }

        $model->properties = $parameters;
        $model->required = $required;

        return $model;
    }
}
