<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Describer;

use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use function array_merge;

class OperationDescriber extends AbstractDescriber
{
    protected function getItemsToParse(string $class): iterable
    {
        $reflection = new ReflectionClass($class);

        $invoke = $reflection->getMethod('__invoke');
        $returnType = $invoke->getReturnType();

        $types = [];
        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $rc = new ReflectionClass($type->getName());
                    if (!$rc->isInterface() && !$rc->isAbstract()) {
                        $types[] = $rc;
                    }
                }
            }
        } elseif ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
            $rc = new ReflectionClass($returnType->getName());
            if (!$rc->isInterface() && !$rc->isAbstract()) {
                $types[] = $rc;
            }
        }

        return array_merge($reflection->getAttributes(), $types);
    }

    protected function getModel(): string
    {
        return Operation::class;
    }
}
