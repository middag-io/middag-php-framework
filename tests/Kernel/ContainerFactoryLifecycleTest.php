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
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
