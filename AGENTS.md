# Repository Guidelines

## Project Overview

`chamber-orchestra/openapi-doc-bundle` is a Symfony bundle that auto-generates OpenAPI 3.0.1 documentation by scanning PHP source files for action classes annotated with `#[Operation]` and `#[Route]` attributes. It targets the **Action-Domain-Responder (ADR)** pattern where each API endpoint is an invokable class.

- Namespace: `ChamberOrchestra\OpenApiDocBundle\`
- Autoloading: PSR-4 from `src/`
- Requirements: PHP 8.5+, Symfony 8.x components

## Project Structure

```
src/
  ApiDocBundle.php              — bundle entry point
  Attribute/                    — #[Operation], #[Property], #[Model]
  Command/                      — ApiDocGenerator console command
  config/services.yaml          — service definitions and parser tags
  DependencyInjection/          — bundle extension (config loading)
  Describer/                    — OperationDescriber, ComponentDescriber
  Exception/                    — DescriberException, Exception
  Locator/                      — Locator (scans src for action classes)
  Model/                        — Operation, Component, Property, Constraint, ModelFactory
  Parser/                       — operation and component parsers
  Registry/                     — OperationRegistry, ComponentRegistry
  Utils/                        — ClassParser helper
```

## Build & Development Commands

```bash
# Install dependencies
composer install

# Generate OpenAPI documentation
php bin/console openapi-doc:generate \
  [--src <path>]           # default: <project_dir>/src
  [--output <file>]        # default: <project_dir>/doc.yaml
  [--proto <file>]         # default: <project_dir>/proto.yaml
  [--title <string>]       # default: "API Documentation"
  [--doc-version <string>] # default: "1.0.0"

# Lint a file
php -l src/Path/To/File.php
```

## Generation Pipeline

1. **`Locator`** — scans `src/` recursively for classes with **both** `#[Operation]` and `#[Route]` attributes.
2. **`OperationDescriber`** — for each action class, collects PHP attributes and `__invoke` return types, runs all `OperationParserInterface` parsers, registers the result in `OperationRegistry`.
3. **`ComponentDescriber`** — called lazily when a parser encounters a class needing an OpenAPI schema component; runs `ComponentParserInterface` parsers, registers in `ComponentRegistry`.
4. **Registries** serialize to OpenAPI structure: `OperationRegistry::toArray()` → `paths`, `ComponentRegistry::toArray()` → `components.schemas`.
5. **`ApiDocGenerator` command** merges registry output with optional `proto.yaml` and writes the final YAML.

## Parser System

Each parser implements `supports(object $item): bool` and `parse(Model $model, object $item): Model`.

**Operation parsers** (tag: `openapi_doc.operation_parser`):
- `RouteParser` — extracts `path`, `method`, `operationId` from `#[Route]`
- `OperationParser` — reads `#[Operation]` for description, request body, responses, security overrides
- `ResponseParser` — triggered by `ReflectionClass` return types from `__invoke`; delegates to `ComponentDescriber`
- `SecurityParser` — sets default security when `#[Security]` or `#[IsGranted]` is present
- `ModelParser` — stub for `#[Model]` (currently a no-op)

**Component parsers** (tag: `openapi_doc.component_parser`):
- `FormParser` — handles Symfony `FormTypeInterface` classes; maps field types and validator constraints to OpenAPI properties
- `ViewParser` — handles classes implementing `Dev\ViewBundle\View\ViewInterface`; recursively describes nested objects
- `ObjectParser` — fallback for plain DTOs; reads public properties and PHP types

## Attributes (used in consuming application)

- `#[Operation(description, request, responses, security)]` — class-level; marks an action as a documented endpoint
- `#[Property(required, attr)]` — property-level on DTOs; sets required flag and extra OpenAPI attributes
- `#[Model(model, type)]` — class-level; currently unused in parsers

## Coding Style

- Follow PSR-12: 4-space indent, one class per file, `declare(strict_types=1)`.
- Use typed properties and return types; favor `readonly` where appropriate.
- Keep constructors light; prefer small, composable services injected via Symfony DI.
- `php-cs-fixer.dist.php` is present — run PHP CS Fixer before committing if available.

## proto.yaml

A hand-authored YAML merged at the end of generation:
- `components.securitySchemes` — first scheme becomes default when `#[Security]` is detected
- `components.schemas` — merged with generated schemas
- `components.responses` — shared response refs used in `#[Operation](responses: [...])`

## Commit Guidelines

- Commit messages: short, action-oriented, optionally bracketed scope (e.g., `[fix] handle missing route`, `[feat] add form parser`).
- Keep commits focused; avoid unrelated formatting churn.
