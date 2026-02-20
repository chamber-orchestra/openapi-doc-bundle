<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;

class OperationRegistry extends Registry
{
    /** Component IDs expanded as query params (excluded from schemas output). Populated during getAll(). */
    private array $excludedComponentIds = [];

    public function getExcludedComponentIds(): array
    {
        return $this->excludedComponentIds;
    }

    public function getAll(): array
    {
        $this->excludedComponentIds = [];
        $operations = $this->models;
        $data = [];

        /* @var Operation $operation */
        /* @var string|Component $response */
        foreach ($operations as $operation) {
            if ('' === $operation->path || '' === $operation->method) {
                throw new \LogicException(sprintf(
                    'Operation "%s" is missing path or method.',
                    $operation->id ?? '(unknown)',
                ));
            }

            $pathData = [];
            if (null !== $description = $operation->description) {
                $pathData['description'] = $description;
            }
            $pathData['operationId'] = $operation->id;

            if (!empty($operation->parameters)) {
                $pathData['parameters'] = $operation->parameters;
            }

            $responses = [];
            foreach ($operation->responses as $status => $response) {
                if ($response instanceof Component) {
                    // OpenAPI requires status codes to be string keys ("200", not 200)
                    $httpStatus = (string)($response->status ?? 200);
                    $content = $response->headers['Content-Type'] ?? 'application/json';
                    $responses[$httpStatus]['description'] = $response->id ?? 'Successful response';
                    $responses[$httpStatus]['content'][$content]['schema']['$ref'] =
                        '#/components/schemas/'.$response->id;
                } else {
                    $responses[(string)$status]['$ref'] = '#/components/responses/'.$response;
                }
            }

            if (null !== $operation->request) {
                if (in_array(strtoupper($operation->method), ['GET', 'DELETE', 'HEAD'], true)) {
                    // GET/DELETE have no request body — expand form fields as query parameters
                    $queryParams = $this->requestToQueryParams($operation->request);
                    if (!empty($queryParams)) {
                        $pathData['parameters'] = array_merge($pathData['parameters'] ?? [], $queryParams);
                    }
                } else {
                    $request = [];
                    $request['content']['application/json']['schema']['$ref'] =
                        '#/components/schemas/'.$operation->request->id;
                    $pathData['requestBody'] = $request;
                }
            }

            if (!empty($security = $operation->security)) {
                $pathData['security'] = [$security];
            }

            $pathData['responses'] = $responses;
            $data[$operation->path][strtolower($operation->method)] = $pathData;
        }

        return $data;
    }

    private function requestToQueryParams(Component $component): array
    {
        $this->excludedComponentIds[] = $component->id;
        $params = [];
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

            $param = [
                'name'     => $property->name,
                'in'       => 'query',
                'required' => in_array($property->name, $required, true),
                'schema'   => $schema,
            ];

            $params[] = $param;
        }

        return $params;
    }
}
