<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser;

use ChamberOrchestra\OpenApiDocBundle\Describer\DescriberInterface;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ReflectionClass;

class ResponseParser implements OperationParserInterface
{
    public function __construct(private DescriberInterface $componentDescriber)
    {
    }

    public function supports(object $attribute): bool
    {
        return $attribute instanceof ReflectionClass;
    }

    public function parse(Model $model, object $reflection): Model
    {
        // Skip return-type reflection when a 2xx response is already documented via
        // #[Operation(responses: [...])]. Only matching on 2xx (not all statuses) so that
        // an action which documents only error codes (e.g. '422') still gets the reflected
        // success response added — otherwise the main contract silently disappears.
        foreach (\array_keys($model->responses) as $status) {
            if (1 === \preg_match('/^2\d\d$/', (string) $status)) {
                return $model;
            }
        }

        /* @var Component $response */
        $response = $this->componentDescriber->describe($reflection->getName());

        // Components built from a return-type reflection don't carry a status code
        // on their own; default to "200" so OpenApiSerializer doesn't emit a
        // synthetic empty key (see the `?? 200` fallback there).
        $status = (string) ($response->status ?? 200);
        $model->responses[$status] = $response;

        return $model;
    }
}