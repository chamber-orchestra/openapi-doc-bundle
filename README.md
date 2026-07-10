# OpenAPI ADR Bundle

Symfony bundle that **auto-generates OpenAPI 3.0.1 documentation** for applications built on the [Action-Domain-Responder](https://en.wikipedia.org/wiki/Action%E2%80%93domain%E2%80%93responder) (ADR) pattern. Works by scanning PHP source files for action classes annotated with `#[Operation]` and `#[Route]` attributes — no YAML configuration required.

## Features

- Zero-config documentation generation from PHP attributes
- Automatic schema inference from Symfony Form types (including validation constraints → OpenAPI constraints)
- Automatic schema inference from View classes (`ViewInterface`, `IterableView`)
- Automatic schema inference from plain DTO classes
- BackedEnum properties → `enum` values in schemas
- `Uuid`/`Ulid` → `{ type: string, format: uuid }`
- `DateTime`/`DateTimeImmutable` → `{ type: string, format: date-time }`
- GET/DELETE/HEAD requests: form fields automatically expanded as query parameters
- Recursive schema detection (cycle guard)
- Security via `#[IsGranted]` — no extra annotation needed
- OpenAPI specification extensions (`x-*` keys) on request/response schemas via `#[Extension]`

## Requirements

- PHP 8.5+
- Symfony 8.x
- `chamber-orchestra/view-bundle`

## Installation

```bash
composer require chamber-orchestra/openapi-doc-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    ChamberOrchestra\OpenApiDocBundle\OpenApiDocBundle::class => ['all' => true],
];
```

## Configuration

### proto.yaml

Create a `proto.yaml` file in your project root. This file is merged into the final output and **must contain `securitySchemes`** for security annotations to appear in the generated documentation:

```yaml
# proto.yaml
components:
    securitySchemes:
        BearerAuth:
            type: http
            scheme: bearer
            bearerFormat: JWT

    # Shared response references (used as string refs in #[Operation])
    responses:
        Unauthorized:
            description: Unauthorized
        NotFound:
            description: Not found
        Forbidden:
            description: Forbidden

    # Additional schemas not generated from code
    schemas:
        Error:
            type: object
            properties:
                message:
                    type: string
```

> **Note:** Without `securitySchemes` in proto.yaml, `#[IsGranted]` annotations are silently ignored and operations appear as public in the generated documentation.

## Usage

### 1. Annotate action classes

Each invokable action class needs `#[Route]` and `#[Operation]`:

```php
use ChamberOrchestra\OpenApiDocBundle\Attribute\Operation;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users/{id}', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
#[Operation(
    description: 'Get a user by ID',
    responses: [UserView::class],
)]
class GetUserAction
{
    public function __invoke(): UserView
    {
        // ...
    }
}
```

### 2. POST/PUT/PATCH — request body from a Form

```php
#[Route('/users', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
#[Operation(
    description: 'Create a new user',
    request: CreateUserForm::class,
    responses: [UserView::class],
)]
class CreateUserAction
{
    public function __invoke(): UserView { /* ... */ }
}
```

The form fields are read at generation time and become the `requestBody` schema. Symfony validation constraints are mapped to OpenAPI constraints:

| Constraint | OpenAPI |
|---|---|
| `NotBlank` | `required: [field]` at schema level |
| `Length(min, max)` | `minLength`, `maxLength` |
| `Range(min, max)` | `minimum`, `maximum` |
| `Positive` | `minimum: 1` |
| `GreaterThanOrEqual(n)` | `minimum: n` |
| `Count(min, max)` | `minItems`, `maxItems` (array fields) |
| `Email` | `format: email` |
| `Url` | `format: uri` |
| `ChoiceType(choices)` | `enum` values |

#### Form example

```php
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 2, max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('age', IntegerType::class, [
                'required' => false,
                'constraints' => [new Assert\Range(min: 18, max: 120)],
            ])
            ->add('role', ChoiceType::class, [
                'choices' => ['User' => 'user', 'Admin' => 'admin', 'Moderator' => 'moderator'],
                'constraints' => [new Assert\NotBlank()],
            ]);
    }
}
```

This generates the following OpenAPI schema:

```yaml
CreateUserForm:
    type: object
    required: [name, email, role]
    properties:
        name:
            type: string
            minLength: 2
            maxLength: 100
        email:
            type: string
            format: email
        age:
            type: integer
            minimum: 18
            maximum: 120
        role:
            type: string
            enum: [user, admin, moderator]
```

#### Nested form (sub-form as object)

```php
class AddressForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('city', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('zip', TextType::class);
    }
}

class CreateOrderForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('address', AddressForm::class);  // nested form → $ref
    }
}
```

Generated schema:

```yaml
CreateOrderForm:
    type: object
    required: [title]
    properties:
        title:
            type: string
        address:
            $ref: '#/components/schemas/AddressForm'

AddressForm:
    type: object
    required: [street, city]
    properties:
        street:
            type: string
        city:
            type: string
        zip:
            type: string
```

#### Collection of sub-forms

```php
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class CreateInvoiceForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('lines', CollectionType::class, [
                'entry_type' => InvoiceLineForm::class,
                'constraints' => [new Assert\Count(min: 1)],
            ]);
    }
}
```

Generated schema:

```yaml
CreateInvoiceForm:
    type: object
    required: [number]
    properties:
        number:
            type: string
        lines:
            type: array
            minItems: 1
            items:
                $ref: '#/components/schemas/InvoiceLineForm'
```

### 3. GET — form fields become query parameters

GET/DELETE/HEAD actions automatically expand form fields into query parameters instead of a request body:

```php
#[Route('/users', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
#[Operation(
    description: 'Search users',
    request: SearchUsersForm::class,
    responses: [UserListView::class],
)]
class SearchUsersAction
{
    public function __invoke(): UserListView { /* ... */ }
}
```

```php
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SearchUsersForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', TextType::class, ['required' => false])
            ->add('role', ChoiceType::class, [
                'required' => false,
                'choices'  => ['User' => 'user', 'Admin' => 'admin'],
            ])
            ->add('page', IntegerType::class, ['required' => false]);
    }
}
```

Each field becomes an individual query parameter:

```yaml
parameters:
    - name: query
      in: query
      schema:
          type: string
    - name: role
      in: query
      schema:
          type: string
          enum: [user, admin]
    - name: page
      in: query
      schema:
          type: integer
```

### 4. Multiple responses (including shared references from proto.yaml)

```php
#[Operation(
    description: 'Update user',
    request: UpdateUserForm::class,
    responses: [
        UserView::class,           // Described as a component schema
        '404' => 'NotFound',       // String ref → #/components/responses/NotFound
        '403' => 'Forbidden',      // String ref → #/components/responses/Forbidden
    ],
)]
```

### 5. Custom security

Override security for a specific operation (e.g., API key instead of the default Bearer):

```php
#[Operation(
    description: 'Webhook endpoint',
    security: ['ApiKeyAuth' => []],
)]
```

Disable security for a public endpoint:

```php
#[Operation(
    description: 'Public endpoint',
    security: [],
)]
```

### 6. Annotating DTO properties

Use `#[Property]` to mark a property as required or add arbitrary OpenAPI attributes:

```php
use ChamberOrchestra\OpenApiDocBundle\Attribute\Property;

class UserView
{
    public Uuid $id;
    public string $name;

    #[Property(required: true, attr: ['example' => 'user@example.com'])]
    public ?string $email = null;  // nullable but explicitly required

    #[Property(attr: ['minLength' => 3, 'maxLength' => 50])]
    public string $username;
}
```

### 7. Iterable views (lists)

Use `#[Type]` from `chamber-orchestra/view-bundle` to specify the item type:

```php
use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\IterableView;

class UserListView extends IterableView
{
    #[Type(UserView::class)]
    protected array $entries = [];
}
```

Generates:

```yaml
UserListView:
    type: array
    items:
        $ref: '#/components/schemas/UserView'
```

### 8. Vendor extensions (`x-*` keys)

Use `#[Extension]` (repeatable) on a request class (form type, DTO) or response class
(view, DTO) to add OpenAPI specification extensions to its generated schema:

```php
use ChamberOrchestra\OpenApiDocBundle\Attribute\Extension;

#[Extension('x-entity', User::class)]
#[Extension('x-doc-group', 'users')]
class UserView implements ViewInterface
{
    public string $name;
}
```

Generates:

```yaml
UserView:
    properties:
        name: { type: string }
    required: [name]
    x-entity: App\Entity\User
    x-doc-group: users
```

The extension name must start with `x-`; values may be any scalar, array, or null.

Notes:

- For **property-level** extensions, use the existing `#[Property(attr: [...])]`:
  `#[Property(attr: ['x-faker' => 'email'])]`.
- GET/DELETE/HEAD request forms are expanded into query parameters and their schema is
  excluded from the output — extensions declared on such forms are not emitted.
- Extensions declared in `proto.yaml` remain untouched and merge as usual.

## Generating documentation

```bash
php bin/console openapi-doc:generate
```

Options:

```
--src         Source directory to scan (default: <project_dir>/src)
--output      Output file path      (default: <project_dir>/doc.yaml)
--proto       Proto YAML file path  (default: <project_dir>/proto.yaml)
--title       API title             (default: "API Documentation")
--doc-version API version string    (default: "1.0.0")
```

Example:

```bash
php bin/console openapi-doc:generate \
  --src src/Api \
  --output public/openapi.yaml \
  --proto proto.yaml \
  --title "My API" \
  --doc-version "2.1.0"
```

## Type mapping reference

### PHP types → OpenAPI

| PHP type | OpenAPI |
|---|---|
| `string` | `type: string` |
| `int` | `type: integer` |
| `float` | `type: number` |
| `bool` | `type: boolean` |
| `array` / `iterable` | `type: array` |
| `BackedEnum` | `type: string\|integer` + `enum: [...]` |
| `Uuid` / `Ulid` | `type: string, format: uuid` |
| `DateTime` / `DateTimeImmutable` | `type: string, format: date-time` |
| Custom class | `$ref: '#/components/schemas/ClassName'` |

### Form field types → OpenAPI

| Symfony type | OpenAPI |
|---|---|
| `TextType` | `type: string` |
| `IntegerType` | `type: integer` |
| `NumberType` | `type: number` |
| `CheckboxType` | `type: boolean` |
| `EmailType` | `type: string, format: email` |
| `UrlType` | `type: string, format: uri` |
| `DateType` | `type: string, format: date` |
| `DateTimeType` | `type: string, format: date-time` |
| `ChoiceType` | `type: string` + `enum: [...]` |
| `ChoiceType(multiple: true)` | `type: array, items: { enum: [...] }` |
| `CollectionType` | `type: array, items: { ... }` |
| `RepeatedType` | `type: object` with sub-properties |
| Custom `FormTypeInterface` | `$ref: '#/components/schemas/FormName'` |

## Architecture

```
Action class
  └─ Locator           scans src/ for #[Operation] + #[Route]
  └─ OperationDescriber
       └─ SecurityParser    #[IsGranted] → security placeholder
       └─ RouteParser       #[Route]     → path / method / operationId
       └─ OperationParser   #[Operation] → description / request / responses
       └─ ResponseParser    __invoke return type → ComponentDescriber
  └─ ComponentDescriber (lazy, per class)
       └─ FormParser    FormTypeInterface → schema from form fields + constraints
       └─ ViewParser    ViewInterface     → schema from public properties
       └─ ObjectParser  plain DTO         → schema from public properties
  └─ DocumentBuilder
       └─ merge proto.yaml
       └─ resolve 'default' security → first securityScheme
       └─ emit OpenAPI 3.0.1 YAML
```

## Running tests

```bash
composer test           # all tests
composer test:unit      # unit tests only
composer test:integration  # integration tests only
```

## License

MIT
