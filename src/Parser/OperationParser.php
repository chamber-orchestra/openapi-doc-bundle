<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Operation;
use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation as OperationModel;
use ReflectionAttribute;
use function array_merge;

class OperationParser implements OperationParserInterface
{
    public function __construct(private DescriberInterface $componentDescriber)
    {
    }

    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionAttribute
            && $attribute->getName() === Operation::class;
    }

    public function parse(Model $model, object $attribute): Model
    {
        /* @var OperationModel $model */
        /* @var ReflectionAttribute $attribute */
        $instance = $attribute->newInstance();
        $model->description = $instance->description;
        $model->requestContentType = $instance->requestContentType;
        $model->responseContentType = $instance->responseContentType;
        $model->responseShape = $instance->responseShape;
        $model->queryParameters = $instance->queryParameters;
        $model->headerParameters = $instance->headerParameters;

        $request = $instance->request;
        $requestModel = null;
        if (null !== $request) {
            if (class_exists($request)) {
                $requestModel = $this->componentDescriber->describe($request);
            } else {
                $requestModel = new Component();
                $requestModel->id = $request;
            }
        }

        if (!empty($security = $instance->security)) {
            $modelSecurity = $model->security;
            if (isset($modelSecurity['default'])) {
                foreach ($security as $key => $value) {
                    $security[$key] = array_merge($value, $modelSecurity['default']);
                }
            }
            $model->security = $security;
        }

        foreach ($instance->responses as $status => $response) {
            if (is_string($response) && class_exists($response)) {
                $component = $this->componentDescriber->describe($response);
                $model->responses[$status] = $component;
            } else {
                $model->responses[$status] = $response;
            }
        }
        $model->request = $requestModel;

        return $model;
    }
}
