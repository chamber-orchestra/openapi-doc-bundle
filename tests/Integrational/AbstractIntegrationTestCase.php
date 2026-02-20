<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Integrational;

use ArrayObject;
use ChamberOrchestra\OpenApiDocBundle\Describer\ComponentDescriber;
use ChamberOrchestra\OpenApiDocBundle\Parser\ObjectParser;
use ChamberOrchestra\OpenApiDocBundle\Parser\PropertyParser;
use ChamberOrchestra\OpenApiDocBundle\Parser\PublicPropertyParser;
use ChamberOrchestra\OpenApiDocBundle\Parser\ViewParser;
use ChamberOrchestra\OpenApiDocBundle\Registry\ComponentRegistry;
use ChamberOrchestra\OpenApiDocBundle\Serializer\OpenApiSerializer;
use ChamberOrchestra\OpenApiDocBundle\Utils\TypeConverter;
use PHPUnit\Framework\TestCase;

abstract class AbstractIntegrationTestCase extends TestCase
{
    protected ComponentRegistry $registry;
    protected ComponentDescriber $describer;
    protected ArrayObject $parsersContainer;
    protected OpenApiSerializer $serializer;

    protected function setUp(): void
    {
        $this->registry   = new ComponentRegistry();
        $this->serializer = new OpenApiSerializer();

        // Use ArrayObject so we can break the circular dependency:
        // ComponentDescriber needs parsers, but parsers need ComponentDescriber.
        // We create the ArrayObject first, pass it to ComponentDescriber,
        // then append parsers to it after creating them with the describer reference.
        $this->parsersContainer = new ArrayObject();

        $this->describer = new ComponentDescriber($this->parsersContainer, $this->registry);

        $propertyParser       = new PropertyParser();
        $typeConverter        = new TypeConverter();
        $publicPropertyParser = new PublicPropertyParser($typeConverter, $this->describer);

        $viewParser   = new ViewParser($this->describer, $propertyParser, $typeConverter, $publicPropertyParser);
        $objectParser = new ObjectParser($propertyParser, $publicPropertyParser);

        $this->parsersContainer->append($viewParser);
        $this->parsersContainer->append($objectParser);
    }

    /** Serialize all registered components to OpenAPI schemas array. */
    protected function schemas(): array
    {
        return $this->serializer->serializeComponents($this->registry->getAll())['schemas'];
    }
}
