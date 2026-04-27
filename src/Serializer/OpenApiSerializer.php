<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Serializer;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\SecurityParser;
use function in_array;

/**
 * Converts internal model objects into OpenAPI 3.0.1 array structures.
 * Owns all serialization logic that was previously split across registries.
 */
class OpenApiSerializer
{
    /**
     * Serialize operations into an OpenAPI `paths` structure.
     *
     * @param Operation[]            $operations
     * @param string|null            $firstSecurityScheme  Name of the first proto.yaml securityScheme,
     *                                                     used to resolve the 'default' placeholder.
     * @param array<string, string>  $autoResponses        Status code → proto response name. The
     *                                                     bundle auto-injects these refs:
     *                                                     401/403 for security-protected operations,
     *                                                     422 for operations with form bodies,
     *                                                     404 for operations with path parameters.
     *                                                     Existing explicit responses are never
     *                                                     overwritten.
     * @return array{0: array, 1: string[]}  [paths, excludedComponentIds]
     */
    public function serializePaths(array $operations, ?string $firstSecurityScheme, array $autoResponses = []): array
    {
        $paths       = [];
        $excludedIds = [];

        foreach ($operations as $operation) {
            if ('' === $operation->path || '' === $operation->method) {
                throw new \LogicException(sprintf(
                    'Operation "%s" is missing path or method.',
                    $operation->id ?? '(unknown)',
                ));
            }

            $pathData = [];
            if (null !== $operation->description) {
                $pathData['description'] = $operation->description;
            }
            $pathData['operationId'] = $operation->id;

            if (!empty($operation->parameters)) {
                $pathData['parameters'] = $operation->parameters;
            }

            $responses = [];
            $defaultResponseContent = $operation->responseContentType ?? 'application/json';
            foreach ($operation->responses as $status => $response) {
                if ($response instanceof Component) {
                    $httpStatus = (string) ($response->status ?? 200);
                    $content    = $response->headers['Content-Type'] ?? $defaultResponseContent;
                    $responses[$httpStatus]['description']                          = $response->id ?? 'Successful response';
                    $responses[$httpStatus]['content'][$content]['schema']['$ref']  = '#/components/schemas/'.$response->id;
                } else {
                    $responses[(string) $status]['$ref'] = '#/components/responses/'.$response;
                }
            }

            if (null !== $operation->request) {
                if (in_array(strtoupper($operation->method), ['GET', 'DELETE', 'HEAD'], true)) {
                    [$queryParams, $excludedId] = $this->requestToQueryParams($operation->request);
                    $excludedIds[] = $excludedId;
                    if (!empty($queryParams)) {
                        $pathData['parameters'] = array_merge($pathData['parameters'] ?? [], $queryParams);
                    }
                } else {
                    $requestContentType = $operation->requestContentType ?? 'application/json';
                    $pathData['requestBody']['content'][$requestContentType]['schema']['$ref'] =
                        '#/components/schemas/'.$operation->request->id;
                }
            }

            $isProtected = false;
            if (!empty($security = $operation->security)) {
                $resolved = $security;
                if (isset($resolved[SecurityParser::SECURITY_PLACEHOLDER])) {
                    if (null !== $firstSecurityScheme) {
                        $resolved[$firstSecurityScheme] = $resolved[SecurityParser::SECURITY_PLACEHOLDER];
                    }
                    unset($resolved[SecurityParser::SECURITY_PLACEHOLDER]);
                }
                if (!empty($resolved)) {
                    $pathData['security'] = [$resolved];
                    $isProtected = true;
                }
            }

            // Public actions (no #[IsGranted]) get an explicit empty security requirement, so
            // OpenAPI clients know auth is not required instead of inheriting a global default.
            if (!$isProtected) {
                $pathData['security'] = [];
            }

            // Auto-inject conventional 4xx response refs when proto.yaml defines them and the
            // operation hasn't already documented that status code explicitly.
            $this->injectAutoResponses($responses, $operation, $autoResponses, $isProtected);

            $pathData['responses'] = $responses;
            $paths[$operation->path][strtolower($operation->method)] = $pathData;
        }

        return [$paths, $excludedIds];
    }

    /**
     * @param array<string, mixed>   $responses     Mutated in place.
     * @param array<string, string>  $autoResponses Status → proto response name.
     */
    private function injectAutoResponses(array &$responses, Operation $operation, array $autoResponses, bool $isProtected): void
    {
        if ([] === $autoResponses) {
            return;
        }

        $hasForm = null !== $operation->request;
        $isWriteVerb = !in_array(strtoupper($operation->method), ['GET', 'DELETE', 'HEAD'], true);
        $hasPathParam = 1 === preg_match('/\{[^}]+\}/', $operation->path);

        $needed = [];
        if ($isProtected) {
            $needed[] = '401';
            $needed[] = '403';
        }
        if ($hasForm && $isWriteVerb) {
            $needed[] = '422';
        }
        if ($hasPathParam) {
            $needed[] = '404';
        }

        foreach ($needed as $status) {
            if (isset($responses[$status]) || !isset($autoResponses[$status])) {
                continue;
            }
            $responses[$status] = ['$ref' => '#/components/responses/'.$autoResponses[$status]];
        }
    }

    /**
     * Serialize components into an OpenAPI `components.schemas` structure.
     *
     * @param Component[] $components
     * @param string[]    $excludedIds  Component IDs to omit (e.g. GET-request form schemas).
     * @return array{schemas: array}
     */
    public function serializeComponents(array $components, array $excludedIds = []): array
    {
        $data = [];

        foreach ($components as $component) {
            if (in_array($component->id, $excludedIds, true)) {
                continue;
            }

            if ('array' === $component->type) {
                $componentData = ['type' => 'array'];
                if (null !== $component->items) {
                    $componentData['items'] = $this->propertyToArray($component->items);
                }
            } else {
                $componentData = ['properties' => []];
                foreach ($component->properties as $property) {
                    $componentData['properties'][$property->name] = $this->propertyToArray($property);
                }
                if (!empty($component->required)) {
                    $componentData['required'] = $component->required;
                }
            }

            $data[$component->id] = $componentData;
        }

        return ['schemas' => $data];
    }

    public function propertyToArray(Property $property): array
    {
        $data = [];

        if (null !== $ref = $property->ref) {
            $data['$ref'] = '#/components/schemas/'.$ref->id;
        } else {
            $data['type'] = $property->type;
        }

        if (null !== $property->format) {
            $data['format'] = $property->format;
        }

        if (!empty($property->items)) {
            $data['items'] = $this->propertyToArray($property->items);
        } elseif (($data['type'] ?? null) === 'array') {
            $data['items'] = ['type' => 'object'];
        }

        if (!empty($property->enum)) {
            $data['enum'] = $property->enum;
        }

        if (!empty($property->properties)) {
            foreach ($property->properties as $subProperty) {
                $data['properties'][$subProperty->name] = $this->propertyToArray($subProperty);
            }
            if (!empty($property->requiredProperties)) {
                $data['required'] = $property->requiredProperties;
            }
        }

        $data += $property->attributes;

        return $data;
    }

    /**
     * Convert a form Component's properties into OpenAPI query parameters.
     *
     * @return array{0: array, 1: string}  [queryParams, excludedComponentId]
     */
    private function requestToQueryParams(Component $component): array
    {
        $params   = [];
        $required = $component->required ?? [];

        foreach ($component->properties as $property) {
            $schema = [];
            if (null !== $property->ref) {
                $schema['$ref'] = '#/components/schemas/'.$property->ref->id;
            } else {
                $schema['type'] = $property->type;
                if (!empty($property->enum)) {
                    $schema['enum'] = $property->enum;
                }
                if (!empty($property->format)) {
                    $schema['format'] = $property->format;
                }
            }

            $params[] = [
                'name'     => $property->name,
                'in'       => 'query',
                'required' => in_array($property->name, $required, true),
                'schema'   => $schema,
            ];
        }

        return [$params, $component->id];
    }
}
