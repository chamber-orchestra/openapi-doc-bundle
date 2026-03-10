<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Registry;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ChamberOrchestra\OpenApiDocBundle\Registry\OperationRegistry;
use PHPUnit\Framework\TestCase;

class OperationRegistryTest extends TestCase
{
    private OperationRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new OperationRegistry();
    }

    public function testEmptyRegistry(): void
    {
        self::assertSame([], $this->registry->getAll());
    }

    public function testRegisterAndRetrieve(): void
    {
        $operation         = new Operation();
        $operation->id     = 'getUser';
        $operation->path   = '/users/{id}';
        $operation->method = 'GET';

        $this->registry->register($operation);

        $all = $this->registry->getAll();

        self::assertCount(1, $all);
        self::assertSame($operation, $all[0]);
    }

    public function testDuplicateRegistrationIsIgnored(): void
    {
        $first     = new Operation();
        $first->id = 'op';
        $first->path   = '/a';
        $first->method = 'GET';

        $second     = new Operation();
        $second->id = 'op';
        $second->path   = '/b';
        $second->method = 'POST';

        $this->registry->register($first);
        $this->registry->register($second);

        $all = $this->registry->getAll();

        self::assertCount(1, $all);
        self::assertSame('/a', $all[0]->path);
    }

    public function testMultipleOperations(): void
    {
        $get         = new Operation();
        $get->id     = 'getUser';
        $get->path   = '/users/{id}';
        $get->method = 'GET';

        $post         = new Operation();
        $post->id     = 'createUser';
        $post->path   = '/users';
        $post->method = 'POST';

        $this->registry->register($get);
        $this->registry->register($post);

        self::assertCount(2, $this->registry->getAll());
    }

    public function testRegisterReturnsSameModelOnDuplicate(): void
    {
        $operation         = new Operation();
        $operation->id     = 'op';
        $operation->path   = '/x';
        $operation->method = 'GET';

        $returned = $this->registry->register($operation);
        self::assertSame($operation, $returned);

        $duplicate         = new Operation();
        $duplicate->id     = 'op';
        $duplicate->path   = '/y';
        $duplicate->method = 'POST';

        $returned2 = $this->registry->register($duplicate);
        self::assertSame($operation, $returned2);
    }
}
