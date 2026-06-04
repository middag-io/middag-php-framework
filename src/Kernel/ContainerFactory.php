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

use Middag\Framework\Exception\MiddagLifecycleViolationException;
use Middag\Framework\Kernel\Bootstrap\BootRethrowFailurePolicy;
use Middag\Framework\Kernel\Contract\BootFailurePolicyInterface;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use Middag\Framework\Kernel\Facade\AbstractFacade;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\LazyServiceInstantiator;
use Throwable;

/**
 * DI container orchestration engine.
 *
 * Builds the Symfony DI container through a multi-phase process:
 * 1. Register synthetic service placeholders.
 * 2. Configure via platform-specific BootstrapInterface.
 * 3. Auto-discover services via ServiceProvider.
 * 4. Compile the container (freeze the dependency graph).
 * 5. Inject synthetic instances (platform globals available only at runtime).
 * 6. Wire facades via {@see AbstractFacade::setFacadeContainer()}.
 *
 * Phases 1-6 run inside {@see self::build()}. Booting modules is a separate
 * step: {@see self::bootModules()} runs each {@see ModuleInterface::boot()}
 * under the configured {@see BootFailurePolicyInterface}.
 *
 * Adapters subclass or configure this factory for platform-specific needs;
 * a standalone host builds its container directly through {@see self::build()}
 * and {@see self::bootModules()}. This is the supported boot entry point.
 *
 * @api
 */
class ContainerFactory
{
    protected ?ContainerBuilder $container = null;

    /** @var array<string, object> Synthetic services to inject post-compile. */
    protected array $synthetics = [];

    /** True once {@see self::build()} has compiled the container (register phase closed). */
    protected bool $built = false;

    private readonly BootFailurePolicyInterface $failurePolicy;

    public function __construct(
        protected readonly LoggerInterface $logger = new NullLogger(),
        ?BootFailurePolicyInterface $failurePolicy = null,
    ) {
        $this->failurePolicy = $failurePolicy ?? new BootRethrowFailurePolicy();
    }

    /**
     * Build and return the compiled container.
     *
     * Ordering: register synthetic placeholders, run {@see BootstrapInterface::configure()},
     * auto-discover services via {@see ServiceProvider} (when $projectRoot is set), then
     * compile() to freeze the graph. Only AFTER compile() are the registered synthetic
     * instances injected, followed by {@see AbstractFacade::setFacadeContainer()}.
     * Booting modules is NOT part of build — call {@see self::bootModules()} separately
     * once the returned container is wired.
     *
     * @param BootstrapInterface         $bootstrap            Platform-specific bootstrap configuration
     * @param array<string, null|string> $syntheticDefinitions Service IDs to declare as synthetic (injected post-compile)
     * @param null|string                $projectRoot          Base path for service discovery
     */
    public function build(
        BootstrapInterface $bootstrap,
        array $syntheticDefinitions = [],
        ?string $projectRoot = null,
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        // Enable lazy services: definitions marked lazy (via #[Lazy] or setLazy)
        // are handed out as ghost proxies and built only on first use. No-op for
        // non-lazy definitions, so this is free unless a service opts in.
        $container->setProxyInstantiator(new LazyServiceInstantiator());

        $this->registerSyntheticDefinitions($container, $syntheticDefinitions);
        $bootstrap->configure($container);

        if ($projectRoot !== null) {
            ServiceProvider::register($container, $projectRoot);
        }

        $container->compile();

        foreach ($this->synthetics as $id => $instance) {
            $container->set($id, $instance);
        }

        AbstractFacade::setFacadeContainer($container);

        $this->container = $container;
        $this->built = true;

        return $container;
    }

    /**
     * Boot all modules after container is compiled and synthetics injected.
     *
     * @param ModuleInterface[] $modules Modules in dependency order
     */
    public function bootModules(array $modules): void
    {
        foreach ($modules as $module) {
            try {
                $module->boot();
            } catch (Throwable $e) {
                $this->failurePolicy->handle($module, $e);
            }
        }
    }

    /**
     * Register a synthetic instance to be injected after container compile.
     *
     * Must be called before {@see self::build()} — synthetics are injected
     * during build's post-compile step, so registering one afterwards would
     * never reach the container.
     *
     * @throws MiddagLifecycleViolationException if called after the container is built
     */
    public function addSynthetic(string $serviceId, object $instance): void
    {
        if ($this->built) {
            throw new MiddagLifecycleViolationException(sprintf(
                'Cannot register synthetic "%s" after the container is built; addSynthetic() must run before build().',
                $serviceId,
            ));
        }

        $this->synthetics[$serviceId] = $instance;
    }

    public function getContainer(): ?ContainerBuilder
    {
        return $this->container;
    }

    public function reset(): void
    {
        $this->container = null;
        $this->synthetics = [];
        $this->built = false;
        AbstractFacade::reset();
    }

    /**
     * @param array<string, null|string> $definitions Service ID => class (null = same as ID)
     */
    protected function registerSyntheticDefinitions(ContainerBuilder $container, array $definitions): void
    {
        $container->register(ContainerInterface::class)
            ->setSynthetic(true)
            ->setPublic(true);

        foreach ($definitions as $serviceId => $class) {
            $def = $container->register($serviceId);
            $def->setSynthetic(true);
            $def->setPublic(true);

            if ($class !== null && $class !== $serviceId) {
                $def->setClass($class);
            }
        }
    }
}
