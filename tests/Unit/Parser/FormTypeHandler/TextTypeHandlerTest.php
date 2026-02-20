<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\TextTypeHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class TextTypeHandlerTest extends TestCase
{
    private TextTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TextTypeHandler();
    }

    public function testSupportsText(): void
    {
        self::assertTrue($this->handler->supports('text'));
    }

    public function testDoesNotSupportOtherPrefixes(): void
    {
        self::assertFalse($this->handler->supports('integer'));
        self::assertFalse($this->handler->supports('number'));
        self::assertFalse($this->handler->supports('choice'));
        self::assertFalse($this->handler->supports('password'));
    }

    public function testHandleSetsStringType(): void
    {
        $property = Property::factory('name');
        $config = $this->makeConfig([]);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
    }

    public function testHandleWithNoConstraints(): void
    {
        $property = Property::factory('name');
        $config = $this->makeConfig([]);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
        self::assertNull($property->format);
        self::assertSame([], $property->attributes);
    }

    public function testHandleWithLengthConstraint(): void
    {
        $property = Property::factory('username');
        $config = $this->makeConfig([new Length(min: 3, max: 50)]);

        $this->handler->handle($property, $config);

        self::assertSame(3, $property->attributes['minLength']);
        self::assertSame(50, $property->attributes['maxLength']);
    }

    public function testHandleWithLengthConstraintMinOnly(): void
    {
        $property = Property::factory('username');
        $config = $this->makeConfig([new Length(min: 2)]);

        $this->handler->handle($property, $config);

        self::assertSame(2, $property->attributes['minLength']);
        self::assertArrayNotHasKey('maxLength', $property->attributes);
    }

    public function testHandleWithEmailConstraint(): void
    {
        $property = Property::factory('email');
        $config = $this->makeConfig([new Email()]);

        $this->handler->handle($property, $config);

        self::assertSame('email', $property->format);
    }

    public function testHandleWithUrlConstraint(): void
    {
        $property = Property::factory('website');
        $config = $this->makeConfig([new Url()]);

        $this->handler->handle($property, $config);

        self::assertSame('uri', $property->format);
    }

    public function testHandleWithRegexConstraint(): void
    {
        $property = Property::factory('slug');
        $config = $this->makeConfig([new Regex(pattern: '/^[a-z0-9-]+$/')]);

        $this->handler->handle($property, $config);

        self::assertSame('/^[a-z0-9-]+$/', $property->attributes['pattern']);
    }

    public function testHandleWithMultipleConstraints(): void
    {
        $property = Property::factory('email');
        $config = $this->makeConfig([
            new Email(),
            new Length(min: 5, max: 255),
        ]);

        $this->handler->handle($property, $config);

        self::assertSame('email', $property->format);
        self::assertSame(5, $property->attributes['minLength']);
        self::assertSame(255, $property->attributes['maxLength']);
    }

    private function makeConfig(array $constraints): FormConfigInterface
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getOption')->with('constraints')->willReturn($constraints);

        return $config;
    }
}
