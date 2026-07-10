<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Describer;

use ChamberOrchestra\OpenApiDocBundle\Attribute\Extension;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ReflectionClass;

class ComponentDescriber extends AbstractDescriber
{
    /** @var array<string, Component> Classes currently being described (cycle guard) */
    private array $inProgress = [];

    public function describe(string $class): Model
    {
        if (isset($this->inProgress[$class])) {
            return $this->inProgress[$class];
        }

        $reflection = new ReflectionClass($class);

        $stub = new Component();
        $stub->id = $reflection->getShortName();
        $this->inProgress[$class] = $stub;

        try {
            $model = parent::describe($class);
        } finally {
            unset($this->inProgress[$class]);
        }

        // Collected here (not in the parsers) so #[Extension] works uniformly for
        // forms, views and plain DTOs regardless of which component parser matched.
        foreach ($reflection->getAttributes(Extension::class) as $attribute) {
            $instance = $attribute->newInstance();
            $model->extensions[$instance->name] = $instance->value;
        }

        return $model;
    }

    protected function getItemsToParse(string $class): iterable
    {
        return [new ReflectionClass($class)];
    }

    protected function getModel(): string
    {
        return Component::class;
    }
}
