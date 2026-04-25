<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

/**
 * Maps EntityType / DocumentType form fields to OpenAPI string (uuid).
 *
 * Most modern Symfony projects identify entities with UUIDs (string), not
 * auto-increment integers. Emitting `integer` here used to produce a silent
 * contract drift: clients sent the canonical UUID string, OpenAPI codegen
 * reported the field as `number`, and TypeScript happily compiled the wrong
 * type. Standardising on `string` + `format: uuid` matches the common case;
 * integer-id projects can override this handler via DI.
 */
class EntityTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'entity' === $blockPrefix || 'document' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        if ($config->getOption('multiple')) {
            $property->type = 'array';
            $items = Property::factory('items', 'string');
            $items->format = 'uuid';
            $property->items = $items;

            return;
        }

        $property->type = 'string';
        $property->format = 'uuid';
    }
}
