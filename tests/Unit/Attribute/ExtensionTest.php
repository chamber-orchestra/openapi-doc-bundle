<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Attribute;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Extension;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    public function testAcceptsVendorExtensionName(): void
    {
        $extension = new Extension('x-entity', 'App\Entity\User');

        self::assertSame('x-entity', $extension->name);
        self::assertSame('App\Entity\User', $extension->value);
    }

    public function testAcceptsArbitraryValueTypes(): void
    {
        self::assertSame(['a' => 1], (new Extension('x-meta', ['a' => 1]))->value);
        self::assertTrue((new Extension('x-internal', true))->value);
        self::assertNull((new Extension('x-nothing', null))->value);
    }

    public function testRejectsNameWithoutVendorPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with "x-"');

        new Extension('entity', 'App\Entity\User');
    }

    public function testRejectsBareXName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Extension('x', 'value');
    }
}
