<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ReflectionClass;

class ResponseParser implements OperationParserInterface
{
    public function __construct(private DescriberInterface $componentDescriber)
    {
    }

    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionClass;
    }

    public function parse(Model $model, object $reflection): Model
    {
        /* @var Component $response */
        $response = $this->componentDescriber->describe($reflection->getName());
        $model->responses[$response->status] = $response;

        return $model;
    }
}