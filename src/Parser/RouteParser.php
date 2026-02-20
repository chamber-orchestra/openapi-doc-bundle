<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ReflectionAttribute;
use Symfony\Component\Routing\Attribute\Route;

class RouteParser implements OperationParserInterface
{
    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionAttribute
            && $attribute->getName() === Route::class;
    }

    public function parse(Model $model, object $attribute): Model
    {
        /* @var Route $instance */
        /* @var Operation $model */
        $instance = $attribute->newInstance();
        $model->path = $instance->path;
        // Use route name if available, otherwise derive from path + first method
        $model->id = $instance->name ?? $this->deriveOperationId($instance->path, $instance->methods[0] ?? 'GET');

        // Extract path template parameters, e.g. {id}, {group}
        preg_match_all('/\{(\w+)\}/', $instance->path, $matches);
        foreach ($matches[1] as $paramName) {
            $model->parameters[] = [
                'name'     => $paramName,
                'in'       => 'path',
                'required' => true,
                'schema'   => ['type' => 'string'],
            ];
        }

        $methods = $instance->methods;
        if (empty($methods)) {
            throw new \InvalidArgumentException(sprintf(
                'Route "%s" has no HTTP method defined. An explicit method (GET, POST, etc.) is required.',
                $instance->name ?? $instance->path
            ));
        }
        $model->method = $methods[0];

        return $model;
    }

    private function deriveOperationId(string $path, string $method): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', '_', trim($path, '/'));
        $normalized = trim($normalized, '_');

        return strtolower($method) . '_' . ($normalized ?: 'root');
    }
}