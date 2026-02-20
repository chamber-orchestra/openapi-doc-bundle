<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;

abstract class Registry implements RegistryInterface
{
    protected iterable $models = [];

    public function register(Model $model): Model
    {
        $id = $model->id;
        if (!isset($this->models[$id])) {
            $this->models[$id] = $model;
        }

        return $this->models[$id];
    }

    abstract public function getAll(): array;
}
