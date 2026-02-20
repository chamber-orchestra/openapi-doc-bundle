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

    public function testSimpleOperation(): void
    {
        $operation = new Operation();
        $operation->id = 'getUser';
        $operation->path = '/users/{id}';
        $operation->method = 'GET';
        $operation->description = 'Get a user';

        $this->registry->register($operation);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('/users/{id}', $result);
        self::assertArrayHasKey('get', $result['/users/{id}']);

        $op = $result['/users/{id}']['get'];

        self::assertSame('getUser', $op['operationId']);
        self::assertSame('Get a user', $op['description']);
    }

    public function testOperationWithoutDescriptionOmitsField(): void
    {
        $operation = new Operation();
        $operation->id = 'listItems';
        $operation->path = '/items';
        $operation->method = 'GET';
        $operation->description = null;

        $this->registry->register($operation);

        $result = $this->registry->getAll();
        $op = $result['/items']['get'];

        self::assertArrayNotHasKey('description', $op);
    }

    public function testOperationWithComponentResponse(): void
    {
        $response = new Component();
        $response->id = 'UserView';
        $response->status = 200;
        $response->headers = ['Content-Type' => 'application/json'];

        $operation = new Operation();
        $operation->id = 'getUser';
        $operation->path = '/users';
        $operation->method = 'GET';
        $operation->responses = [200 => $response];

        $this->registry->register($operation);

        $result = $this->registry->getAll();
        $responses = $result['/users']['get']['responses'];

        self::assertArrayHasKey(200, $responses);
        self::assertSame('#/components/schemas/UserView', $responses[200]['content']['application/json']['schema']['$ref']);
    }

    public function testOperationWithStringResponse(): void
    {
        $operation = new Operation();
        $operation->id = 'createUser';
        $operation->path = '/users';
        $operation->method = 'POST';
        $operation->responses = ['404' => 'NotFound'];

        $this->registry->register($operation);

        $result = $this->registry->getAll();
        $responses = $result['/users']['post']['responses'];

        self::assertArrayHasKey('404', $responses);
        self::assertSame('#/components/responses/NotFound', $responses['404']['$ref']);
    }

    public function testOperationWithRequestBody(): void
    {
        $requestComponent = new Component();
        $requestComponent->id = 'UserForm';

        $operation = new Operation();
        $operation->id = 'createUser';
        $operation->path = '/users';
        $operation->method = 'POST';
        $operation->request = $requestComponent;

        $this->registry->register($operation);

        $result = $this->registry->getAll();
        $op = $result['/users']['post'];

        self::assertArrayHasKey('requestBody', $op);
        self::assertSame(
            '#/components/schemas/UserForm',
            $op['requestBody']['content']['application/json']['schema']['$ref']
        );
    }

    public function testOperationWithSecurity(): void
    {
        $operation = new Operation();
        $operation->id = 'securedEndpoint';
        $operation->path = '/secured';
        $operation->method = 'GET';
        $operation->security = ['default' => []];

        $this->registry->register($operation);

        $result = $this->registry->getAll();
        $op = $result['/secured']['get'];

        self::assertSame([['default' => []]], $op['security']);
    }

    public function testMethodIsLowercased(): void
    {
        $operation = new Operation();
        $operation->id = 'myEndpoint';
        $operation->path = '/test';
        $operation->method = 'DELETE';

        $this->registry->register($operation);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('delete', $result['/test']);
        self::assertArrayNotHasKey('DELETE', $result['/test']);
    }

    public function testMultipleOperationsSamePath(): void
    {
        $get = new Operation();
        $get->id = 'getUser';
        $get->path = '/users/{id}';
        $get->method = 'GET';

        $put = new Operation();
        $put->id = 'updateUser';
        $put->path = '/users/{id}';
        $put->method = 'PUT';

        $this->registry->register($get);
        $this->registry->register($put);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('get', $result['/users/{id}']);
        self::assertArrayHasKey('put', $result['/users/{id}']);
    }
}
