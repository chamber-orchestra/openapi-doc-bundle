<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Model;

use ChamberOrchestra\OpenApiDocBundle\Attribute\ResponseShape;

class Operation extends Model
{
    public string $path = '';
    public string $method = '';
    public array $parameters = [];

    public ?string $description = '';
    public ?object $request = null;
    public array $security = [];
    public array $responses = [];

    /** Override for requestBody content type (multipart/form-data, etc.). Null = application/json. */
    public ?string $requestContentType = null;

    /** Override for success response content type (text/csv, application/pdf, etc.). Null = application/json. */
    public ?string $responseContentType = null;

    /** Transport shape of the 2xx success payload. Null = ITEM (single entity in {"data": ...}). */
    public ?ResponseShape $responseShape = null;

    /** @var array<string, array<string, mixed>> Explicit query parameters read directly from Request (no form). */
    public array $queryParameters = [];
}