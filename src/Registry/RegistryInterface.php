<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;

interface RegistryInterface
{
    public function register(Model $model): Model;

    public function getAll(): array;
}
