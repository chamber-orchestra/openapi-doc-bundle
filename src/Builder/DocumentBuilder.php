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

    /**
     * Conventional names for shared 4xx responses defined in proto.yaml. When a name is found
     * in proto's `components.responses`, the serializer auto-injects the matching status code
     * into every operation that needs it (security-protected → 401/403, has form body → 422,
     * has path parameter → 404). Names that don't exist in proto are skipped — keeps the
     * bundle convention-over-configuration without hardcoding response shapes.
     */
    private const AUTO_RESPONSE_NAMES = [
        '401' => 'UnauthorizedError',
        '403' => 'ForbiddenError',
        '404' => 'NotFoundError',
        '422' => 'ValidationError',
    ];

    public function build(
        array  $protoData = [],
        string $version = '1.0.0',
        string $title = 'API Documentation',
    ): array {
        $protoSchemes    = $protoData['components']['securitySchemes'] ?? [];
        $firstScheme     = !empty($protoSchemes) ? array_key_first($protoSchemes) : null;

        $protoResponseNames = array_keys($protoData['components']['responses'] ?? []);
        $autoResponses = [];
        foreach (self::AUTO_RESPONSE_NAMES as $status => $name) {
            if (in_array($name, $protoResponseNames, true)) {
                $autoResponses[$status] = $name;
            }
        }

        $protoSchemaNames = array_keys($protoData['components']['schemas'] ?? []);

        [$paths, $excludedIds] = $this->serializer->serializePaths(
            $this->operationRegistry->getAll(),
            $firstScheme,
            $autoResponses,
            $protoSchemaNames,
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
