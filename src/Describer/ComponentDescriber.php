<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Describer;

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

        $stub = new Component();
        $stub->id = (new ReflectionClass($class))->getShortName();
        $this->inProgress[$class] = $stub;

        try {
            return parent::describe($class);
        } finally {
            unset($this->inProgress[$class]);
        }
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
