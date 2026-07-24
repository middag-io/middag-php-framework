<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Contract;

use Middag\Framework\Http\Contract\AuthorizationAwareInterface;
use Middag\Framework\Http\Contract\ContainerAwareInterface;
use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\Contract\RequestHandlingInterface;
use Middag\Framework\Http\Controller\AbstractController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainer;
use ReflectionClass;
use ReflectionMethod;

/**
 * FW-014: ControllerInterface is the union of three segregated role interfaces.
 * The split is non-breaking (full surface preserved) and lets an adapter adopt
 * a single role without the request lifecycle.
 *
 * @internal
 */
#[CoversClass(ControllerInterface::class)]
final class ControllerInterfaceSegregationTest extends TestCase
{
    #[Test]
    public function controllerInterfaceComposesTheThreeRoles(): void
    {
        $reflection = new ReflectionClass(ControllerInterface::class);

        self::assertTrue($reflection->implementsInterface(ContainerAwareInterface::class));
        self::assertTrue($reflection->implementsInterface(RequestHandlingInterface::class));
        self::assertTrue($reflection->implementsInterface(AuthorizationAwareInterface::class));
    }

    #[Test]
    public function fullSurfaceIsPreservedForExistingImplementations(): void
    {
        $reflection = new ReflectionClass(ControllerInterface::class);
        $methods = array_map(
            static fn (ReflectionMethod $m): string => $m->getName(),
            $reflection->getMethods(),
        );
        sort($methods);

        self::assertSame(
            ['handle', 'preHandle', 'setContainer', 'setRequest', 'setRequireCapabilities', 'setRequireLogin'],
            $methods,
            'the segregation keeps every method the monolithic contract had',
        );

        // The concrete base still satisfies the full contract unchanged.
        self::assertInstanceOf(ControllerInterface::class, $this->fullController());
    }

    #[Test]
    public function anAdapterMayAdoptASingleRoleWithoutTheLifecycle(): void
    {
        // A collaborator that only needs container wiring — e.g. a host REST
        // controller whose dispatch is the host's, not the kernel's handle().
        $containerOnly = new class implements ContainerAwareInterface {
            public ?PsrContainer $container = null;

            public function setContainer(PsrContainer $container): void
            {
                $this->container = $container;
            }
        };

        self::assertInstanceOf(ContainerAwareInterface::class, $containerOnly);
        self::assertNotInstanceOf(RequestHandlingInterface::class, $containerOnly);
        self::assertNotInstanceOf(ControllerInterface::class, $containerOnly);
    }

    private function fullController(): ControllerInterface
    {
        return new class extends AbstractController {
            public function handle(): void {}
        };
    }
}
