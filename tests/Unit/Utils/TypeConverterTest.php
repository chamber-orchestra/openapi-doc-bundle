<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Utils;

use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Enum\Priority;
use ChamberOrchestra\OpenApiDocBundle\Utils\TypeConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class TypeConverterTest extends TestCase
{
    private TypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new TypeConverter();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('primitiveTypesProvider')]
    public function testPrimitiveTypes(string $phpType, string $expected): void
    {
        self::assertSame($expected, $this->converter->toOpenApiType($phpType));
    }

    public static function primitiveTypesProvider(): array
    {
        return [
            'int'      => ['int', 'integer'],
            'float'    => ['float', 'number'],
            'bool'     => ['bool', 'boolean'],
            'string'   => ['string', 'string'],
            'array'    => ['array', 'array'],
            'iterable' => ['iterable', 'array'],
            '?int'     => ['?int', 'integer'],
            '?string'  => ['?string', 'string'],
            '?bool'    => ['?bool', 'boolean'],
            '?float'   => ['?float', 'number'],
        ];
    }

    public function testClassNameReturnsNull(): void
    {
        self::assertNull($this->converter->toOpenApiType('App\Dto\UserDto'));
    }

    public function testUnknownTypeReturnsNull(): void
    {
        self::assertNull($this->converter->toOpenApiType('mixed'));
    }

    public function testVoidReturnsNull(): void
    {
        self::assertNull($this->converter->toOpenApiType('void'));
    }

    // -------------------------------------------------------------------------
    // toOpenApiProperty — class types
    // -------------------------------------------------------------------------

    public function testBackedStringEnumMapsToStringWithEnumValues(): void
    {
        $result = $this->converter->toOpenApiProperty(Priority::class);

        self::assertSame(['type' => 'string', 'enum' => ['low', 'medium', 'high']], $result);
    }

    public function testUuidMapsToStringWithUuidFormat(): void
    {
        if (!class_exists(Uuid::class)) {
            self::markTestSkipped('symfony/uid not installed');
        }

        $result = $this->converter->toOpenApiProperty(Uuid::class);

        self::assertSame(['type' => 'string', 'format' => 'uuid'], $result);
    }

    public function testDateTimeImmutableMapsToStringWithDateTimeFormat(): void
    {
        $result = $this->converter->toOpenApiProperty(\DateTimeImmutable::class);

        self::assertSame(['type' => 'string', 'format' => 'date-time'], $result);
    }

    public function testDateTimeInterfaceMapsToStringWithDateTimeFormat(): void
    {
        $result = $this->converter->toOpenApiProperty(\DateTimeInterface::class);

        self::assertSame(['type' => 'string', 'format' => 'date-time'], $result);
    }

    public function testUnknownClassReturnsNull(): void
    {
        self::assertNull($this->converter->toOpenApiProperty(\stdClass::class));
    }
}
