# API Stability

This document defines what is public and supported in `middag-io/framework`,
so downstream packages — the OSS adapters (`middag-io/moodle`,
`middag-io/wordpress`), `middag-io/ui`, and proprietary consumers built on
top — can depend on the framework without guessing.

The support rules of the whole library family (lifecycle, what each release
may carry, the previous-MAJOR line and the compatibility matrix of each MAJOR)
live on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
This file lists the framework's public surface and points there for the rest.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** You may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning rules below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the framework. |

If a type has neither annotation, treat it as `@internal`.

## What the support covers

- **Covered:** the classes, interfaces, enums, attributes and functions tagged
  `@api` — the interfaces under each concern's `Contract/` sub-namespace, plus
  the exported `@api` classes (value objects, OSS default implementations,
  base classes).
- **Not covered:** anything tagged `@internal` or untagged, private and
  protected members of `@internal` types, test helpers, and the layout of
  `src/` beyond the `@api` types. These may change in any release.
- **Not shipped:** MIDDAG domain base classes and utility bags.
  `Persistence\Contract\EntityInterface` is the generic contract here;
  `AbstractEntity`, `AbstractValueObject`, MIDDAG formatting helpers and
  validators belong to `middag-io/core`. Legacy/speculative
  `abstract_service`, faker helpers and generic validator packages are not
  supported framework API.

If you need behaviour exposed only by an `@internal` symbol, open an issue to
have it promoted rather than reaching in. Promoting a symbol to `@api` is an
additive change that ships in a MINOR.

## Lifecycle

- **`2.0.0` is the first supported release.** Support starts there.
- **The `1.x` line is pre-stable and unsupported.** It carries no
  compatibility promise, no deprecation cycle, no backports and no security
  fixes. A breaking change may ship in a `1.x` minor.
- **The cut to `2.0.0` is clean.** There is no upgrade path from `1.x`. The
  `CHANGELOG` continues across the cut.

## Versioning from `2.0.0`

From `2.0.0`, the open-source `middag-php-*` libraries (`ui`, `framework`, `moodle`, `wordpress` and the `demo-standalone` example) share the MAJOR version. MINOR and PATCH releases are per library. Each library requires its siblings with `^2.N`, where `N` is the lowest MINOR it needs, so Composer installs a compatible set. Require each package you import with `^2.0`, or with the MINOR whose features you use.

| Part | May carry |
|---|---|
| PATCH | Fixes only, per library. Never a change to the `@api` surface. |
| MINOR | Additive `@api` changes (new contracts, new optional parameters, promoted symbols). Never a break. |
| MAJOR | Breaking `@api` changes, following [Deprecation](#deprecation). |

A PATCH carries fixes only. A MINOR carries additive `@api` changes. A MAJOR carries breaking `@api` changes, following [Deprecation](#deprecation).

### The family rule

Only the MAJOR is shared across the libraries. A platform floor (the lowest PHP version or the lowest Symfony line) rises only in a MAJOR. Support for a new PHP version or a new Symfony LTS line is added in a MINOR.

The next MAJOR (`3.0`) raises the Symfony floor to the next Symfony LTS line once it is released, and drops the PHP versions that no longer receive security support.

### Enum evolution

Not every `@api` enum can grow in a MINOR. It depends on what code does with a value it does not know. This mirrors the policy of
[`middag-io/ui`](https://github.com/middag-io/middag-php-ui/blob/main/API-STABILITY.md).

- **Control enums** drive behavior or are matched exhaustively, and the framework rejects an unknown value instead of guessing. Adding a value to one is a **MAJOR** change. The framework's `@api` enums are all control enums: `Database\Enum\Capability`, `Logging\Enum\LogLevel` (the PSR-3 level set), `Security\Enum\SignatureEncoding`, `Shared\Enum\DebugMode` and `Shared\Enum\Operator` (compiled to SQL; an unknown operator is refused).
- **Open enums** reach a consumer that falls back to a safe default for a value it does not know. Adding a value to one is a **MINOR** change. The framework exposes no open `@api` enum today. A new one must say so in its docblock.
- **Removing or renaming a value** of any enum is always a **MAJOR** change.

## Deprecation

A removal or incompatible change of an `@api` element ships only in a MAJOR. Where possible it is announced first in a MINOR of the current MAJOR: the element carries `@deprecated` naming its replacement, the changelog lists it, and code that still runs emits an `E_USER_DEPRECATED` notice where PHP can raise one. A change that cannot be deprecated in place, such as a new abstract method on an interface or a changed signature, ships in a MAJOR with an entry in `UPGRADING.md`.

## Support

Fixes go to the latest MINOR of the current MAJOR, as PATCH releases. When the next MAJOR ships, the last MINOR of the previous MAJOR receives critical and high severity security fixes only, from an `N.x` branch opened at the cut, for 6 months or until the end of life of the PHP or host version that motivated the new MAJOR, whichever comes first. Releases before `2.0.0` are unsupported.

## Releases and contributing

Releases are cut by release-please from Conventional Commits. The contributor rules, the version check included, are in [`CONTRIBUTING.md`](CONTRIBUTING.md).

## The public surface

Every type tagged `@api` in `src/`, by concern. `tests/Tooling/ApiStabilityListTest.php` fails when this list and the tags in `src/` differ.

### Bus

- `Middag\Framework\Bus\Attribute\AsCommandHandler`
- `Middag\Framework\Bus\Attribute\Schedule`
- `Middag\Framework\Bus\Command\AbstractCommand`
- `Middag\Framework\Bus\Command\AttributeHandlersLocator`
- `Middag\Framework\Bus\Command\CommandSerializer`
- `Middag\Framework\Bus\Command\CommandWorker`
- `Middag\Framework\Bus\Command\ConventionHandlersLocator`
- `Middag\Framework\Bus\Command\WorkerHeartbeatInterface`
- `Middag\Framework\Bus\Command\WorkerLimits`
- `Middag\Framework\Bus\Context\UserContextStamp`
- `Middag\Framework\Bus\Contract\CommandInterface`
- `Middag\Framework\Bus\Contract\MessageBusInterface`
- `Middag\Framework\Bus\Contract\ScheduleReaderInterface`
- `Middag\Framework\Bus\Contract\SignalDispatcherInterface`
- `Middag\Framework\Bus\Contract\TransportInterface`
- `Middag\Framework\Bus\Contract\UserContextResolverInterface`
- `Middag\Framework\Bus\MessageBusFactory`
- `Middag\Framework\Bus\Middleware\ProfilingMiddleware`
- `Middag\Framework\Bus\NullUserContextResolver`
- `Middag\Framework\Bus\Retry\AttemptStamp`
- `Middag\Framework\Bus\Retry\AttemptStoreInterface`
- `Middag\Framework\Bus\Retry\AttemptableInterface`
- `Middag\Framework\Bus\Retry\MultiplierRetryPolicy`
- `Middag\Framework\Bus\Retry\RetryPolicyInterface`
- `Middag\Framework\Bus\Schedule\CronFieldMatcher`
- `Middag\Framework\Bus\Schedule\ScheduleReader`
- `Middag\Framework\Bus\Schedule\ScheduleRunner`
- `Middag\Framework\Bus\Transport\DoctrineTransportFactory`
- `Middag\Framework\Bus\Transport\InMemoryTransport`
- `Middag\Framework\Bus\Transport\MiddagDoctrineTransport`
- `Middag\Framework\Bus\Transport\TransportLocator`

### Database

- `Middag\Framework\Database\Attribute\Column`
- `Middag\Framework\Database\Attribute\Index`
- `Middag\Framework\Database\Attribute\Key`
- `Middag\Framework\Database\Attribute\Table`
- `Middag\Framework\Database\Contract\ConnectionAdapterInterface`
- `Middag\Framework\Database\Contract\ConnectionInterface`
- `Middag\Framework\Database\Contract\IndexIntrospectionInterface`
- `Middag\Framework\Database\Contract\RecordConnectionInterface`
- `Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface`
- `Middag\Framework\Database\Contract\SqlDialectInterface`
- `Middag\Framework\Database\Contract\TransactionManagerInterface`
- `Middag\Framework\Database\Contract\VersionTrackerInterface`
- `Middag\Framework\Database\Enum\Capability`
- `Middag\Framework\Database\MysqlSqlDialect`
- `Middag\Framework\Database\PdoConnectionAdapter`
- `Middag\Framework\Database\PrefixingSqlDialect`
- `Middag\Framework\Database\Schema\DbalSchemaBuilderAdapter`
- `Middag\Framework\Database\Schema\MigrationRunner`
- `Middag\Framework\Database\Schema\MysqlMigrationRunner`
- `Middag\Framework\Database\Schema\MysqlSchemaBuilderAdapter`
- `Middag\Framework\Database\Schema\MysqlVersionTracker`
- `Middag\Framework\Database\Schema\SchemaAttributeReader`
- `Middag\Framework\Database\Schema\SchemaBuilder`
- `Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter`
- `Middag\Framework\Database\StandardSqlDialect`

### Exception

- `Middag\Framework\Exception\MiddagAuthenticationException`
- `Middag\Framework\Exception\MiddagAuthorizationException`
- `Middag\Framework\Exception\MiddagDomainException`
- `Middag\Framework\Exception\MiddagException`
- `Middag\Framework\Exception\MiddagInfrastructureException`
- `Middag\Framework\Exception\MiddagNotFoundException`
- `Middag\Framework\Exception\MiddagPersistenceException`
- `Middag\Framework\Exception\MiddagValidationException`

### Filesystem

- `Middag\Framework\Filesystem\Contract\FilesystemInterface`
- `Middag\Framework\Filesystem\FlysystemFilesystem`
- `Middag\Framework\Filesystem\LocalFilesystem`

### Form

- `Middag\Framework\Form\AbstractForm`
- `Middag\Framework\Form\Attribute\Field`
- `Middag\Framework\Form\Contract\EntitySourceInterface`
- `Middag\Framework\Form\Contract\FormValidatorInterface`
- `Middag\Framework\Form\EntitySourceRegistry`
- `Middag\Framework\Form\FieldFactory`
- `Middag\Framework\Form\Renderer\RendererRegistry`
- `Middag\Framework\Form\Schema\FieldSchemaReader`

### Http

- `Middag\Framework\Http\Attribute\Auth`
- `Middag\Framework\Http\Attribute\Cors`
- `Middag\Framework\Http\Attribute\Middleware`
- `Middag\Framework\Http\Attribute\ValidatedDto`
- `Middag\Framework\Http\Auth\CapabilityReference`
- `Middag\Framework\Http\Auth\CapabilityRequirement`
- `Middag\Framework\Http\Auth\SessionAuthenticator`
- `Middag\Framework\Http\Auth\SessionUserContextResolver`
- `Middag\Framework\Http\Contract\AuthenticatorInterface`
- `Middag\Framework\Http\Contract\AuthorizationAwareInterface`
- `Middag\Framework\Http\Contract\CapabilityDefinitionInterface`
- `Middag\Framework\Http\Contract\CapabilityRequirementAwareInterface`
- `Middag\Framework\Http\Contract\ContainerAwareInterface`
- `Middag\Framework\Http\Contract\ControllerInterface`
- `Middag\Framework\Http\Contract\ExceptionRendererInterface`
- `Middag\Framework\Http\Contract\FormRequestInterface`
- `Middag\Framework\Http\Contract\HttpKernelInterface`
- `Middag\Framework\Http\Contract\PublicRouteAwareInterface`
- `Middag\Framework\Http\Contract\RequestHandlingInterface`
- `Middag\Framework\Http\Contract\RetryPolicyInterface`
- `Middag\Framework\Http\Contract\RouteMiddlewareInterface`
- `Middag\Framework\Http\Contract\SessionInterface`
- `Middag\Framework\Http\Controller\AbstractApiController`
- `Middag\Framework\Http\Controller\AbstractController`
- `Middag\Framework\Http\DefaultExceptionRenderer`
- `Middag\Framework\Http\FatalErrorHandler`
- `Middag\Framework\Http\HttpClientFactory`
- `Middag\Framework\Http\HttpKernelFactory`
- `Middag\Framework\Http\Inertia\InertiaAdapter`
- `Middag\Framework\Http\Inertia\InertiaFactory`
- `Middag\Framework\Http\Inertia\InertiaManager`
- `Middag\Framework\Http\Inertia\InertiaVersionManager`
- `Middag\Framework\Http\Middleware\CorsMiddleware`
- `Middag\Framework\Http\Middleware\MiddlewareDispatcher`
- `Middag\Framework\Http\Middleware\ShareFlashMiddleware`
- `Middag\Framework\Http\Middleware\StartSessionMiddleware`
- `Middag\Framework\Http\Middleware\VerifyCsrfMiddleware`
- `Middag\Framework\Http\Request\AbstractFormRequest`
- `Middag\Framework\Http\Retry\RetryPolicy`
- `Middag\Framework\Http\Routing\RouteMiddlewareStack`
- `Middag\Framework\Http\Security\CsrfTokenManager`
- `Middag\Framework\Http\Session\ArraySession`
- `Middag\Framework\Http\Session\FlashBag`
- `Middag\Framework\Http\Session\NativeSession`
- `Middag\Framework\Http\StandaloneKernel`

### Kernel

- `Middag\Framework\Kernel\Attribute\Lazy`
- `Middag\Framework\Kernel\Bootstrap\BootIsolateFailurePolicy`
- `Middag\Framework\Kernel\Bootstrap\EnvConfigResolver`
- `Middag\Framework\Kernel\Bootstrap\NullMaintenanceGate`
- `Middag\Framework\Kernel\ContainerFactory`
- `Middag\Framework\Kernel\Contract\BootFailurePolicyInterface`
- `Middag\Framework\Kernel\Contract\BootstrapInterface`
- `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface`
- `Middag\Framework\Kernel\Contract\ConfigResolverInterface`
- `Middag\Framework\Kernel\Contract\FacadeInterface`
- `Middag\Framework\Kernel\Contract\FacadeLoaderInterface`
- `Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface`
- `Middag\Framework\Kernel\Contract\HookManagerInterface`
- `Middag\Framework\Kernel\Contract\HookRegisterInterface`
- `Middag\Framework\Kernel\Contract\HookfileLoaderInterface`
- `Middag\Framework\Kernel\Contract\HostComponentContextInterface`
- `Middag\Framework\Kernel\Contract\HostEventBridgeInterface`
- `Middag\Framework\Kernel\Contract\KernelInterface`
- `Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface`
- `Middag\Framework\Kernel\Contract\LoaderInterface`
- `Middag\Framework\Kernel\Contract\MaintenanceGateInterface`
- `Middag\Framework\Kernel\Contract\ModuleInterface`
- `Middag\Framework\Kernel\Contract\ModuleLoaderInterface`
- `Middag\Framework\Kernel\Contract\ServiceLoaderInterface`
- `Middag\Framework\Kernel\Facade\AbstractFacade`
- `Middag\Framework\Kernel\Facade\HookFacade`
- `Middag\Framework\Kernel\HostContext`
- `Middag\Framework\Kernel\Manager\HookManager`
- `Middag\Framework\Kernel\Module\AbstractModule`
- `Middag\Framework\Kernel\ServiceProvider`
- `Middag\Framework\Kernel\ShutdownCleanup`

### Logging

- `Middag\Framework\Logging\Attribute\LogChannel`
- `Middag\Framework\Logging\CleanLogsCommand`
- `Middag\Framework\Logging\Contract\ActorResolverInterface`
- `Middag\Framework\Logging\Contract\OriginResolverInterface`
- `Middag\Framework\Logging\Contract\SecretRedactorInterface`
- `Middag\Framework\Logging\Enum\LogLevel`
- `Middag\Framework\Logging\ErrorLogFallbackLogger`
- `Middag\Framework\Logging\Formatter\MiddagLineFormatter`
- `Middag\Framework\Logging\Handler\RotatingStreamHandler`
- `Middag\Framework\Logging\LoggerFactory`
- `Middag\Framework\Logging\NullActorResolver`
- `Middag\Framework\Logging\NullOriginResolver`
- `Middag\Framework\Logging\Processor\ActorOriginProcessor`
- `Middag\Framework\Logging\Processor\RedactionProcessor`
- `Middag\Framework\Logging\Redactor\DefaultSecretRedactor`

### Mail

- `Middag\Framework\Mail\Address`
- `Middag\Framework\Mail\Attachment`
- `Middag\Framework\Mail\Contract\MailerInterface`
- `Middag\Framework\Mail\Mail`
- `Middag\Framework\Mail\NullMailer`

### Observability

- `Middag\Framework\Observability\Contract\ErrorReporterInterface`
- `Middag\Framework\Observability\Contract\ProfileCollectorInterface`
- `Middag\Framework\Observability\NullErrorReporter`
- `Middag\Framework\Observability\ProfileCollector`
- `Middag\Framework\Observability\SentryErrorReporter`

### Persistence

- `Middag\Framework\Persistence\Attribute\EntityType`
- `Middag\Framework\Persistence\Contract\CastInterface`
- `Middag\Framework\Persistence\Contract\ConditionCompilerInterface`
- `Middag\Framework\Persistence\Contract\ConnectionResolverInterface`
- `Middag\Framework\Persistence\Contract\EntityInterface`
- `Middag\Framework\Persistence\Contract\EntityTypeInterface`
- `Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface`
- `Middag\Framework\Persistence\Contract\MapperInterface`
- `Middag\Framework\Persistence\Contract\QueryBuilderInterface`
- `Middag\Framework\Persistence\Contract\RepositoryInterface`
- `Middag\Framework\Persistence\Contract\ResultInterface`
- `Middag\Framework\Persistence\Entity\DefaultEntityType`
- `Middag\Framework\Persistence\Entity\EntityTypeRegistry`
- `Middag\Framework\Persistence\Loader\EntityTypeRegistrar`
- `Middag\Framework\Persistence\Mapper\AbstractMapper`
- `Middag\Framework\Persistence\Model`
- `Middag\Framework\Persistence\ModelQuery`
- `Middag\Framework\Persistence\Query\Page`
- `Middag\Framework\Persistence\Query\QueryBuilder`
- `Middag\Framework\Persistence\Query\RelationRef`
- `Middag\Framework\Persistence\Relation\BelongsTo`
- `Middag\Framework\Persistence\Relation\BelongsToMany`
- `Middag\Framework\Persistence\Relation\HasMany`
- `Middag\Framework\Persistence\Relation\HasOne`
- `Middag\Framework\Persistence\Relation\Relation`
- `Middag\Framework\Persistence\Repository\AbstractRepository`
- `Middag\Framework\Persistence\SingleConnectionResolver`

### Runtime

- `Middag\Framework\Runtime\ServiceMap`
- `Middag\Framework\Runtime\StandaloneKernel`

### Security

- `Middag\Framework\Security\Contract\PasswordHasherInterface`
- `Middag\Framework\Security\Contract\SignatureVerifierInterface`
- `Middag\Framework\Security\Enum\SignatureEncoding`
- `Middag\Framework\Security\HmacSignatureVerifier`
- `Middag\Framework\Security\NativePasswordHasher`

### Shared

- `Middag\Framework\Shared\Attribute\TrustedOutput`
- `Middag\Framework\Shared\Contract\DtoInterface`
- `Middag\Framework\Shared\Dto\AbstractDto`
- `Middag\Framework\Shared\Dto\SyncResult`
- `Middag\Framework\Shared\Enum\DebugMode`
- `Middag\Framework\Shared\Enum\Operator`
- `Middag\Framework\Shared\Util\Arr`
- `Middag\Framework\Shared\Util\Debug`
- `Middag\Framework\Shared\Util\Environment`
- `Middag\Framework\Shared\Util\Inflector`
- `Middag\Framework\Shared\Util\Typing`
- `Middag\Framework\Shared\ValueObject\Uuid`

### Translation

- `Middag\Framework\Translation\Contract\TranslatorInterface`
- `Middag\Framework\Translation\FallbackTranslator`
- `Middag\Framework\Translation\Resolver\ViolationKeyMap`
- `Middag\Framework\Translation\TranslatableMessage`

## Host-neutral bridge contracts

These `@api` contracts are the host-neutral seams that downstream code binds
to. They follow the same versioning rules as the rest of the `@api` surface.

| Contract | Role |
|---|---|
| `Middag\Framework\Kernel\Contract\HostComponentContextInterface` | Neutral runtime context a host exposes to the adapters (component name, asset version, base path). Registered once at boot via `HostContext`. |
| `Middag\Framework\Kernel\Contract\HostEventBridgeInterface` | Public OSS seam for synchronous, in-process broadcast of named host events. Adapters implement it over the host's native eventing. |
| `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface` | Resolves the native component identifier (Moodle frankenstyle / WordPress plugin slug) that owns the running boot cycle. |
| `Middag\Framework\Bus\Contract\SignalDispatcherInterface` | Canonical publish-side entry point for dispatching signals. No OSS implementation ships here; it is fulfilled by `middag-io/core` or a host adapter. |

## Depending on the framework safely

- Depend only on `@api` types.
- From `2.0.0`, require the framework with `^2.0`, or with the MINOR whose
  features you use. Only the MAJOR is shared across the libraries.
- The dependency direction only ever points downward: the framework never
  imports the proprietary layer, and OSS adapters never import the
  proprietary core namespace.
