<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use Symfony\Component\Form\FormConfigInterface;

class FileTypeHandler extends AbstractFormTypeHandler
{
    public function supports(string $blockPrefix): bool
    {
        return 'file' === $blockPrefix;
    }

    public function handle(Property $property, FormConfigInterface $config): void
    {
        $property->type = 'string';
        // format: binary signals to OpenAPI tooling that this field carries raw file bytes
        // (used in multipart/form-data uploads). Without it generators treat the field as
        // a plain string and do not produce a proper file-upload widget.
        $property->format = 'binary';
    }
}
