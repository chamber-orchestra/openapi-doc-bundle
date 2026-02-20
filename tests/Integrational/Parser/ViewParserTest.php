<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\Parser;

use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\IterableContainerView;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\NestedView;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\RecursiveView;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\SimpleView;
use ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\AbstractIntegrationTestCase;

class ViewParserTest extends AbstractIntegrationTestCase
{
    public function testSimpleViewProducesObjectSchema(): void
    {
        $component = $this->describer->describe(SimpleView::class);

        self::assertSame('SimpleView', $component->id);
        self::assertNull($component->type); // plain object, not 'array'

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('name', $properties);
        self::assertSame('string', $properties['name']->type);

        self::assertArrayHasKey('age', $properties);
        self::assertSame('integer', $properties['age']->type);

        self::assertArrayHasKey('active', $properties);
        self::assertSame('boolean', $properties['active']->type);

        self::assertArrayHasKey('score', $properties);
        self::assertSame('number', $properties['score']->type);
    }

    public function testSimpleViewIsRegisteredInRegistry(): void
    {
        $this->describer->describe(SimpleView::class);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('SimpleView', $result['schemas']);
    }

    public function testNestedViewCreatesRefToChild(): void
    {
        $component = $this->describer->describe(NestedView::class);

        self::assertSame('NestedView', $component->id);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('title', $properties);
        self::assertSame('string', $properties['title']->type);

        self::assertArrayHasKey('simple', $properties);
        $simpleProperty = $properties['simple'];
        self::assertSame('object', $simpleProperty->type);
        self::assertNotNull($simpleProperty->ref);
        self::assertSame('SimpleView', $simpleProperty->ref->id);
    }

    public function testNestedViewRegistersChildComponent(): void
    {
        $this->describer->describe(NestedView::class);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('NestedView', $result['schemas']);
        self::assertArrayHasKey('SimpleView', $result['schemas']);
    }

    public function testNestedViewRefInOutput(): void
    {
        $this->describer->describe(NestedView::class);

        $result = $this->registry->getAll();
        $nestedSchema = $result['schemas']['NestedView'];

        self::assertSame(
            '#/components/schemas/SimpleView',
            $nestedSchema['properties']['simple']['$ref']
        );
    }

    public function testIterableContainerViewProducesStringProperty(): void
    {
        $component = $this->describer->describe(IterableContainerView::class);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('title', $properties);
        self::assertSame('string', $properties['title']->type);
    }

    public function testIterableContainerViewProducesArrayProperty(): void
    {
        $component = $this->describer->describe(IterableContainerView::class);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('items', $properties);
        $itemsProp = $properties['items'];
        self::assertSame('array', $itemsProp->type);
        self::assertNotNull($itemsProp->items);
        self::assertSame('SimpleView', $itemsProp->items->ref->id);
    }

    public function testIterableContainerViewOutputSchema(): void
    {
        $this->describer->describe(IterableContainerView::class);

        $result = $this->registry->getAll();
        $schema = $result['schemas']['IterableContainerView'];

        self::assertSame('string', $schema['properties']['title']['type']);

        $itemsSchema = $schema['properties']['items'];
        self::assertSame('array', $itemsSchema['type']);
        self::assertSame('#/components/schemas/SimpleView', $itemsSchema['items']['$ref']);
    }

    public function testDuplicateDescribeReturnsSameComponent(): void
    {
        $first = $this->describer->describe(SimpleView::class);
        $second = $this->describer->describe(SimpleView::class);

        self::assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // RecursiveView — self-referential tree structure
    // -------------------------------------------------------------------------

    public function testRecursiveViewDoesNotCauseInfiniteLoop(): void
    {
        $component = $this->describer->describe(RecursiveView::class);

        self::assertSame('RecursiveView', $component->id);
    }

    public function testRecursiveViewChildrenRefPointsToItself(): void
    {
        $this->describer->describe(RecursiveView::class);

        $result = $this->registry->getAll();
        $schema = $result['schemas']['RecursiveView'];

        self::assertSame(
            '#/components/schemas/RecursiveView',
            $schema['properties']['children']['items']['$ref']
        );
    }

    public function testRecursiveViewRegisteredOnce(): void
    {
        $first = $this->describer->describe(RecursiveView::class);
        $second = $this->describer->describe(RecursiveView::class);

        self::assertSame($first, $second);
        self::assertCount(1, array_filter(
            array_keys($this->registry->getAll()['schemas']),
            fn($k) => $k === 'RecursiveView'
        ));
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
