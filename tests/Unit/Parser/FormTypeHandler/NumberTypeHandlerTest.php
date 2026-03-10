<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\NumberTypeHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;

class NumberTypeHandlerTest extends TestCase
{
    private NumberTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new NumberTypeHandler();
    }

    public function testSupportsNumberAndInteger(): void
    {
        self::assertTrue($this->handler->supports('number'));
        self::assertTrue($this->handler->supports('integer'));
    }

    public function testDoesNotSupportOtherPrefixes(): void
    {
        self::assertFalse($this->handler->supports('text'));
        self::assertFalse($this->handler->supports('choice'));
    }

    public function testHandleWithNumberBlockPrefix(): void
    {
        $property = Property::factory('price');
        $config = $this->makeConfig('number', []);

        $this->handler->handle($property, $config);

        self::assertSame('number', $property->type);
    }

    public function testHandleWithIntegerBlockPrefix(): void
    {
        $property = Property::factory('count');
        $config = $this->makeConfig('integer', []);

        $this->handler->handle($property, $config);

        self::assertSame('integer', $property->type);
    }

    public function testHandleWithNoConstraints(): void
    {
        $property = Property::factory('value');
        $config = $this->makeConfig('number', []);

        $this->handler->handle($property, $config);

        self::assertSame([], $property->attributes);
    }

    public function testHandleWithPositiveConstraint(): void
    {
        $property = Property::factory('count');
        $config = $this->makeConfig('integer', [new Positive()]);

        $this->handler->handle($property, $config);

        self::assertSame(1, $property->attributes['minimum']);
    }

    public function testHandleWithPositiveOrZeroConstraint(): void
    {
        $property = Property::factory('count');
        $config = $this->makeConfig('integer', [new PositiveOrZero()]);

        $this->handler->handle($property, $config);

        self::assertSame(0, $property->attributes['minimum']);
    }

    public function testHandleWithRangeConstraint(): void
    {
        $property = Property::factory('score');
        $config = $this->makeConfig('number', [new Range(min: 0, max: 100)]);

        $this->handler->handle($property, $config);

        self::assertSame(0, $property->attributes['minimum']);
        self::assertSame(100, $property->attributes['maximum']);
    }

    public function testHandleWithRangeMinOnly(): void
    {
        $property = Property::factory('score');
        $config = $this->makeConfig('number', [new Range(min: 5)]);

        $this->handler->handle($property, $config);

        self::assertSame(5, $property->attributes['minimum']);
        self::assertArrayNotHasKey('maximum', $property->attributes);
    }

    private function makeConfig(string $blockPrefix, array $constraints): FormConfigInterface
    {
        $resolvedType = $this->createMock(ResolvedFormTypeInterface::class);
        $resolvedType->method('getBlockPrefix')->willReturn($blockPrefix);

        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getType')->willReturn($resolvedType);
        $config->method('getOption')->willReturnMap([
            ['constraints', null, null, $constraints],
        ]);

        return $config;
    }
}
