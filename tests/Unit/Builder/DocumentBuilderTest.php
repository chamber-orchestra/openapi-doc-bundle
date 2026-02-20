<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Unit\Builder;

use ChamberOrchestra\OpenApiDocBundle\Builder\DocumentBuilder;
use ChamberOrchestra\OpenApiDocBundle\Model\Component;
use ChamberOrchestra\OpenApiDocBundle\Model\Operation;
use ChamberOrchestra\OpenApiDocBundle\Model\Property;
use ChamberOrchestra\OpenApiDocBundle\Registry\ComponentRegistry;
use ChamberOrchestra\OpenApiDocBundle\Registry\OperationRegistry;
use ChamberOrchestra\OpenApiDocBundle\Serializer\OpenApiSerializer;
use PHPUnit\Framework\TestCase;

class DocumentBuilderTest extends TestCase
{
    private OperationRegistry $operationRegistry;
    private ComponentRegistry $componentRegistry;
    private DocumentBuilder $builder;

    protected function setUp(): void
    {
        $this->operationRegistry = new OperationRegistry();
        $this->componentRegistry = new ComponentRegistry();
        $this->builder = new DocumentBuilder($this->operationRegistry, $this->componentRegistry, new OpenApiSerializer());
    }

    public function testBuildReturnsOpenApiStructure(): void
    {
        $result = $this->builder->build();

        self::assertSame('3.0.1', $result['openapi']);
        self::assertSame('1.0.0', $result['info']['version']);
        self::assertSame('API Documentation', $result['info']['title']);
        self::assertArrayHasKey('paths', $result);
        self::assertArrayHasKey('components', $result);
    }

    public function testBuildWithCustomVersionAndTitle(): void
    {
        $result = $this->builder->build([], '2.5.0', 'My API');

        self::assertSame('2.5.0', $result['info']['version']);
        self::assertSame('My API', $result['info']['title']);
    }

    public function testBuildIncludesRegisteredOperation(): void
    {
        $operation = new Operation();
        $operation->id = 'listUsers';
        $operation->path = '/users';
        $operation->method = 'GET';
        $this->operationRegistry->register($operation);

        $result = $this->builder->build();

        self::assertArrayHasKey('/users', $result['paths']);
    }

    public function testBuildIncludesRegisteredComponent(): void
    {
        $component = new Component();
        $component->id = 'UserView';
        $component->properties = [Property::factory('name', 'string')];
        $this->componentRegistry->register($component);

        $result = $this->builder->build();

        self::assertArrayHasKey('UserView', $result['components']['schemas']);
    }

    public function testBuildMergesProtoSecuritySchemes(): void
    {
        $protoData = [
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
        ];

        $result = $this->builder->build($protoData);

        self::assertArrayHasKey('BearerAuth', $result['components']['securitySchemes']);
    }

    public function testBuildMergesProtoSchemas(): void
    {
        $protoData = [
            'components' => [
                'schemas' => [
                    'Error' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
                ],
            ],
        ];

        $result = $this->builder->build($protoData);

        self::assertArrayHasKey('Error', $result['components']['schemas']);
    }

    public function testBuildMergesProtoResponses(): void
    {
        $protoData = [
            'components' => [
                'responses' => [
                    'NotFound' => ['description' => 'Not found'],
                ],
            ],
        ];

        $result = $this->builder->build($protoData);

        self::assertArrayHasKey('NotFound', $result['components']['responses']);
    }

    public function testBuildResolvesDefaultSecurityToFirstScheme(): void
    {
        $operation = new Operation();
        $operation->id = 'securedOp';
        $operation->path = '/secured';
        $operation->method = 'GET';
        $operation->security = [\ChamberOrchestra\OpenApiDocBundle\Parser\SecurityParser::SECURITY_PLACEHOLDER => []];
        $this->operationRegistry->register($operation);

        $protoData = [
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
        ];

        $result = $this->builder->build($protoData);
        $security = $result['paths']['/secured']['get']['security'];

        self::assertArrayHasKey('BearerAuth', $security[0]);
        self::assertArrayNotHasKey('default', $security[0]);
    }

    public function testBuildStripsDefaultSecurityWhenNoProtoSchemes(): void
    {
        $operation = new Operation();
        $operation->id = 'securedOp';
        $operation->path = '/secured';
        $operation->method = 'GET';
        $operation->security = [\ChamberOrchestra\OpenApiDocBundle\Parser\SecurityParser::SECURITY_PLACEHOLDER => []];
        $this->operationRegistry->register($operation);

        $result = $this->builder->build();
        $op = $result['paths']['/secured']['get'];

        // 'default' is a placeholder — stripped when no securitySchemes are defined
        self::assertArrayNotHasKey('security', $op);
    }

    public function testProtoSchemasAreMergedWithGeneratedOnes(): void
    {
        $component = new Component();
        $component->id = 'GeneratedModel';
        $component->properties = [Property::factory('id', 'integer')];
        $this->componentRegistry->register($component);

        $protoData = [
            'components' => [
                'schemas' => [
                    'ProtoModel' => ['type' => 'object'],
                ],
            ],
        ];

        $result = $this->builder->build($protoData);

        self::assertArrayHasKey('GeneratedModel', $result['components']['schemas']);
        self::assertArrayHasKey('ProtoModel', $result['components']['schemas']);
    }
}
