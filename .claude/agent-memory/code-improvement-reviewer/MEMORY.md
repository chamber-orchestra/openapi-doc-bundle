# Code Improvement Reviewer Memory

## Project: dev/openapi-adr-bundle

### Architecture (post-refactor, confirmed 2026-02-20)
- Symfony bundle that auto-generates OpenAPI 3.0.1 docs from PHP attributes on ADR action classes
- Generation pipeline: Locator → OperationDescriber → ComponentDescriber → Registries → DocumentBuilder → YAML
- Parser system uses tagged Symfony services (OperationParserInterface / ComponentParserInterface)
- Key entry point: `src/Command/ApiDocGenerator.php` (console command `api-doc:generate`)
- `DocumentBuilder` (`src/Builder/DocumentBuilder.php`) owns proto-merge and security-scheme alias resolution
- FormTypeHandler strategy pattern in `src/Parser/FormTypeHandler/` (13 files: interface + abstract + 11 handlers)

### Issues Fixed Since First Review (2026-02-19 → 2026-02-20)
All issues from the first review have been resolved in the refactor:
- ComponentDescriber now uses injected registry correctly
- Locator is injectable via DI
- OperationDescriber getItemsToParse now passes string to class_exists correctly
- ViewParser convertType float bug fixed
- RouteParser now throws InvalidArgumentException on empty methods array
- Property::$required is now typed bool|array|null
- declare(strict_types=1) added to all files
- Russian comments removed
- Constraint.php and ModelParser.php deleted

### Remaining Issues Found (second review, 2026-02-20)

**HIGH — correctness bugs:**
- `ComponentRegistry::propertyToArray()` emits `required` as a property-level field (invalid OAS 3.0).
  required is a schema-level array in OpenAPI 3.0; writing it per-property produces invalid YAML.
- `dispatch()` duplicated verbatim in CollectionTypeHandler (line 50) and RepeatedTypeHandler (line 49);
  `getBuiltinFormType()` duplicated as third copy in FormParser (line 83) — three-way DRY violation.

**MEDIUM — design issues:**
- `Component::$excluded` flag is set as a side-effect in OperationRegistry::requestToQueryParams() (line 73),
  creating hidden temporal coupling: OperationRegistry::getAll() MUST run before ComponentRegistry::getAll().
  Fix: track excluded IDs inside OperationRegistry, pass to ComponentRegistry via DocumentBuilder.
- `Property::$required` carries bool|array|null where bool="required in parent" and array="sub-property required list" —
  two unrelated concepts in one field; caused the invalid-OAS bug above.
  Fix: split into $required ?bool and $requiredProperties array.
- ViewParser injects concrete ComponentDescriber instead of DescriberInterface (line 27).
  ResponseParser has same issue (line 14). All other parsers correctly use the interface.
- AbstractDescriber::describe() runs ALL matching parsers per item; if two parsers support the same item,
  second silently overwrites first. Convention currently makes them mutually exclusive but not enforced.
  Fix: add `break` after first matching parser (option A) or document additive contract (option B).

**LOW — minor issues:**
- Operation::$path and $method declared ?string but default '' — should be plain string.
- OperationRegistry::getAll() has no guard for empty path/method on registered operations.
- NumberTypeHandler: Range constraint check runs after GreaterThan chain and silently overrides it.
- ChoiceTypeHandler reads getOption('multiple') twice (lines 20 and 26) — extract to local variable.
- PropertyParser is injected as concrete class in ViewParser and ObjectParser — no interface.

### Code Patterns (current state)
- Models are plain mutable public-property objects (no encapsulation) — accepted pattern for this bundle
- `/* @var */` docblock casts still used in parsers for $model and $item type narrowing
- No tests present in the repository
- TypeConverter is a clean pure-function utility with no side effects
- Property::factory() named constructor used consistently across all parsers
- FormTypeHandler pattern: one class per Symfony form block prefix, all extend AbstractFormTypeHandler
- All services tagged correctly: dev_api_doc.component_parser, dev_api_doc.operation_parser, dev_api_doc.form_type_handler
