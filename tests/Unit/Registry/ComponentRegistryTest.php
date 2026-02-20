<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Registry\ComponentRegistry;
use PHPUnit\Framework\TestCase;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ComponentRegistry();
    }

    public function testEmptyRegistry(): void
    {
        self::assertSame([], $this->registry->getAll());
    }

    public function testRegisterAndRetrieve(): void
    {
        $component             = new Component();
        $component->id         = 'UserView';
        $component->properties = [Property::factory('name', 'string')];

        $this->registry->register($component);

        $all = $this->registry->getAll();

        self::assertCount(1, $all);
        self::assertSame($component, $all[0]);
    }

    public function testDuplicateRegistrationIsIgnored(): void
    {
        $first             = new Component();
        $first->id         = 'Same';
        $first->properties = [Property::factory('original', 'string')];

        $second             = new Component();
        $second->id         = 'Same';
        $second->properties = [Property::factory('override', 'integer')];

        $this->registry->register($first);
        $this->registry->register($second);

        $all = $this->registry->getAll();

        self::assertCount(1, $all);
        self::assertSame('original', $all[0]->properties[0]->name);
    }

    public function testMultipleComponents(): void
    {
        $a     = new Component();
        $a->id = 'ModelA';

        $b     = new Component();
        $b->id = 'ModelB';

        $this->registry->register($a);
        $this->registry->register($b);

        self::assertCount(2, $this->registry->getAll());
    }

    public function testRegisterReturnsSameModelOnDuplicate(): void
    {
        $component     = new Component();
        $component->id = 'Comp';

        $returned = $this->registry->register($component);
        self::assertSame($component, $returned);

        $duplicate     = new Component();
        $duplicate->id = 'Comp';

        $returned2 = $this->registry->register($duplicate);
        self::assertSame($component, $returned2);
    }
}
