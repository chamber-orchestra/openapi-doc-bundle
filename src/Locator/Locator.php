<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Locator;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Operation;
use ChamberOrchestra\OpenApiDocBundle\Utils\ClassParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Routing\Attribute\Route;

class Locator
{
    public function __construct(private ClassParser $classParser)
    {
    }

    public function locate(string $srcPath): iterable
    {
        return $this->getClasses($srcPath);
    }

    private function getClasses(string $srcPath): iterable
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcPath));

        $classes = [];
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Pre-filter: only load classes whose source mentions both attributes.
            // Checks both short-name (#[Operation) and FQCN (Attribute\Operation) forms.
            $content = file_get_contents($file->getPathname());
            $hasOperation = str_contains($content, '#[Operation') || str_contains($content, 'Attribute\Operation');
            $hasRoute     = str_contains($content, '#[Route')     || str_contains($content, 'Attribute\Route');
            if (!$hasOperation || !$hasRoute) {
                continue;
            }

            if ($class = $this->classParser->extractClass($file->getPathname())) {
                try {
                    $reflectionClass = new ReflectionClass($class);
                } catch (\Throwable $e) {
                    continue;
                }
                if ($this->containsAttribute($reflectionClass, Operation::class) && $this->containsAttribute($reflectionClass, Route::class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private function containsAttribute(ReflectionClass $reflectionClass, string $attributeName): bool
    {
        $attributes = $reflectionClass->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeName) {
                return true;
            }
        }

        return false;
    }
}
