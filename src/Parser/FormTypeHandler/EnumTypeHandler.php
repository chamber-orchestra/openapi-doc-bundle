<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

/**
 * Symfony's EnumType extends ChoiceType but exposes its own block_prefix `enum`.
 * AbstractFormTypeHandler::getBuiltinFormType() walks the resolved type chain
 * and returns the first class under Symfony\Component\Form\Extension\Core\Type\
 * — which is EnumType itself, not its ChoiceType parent. Without a handler
 * matching `enum`, FormParser falls through and the property's `type` stays
 * `null`. This handler reuses the choice-handling code so EnumType-backed
 * fields are emitted as `string` (or array) with proper `enum:` values.
 */
class EnumTypeHandler extends AbstractFormTypeHandler
{
    public function __construct(private readonly ChoiceTypeHandler $choiceTypeHandler)
    {
    }

    public function supports(string $blockPrefix): bool
    {
        return 'enum' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $this->choiceTypeHandler->handle($property, $config);
    }
}
