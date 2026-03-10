<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use ReflectionEnum;
use Symfony\Component\Uid\AbstractUid;

class TypeConverter
{
    /**
     * Maps primitive PHP types to OpenAPI type strings.
     * Returns null for class names — use toOpenApiProperty() for those.
     */
    public function toOpenApiType(string $phpType): ?string
    {
        return match (ltrim($phpType, '?')) {
            'int'               => 'integer',
            'float'             => 'number',
            'bool'              => 'boolean',
            'string'            => 'string',
            'array', 'iterable' => 'array',
            default             => null,
        };
    }

    /**
     * Maps a class name to an OpenAPI property definition array for types
     * that the Symfony serializer handles as primitives (backed enums, UUIDs,
     * date-times). Returns null if the class should be described as a $ref component.
     *
     * @return array{type: string, format?: string, enum?: list<string|int>}|null
     */
    public function toOpenApiProperty(string $phpClass): ?array
    {
        if (enum_exists($phpClass) && is_a($phpClass, \BackedEnum::class, true)) {
            $backingType = (new ReflectionEnum($phpClass))->getBackingType()->getName();
            /** @var list<\BackedEnum> $cases */
            $cases = $phpClass::cases();

            return [
                'type' => $backingType === 'int' ? 'integer' : 'string',
                'enum' => array_map(static fn(\BackedEnum $c) => $c->value, $cases),
            ];
        }

        if (class_exists(AbstractUid::class) && is_a($phpClass, AbstractUid::class, true)) {
            return ['type' => 'string', 'format' => 'uuid'];
        }

        if (is_a($phpClass, DateTimeInterface::class, true) || is_a($phpClass, DateTimeImmutable::class, true)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        return null;
    }
}
