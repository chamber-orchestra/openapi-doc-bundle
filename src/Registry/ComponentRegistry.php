<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;

class ComponentRegistry extends Registry
{
    /** @return Component[] */
    public function getAll(): array
    {
        return array_values($this->models);
    }
}
