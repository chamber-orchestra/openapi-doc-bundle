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
     */
    public function __construct(
        public ?string $description = null,
        public ?string $request = null,
        public array $responses = [],
        public array $security = [],
        public ?string $requestContentType = null,
        public ?string $responseContentType = null,
        public ?ResponseShape $responseShape = null,
    ) {
    }
}