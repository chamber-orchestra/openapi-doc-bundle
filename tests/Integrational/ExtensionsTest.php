<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Integrational;

use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\ExtendedView;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View\SimpleView;

class ExtensionsTest extends AbstractIntegrationTestCase
{
    public function testExtensionsAreCollectedOnComponent(): void
    {
        $component = $this->describer->describe(ExtendedView::class);

        self::assertSame(
            [
                'x-entity' => 'App\Entity\User',
                'x-doc-group' => 'users',
                'x-internal' => true,
            ],
            $component->extensions,
        );
    }

    public function testExtensionsAreEmittedOnSchema(): void
    {
        $this->describer->describe(ExtendedView::class);

        $schema = $this->schemas()['ExtendedView'];

        self::assertSame('App\Entity\User', $schema['x-entity']);
        self::assertSame('users', $schema['x-doc-group']);
        self::assertTrue($schema['x-internal']);
        // structural keys are untouched
        self::assertSame('string', $schema['properties']['name']['type']);
    }

    public function testClassWithoutExtensionsEmitsNoVendorKeys(): void
    {
        $this->describer->describe(SimpleView::class);

        $schema = $this->schemas()['SimpleView'];

        self::assertSame([], array_filter(
            array_keys($schema),
            static fn (string $key) => str_starts_with($key, 'x-'),
        ));
    }
}
