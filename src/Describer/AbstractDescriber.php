<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Describer;

use ChamberOrchestra\OpenApiDocBundle\Exception\DescriberException;
use ChamberOrchestra\OpenApiDocBundle\Model\Model;
use ChamberOrchestra\OpenApiDocBundle\Parser\ParserInterface;
use ChamberOrchestra\OpenApiDocBundle\Registry\RegistryInterface;

abstract class AbstractDescriber implements DescriberInterface
{
    public function __construct(protected iterable $parsers, protected RegistryInterface $registry)
    {
    }

    protected abstract function getItemsToParse(string $class): iterable;

    protected abstract function getModel(): string;

    public function describe(string $class): Model
    {
        $items = $this->getItemsToParse($class);

        $model = new ($this->getModel())();

        /* @var ParserInterface $parser */
        foreach ($items as $item) {
            foreach ($this->parsers as $parser) {
                if ($parser->supports($item)) {
                    $model = $parser->parse($model, $item);
                    break;
                }
            }
        }

        if (null === $model->id){
            throw new DescriberException('No parsers was found for $class '.$class);
        }

        return $this->registry->register($model);
    }
}
