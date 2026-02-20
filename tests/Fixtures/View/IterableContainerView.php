<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

class IterableContainerView implements ViewInterface
{
    public string $title = '';

    #[Type(SimpleView::class)]
    public IterableView $items;
}
