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
        self::assertSame(['schemas' => []], $this->registry->getAll());
    }

    public function testSimpleComponent(): void
    {
        $component = new Component();
        $component->id = 'SimpleModel';
        $component->properties = [Property::factory('name', 'string')];

        $this->registry->register($component);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('SimpleModel', $result['schemas']);
        self::assertSame('string', $result['schemas']['SimpleModel']['properties']['name']['type']);
    }

    public function testComponentWithMultipleProperties(): void
    {
        $component = new Component();
        $component->id = 'UserModel';
        $component->properties = [
            Property::factory('name', 'string'),
            Property::factory('age', 'integer'),
            Property::factory('active', 'boolean'),
        ];

        $this->registry->register($component);

        $result = $this->registry->getAll();
        $schema = $result['schemas']['UserModel'];

        self::assertSame('string', $schema['properties']['name']['type']);
        self::assertSame('integer', $schema['properties']['age']['type']);
        self::assertSame('boolean', $schema['properties']['active']['type']);
    }

    public function testComponentWithRequired(): void
    {
        $component = new Component();
        $component->id = 'RequiredModel';
        $component->properties = [Property::factory('name', 'string')];
        $component->required = ['name'];

        $this->registry->register($component);

        $result = $this->registry->getAll();

        self::assertSame(['name'], $result['schemas']['RequiredModel']['required']);
    }

    public function testComponentWithoutRequiredOmitsField(): void
    {
        $component = new Component();
        $component->id = 'OptionalModel';
        $component->properties = [Property::factory('name', 'string')];
        // $component->required stays []

        $this->registry->register($component);

        $result = $this->registry->getAll();

        self::assertArrayNotHasKey('required', $result['schemas']['OptionalModel']);
    }

    public function testPropertyWithRef(): void
    {
        $refComponent = new Component();
        $refComponent->id = 'RefModel';

        $component = new Component();
        $component->id = 'ParentModel';
        $refProperty = Property::factory('child', 'object');
        $refProperty->ref = $refComponent;
        $component->properties = [$refProperty];

        $this->registry->register($component);

        $result = $this->registry->getAll();
        $childData = $result['schemas']['ParentModel']['properties']['child'];

        self::assertArrayHasKey('$ref', $childData);
        self::assertSame('#/components/schemas/RefModel', $childData['$ref']);
        self::assertArrayNotHasKey('type', $childData);
    }

    public function testArrayTypeComponent(): void
    {
        $itemRef = new Component();
        $itemRef->id = 'ItemModel';

        $itemsProperty = Property::factory('items');
        $itemsProperty->ref = $itemRef;

        $component = new Component();
        $component->id = 'MyList';
        $component->type = 'array';
        $component->items = $itemsProperty;

        $this->registry->register($component);

        $result = $this->registry->getAll();
        $schema = $result['schemas']['MyList'];

        self::assertSame('array', $schema['type']);
        self::assertSame('#/components/schemas/ItemModel', $schema['items']['$ref']);
    }

    public function testDuplicateRegistrationIsIgnored(): void
    {
        $first = new Component();
        $first->id = 'Duplicate';
        $first->properties = [Property::factory('original', 'string')];

        $second = new Component();
        $second->id = 'Duplicate';
        $second->properties = [Property::factory('override', 'integer')];

        $this->registry->register($first);
        $this->registry->register($second);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('original', $result['schemas']['Duplicate']['properties']);
        self::assertArrayNotHasKey('override', $result['schemas']['Duplicate']['properties']);
    }

    public function testPropertyWithFormat(): void
    {
        $property = Property::factory('email', 'string', 'email');

        $component = new Component();
        $component->id = 'FormModel';
        $component->properties = [$property];

        $this->registry->register($component);

        $result = $this->registry->getAll();

        self::assertSame('email', $result['schemas']['FormModel']['properties']['email']['format']);
    }

    public function testPropertyWithEnum(): void
    {
        $property = Property::factory('status', 'string');
        $property->enum = ['active', 'inactive'];

        $component = new Component();
        $component->id = 'StatusModel';
        $component->properties = [$property];

        $this->registry->register($component);

        $result = $this->registry->getAll();

        self::assertSame(['active', 'inactive'], $result['schemas']['StatusModel']['properties']['status']['enum']);
    }

    public function testPropertyWithExtraAttributes(): void
    {
        $property = Property::factory('username', 'string');
        $property->attributes = ['minLength' => 3, 'maxLength' => 50, 'pattern' => '^[a-z]+$'];

        $component = new Component();
        $component->id = 'UsernameModel';
        $component->properties = [$property];

        $this->registry->register($component);

        $result = $this->registry->getAll();
        $schema = $result['schemas']['UsernameModel']['properties']['username'];

        self::assertSame(3, $schema['minLength']);
        self::assertSame(50, $schema['maxLength']);
        self::assertSame('^[a-z]+$', $schema['pattern']);
    }

    public function testMultipleComponents(): void
    {
        $c1 = new Component();
        $c1->id = 'ModelA';
        $c1->properties = [Property::factory('x', 'string')];

        $c2 = new Component();
        $c2->id = 'ModelB';
        $c2->properties = [Property::factory('y', 'integer')];

        $this->registry->register($c1);
        $this->registry->register($c2);

        $result = $this->registry->getAll();

        self::assertArrayHasKey('ModelA', $result['schemas']);
        self::assertArrayHasKey('ModelB', $result['schemas']);
    }
}
