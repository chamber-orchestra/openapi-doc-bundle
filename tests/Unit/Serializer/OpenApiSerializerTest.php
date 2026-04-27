<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Serializer;

use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Parser\SecurityParser;
use ChamberOrchestra\OpenApiDocBundle\Serializer\OpenApiSerializer;
use PHPUnit\Framework\TestCase;

class OpenApiSerializerTest extends TestCase
{
    private OpenApiSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new OpenApiSerializer();
    }

    // -------------------------------------------------------------------------
    // serializeComponents
    // -------------------------------------------------------------------------

    public function testEmptyComponents(): void
    {
        self::assertSame(['schemas' => []], $this->serializer->serializeComponents([]));
    }

    public function testSimpleComponent(): void
    {
        $component             = new Component();
        $component->id         = 'UserModel';
        $component->properties = [Property::factory('name', 'string')];

        [$result] = [$this->serializer->serializeComponents([$component])];

        self::assertArrayHasKey('UserModel', $result['schemas']);
        self::assertSame('string', $result['schemas']['UserModel']['properties']['name']['type']);
    }

    public function testComponentWithRequired(): void
    {
        $component             = new Component();
        $component->id         = 'RequiredModel';
        $component->properties = [Property::factory('name', 'string')];
        $component->required   = ['name'];

        $result = $this->serializer->serializeComponents([$component]);

        self::assertSame(['name'], $result['schemas']['RequiredModel']['required']);
    }

    public function testComponentWithoutRequiredOmitsField(): void
    {
        $component             = new Component();
        $component->id         = 'OptModel';
        $component->properties = [Property::factory('x', 'string')];

        $result = $this->serializer->serializeComponents([$component]);

        self::assertArrayNotHasKey('required', $result['schemas']['OptModel']);
    }

    public function testPropertyWithRef(): void
    {
        $ref     = new Component();
        $ref->id = 'RefModel';

        $prop      = Property::factory('child', 'object');
        $prop->ref = $ref;

        $component             = new Component();
        $component->id         = 'Parent';
        $component->properties = [$prop];

        $result = $this->serializer->serializeComponents([$component]);
        $child  = $result['schemas']['Parent']['properties']['child'];

        self::assertSame('#/components/schemas/RefModel', $child['$ref']);
        self::assertArrayNotHasKey('type', $child);
    }

    public function testArrayTypeComponent(): void
    {
        $ref     = new Component();
        $ref->id = 'ItemModel';

        $itemsProp      = Property::factory('items');
        $itemsProp->ref = $ref;

        $component        = new Component();
        $component->id    = 'MyList';
        $component->type  = 'array';
        $component->items = $itemsProp;

        $result = $this->serializer->serializeComponents([$component]);
        $schema = $result['schemas']['MyList'];

        self::assertSame('array', $schema['type']);
        self::assertSame('#/components/schemas/ItemModel', $schema['items']['$ref']);
    }

    public function testPropertyWithFormat(): void
    {
        $prop             = new Component();
        $prop->id         = 'M';
        $prop->properties = [Property::factory('email', 'string', 'email')];

        $result = $this->serializer->serializeComponents([$prop]);

        self::assertSame('email', $result['schemas']['M']['properties']['email']['format']);
    }

    public function testPropertyWithEnum(): void
    {
        $p       = Property::factory('status', 'string');
        $p->enum = ['active', 'inactive'];

        $component             = new Component();
        $component->id         = 'StatusModel';
        $component->properties = [$p];

        $result = $this->serializer->serializeComponents([$component]);

        self::assertSame(['active', 'inactive'], $result['schemas']['StatusModel']['properties']['status']['enum']);
    }

    public function testPropertyWithExtraAttributes(): void
    {
        $p             = Property::factory('username', 'string');
        $p->attributes = ['minLength' => 3, 'maxLength' => 50, 'pattern' => '^[a-z]+$'];

        $component             = new Component();
        $component->id         = 'UsernameModel';
        $component->properties = [$p];

        $result = $this->serializer->serializeComponents([$component]);
        $schema = $result['schemas']['UsernameModel']['properties']['username'];

        self::assertSame(3, $schema['minLength']);
        self::assertSame(50, $schema['maxLength']);
        self::assertSame('^[a-z]+$', $schema['pattern']);
    }

    public function testExcludedComponentIsOmitted(): void
    {
        $a     = new Component();
        $a->id = 'IncludedModel';

        $b     = new Component();
        $b->id = 'ExcludedForm';

        $result = $this->serializer->serializeComponents([$a, $b], ['ExcludedForm']);

        self::assertArrayHasKey('IncludedModel', $result['schemas']);
        self::assertArrayNotHasKey('ExcludedForm', $result['schemas']);
    }

    // -------------------------------------------------------------------------
    // serializePaths
    // -------------------------------------------------------------------------

    public function testSimpleOperation(): void
    {
        $op              = new Operation();
        $op->id          = 'getUser';
        $op->path        = '/users/{id}';
        $op->method      = 'GET';
        $op->description = 'Get a user';

        [$paths] = $this->serializer->serializePaths([$op], null);

        self::assertArrayHasKey('/users/{id}', $paths);
        self::assertArrayHasKey('get', $paths['/users/{id}']);
        self::assertSame('getUser', $paths['/users/{id}']['get']['operationId']);
        self::assertSame('Get a user', $paths['/users/{id}']['get']['description']);
    }

    public function testOperationWithoutDescriptionOmitsField(): void
    {
        $op              = new Operation();
        $op->id          = 'list';
        $op->path        = '/items';
        $op->method      = 'GET';
        $op->description = null;

        [$paths] = $this->serializer->serializePaths([$op], null);

        self::assertArrayNotHasKey('description', $paths['/items']['get']);
    }

    public function testOperationWithComponentResponse(): void
    {
        $response         = new Component();
        $response->id     = 'UserView';
        $response->status = 200;
        $response->headers = ['Content-Type' => 'application/json'];

        $op            = new Operation();
        $op->id        = 'getUser';
        $op->path      = '/users';
        $op->method    = 'GET';
        $op->responses = [200 => $response];

        [$paths] = $this->serializer->serializePaths([$op], null);

        $responses = $paths['/users']['get']['responses'];
        self::assertSame('#/components/schemas/UserView', $responses['200']['content']['application/json']['schema']['$ref']);
    }

    public function testOperationWithStringResponse(): void
    {
        $op            = new Operation();
        $op->id        = 'createUser';
        $op->path      = '/users';
        $op->method    = 'POST';
        $op->responses = ['404' => 'NotFound'];

        [$paths] = $this->serializer->serializePaths([$op], null);

        self::assertSame('#/components/responses/NotFound', $paths['/users']['post']['responses']['404']['$ref']);
    }

    public function testOperationWithRequestBody(): void
    {
        $requestComponent     = new Component();
        $requestComponent->id = 'UserForm';

        $op          = new Operation();
        $op->id      = 'createUser';
        $op->path    = '/users';
        $op->method  = 'POST';
        $op->request = $requestComponent;

        [$paths] = $this->serializer->serializePaths([$op], null);

        self::assertSame(
            '#/components/schemas/UserForm',
            $paths['/users']['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
    }

    public function testGetRequestExpandsFormAsQueryParams(): void
    {
        $form             = new Component();
        $form->id         = 'SearchForm';
        $form->properties = [Property::factory('query', 'string')];
        $form->required   = [];

        $op          = new Operation();
        $op->id      = 'search';
        $op->path    = '/search';
        $op->method  = 'GET';
        $op->request = $form;

        [$paths, $excludedIds] = $this->serializer->serializePaths([$op], null);

        self::assertArrayNotHasKey('requestBody', $paths['/search']['get']);
        self::assertSame('query', $paths['/search']['get']['parameters'][0]['name']);
        self::assertSame('query', $paths['/search']['get']['parameters'][0]['in']);
        self::assertContains('SearchForm', $excludedIds);
    }

    public function testSecurityPlaceholderResolvedToFirstScheme(): void
    {
        $op           = new Operation();
        $op->id       = 'secured';
        $op->path     = '/secured';
        $op->method   = 'GET';
        $op->security = [SecurityParser::SECURITY_PLACEHOLDER => []];

        [$paths] = $this->serializer->serializePaths([$op], 'BearerAuth');

        $security = $paths['/secured']['get']['security'];
        self::assertArrayHasKey('BearerAuth', $security[0]);
        self::assertArrayNotHasKey(SecurityParser::SECURITY_PLACEHOLDER, $security[0]);
    }

    public function testSecurityPlaceholderStrippedWhenNoScheme(): void
    {
        $op           = new Operation();
        $op->id       = 'secured';
        $op->path     = '/secured';
        $op->method   = 'GET';
        $op->security = [SecurityParser::SECURITY_PLACEHOLDER => []];

        [$paths] = $this->serializer->serializePaths([$op], null);

        // The 'default' placeholder cannot be resolved without a scheme. Falling back to an
        // explicit empty security requirement means the operation is treated as public, which
        // matches OpenAPI semantics and satisfies tooling that requires `security` to be set
        // on every operation (e.g. redocly's `security-defined` rule).
        self::assertSame([], $paths['/secured']['get']['security']);
    }

    public function testAutoResponses4xxInjectedWhenProtoDefinesThem(): void
    {
        $form         = new Component();
        $form->id     = 'CreateThingForm';

        $op           = new Operation();
        $op->id       = 'create';
        $op->path     = '/things/{id}';
        $op->method   = 'POST';
        $op->security = [SecurityParser::SECURITY_PLACEHOLDER => []];
        $op->request  = $form;

        $auto = [
            '401' => 'UnauthorizedError',
            '403' => 'ForbiddenError',
            '404' => 'NotFoundError',
            '422' => 'ValidationError',
        ];

        [$paths] = $this->serializer->serializePaths([$op], 'BearerAuth', $auto);
        $responses = $paths['/things/{id}']['post']['responses'];

        self::assertSame('#/components/responses/UnauthorizedError', $responses['401']['$ref']);
        self::assertSame('#/components/responses/ForbiddenError',    $responses['403']['$ref']);
        self::assertSame('#/components/responses/NotFoundError',     $responses['404']['$ref']);
        self::assertSame('#/components/responses/ValidationError',   $responses['422']['$ref']);
    }

    public function testAutoResponsesSkippedForUnprotectedOpWithoutFormOrPathParam(): void
    {
        $op         = new Operation();
        $op->id     = 'health';
        $op->path   = '/health';
        $op->method = 'GET';

        $auto = [
            '401' => 'UnauthorizedError',
            '403' => 'ForbiddenError',
            '404' => 'NotFoundError',
            '422' => 'ValidationError',
        ];

        [$paths] = $this->serializer->serializePaths([$op], null, $auto);

        self::assertArrayNotHasKey('401', $paths['/health']['get']['responses']);
        self::assertArrayNotHasKey('403', $paths['/health']['get']['responses']);
        self::assertArrayNotHasKey('404', $paths['/health']['get']['responses']);
        self::assertArrayNotHasKey('422', $paths['/health']['get']['responses']);
    }

    public function testAutoResponsesNeverOverwriteExplicit4xx(): void
    {
        $op         = new Operation();
        $op->id     = 'login';
        $op->path   = '/login';
        $op->method = 'POST';
        $op->responses = ['401' => 'CustomLoginFailure'];

        [$paths] = $this->serializer->serializePaths([$op], null, ['401' => 'UnauthorizedError']);

        // Explicit ref wins; auto-injection must not clobber the developer's choice.
        self::assertSame('#/components/responses/CustomLoginFailure', $paths['/login']['post']['responses']['401']['$ref']);
    }

    public function testMethodIsLowercased(): void
    {
        $op         = new Operation();
        $op->id     = 'del';
        $op->path   = '/items/{id}';
        $op->method = 'DELETE';

        [$paths] = $this->serializer->serializePaths([$op], null);

        self::assertArrayHasKey('delete', $paths['/items/{id}']);
        self::assertArrayNotHasKey('DELETE', $paths['/items/{id}']);
    }

    public function testMissingPathThrowsLogicException(): void
    {
        $op         = new Operation();
        $op->id     = 'bad';
        $op->path   = '';
        $op->method = 'GET';

        $this->expectException(\LogicException::class);
        $this->serializer->serializePaths([$op], null);
    }
}
