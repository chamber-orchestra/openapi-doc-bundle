<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Describer;

use ChamberOrchestra\OpenApiDocBundle\Model\Model;

interface DescriberInterface
{
    /**
     * Creates model and fill it with data by using attribute parsers
     * @param string $class
     * @return Model
     */
    public function describe(string $class): Model;
}