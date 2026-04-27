<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

/**
 * Transport shape of the success (2xx) response payload.
 *
 * Lets `#[Operation]` describe the JSON envelope produced by the action's view, without
 * introducing implicit coupling between this bundle and any specific view-bundle / pagination-
 * bundle implementation. The class declared in `responses` describes ONE entity; this enum
 * picks the wrapper applied around it.
 *
 * The bundle assumes the project's view layer wraps every successful payload in `{"data": ...}`
 * (the chamber-orchestra/view-bundle convention). Each shape below is the actual JSON the
 * client sees:
 *
 *   ITEM            → {"data": {<entity>}}
 *   LIST            → {"data": [<entity>, ...]}
 *   PAGINATED_LIST  → {"data": [<entity>, ...], "metadata": {<PaginationMetadata>}}
 */
enum ResponseShape: string
{
    case ITEM = 'item';
    case LIST = 'list';
    case PAGINATED_LIST = 'paginated_list';
}
