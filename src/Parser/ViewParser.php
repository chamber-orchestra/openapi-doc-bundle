<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ResponseViewInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Utils\TypeConverter;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use function in_array;

class ViewParser implements ComponentParserInterface
{
    public function __construct(
        private DescriberInterface $describer,
        private PropertyParser $parser,
        private TypeConverter $typeConverter,
        private PublicPropertyParser $publicPropertyParser,
    ) {
    }

    public function supports(object $item): bool
    {
        return $item instanceof ReflectionClass
            && in_array(ViewInterface::class, $item->getInterfaceNames());
    }

    public function parse(Model $model, object $item): Model
    {
        /* @var Component $model */
        /* @var ReflectionClass $item */

        $status = Response::HTTP_OK;
        $headers = ['Content-Type' => 'application/json'];

        // Reading the canonical status/headers from a ResponseView requires instantiating it,
        // but views often declare required constructor arguments (e.g. payload fields, status).
        // Fall back to defaults if instantiation fails — the resulting schema still describes
        // the view's properties; only the metadata (HTTP status, content-type) is lost.
        if (in_array(ResponseViewInterface::class, $item->getInterfaceNames())) {
            try {
                $instance = $item->newInstance();
                $status = $item->getMethod('getStatus')->invoke($instance);
                $headers = $item->getMethod('getHeaders')->invoke($instance);
            } catch (\ArgumentCountError | \TypeError) {
                // View has required constructor arguments — keep defaults.
            }
        }

        $model->id = $item->getShortName();

        if (null === $model->parent) {
            $model->status = $status;
            $model->headers = $headers;
        }

        // IterableView itself or subclasses serialize as a plain JSON array
        if ($item->getName() === IterableView::class || $item->isSubclassOf(IterableView::class)) {
            $model->type = 'array';
            $itemClass = $this->resolveIterableItemType($item);
            if (null !== $itemClass) {
                try {
                    $child = $this->describer->describe($itemClass);
                    $child->parent = $model;
                    $itemsProperty = Property::factory('items');
                    $itemsProperty->ref = $child;
                    $model->items = $itemsProperty;
                } catch (\Exception $e) {
                    // item schema unresolvable — leave as plain type: array
                }
            }

            return $model;
        }

        $parameters = [];
        $required = [];
        // Only public properties are part of the public API contract — protected/private
        // fields belong to the view's internals (status, headers on ResponseView etc.) and
        // must not leak into the OpenAPI schema.
        foreach ($item->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $parameter = $this->buildProperty($property, $model);
            $parameter = $this->parser->parse($parameter, $property);
            $parameters[] = $parameter;

            $type = $property->getType();
            $isRequired = ($type instanceof ReflectionNamedType && !$type->allowsNull())
                || $parameter->required === true;
            if ($isRequired) {
                $required[] = $property->getName();
            }
        }

        $model->properties = $parameters;
        $model->required = $required;

        return $model;
    }

    private function buildProperty(ReflectionProperty $property, Component $model): Property
    {
        $propertyName   = $property->getName();
        $reflectionType = $property->getType();
        $phpTypeName    = $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : null;

        // IterableView property — always renders as array; items resolved from #[Type]
        if (null !== $phpTypeName && $this->isIterableViewType($phpTypeName)) {
            $parameter = Property::factory($propertyName, 'array');
            $this->resolveArrayItems($parameter, $property);

            return $parameter;
        }

        $parameter = $this->publicPropertyParser->build($propertyName, $phpTypeName, $model);

        if ('array' === $parameter->type && null === $parameter->items) {
            $this->resolveArrayItems($parameter, $property);
        }

        return $parameter;
    }

    /**
     * Resolves the item type of an IterableView subclass via two mechanisms:
     * 1. #[Type(ItemClass::class)] attribute on the $entries property
     * 2. Return type of the overridden map() method
     */
    private function resolveIterableItemType(ReflectionClass $item): ?string
    {
        try {
            $entriesProperty = $item->getProperty('entries');
            foreach ($entriesProperty->getAttributes() as $attr) {
                if ($attr->getName() === Type::class) {
                    return $attr->newInstance()->class;
                }
            }
        } catch (ReflectionException $e) {
            // entries property not accessible
        }

        try {
            $mapMethod = $item->getMethod('map');
            $returnType = $mapMethod->getReturnType();
            if ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
                $typeName = $returnType->getName();
                if ($typeName !== ViewInterface::class) {
                    return $typeName;
                }
            }
        } catch (ReflectionException $e) {
            // map() not found
        }

        return null;
    }

    /**
     * Checks for #[Type(ItemClass::class)] on a property and populates $parameter->items
     * with a $ref to the item schema.
     */
    private function resolveArrayItems(Property $parameter, ReflectionProperty $property): void
    {
        foreach ($property->getAttributes() as $attr) {
            if ($attr->getName() === Type::class) {
                $itemClass = $attr->newInstance()->class;
                try {
                    $itemChild = $this->describer->describe($itemClass);
                    $itemsProperty = Property::factory('items');
                    $itemsProperty->ref = $itemChild;
                    $parameter->items = $itemsProperty;
                } catch (\Exception $e) {
                    // item schema unresolvable
                }
                break;
            }
        }
    }

    private function isIterableViewType(string $className): bool
    {
        return $className === IterableView::class
            || (class_exists($className) && is_a($className, IterableView::class, true));
    }
}
