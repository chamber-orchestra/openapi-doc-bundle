<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Serializer;

use ChamberOrchestra\OpenApiDocBundle\Attribute\ResponseShape;
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
     * Schema name of the shared pagination metadata block. Must exist in `proto.yaml`
     * under `components.schemas` whenever an operation uses
     * {@see ResponseShape::PAGINATED_LIST}.
     */
    public const PAGINATION_METADATA_SCHEMA = 'PaginationMetadata';

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
     * @param string[]               $protoSchemaNames     Names of schemas declared in proto.yaml.
     *                                                     Required to validate that operations using
     *                                                     PAGINATED_LIST have access to
     *                                                     `PaginationMetadata`.
     * @return array{0: array, 1: string[]}  [paths, excludedComponentIds]
     */
    public function serializePaths(
        array $operations,
        ?string $firstSecurityScheme,
        array $autoResponses = [],
        array $protoSchemaNames = [],
    ): array {
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
                    $responses[$httpStatus]['description']                = $response->id ?? 'Successful response';
                    $responses[$httpStatus]['content'][$content]['schema'] = $this->buildResponseSchema(
                        $response,
                        $operation->responseShape,
                        $operation->id,
                        $protoSchemaNames,
                    );
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

            // For cursor-paginated endpoints, auto-inject `cursor` if not already present.
            // `cursor` is part of the wire contract for ResponseShape::PAGINATED_LIST — without
            // it the schema is incomplete regardless of whether the action uses a form or not.
            // `limit` is NOT auto-injected: some actions hardcode the page size and ignore a
            // client-supplied limit; if the form already declares limit it will appear via the
            // query-params expansion above; otherwise documenting it would mislead clients.
            if ($operation->responseShape === \ChamberOrchestra\OpenApiDocBundle\Attribute\ResponseShape::PAGINATED_LIST) {
                $existing = array_column($pathData['parameters'] ?? [], 'name');
                if (!in_array('cursor', $existing, true)) {
                    $pathData['parameters'][] = [
                        'name'        => 'cursor',
                        'in'          => 'query',
                        'required'    => false,
                        'description' => 'Pagination cursor from the previous response metadata.next. Omit to fetch the first page.',
                        'schema'      => ['type' => 'string'],
                    ];
                }
            }

            // Merge explicitly declared query parameters (params read directly from Request,
            // not via a form — e.g. forAll, installationId, search on non-form endpoints).
            foreach ($operation->queryParameters as $name => $paramSpec) {
                $param = array_merge(
                    ['name' => $name, 'in' => 'query', 'required' => false],
                    $paramSpec,
                );
                if (!isset($param['schema'])) {
                    $param['schema'] = ['type' => 'string'];
                }
                $pathData['parameters'][] = $param;
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
        $hasPathParam = 1 === preg_match('/\{[^}]+\}/', $operation->path);

        $needed = [];
        if ($isProtected) {
            $needed[] = '401';
            $needed[] = '403';
        }
        // 422 is injected whenever a form is present, regardless of HTTP verb.
        // GET/HEAD forms (query-param validation via GetWithFormAction) can also return 422
        // when the submitted query parameters fail form validation.
        if ($hasForm) {
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
     * Build the OpenAPI schema for a 2xx success payload, applying the {@see ResponseShape}
     * envelope around the entity component.
     *
     * The project's view layer (chamber-orchestra/view-bundle) wraps every successful response
     * in `{"data": ...}`. The shape parameter picks the wrapper:
     *
     *  - ITEM (default)    → `{"data": {<entity>}}`
     *  - LIST              → `{"data": [<entity>, ...]}`
     *  - PAGINATED_LIST    → `{"data": [<entity>, ...], "metadata": {<PaginationMetadata>}}`
     *
     * @param string[] $protoSchemaNames Names of schemas declared in proto.yaml — used to
     *                                   verify that PAGINATED_LIST has access to the shared
     *                                   {@see self::PAGINATION_METADATA_SCHEMA} schema.
     *
     * @return array<string, mixed>
     */
    private function buildResponseSchema(
        Component $entity,
        ?ResponseShape $shape,
        ?string $operationId,
        array $protoSchemaNames,
    ): array {
        $shape ??= ResponseShape::ITEM;
        $entityRef = ['$ref' => '#/components/schemas/'.$entity->id];

        return match ($shape) {
            ResponseShape::ITEM => [
                'type' => 'object',
                'properties' => ['data' => $entityRef],
                'required' => ['data'],
            ],
            ResponseShape::LIST => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => $entityRef],
                ],
                'required' => ['data'],
            ],
            ResponseShape::PAGINATED_LIST => $this->buildPaginatedSchema($entityRef, $operationId, $protoSchemaNames),
        };
    }

    /**
     * @param array{$ref: string} $entityRef
     * @param string[]            $protoSchemaNames
     *
     * @return array<string, mixed>
     */
    private function buildPaginatedSchema(array $entityRef, ?string $operationId, array $protoSchemaNames): array
    {
        if (!in_array(self::PAGINATION_METADATA_SCHEMA, $protoSchemaNames, true)) {
            throw new \LogicException(\sprintf(
                'Operation "%s" declares ResponseShape::PAGINATED_LIST but proto.yaml does not '
                .'define a `components.schemas.%s` schema. Add it to proto.yaml or change the '
                .'response shape.',
                $operationId ?? '(unknown)',
                self::PAGINATION_METADATA_SCHEMA,
            ));
        }

        return [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => $entityRef],
                'metadata' => ['$ref' => '#/components/schemas/'.self::PAGINATION_METADATA_SCHEMA],
            ],
            'required' => ['data', 'metadata'],
        ];
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
