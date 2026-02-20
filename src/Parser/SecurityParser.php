<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ReflectionAttribute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityParser implements OperationParserInterface
{
    /** Placeholder replaced by DocumentBuilder with the first proto.yaml securityScheme name. */
    public const SECURITY_PLACEHOLDER = 'default';

    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionAttribute
            && $attribute->getName() === IsGranted::class;
    }

    public function parse(Model $model, object $attribute): Model
    {
        if (empty($model->security)) {
            $model->security[self::SECURITY_PLACEHOLDER] = [];
        }

        return $model;
    }
}