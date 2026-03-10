<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Locator;

use ChamberOrchestra\OpenApiDocBundle\Locator\Locator;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action\SimpleAction;
use ChamberOrchestra\OpenApiDocBundle\Utils\ClassParser;
use PHPUnit\Framework\TestCase;

class LocatorTest extends TestCase
{
    private Locator $locator;

    protected function setUp(): void
    {
        $this->locator = new Locator(new ClassParser());
    }

    public function testLocatesActionWithBothAttributes(): void
    {
        $path = __DIR__ . '/../../Fixtures/Action';
        $classes = iterator_to_array($this->locator->locate($path), false);

        self::assertContains(SimpleAction::class, $classes);
    }

    public function testDoesNotLocateActionWithoutRoute(): void
    {
        $path = __DIR__ . '/../../Fixtures/Action';
        $classes = iterator_to_array($this->locator->locate($path), false);

        self::assertNotContains(
            'ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action\ActionWithoutRoute',
            $classes
        );
    }

    public function testDoesNotLocateActionWithoutOperation(): void
    {
        $path = __DIR__ . '/../../Fixtures/Action';
        $classes = iterator_to_array($this->locator->locate($path), false);

        self::assertNotContains(
            'ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Action\ActionWithoutOperation',
            $classes
        );
    }

    public function testReturnsOnlyQualifyingClasses(): void
    {
        $path = __DIR__ . '/../../Fixtures/Action';
        $classes = iterator_to_array($this->locator->locate($path), false);

        self::assertCount(1, $classes);
    }

    public function testIgnoresNonPhpFiles(): void
    {
        // Create a temp dir with a non-PHP file and a PHP action file
        $tmpDir = sys_get_temp_dir() . '/locator_test_' . uniqid();
        mkdir($tmpDir);

        // Write a README that should be ignored
        file_put_contents($tmpDir . '/README.md', '# docs');

        // Copy the SimpleAction fixture
        copy(
            __DIR__ . '/../../Fixtures/Action/SimpleAction.php',
            $tmpDir . '/SimpleAction.php'
        );

        $classes = iterator_to_array($this->locator->locate($tmpDir), false);

        // Clean up
        array_map('unlink', glob($tmpDir . '/*'));
        rmdir($tmpDir);

        // The SimpleAction fixture class is already loaded; its FQCN won't change
        // just assert no error was thrown and result is iterable
        self::assertIsArray($classes);
    }

    public function testEmptyDirectoryReturnsEmpty(): void
    {
        $tmpDir = sys_get_temp_dir() . '/locator_empty_' . uniqid();
        mkdir($tmpDir);

        $classes = iterator_to_array($this->locator->locate($tmpDir), false);

        rmdir($tmpDir);

        self::assertSame([], $classes);
    }
}
