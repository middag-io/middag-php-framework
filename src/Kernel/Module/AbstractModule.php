<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Module;

use Middag\Framework\Kernel\Contract\HookRegisterInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Minimal base class for framework modules.
 *
 * Provides lifecycle defaults (register, boot) and filesystem-based
 * auto-discovery of controllers and hooks.
 *
 * Defaults: getName() and getLabel() both return MODULE_IDNUMBER; getVersion()
 * returns VERSION ('1.0.0'); getDependencies() returns REQUIRES; isAvailable()
 * delegates to isEnabled() (true); boot() runs registerControllers() then
 * registerHooks(). Override the constants and methods as needed.
 *
 * Richer distribution-flavored module bases may extend this in a consumer
 * package. Platform adapters (Moodle, WordPress) extend their own AbstractModule.
 *
 * @api
 */
abstract class AbstractModule implements ModuleInterface
{
    /** Unique slug identifier for this module. */
    protected const MODULE_IDNUMBER = '';

    /** Module slugs this module depends on. @var string[] */
    protected const REQUIRES = [];

    /** Human-readable version string. */
    protected const VERSION = '1.0.0';

    protected ?ContainerInterface $container = null;

    public function getName(): string
    {
        return static::MODULE_IDNUMBER;
    }

    public function getLabel(): string
    {
        return static::MODULE_IDNUMBER;
    }

    public function getVersion(): string
    {
        return static::VERSION;
    }

    /** @return string[] */
    public function getDependencies(): array
    {
        return static::REQUIRES;
    }

    public function register(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function boot(): void
    {
        $this->registerControllers();
        $this->registerHooks();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return $this->isEnabled();
    }

    /**
     * Auto-discover and register controllers via #[Route] attributes.
     */
    public function registerControllers(): void
    {
        $this->discoverClassesBySuffix('Controller');
    }

    /**
     * Auto-discover and register hook classes.
     */
    public function registerHooks(): void
    {
        $hooks = $this->discoverClassesBySuffix('Hooks');

        foreach ($hooks as $hookClass) {
            if ($this->container?->has($hookClass)) {
                $hook = $this->container->get($hookClass);

                if ($hook instanceof HookRegisterInterface) {
                    $hook->register();
                }
            }
        }
    }

    /**
     * Discover PHP classes in the module's directory matching a suffix.
     *
     * @return string[] FQCNs of discovered classes
     */
    protected function discoverClassesBySuffix(string $suffix): array
    {
        $moduleDir = $this->getModuleDirectory();

        if ($moduleDir === null || !is_dir($moduleDir)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getBasename('.php');

            if (!str_ends_with($filename, $suffix)) {
                continue;
            }

            $moduleReflection = new ReflectionClass(static::class);
            $namespace = $moduleReflection->getNamespaceName();
            $relativePath = str_replace($moduleDir, '', $file->getPath());
            $subNamespace = str_replace('/', '\\', trim($relativePath, '/'));
            $fqcn = $subNamespace !== ''
                ? $namespace . '\\' . $subNamespace . '\\' . $filename
                : $namespace . '\\' . $filename;

            if (class_exists($fqcn)) {
                $reflection = new ReflectionClass($fqcn);

                if (!$reflection->isAbstract() && !$reflection->isInterface()) {
                    $classes[] = $fqcn;
                }
            }
        }

        return $classes;
    }

    protected function getModuleDirectory(): ?string
    {
        $reflection = new ReflectionClass(static::class);
        $fileName = $reflection->getFileName();

        return $fileName !== false ? dirname($fileName) : null;
    }
}
