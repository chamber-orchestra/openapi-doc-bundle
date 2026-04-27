<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\EntityTypeHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;

class EntityTypeHandlerTest extends TestCase
{
    private EntityTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new EntityTypeHandler();
    }

    public function testSupportsEntityAndDocument(): void
    {
        self::assertTrue($this->handler->supports('entity'));
        self::assertTrue($this->handler->supports('document'));
        self::assertFalse($this->handler->supports('choice'));
        self::assertFalse($this->handler->supports('text'));
    }

    public function testSingleEntityIsUuidString(): void
    {
        $property = Property::factory('classroom');
        $config = $this->makeConfig(false);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
        self::assertSame('uuid', $property->format);
    }

    public function testMultipleEntityIsArrayOfUuidStrings(): void
    {
        $property = Property::factory('students');
        $config = $this->makeConfig(true);

        $this->handler->handle($property, $config);

        self::assertSame('array', $property->type);
        self::assertNotNull($property->items);
        self::assertSame('string', $property->items->type);
        self::assertSame('uuid', $property->items->format);
    }

    private function makeConfig(bool $multiple): FormConfigInterface
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getOption')->willReturnMap([
            ['multiple', null, null, $multiple],
        ]);

        return $config;
    }
}
