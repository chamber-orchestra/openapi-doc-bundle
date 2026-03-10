<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Operation;

class OperationRegistry extends Registry
{
    /** @return Operation[] */
    public function getAll(): array
    {
        return array_values($this->models);
    }
}
