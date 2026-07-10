<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * Declares an OpenAPI specification extension (`x-*` key) on the schema generated for
 * a request or response class (form type, DTO, or view).
 *
 * Repeatable — declare one attribute per extension key:
 *
 *     #[Extension('x-entity', User::class)]
 *     #[Extension('x-doc-group', 'users')]
 *     class UserView implements ViewInterface { ... }
 *
 * The keys are emitted verbatim on the component schema:
 *
 *     components:
 *       schemas:
 *         UserView:
 *           properties: { ... }
 *           x-entity: App\Entity\User
 *           x-doc-group: users
 *
 * Caveat: GET/DELETE/HEAD request forms are expanded into query parameters and their
 * schema is excluded from the output, so extensions declared on such forms are dropped.
 *
 * For property-level extensions use `#[Property(attr: ['x-...' => ...])]`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Extension
{
    public function __construct(public string $name, public mixed $value)
    {
        if (!str_starts_with($name, 'x-')) {
            throw new InvalidArgumentException(sprintf(
                'OpenAPI specification extension name must start with "x-", got "%s".',
                $name,
            ));
        }
    }
}
