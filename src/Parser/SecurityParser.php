<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ReflectionAttribute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityParser implements OperationParserInterface
{
    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionAttribute
            && $attribute->getName() === IsGranted::class;
    }

    public function parse(Model $model, object $attribute): Model
    {
        if (empty($model->security)) {
            $model->security['default'] = [];
        }

        return $model;
    }
}