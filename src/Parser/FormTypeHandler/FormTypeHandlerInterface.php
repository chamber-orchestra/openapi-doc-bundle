<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

interface FormTypeHandlerInterface
{
    public function supports(string $blockPrefix): bool;

    /** Populates $property based on form field config and constraints */
    public function handle(Property $property, FormConfigInterface $config): void;
}
