<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\Parser;

use ArrayObject;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormParser;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\CheckboxTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\ChoiceTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\CollectionTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\DateTimeTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\DateTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\EntityTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\FileTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\NumberTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\PasswordTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\RepeatedTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Parser\FormTypeHandler\TextTypeHandler;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\ChoiceFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\CollectionFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\ConstrainedFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\EnumChoiceFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\ExtendedFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\NestedFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\RecursiveFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Fixtures\Form\SimpleFormType;
use ChamberOrchestra\OpenApiDocBundle\Tests\Integrational\AbstractIntegrationTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

class FormParserTest extends AbstractIntegrationTestCase
{
    private FormParser $formParser;

    protected function setUp(): void
    {
        parent::setUp();

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        // Build handlers with an ArrayObject so Collection/Repeated can reference
        // the same iterable without a circular constructor dependency.
        $handlersContainer = new ArrayObject();

        $collectionHandler = new CollectionTypeHandler($formFactory, $handlersContainer, $this->describer);
        $repeatedHandler = new RepeatedTypeHandler($formFactory, $handlersContainer, $this->describer);

        $handlersContainer->append(new TextTypeHandler());
        $handlersContainer->append(new NumberTypeHandler());
        $handlersContainer->append(new DateTypeHandler());
        $handlersContainer->append(new DateTimeTypeHandler());
        $handlersContainer->append(new CheckboxTypeHandler());
        $handlersContainer->append(new PasswordTypeHandler());
        $handlersContainer->append(new FileTypeHandler());
        $handlersContainer->append(new ChoiceTypeHandler());
        $handlersContainer->append(new EntityTypeHandler());
        $handlersContainer->append($collectionHandler);
        $handlersContainer->append($repeatedHandler);

        $this->formParser = new FormParser($formFactory, $this->describer, $handlersContainer);

        // Register FormParser so ComponentDescriber can delegate to it when it
        // encounters a FormTypeInterface class (used by nested form resolution).
        $this->parsersContainer->append($this->formParser);
    }

    // -------------------------------------------------------------------------
    // SimpleFormType
    // -------------------------------------------------------------------------

    public function testSimpleFormTypeProducesSchema(): void
    {
        $component = $this->describer->describe(SimpleFormType::class);

        self::assertSame('SimpleFormType', $component->id);

        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('name', $properties);
        self::assertSame('string', $properties['name']->type);

        self::assertArrayHasKey('age', $properties);
        self::assertSame('integer', $properties['age']->type);
    }

    public function testSimpleFormTypeRequiredFields(): void
    {
        $component = $this->describer->describe(SimpleFormType::class);

        self::assertContains('name', $component->required);
        self::assertNotContains('age', $component->required);
    }

    // -------------------------------------------------------------------------
    // ExtendedFormType
    // -------------------------------------------------------------------------

    public function testFormExtensionsAreEmittedOnSchema(): void
    {
        $this->describer->describe(ExtendedFormType::class);

        $schema = $this->serializer->serializeComponents($this->registry->getAll())['schemas']['ExtendedFormType'];

        self::assertSame('users', $schema['x-request-group']);
    }

    // -------------------------------------------------------------------------
    // ConstrainedFormType
    // -------------------------------------------------------------------------

    public function testConstrainedFormTypeEmailField(): void
    {
        $component = $this->describer->describe(ConstrainedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('email', $properties);
        self::assertSame('string', $properties['email']->type);
        self::assertSame('email', $properties['email']->format);
        self::assertSame(3, $properties['email']->attributes['minLength']);
        self::assertSame(255, $properties['email']->attributes['maxLength']);
    }

    public function testConstrainedFormTypeWebsiteField(): void
    {
        $component = $this->describer->describe(ConstrainedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('website', $properties);
        self::assertSame('uri', $properties['website']->format);
        self::assertNotContains('website', $component->required);
    }

    public function testConstrainedFormTypeUsernameField(): void
    {
        $component = $this->describer->describe(ConstrainedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('username', $properties);
        self::assertSame('/^[a-z]+$/', $properties['username']->attributes['pattern']);
    }

    public function testConstrainedFormTypeScoreField(): void
    {
        $component = $this->describer->describe(ConstrainedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('score', $properties);
        self::assertSame('number', $properties['score']->type);
        self::assertSame(0, $properties['score']->attributes['minimum']);
        self::assertSame(100, $properties['score']->attributes['maximum']);
    }

    public function testConstrainedFormTypeCountField(): void
    {
        $component = $this->describer->describe(ConstrainedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('count', $properties);
        self::assertSame('integer', $properties['count']->type);
        self::assertSame(1, $properties['count']->attributes['minimum']);
    }

    // -------------------------------------------------------------------------
    // ChoiceFormType
    // -------------------------------------------------------------------------

    public function testChoiceFormTypeSingleChoice(): void
    {
        $component = $this->describer->describe(ChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('status', $properties);
        self::assertSame('string', $properties['status']->type);
        self::assertSame(['active', 'inactive'], $properties['status']->enum);
    }

    public function testChoiceFormTypeMultipleChoice(): void
    {
        $component = $this->describer->describe(ChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('tags', $properties);
        self::assertSame('array', $properties['tags']->type);
        self::assertNotNull($properties['tags']->items);
        self::assertSame(['php', 'python'], $properties['tags']->items->enum);
    }

    // -------------------------------------------------------------------------
    // NestedFormType
    // -------------------------------------------------------------------------

    public function testNestedFormTypeHasLabelField(): void
    {
        $component = $this->describer->describe(NestedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('label', $properties);
        self::assertSame('string', $properties['label']->type);
    }

    public function testNestedFormTypeHasRefToSimpleForm(): void
    {
        $component = $this->describer->describe(NestedFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('simple', $properties);
        $simpleProp = $properties['simple'];
        self::assertNotNull($simpleProp->ref);
        self::assertSame('SimpleFormType', $simpleProp->ref->id);
    }

    public function testNestedFormTypeRegistersChildComponent(): void
    {
        $this->describer->describe(NestedFormType::class);

        $result = $this->schemas();

        self::assertArrayHasKey('NestedFormType', $result);
        self::assertArrayHasKey('SimpleFormType', $result);
    }

    public function testNestedFormTypeOutputRefSchema(): void
    {
        $this->describer->describe(NestedFormType::class);

        $result = $this->schemas();
        $schema = $result['NestedFormType'];

        self::assertSame(
            '#/components/schemas/SimpleFormType',
            $schema['properties']['simple']['$ref']
        );
    }

    // -------------------------------------------------------------------------
    // CollectionFormType
    // -------------------------------------------------------------------------

    public function testCollectionFormTypeProducesArrayProperty(): void
    {
        $component = $this->describer->describe(CollectionFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('items', $properties);
        self::assertSame('array', $properties['items']->type);
    }

    public function testCollectionFormTypeItemsRefSimpleForm(): void
    {
        $component = $this->describer->describe(CollectionFormType::class);
        $properties = $this->indexByName($component->properties);

        $itemsProp = $properties['items'];
        self::assertNotNull($itemsProp->items);
        self::assertNotNull($itemsProp->items->ref);
        self::assertSame('SimpleFormType', $itemsProp->items->ref->id);
    }

    public function testCollectionFormTypeRegistersEntryTypeComponent(): void
    {
        $this->describer->describe(CollectionFormType::class);

        $result = $this->schemas();

        self::assertArrayHasKey('SimpleFormType', $result);
    }

    // -------------------------------------------------------------------------
    // EnumChoiceFormType
    // -------------------------------------------------------------------------

    public function testEnumChoiceFormTypeTextFieldWithConstraints(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('title', $properties);
        self::assertSame('string', $properties['title']->type);
        self::assertSame(1, $properties['title']->attributes['minLength']);
        self::assertSame(255, $properties['title']->attributes['maxLength']);
    }

    public function testEnumChoiceFormTypeMultipleFieldUsesEnumValues(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('priorities', $properties);
        $prop = $properties['priorities'];
        self::assertSame('array', $prop->type);
        self::assertNotNull($prop->items);
        self::assertSame('string', $prop->items->type);
        self::assertSame(['low', 'medium', 'high'], $prop->items->enum);
    }

    public function testEnumChoiceFormTypeMultipleFieldHasCountConstraints(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        $prop = $properties['priorities'];
        self::assertSame(1, $prop->attributes['minItems']);
        self::assertSame(3, $prop->attributes['maxItems']);
    }

    public function testEnumChoiceFormTypeSingleFieldUsesEnumValues(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayHasKey('primaryPriority', $properties);
        $prop = $properties['primaryPriority'];
        self::assertSame('string', $prop->type);
        self::assertSame(['low', 'medium', 'high'], $prop->enum);
    }

    public function testEnumChoiceFormTypeSingleFieldHasNoMinItems(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);
        $properties = $this->indexByName($component->properties);

        self::assertArrayNotHasKey('minItems', $properties['primaryPriority']->attributes);
    }

    public function testEnumChoiceFormTypeRequiredFields(): void
    {
        $component = $this->describer->describe(EnumChoiceFormType::class);

        self::assertContains('priorities', $component->required);
        self::assertContains('title', $component->required);
    }

    // -------------------------------------------------------------------------
    // RecursiveFormType — self-referential form (cycle detection)
    // -------------------------------------------------------------------------

    public function testRecursiveFormTypeDoesNotCauseInfiniteLoop(): void
    {
        $component = $this->describer->describe(RecursiveFormType::class);

        self::assertSame('RecursiveFormType', $component->id);
    }

    public function testRecursiveFormTypeChildrenRefPointsToItself(): void
    {
        $this->describer->describe(RecursiveFormType::class);

        $result = $this->schemas();
        $schema = $result['RecursiveFormType'];

        self::assertSame(
            '#/components/schemas/RecursiveFormType',
            $schema['properties']['children']['items']['$ref']
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function indexByName(array $properties): array
    {
        $indexed = [];
        foreach ($properties as $property) {
            $indexed[$property->name] = $property;
        }

        return $indexed;
    }
}
