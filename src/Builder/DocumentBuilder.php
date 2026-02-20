<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Builder;

use ChamberOrchestra\OpenApiDocBundle\Registry\ComponentRegistry;
use ChamberOrchestra\OpenApiDocBundle\Registry\OperationRegistry;
use function array_key_first;
use function array_merge;

class DocumentBuilder
{
    public function __construct(
        private OperationRegistry $operationRegistry,
        private ComponentRegistry $componentRegistry,
    ) {
    }

    public function build(
        array  $protoData = [],
        string $version = '1.0.0',
        string $title = 'API Documentation',
    ): array {
        $paths      = $this->buildPaths($protoData);
        $components = $this->buildComponents($protoData);

        return [
            'openapi'    => '3.0.1',
            'info'       => ['version' => $version, 'title' => $title],
            'paths'      => $paths,
            'components' => $components,
        ];
    }

    private function buildPaths(array $protoData): array
    {
        $paths = $this->operationRegistry->getAll();
        $protoSecuritySchemes = $protoData['components']['securitySchemes'] ?? [];
        $firstSecurity = !empty($protoSecuritySchemes) ? array_key_first($protoSecuritySchemes) : null;

        foreach ($paths as &$methods) {
            foreach ($methods as &$operation) {
                // Do NOT use ?? here: it creates a temporary copy, breaking the reference chain.
                if (empty($operation['security'])) {
                    continue;
                }
                foreach ($operation['security'] as &$security) {
                    if (isset($security['default'])) {
                        if (null !== $firstSecurity) {
                            // Replace placeholder with the first defined scheme
                            $security[$firstSecurity] = $security['default'];
                        }
                        unset($security['default']);
                    }
                }
                // Remove security entries that became empty after stripping 'default'
                $operation['security'] = array_values(array_filter($operation['security']));
                if (empty($operation['security'])) {
                    unset($operation['security']);
                }
            }
        }

        return $paths;
    }

    private function buildComponents(array $protoData): array
    {
        $excludedIds = $this->operationRegistry->getExcludedComponentIds();
        $components = $this->componentRegistry->getAll($excludedIds);

        $securitySchemes = $protoData['components']['securitySchemes'] ?? [];
        if (!empty($securitySchemes)) {
            $components['securitySchemes'] = $securitySchemes;
        }

        $schemas = $protoData['components']['schemas'] ?? [];
        $components['schemas'] = array_merge($components['schemas'] ?? [], $schemas);

        $responses = $protoData['components']['responses'] ?? [];
        if (!empty($responses)) {
            $components['responses'] = $responses;
        }

        return $components;
    }
}
