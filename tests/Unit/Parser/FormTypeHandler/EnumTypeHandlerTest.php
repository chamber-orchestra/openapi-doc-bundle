<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Parser\FormTypeHandler;

use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\ChoiceTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\EnumTypeHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;

class EnumTypeHandlerTest extends TestCase
{
    private EnumTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new EnumTypeHandler(new ChoiceTypeHandler());
    }

    public function testSupportsEnumOnly(): void
    {
        self::assertTrue($this->handler->supports('enum'));
        self::assertFalse($this->handler->supports('choice'));
        self::assertFalse($this->handler->supports('text'));
    }

    public function testSingleEnumWithBackedEnumChoices(): void
    {
        $property = Property::factory('status');
        $config = $this->makeConfig(false, [
            'Draft' => SampleStatus::Draft,
            'Published' => SampleStatus::Published,
        ]);

        $this->handler->handle($property, $config);

        self::assertSame('string', $property->type);
        self::assertSame(['draft', 'published'], $property->enum);
    }

    public function testMultipleEnumWithBackedEnumChoices(): void
    {
        $property = Property::factory('statuses');
        $config = $this->makeConfig(true, [
            'Draft' => SampleStatus::Draft,
            'Published' => SampleStatus::Published,
        ]);

        $this->handler->handle($property, $config);

        self::assertSame('array', $property->type);
        self::assertNotNull($property->items);
        self::assertSame('string', $property->items->type);
        self::assertSame(['draft', 'published'], $property->items->enum);
    }

    /**
     * @param array<string, mixed> $choices
     */
    private function makeConfig(bool $multiple, array $choices): FormConfigInterface
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getOption')->willReturnMap([
            ['multiple', null, null, $multiple],
            ['choices', null, null, $choices],
            ['constraints', null, null, []],
        ]);

        return $config;
    }
}

enum SampleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
