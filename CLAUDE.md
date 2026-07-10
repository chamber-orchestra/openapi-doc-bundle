# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`chamber-orchestra/openapi-doc-bundle` is a Symfony bundle that auto-generates OpenAPI 3.0.1 documentation by scanning PHP source files for action classes annotated with `#[Operation]` and `#[Route]` attributes. It is designed for the **Action-Domain-Responder (ADR)** pattern where each API endpoint is an invokable class.

## Commands

### Generate documentation
```bash
php bin/console openapi-doc:generate \
  [--src <path>]          # default: <project_dir>/src \
  [--output <file>]       # default: <project_dir>/doc.yaml \
  [--proto <file>]        # default: <project_dir>/proto.yaml \
  [--title <string>]      # default: "API Documentation" \
  [--doc-version <string>]# default: "1.0.0"
```

### Install dependencies
```bash
composer install
```

## Architecture

### Generation pipeline

1. **`Locator`** (`src/Locator/Locator.php`) — scans the `src` directory recursively for classes that have **both** `#[Operation]` and `#[Route]` attributes.

2. **`OperationDescriber`** (`src/Describer/OperationDescriber.php`) — for each located action class:
   - Collects its PHP attributes and the non-interface/non-abstract return types of its `__invoke` method.
   - Runs all registered `OperationParserInterface` parsers against those items (first matching parser wins per item).
   - Registers the resulting `Operation` model in `OperationRegistry`.

3. **`ComponentDescriber`** (`src/Describer/ComponentDescriber.php`) — called lazily by operation/response parsers when they encounter a class that should become an OpenAPI schema component. Runs `ComponentParserInterface` parsers and registers results in `ComponentRegistry`. Contains a cycle guard (`$inProgress`) to handle recursive schemas.

4. **Registries** serialize their models to OpenAPI structure:
   - `OperationRegistry::getAll()` → OpenAPI `paths`
   - `ComponentRegistry::getAll(array $excludedIds = [])` → OpenAPI `components.schemas`

5. **`DocumentBuilder`** (`src/Builder/DocumentBuilder.php`) — merges registry output with an optional `proto.yaml`, resolves the `'default'` security placeholder to the first defined scheme, and returns the final OpenAPI 3.0.1 array.

6. **`ApiDocGenerator` command** serializes the DocumentBuilder output to YAML and writes it to disk.

### Parser system

Parsers are tagged Symfony services. Each implements `supports(object $item): bool` and `parse(Model $model, object $item): Model`. The first matching parser per item wins (no fallthrough).

**Operation parsers** (`OperationParserInterface`, tag: `openapi_doc.operation_parser`):
- `SecurityParser` (priority: 10) — sets `security['default'] = []` when `#[IsGranted]` is present. The `'default'` key is later replaced by `DocumentBuilder` with the first scheme from `proto.yaml`. **Note: only `#[IsGranted]` is supported; `#[Security]` is not.**
- `RouteParser` — extracts `path`, `method`, `operationId` from `#[Route]`
- `OperationParser` — reads `#[Operation]` for description, request body class, responses, and optional security override
- `ResponseParser` — triggered by `ReflectionClass` items (return types from `__invoke`); delegates to `ComponentDescriber`

**Component parsers** (`ComponentParserInterface`, tag: `openapi_doc.component_parser`):
- `FormParser` — handles Symfony `FormTypeInterface` classes; maps form field types and validator constraints to OpenAPI property definitions via `FormTypeHandler` strategy
- `ViewParser` — handles classes implementing `ChamberOrchestra\ViewBundle\View\ViewInterface`; recursively describes nested object properties
- `ObjectParser` — fallback for plain DTO classes; reads public properties and PHP types

### FormTypeHandler strategy

`AbstractFormTypeHandler` (`src/Parser/FormTypeHandler/`) provides shared helpers:
- `static getBuiltinFormType(ResolvedFormTypeInterface): ?ResolvedFormTypeInterface` — walks the resolved type chain to find the first Symfony built-in type
- `dispatchSubProperty(Property, FormConfigInterface, iterable, DescriberInterface)` — shared dispatch used by `CollectionTypeHandler` and `RepeatedTypeHandler`

### Attributes (apply in consuming application)

- `#[Operation(description, request, responses, security)]` — class-level; marks an action as a documented endpoint
- `#[Property(required, attr)]` — property-level on DTOs/views; sets required flag and arbitrary extra OpenAPI attributes
- `#[Extension(name, value)]` — class-level, repeatable; adds an OpenAPI specification extension (`x-*` key, prefix validated in the constructor) to the schema generated for a request/response class. Collected centrally in `ComponentDescriber` (works for forms, views, and DTOs alike), emitted by `OpenApiSerializer::serializeComponents`. Dropped for GET/DELETE/HEAD request forms (their schema is excluded in favor of query params).

### Data flow for a typical action class

```
Action class (#[Route] + #[IsGranted] + #[Operation])
  └─ SecurityParser     → Operation.security = ['default' => []]
  └─ RouteParser        → Operation.path / method / id
  └─ OperationParser    → Operation.description / request / responses / security override
       └─ ComponentDescriber (for request class)
            └─ FormParser or ObjectParser → Component schema
  └─ ResponseParser (from __invoke return type)
       └─ ComponentDescriber (for return type class)
            └─ ViewParser or ObjectParser → Component schema

DocumentBuilder
  └─ OperationRegistry.getAll()  — GET/DELETE/HEAD: request form → query params (schema excluded)
  └─ ComponentRegistry.getAll(excludedIds)
  └─ proto.yaml merge
  └─ 'default' security → first scheme from proto.yaml securitySchemes
```

### proto.yaml

A hand-authored YAML file merged at the end of generation. **Required for security to appear in the output** — without `securitySchemes` defined here, the `'default'` security placeholder is stripped and operations appear as public.

Used to supply:
- `components.securitySchemes` — first scheme becomes the replacement for the `'default'` placeholder when `#[IsGranted]` is detected
- `components.schemas` — merged with generated schemas
- `components.responses` — shared response references used in `#[Operation](responses: [...])` as string refs

### Key model conventions

- `Property::$required` (`?bool`) — whether this property belongs in its parent component's `required[]` list
- `Property::$requiredProperties` (`array`) — required sub-property names for inline object schemas (type: object); set by `RepeatedTypeHandler`
- `Component::$required` (`array`) — list of required property names, emitted at schema level (valid OAS 3.0)
- `OperationRegistry` tracks `$excludedComponentIds` (GET/DELETE request form schemas expanded as query params); passed to `ComponentRegistry::getAll()`
