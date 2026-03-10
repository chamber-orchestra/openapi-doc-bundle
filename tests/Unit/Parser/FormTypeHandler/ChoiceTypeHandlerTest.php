<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\ChoiceTypeHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Validator\Constraints\Count;

class ChoiceTypeHandlerTest extends TestCase
{
    private ChoiceTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ChoiceTypeHandler();
    }

    public function testSupportsChoice(): void
    {
        self::assertTrue($this->handler->supports('choice'));
    }

    public function testDoesNotSupportOtherPrefixes(): void
    {
        self::assertFalse($this->handler->supports('text'));
        self::assertFalse($this->handler->supports('integer'));
        self::assertFalse($this->handler->supports('collection'));
    }

    public function testHandleSingleChoiceWithStringValues(): void
    {
        $property = Property::factory('status');
        $config = $this->makeConfig(false, ['Active' => 'active', 'Inactive' => 'inactive']);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
        self::assertSame(['active', 'inactive'], $property->enum);
    }

    public function testHandleMultipleChoiceWithStringValues(): void
    {
        $property = Property::factory('tags');
        $config = $this->makeConfig(true, ['PHP' => 'php', 'Python' => 'python']);

        $this->handler->handle($property, $config);

        self::assertSame('array', $property->type);
        self::assertNotNull($property->items);
        self::assertSame('string', $property->items->type);
        self::assertSame(['php', 'python'], $property->items->enum);
    }

    public function testHandleSingleChoiceWithNumericValues(): void
    {
        $property = Property::factory('priority');
        $config = $this->makeConfig(false, ['Low' => 1, 'High' => 10]);

        $this->handler->handle($property, $config);

        self::assertSame('number', $property->type);
        self::assertSame([1, 10], $property->enum);
    }

    public function testHandleSingleChoiceWithBooleanValues(): void
    {
        $property = Property::factory('enabled');
        $config = $this->makeConfig(false, ['Yes' => true, 'No' => false]);

        $this->handler->handle($property, $config);

        self::assertSame('boolean', $property->type);
        self::assertSame([true, false], $property->enum);
    }

    public function testHandleMultipleChoiceWithNoChoices(): void
    {
        $property = Property::factory('tags');
        $config = $this->makeConfig(true, []);

        $this->handler->handle($property, $config);

        self::assertSame('array', $property->type);
        self::assertNull($property->items);
        self::assertSame([], $property->enum);
    }

    public function testHandleSingleChoiceWithNoChoices(): void
    {
        $property = Property::factory('status');
        $config = $this->makeConfig(false, []);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
        self::assertSame([], $property->enum);
    }

    public function testHandleMultipleChoiceWithCountMinConstraint(): void
    {
        $property = Property::factory('tags');
        $config = $this->makeConfig(true, [], [new Count(min: 1)]);

        $this->handler->handle($property, $config);

        self::assertSame('array', $property->type);
        self::assertSame(1, $property->attributes['minItems']);
        self::assertArrayNotHasKey('maxItems', $property->attributes);
    }

    public function testHandleMultipleChoiceWithCountMinMaxConstraint(): void
    {
        $property = Property::factory('tags');
        $config = $this->makeConfig(true, [], [new Count(min: 1, max: 5)]);

        $this->handler->handle($property, $config);

        self::assertSame(1, $property->attributes['minItems']);
        self::assertSame(5, $property->attributes['maxItems']);
    }

    public function testCountConstraintIgnoredForSingleChoice(): void
    {
        $property = Property::factory('status');
        $config = $this->makeConfig(false, [], [new Count(min: 1)]);

        $this->handler->handle($property, $config);

        self::assertArrayNotHasKey('minItems', $property->attributes);
    }

    private function makeConfig(bool $multiple, array $choices, array $constraints = []): FormConfigInterface
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getOption')->willReturnMap([
            ['multiple', null, null, $multiple],
            ['choices', null, null, $choices],
            ['constraints', null, null, $constraints],
        ]);

        return $config;
    }
}
