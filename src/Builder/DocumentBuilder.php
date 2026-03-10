<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Builder;

use ChamberOrchestra\OpenApiDocBundle\Registry\ComponentRegistry;
use ChamberOrchestra\OpenApiDocBundle\Registry\OperationRegistry;
use ChamberOrchestra\OpenApiDocBundle\Serializer\OpenApiSerializer;

class DocumentBuilder
{
    public function __construct(
        private OperationRegistry $operationRegistry,
        private ComponentRegistry $componentRegistry,
        private OpenApiSerializer $serializer,
    ) {
    }

    public function build(
        array  $protoData = [],
        string $version = '1.0.0',
        string $title = 'API Documentation',
    ): array {
        $protoSchemes    = $protoData['components']['securitySchemes'] ?? [];
        $firstScheme     = !empty($protoSchemes) ? array_key_first($protoSchemes) : null;

        [$paths, $excludedIds] = $this->serializer->serializePaths(
            $this->operationRegistry->getAll(),
            $firstScheme,
        );

        $components = $this->serializer->serializeComponents(
            $this->componentRegistry->getAll(),
            $excludedIds,
        );

        $components = $this->mergeProto($components, $protoData);

        return [
            'openapi'    => '3.0.1',
            'info'       => ['version' => $version, 'title' => $title],
            'paths'      => $paths,
            'components' => $components,
        ];
    }

    private function mergeProto(array $components, array $protoData): array
    {
        $protoComponents = $protoData['components'] ?? [];

        if (!empty($protoComponents['securitySchemes'])) {
            $components['securitySchemes'] = $protoComponents['securitySchemes'];
        }

        $components['schemas'] = array_merge(
            $components['schemas'] ?? [],
            $protoComponents['schemas'] ?? [],
        );

        if (!empty($protoComponents['responses'])) {
            $components['responses'] = $protoComponents['responses'];
        }

        return $components;
    }
}
