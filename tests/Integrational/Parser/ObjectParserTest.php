<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\Parser;

use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Dto\NestedDto;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Dto\SimpleDto;
use ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\AbstractIntegrationTestCase;

class ObjectParserTest extends AbstractIntegrationTestCase
{
    public function testSimpleDtoProducesObjectSchema(): void
    {
        $component = $this->describer->describe(SimpleDto::class);

        self::assertSame('SimpleDto', $component->id);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('name', $properties);
        self::assertSame('string', $properties['name']->type);

        self::assertArrayHasKey('count', $properties);
        self::assertSame('integer', $properties['count']->type);

        self::assertArrayHasKey('active', $properties);
        self::assertSame('boolean', $properties['active']->type);

        self::assertArrayHasKey('ratio', $properties);
        self::assertSame('number', $properties['ratio']->type);
    }

    public function testSimpleDtoIsRegisteredInRegistry(): void
    {
        $this->describer->describe(SimpleDto::class);

        self::assertArrayHasKey('SimpleDto', $this->schemas());
    }

    public function testSimpleDtoOutputSchema(): void
    {
        $this->describer->describe(SimpleDto::class);

        $schema = $this->schemas()['SimpleDto'];

        self::assertSame('string', $schema['properties']['name']['type']);
        self::assertSame('integer', $schema['properties']['count']['type']);
        self::assertSame('boolean', $schema['properties']['active']['type']);
        self::assertSame('number', $schema['properties']['ratio']['type']);
    }

    public function testNestedDtoCreatesRefToChild(): void
    {
        $component = $this->describer->describe(NestedDto::class);

        self::assertSame('NestedDto', $component->id);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('title', $properties);
        self::assertSame('string', $properties['title']->type);

        self::assertArrayHasKey('nested', $properties);
        $nestedProperty = $properties['nested'];
        self::assertSame('object', $nestedProperty->type);
        self::assertNotNull($nestedProperty->ref);
        self::assertSame('SimpleDto', $nestedProperty->ref->id);
    }

    public function testNestedDtoRegistersChildComponent(): void
    {
        $this->describer->describe(NestedDto::class);

        $schemas = $this->schemas();

        self::assertArrayHasKey('NestedDto', $schemas);
        self::assertArrayHasKey('SimpleDto', $schemas);
    }

    public function testNestedDtoOutputRefSchema(): void
    {
        $this->describer->describe(NestedDto::class);

        $nestedSchema = $this->schemas()['NestedDto'];

        self::assertSame(
            '#/components/schemas/SimpleDto',
            $nestedSchema['properties']['nested']['$ref']
        );
    }

    public function testDuplicateDescribeReturnsSameInstance(): void
    {
        $first = $this->describer->describe(SimpleDto::class);
        $second = $this->describer->describe(SimpleDto::class);

        self::assertSame($first, $second);
    }

    private function indexByName(array $properties): array
    {
        $indexed = [];
        foreach ($properties as $property) {
            $indexed[$property->name] = $property;
        }

        return $indexed;
    }
}
