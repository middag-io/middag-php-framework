<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\ScheduleReaderInterface;
use Middag\Framework\Bus\MessageBus;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\Schedule\CronFieldMatcher;
use Middag\Framework\Bus\Schedule\ScheduleReader;
use Middag\Framework\Bus\Schedule\ScheduleRunner;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\FormValidator;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Form\Schema\FieldSchemaReader;
use Middag\Framework\Kernel\Attribute\Lazy;
use Middag\Framework\Kernel\Module\AbstractHookRegister;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Logging\Resolver\LogChannelBinder;
use Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface;
use Middag\Framework\Persistence\Entity\EntityTypeRegistry;
use Middag\Framework\Persistence\Loader\EntityTypeRegistrar;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * Convention-based service auto-discovery and registration.
 *
 * Scans configured directories for PHP classes matching suffix patterns,
 * registers them in the Symfony DI container with autowiring enabled,
 * and auto-creates interface aliases for single-interface implementations.
 *
 * Platform-agnostic: adapters and plugins extend this to configure
 * their own scan directories and suffix rules.
 *
 * @api
 */
class ServiceProvider
{
    /** Directories to scan (relative to project root). Override in subclass. */
    protected const SCAN_DIRS = [];

    /** Class suffixes that trigger auto-registration. */
    protected const REGISTER_SUFFIXES = [
        'Service',
        'Repository',
        'Controller',
        'Adapter',
        'Handler',
        'Hooks',
        'Router',
        'Builder',
        'Factory',
        'Registrar',
        'Mapper',
        'Manager',
        'Provider',
        'Resolver',
        'Validator',
        'Dispatcher',
    ];

    /** Class suffixes that are never registered (value objects, contracts). */
    protected const IGNORE_SUFFIXES = [
        'Entity',
        'Interface',
        'Trait',
        'Enum',
        'DTO',
        'Dto',
        'Exception',
        'Signal',
        'Event',
    ];

    /** Directories to skip entirely during scanning. */
    protected const IGNORE_DIRS = [
        'Contract',
        'Entity',
        'Enum',
        'Exception',
        'Trait',
        'ValueObject',
        'DTO',
        'Dto',
    ];

    /** Root namespace for the project. Override in subclass. */
    protected const ROOT_NAMESPACE = '';

    /**
     * Scan and register all discovered services in the container.
     *
     * Load-bearing path contract: SCAN_DIRS resolve relative to {$basePath}/src,
     * and scanDirectory() derives each class name by stripping {$basePath}/src/
     * from the file path. Therefore scanned code MUST live under {$basePath}/src,
     * and ROOT_NAMESPACE MUST map to the PSR-4 root of that src/ tree — otherwise
     * fileToClassName() builds an FQCN that class_exists() rejects and discovery
     * silently registers nothing.
     *
     * @param string $basePath project root (the directory that contains src/),
     *                         NOT the src/ directory itself
     */
    public static function register(ContainerBuilder $container, string $basePath): void
    {
        static::registerCoreBindings($container);

        foreach (static::SCAN_DIRS as $directory) {
            $fullPath = $basePath . '/' . $directory;

            if (!is_dir($fullPath)) {
                continue;
            }

            static::scanDirectory($container, $fullPath, $basePath);
        }
    }

    /**
     * Register framework-level bindings that are always available.
     *
     * Called at the start of register() before service discovery.
     * Guards prevent override when an adapter already registered the binding.
     */
    protected static function registerCoreBindings(ContainerBuilder $container): void
    {
        if (!$container->has(ClockInterface::class)) {
            $container->register(ClockInterface::class, NativeClock::class)
                ->setPublic(true);
        }

        if (!$container->has(CacheInterface::class)) {
            $container->register(CacheInterface::class, Psr16Cache::class)
                ->setArguments([new Definition(ArrayAdapter::class)])
                ->setPublic(true);
        }

        static::registerFormDefaults($container);
        static::registerBusDefaults($container);
        static::registerPersistenceDefaults($container);
        static::registerLoggingDefaults($container);
    }

    /**
     * Register the framework's default form pipeline (validation + Inertia
     * rendering) so hosts never hand-wire these internal collaborators.
     *
     * The concrete validator/renderer classes are @internal; binding them here
     * (each guarded) keeps the host composition root free of internal type
     * references while leaving every binding overridable — register your own
     * before discovery to substitute a different render target.
     */
    protected static function registerFormDefaults(ContainerBuilder $container): void
    {
        if (!$container->has(ConditionEvaluator::class)) {
            $container->register(ConditionEvaluator::class, ConditionEvaluator::class)
                ->setPublic(true);
        }

        if (!$container->has(FormValidator::class)) {
            $arguments = [new Reference(ConditionEvaluator::class)];

            // Route validation messages through the host translator when one is
            // bound; standalone apps without it keep the default English messages.
            if ($container->has(TranslatorInterface::class)) {
                $arguments[] = new Reference(TranslatorInterface::class);
            }

            $container->register(FormValidator::class, FormValidator::class)
                ->setArguments($arguments)
                ->setPublic(true);
        }

        if (!$container->has(InertiaFieldMapper::class)) {
            $container->register(InertiaFieldMapper::class, InertiaFieldMapper::class)
                ->setPublic(true);
        }

        if (!$container->has(InertiaRenderer::class)) {
            $container->register(InertiaRenderer::class, InertiaRenderer::class)
                ->setArguments([new Reference(InertiaFieldMapper::class)])
                ->setPublic(true);
        }

        if (!$container->has(RendererRegistry::class)) {
            $container->register(RendererRegistry::class, RendererRegistry::class)
                ->setArguments([[new Reference(InertiaRenderer::class)]])
                ->setPublic(true);
        }

        if (!$container->has(FieldSchemaReader::class)) {
            $container->register(FieldSchemaReader::class, FieldSchemaReader::class)
                ->setPublic(true);
        }
    }

    /**
     * Register the framework's default command/message bus so a standalone app
     * gets a working {@see MessageBusInterface} with no hand-wiring.
     *
     * One bus, two paths: a message with no `#[AsMessage]` is handled inline at
     * dispatch; a message marked `#[AsMessage('async')]` is routed to the
     * `async` transport and drained later by {@see CommandWorker}. Routing uses
     * an EMPTY-map Symfony {@see SendersLocator}, which falls back to reading
     * `#[AsMessage]` off the message class — so the attribute, not config,
     * decides. Handlers resolve by convention ({Command}Handler) against the
     * container itself.
     *
     * Every binding is guarded, so an adapter that registers its own bus,
     * transport or senders wins. Rebind {@see InMemoryTransport} (or the
     * {@see TransportLocator} `async` alias) to a durable transport
     * (Doctrine/AMQP/Redis) for cross-process async.
     */
    protected static function registerBusDefaults(ContainerBuilder $container): void
    {
        if (!$container->has(InMemoryTransport::class)) {
            $container->register(InMemoryTransport::class, InMemoryTransport::class)
                ->setPublic(true);
        }

        if (!$container->has(TransportLocator::class)) {
            $container->register(TransportLocator::class, TransportLocator::class)
                ->setArguments([['async' => new Reference(InMemoryTransport::class)]])
                ->setPublic(true);
        }

        if (!$container->has(SendersLocator::class)) {
            $container->register(SendersLocator::class, SendersLocator::class)
                ->setArguments([[], new Reference(TransportLocator::class)])
                ->setPublic(true);
        }

        if (!$container->has(MessageBusFactory::class)) {
            $container->register(MessageBusFactory::class, MessageBusFactory::class)
                ->setPublic(true);
        }

        if (!$container->has(MessageBusInterface::class)) {
            $container->register(MessageBusInterface::class, MessageBus::class)
                ->setFactory([new Reference(MessageBusFactory::class), 'create'])
                ->setArguments([
                    new Reference('service_container'),
                    new Reference(SendersLocator::class),
                ])
                ->setPublic(true);
        }

        if (!$container->has(CommandWorker::class)) {
            $container->register(CommandWorker::class, CommandWorker::class)
                ->setArguments([
                    new Reference(InMemoryTransport::class),
                    new Reference(MessageBusInterface::class),
                ])
                ->setPublic(true);
        }

        // Standalone scheduler: a runner that fires due #[Schedule] commands
        // through the bus on each minute tick (driven by an OS/host cron line).
        if (!$container->has(CronFieldMatcher::class)) {
            $container->register(CronFieldMatcher::class, CronFieldMatcher::class)
                ->setPublic(true);
        }

        if (!$container->has(ScheduleReaderInterface::class)) {
            $container->register(ScheduleReader::class, ScheduleReader::class)
                ->setPublic(true);
            $container->setAlias(ScheduleReaderInterface::class, ScheduleReader::class)
                ->setPublic(true);
        }

        if (!$container->has(ScheduleRunner::class)) {
            $container->register(ScheduleRunner::class, ScheduleRunner::class)
                ->setArguments([
                    new Reference(MessageBusInterface::class),
                    new Reference(ScheduleReaderInterface::class),
                    new Reference(CronFieldMatcher::class),
                    new Reference(ClockInterface::class),
                ])
                ->setPublic(true);
        }
    }

    /**
     * Register the framework's default entity-type registry plus the standalone
     * {@see EntityTypeRegistrar}, so a no-core app can populate the registry from
     * its own `#[EntityType]` (or `EntityTypeInterface`) classes and have the
     * entity picker resolve them.
     *
     * The registry sits in an ignored discovery domain, so it is bound here
     * explicitly. Guarded — an adapter/core that owns its own registry wins.
     */
    protected static function registerPersistenceDefaults(ContainerBuilder $container): void
    {
        if (!$container->has(EntityTypeRegistryInterface::class)) {
            $container->register(EntityTypeRegistry::class, EntityTypeRegistry::class)
                ->setPublic(true);
            $container->setAlias(EntityTypeRegistryInterface::class, EntityTypeRegistry::class)
                ->setPublic(true);
        }

        if (!$container->has(EntityTypeRegistrar::class)) {
            $container->register(EntityTypeRegistrar::class, EntityTypeRegistrar::class)
                ->setArguments([new Reference(EntityTypeRegistryInterface::class)])
                ->setPublic(true);
        }
    }

    /**
     * Register the framework's logging defaults so `#[LogChannel]` resolves and
     * services can inject a logger out of the box.
     *
     * The default {@see LoggerFactory} is DISABLED — every channel resolves to a
     * `NullLogger`, with the null actor/origin resolvers — so logs go nowhere
     * until the app rebinds `LoggerFactory` with a real base path and
     * `enabled: true`. Guarded, so an adapter/app that wires real logging wins.
     */
    protected static function registerLoggingDefaults(ContainerBuilder $container): void
    {
        if (!$container->has(ActorResolverInterface::class)) {
            $container->register(NullActorResolver::class, NullActorResolver::class)
                ->setPublic(true);
            $container->setAlias(ActorResolverInterface::class, NullActorResolver::class)
                ->setPublic(true);
        }

        if (!$container->has(OriginResolverInterface::class)) {
            $container->register(NullOriginResolver::class, NullOriginResolver::class)
                ->setPublic(true);
            $container->setAlias(OriginResolverInterface::class, NullOriginResolver::class)
                ->setPublic(true);
        }

        if (!$container->has(LoggerFactory::class)) {
            $container->register(LoggerFactory::class, LoggerFactory::class)
                ->setArguments([
                    sys_get_temp_dir(),
                    new Reference(ActorResolverInterface::class),
                    new Reference(OriginResolverInterface::class),
                    false,
                ])
                ->setPublic(true);
        }
    }

    /**
     * Recursively scan a directory for registrable PHP classes.
     *
     * The relative path used to build each FQCN is computed as
     * str_replace($basePath . '/src/', '', $file->getPathname()), so $directory
     * must sit under {$basePath}/src for the prefix strip to land on a clean
     * PSR-4 sub-path.
     *
     * @param string $directory absolute path being scanned (a {$basePath}/src subtree)
     * @param string $basePath  project root; its /src/ prefix is stripped from file paths
     */
    protected static function scanDirectory(ContainerBuilder $container, string $directory, string $basePath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . '/src/', '', $file->getPathname());

            // Skip ignored directories.
            foreach (static::IGNORE_DIRS as $ignoreDir) {
                if (str_contains($relativePath, $ignoreDir . '/')) {
                    continue 2;
                }
            }

            $className = static::fileToClassName($relativePath);
            if ($className === null) {
                continue;
            }
            if (!static::shouldRegister($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            $definition = new Definition($className);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);
            $definition->setPublic(true);

            // A class marked #[Lazy] is handed out as a ghost proxy, deferring
            // its real construction until first use (see ContainerFactory's
            // LazyServiceInstantiator wiring).
            if ($reflection->getAttributes(Lazy::class) !== []) {
                $definition->setLazy(true);
            }

            $container->setDefinition($className, $definition);

            LogChannelBinder::apply($container, $definition, $reflection);

            static::registerInterfaceAliases($container, $reflection, $className);
        }
    }

    /**
     * Convert a relative file path to a fully-qualified class name.
     */
    protected static function fileToClassName(string $relativePath): ?string
    {
        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $fqcn = static::ROOT_NAMESPACE . '\\' . $className;

        if (!class_exists($fqcn)) {
            return null;
        }

        return $fqcn;
    }

    /**
     * Determine if a class should be registered based on suffix rules and reflection.
     *
     * Precedence: IGNORE_SUFFIXES wins over REGISTER_SUFFIXES — a class is checked
     * against the ignore list first and bailed out before the register match runs.
     * To be registered a class must (a) not end in any IGNORE_SUFFIXES entry,
     * (b) end in some REGISTER_SUFFIXES entry, (c) not be abstract/interface/trait/enum,
     * and (d) not declare a non-public constructor (static-factory pattern).
     *
     * Rules (c)/(d) keep base hook registers out of the container: an abstract
     * base ({@see AbstractHookRegister}) is skipped
     * by (c), and a concrete class exposing only a static factory (non-public
     * constructor) is skipped by (d), even when its suffix is registrable.
     */
    protected static function shouldRegister(string $className): bool
    {
        $shortName = (new ReflectionClass($className))->getShortName();

        // Skip if matches ignore suffix.
        foreach (static::IGNORE_SUFFIXES as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                return false;
            }
        }

        // Must match a register suffix.
        $registerSuffixes = static::REGISTER_SUFFIXES;
        $lastSuffix = $registerSuffixes[array_key_last($registerSuffixes)];
        foreach ($registerSuffixes as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                break;
            }

            if ($suffix === $lastSuffix) {
                return false;
            }
        }

        $reflection = new ReflectionClass($className);

        // Skip abstract, interface, trait, enum.
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum()) {
            return false;
        }

        // Skip classes with non-public constructors (static factories).
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            return false;
        }

        return true;
    }

    /**
     * Auto-alias: if a class implements exactly one project-scoped interface, create an alias.
     *
     * @param ReflectionClass<object> $reflection
     */
    protected static function registerInterfaceAliases(
        ContainerBuilder $container,
        ReflectionClass $reflection,
        string $className,
    ): void {
        $interfaces = $reflection->getInterfaces();
        $projectInterfaces = [];

        foreach ($interfaces as $interface) {
            if (str_starts_with($interface->getName(), static::ROOT_NAMESPACE . '\\')) {
                $projectInterfaces[] = $interface->getName();
            }
        }

        if (count($projectInterfaces) === 1) {
            $container->setAlias($projectInterfaces[0], $className)->setPublic(true);
        }
    }
}
