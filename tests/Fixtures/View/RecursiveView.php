<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\View;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

/**
 * Mirrors the SenseView pattern in dicty/api: a view that contains
 * an IterableView of itself (tree structure).
 */
class RecursiveView implements ViewInterface
{
    public string $name = '';

    #[Type(RecursiveView::class)]
    public IterableView $children;
}
