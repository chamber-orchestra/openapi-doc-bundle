<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Operation
{
    /**
     * @param string|null        $requestContentType  Override the requestBody content type. Defaults to
     *                                                `application/json`. Useful for multipart/form-data
     *                                                uploads where the form contains a `FileType` field.
     * @param string|null        $responseContentType Override the success response content type.
     *                                                Defaults to `application/json`. Useful for
     *                                                `text/csv` exports, PDF/binary downloads, etc.
     * @param ResponseShape|null $responseShape       Transport shape of the 2xx success payload.
     *                                                Defaults to {@see ResponseShape::ITEM} which means
     *                                                the action returns a single entity wrapped in
     *                                                `{"data": ...}`. Use {@see ResponseShape::LIST}
     *                                                for actions returning `IterableView` and
     *                                                {@see ResponseShape::PAGINATED_LIST} for
     *                                                `PaginatedView` (cursor pagination).
     * @param array<string, array<string, mixed>> $queryParameters Explicit query parameters for actions that
     *                                                read query-string values directly from Request without
     *                                                using a form (e.g. `forAll`, `installationId`).
     *                                                Each entry is a partial OpenAPI Parameter Object:
     *                                                `['forAll' => ['schema' => ['type' => 'string'], 'required' => false]]`
     * @param array<string, array<string, mixed>> $headerParameters Explicit request headers. Same shape as
     *                                                $queryParameters; rendered as `in: header` parameters, e.g.
     *                                                `['X-Device-Id' => ['schema' => ['type' => 'string'], 'required' => false]]`
     * @param string|null        $metadataSchema      The `metadata` sibling for
     *                                                {@see ResponseShape::PAGINATED_LIST}. Defaults to the
     *                                                shared `PaginationMetadata` schema from proto.yaml.
     *                                                Accepts either a view class — described into a
     *                                                component like `request` and `responses` are — or the
     *                                                name of a schema declared in proto.yaml.
     *
     *                                                Endpoints whose metadata carries more than the cursor
     *                                                need this: an active filter, summary counters. Without
     *                                                it those keys are absent from the spec, so a generated
     *                                                client drops them while the endpoint keeps sending
     *                                                them — and widening the shared schema to fit one
     *                                                endpoint documents fields the others never return.
     */
    public function __construct(
        public ?string $description = null,
        public ?string $request = null,
        public array $responses = [],
        public array $security = [],
        public ?string $requestContentType = null,
        public ?string $responseContentType = null,
        public ?ResponseShape $responseShape = null,
        public array $queryParameters = [],
        public array $headerParameters = [],
        public ?string $metadataSchema = null,
    ) {
    }
}