<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel;

use Middag\Framework\Exception\MiddagLifecycleViolationException;
use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\BootFailurePolicyInterface;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

/**
 * The register phase closes once the container is built; registering a
 * synthetic afterwards is a lifecycle violation (it would never reach the container).
 *
 * @internal
 */
#[CoversClass(ContainerFactory::class)]
final class ContainerFactoryLifecycleTest extends TestCase
{
    #[Test]
    public function addSyntheticBeforeBuildIsAllowed(): void
    {
        $factory = new ContainerFactory();
        $factory->addSynthetic('demo.flag', (object) ['ok' => true]);

        $factory->build($this->bootstrap(), ['demo.flag' => null]);

        self::assertTrue($factory->getContainer()?->get('demo.flag')->ok);
    }

    #[Test]
    public function addSyntheticAfterBuildThrowsLifecycleViolation(): void
    {
        $factory = new ContainerFactory();
        $factory->build($this->bootstrap());

        $this->expectException(MiddagLifecycleViolationException::class);
        $factory->addSynthetic('demo.late', (object) []);
    }

    #[Test]
    public function resetReopensTheRegisterPhase(): void
    {
        $factory = new ContainerFactory();
        $factory->build($this->bootstrap());
        $factory->reset();

        $factory->addSynthetic('demo.again', (object) []);

        self::assertNull($factory->getContainer());
    }

    #[Test]
    public function buildWithProjectRootRunsServiceDiscoveryAndBindsCoreServices(): void
    {
        $factory = new ContainerFactory();

        // A non-null project root triggers ServiceProvider::register(), which
        // registers the framework core bindings (SCAN_DIRS is empty on the base
        // provider, so nothing is scanned — the core bindings are the signal).
        $container = $factory->build($this->bootstrap(), [], sys_get_temp_dir());

        self::assertTrue($container->has(ClockInterface::class));
    }

    #[Test]
    public function syntheticDefinitionWithADistinctClassSetsThatClass(): void
    {
        $factory = new ContainerFactory();
        $factory->addSynthetic('demo.svc', new stdClass());

        $container = $factory->build($this->bootstrap(), ['demo.svc' => stdClass::class]);

        self::assertInstanceOf(stdClass::class, $container->get('demo.svc'));
    }

    #[Test]
    public function bootModulesBootsEachAndRoutesFailuresToThePolicy(): void
    {
        $policy = new class implements BootFailurePolicyInterface {
            /** @var list<string> */
            public array $failed = [];

            public function handle(ModuleInterface $module, Throwable $e): void
            {
                $this->failed[] = $module->getName();
            }
        };
        $factory = new ContainerFactory(failurePolicy: $policy);

        $ok = $this->createMock(ModuleInterface::class);
        $ok->method('getName')->willReturn('ok');
        $ok->expects(self::once())->method('boot');

        $bad = $this->createMock(ModuleInterface::class);
        $bad->method('getName')->willReturn('bad');
        $bad->method('boot')->willThrowException(new RuntimeException('boom'));

        $factory->bootModules([$ok, $bad]);

        self::assertSame(['bad'], $policy->failed, 'the failing module is routed to the policy, the healthy one boots');
    }

    private function bootstrap(): BootstrapInterface
    {
        return new class implements BootstrapInterface {
            public function configure(ContainerBuilder $builder): void {}

            public function platform(): string
            {
                return 'standalone';
            }

            public function getProjectRoot(): string
            {
                return '';
            }

            /** @return array<string, mixed> */
            public function getOptions(): array
            {
                return [];
            }
        };
    }
}
