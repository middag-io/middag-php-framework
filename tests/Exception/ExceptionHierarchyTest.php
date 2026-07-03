<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Exception;

use Middag\Framework\Exception\MiddagAuthenticationException;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Exception\MiddagDomainException;
use Middag\Framework\Exception\MiddagException;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Exception\MiddagLifecycleViolationException;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Exception\MiddagPersistenceException;
use Middag\Framework\Exception\MiddagValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Guards the typed, status-mapped exception hierarchy consumed by
 * HttpKernel::mapThrowable(): every concrete exception descends from the
 * MiddagException root (one `catch (MiddagException)` covers all of them),
 * and each declared getStatusCode() stays on its contract — the kernel maps
 * it verbatim onto the HTTP response.
 *
 * @internal
 */
#[CoversClass(MiddagAuthenticationException::class)]
#[CoversClass(MiddagAuthorizationException::class)]
#[CoversClass(MiddagDomainException::class)]
#[CoversClass(MiddagException::class)]
#[CoversClass(MiddagInfrastructureException::class)]
#[CoversClass(MiddagLifecycleViolationException::class)]
#[CoversClass(MiddagNotFoundException::class)]
#[CoversClass(MiddagPersistenceException::class)]
#[CoversClass(MiddagValidationException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @param class-string<MiddagException> $class
     */
    #[DataProvider('provideStatusMap')]
    public function testStatusCodeMap(string $class, int $expectedStatus): void
    {
        $exception = new $class('boom');

        self::assertSame($expectedStatus, $exception->getStatusCode());
    }

    /**
     * @param class-string<MiddagException> $class
     */
    #[DataProvider('provideStatusMap')]
    public function testEveryExceptionDescendsFromTheMiddagRoot(string $class): void
    {
        self::assertTrue(is_subclass_of($class, MiddagException::class));
        self::assertTrue(is_subclass_of($class, RuntimeException::class));
    }

    /**
     * The full status map mapThrowable() relies on. Classes without an
     * override inherit the root's 500.
     *
     * @return iterable<string, array{class-string<MiddagException>, int}>
     */
    public static function provideStatusMap(): iterable
    {
        yield 'domain -> 400' => [MiddagDomainException::class, 400];

        yield 'authentication -> 401' => [MiddagAuthenticationException::class, 401];

        yield 'authorization -> 403' => [MiddagAuthorizationException::class, 403];

        yield 'not found -> 404' => [MiddagNotFoundException::class, 404];

        yield 'validation -> 422' => [MiddagValidationException::class, 422];

        yield 'infrastructure -> 500 (root default)' => [MiddagInfrastructureException::class, 500];

        yield 'persistence -> 500 (root default)' => [MiddagPersistenceException::class, 500];

        yield 'lifecycle violation -> 500 (root default)' => [MiddagLifecycleViolationException::class, 500];
    }

    public function testRootIsAbstractAndDefaultsTo500(): void
    {
        $reflection = new ReflectionClass(MiddagException::class);

        self::assertTrue($reflection->isAbstract());
        self::assertTrue($reflection->isSubclassOf(RuntimeException::class));

        $anonymous = new class extends MiddagException {};

        self::assertSame(500, $anonymous->getStatusCode());
    }

    public function testNotFoundIsADomainException(): void
    {
        self::assertTrue(is_subclass_of(MiddagNotFoundException::class, MiddagDomainException::class));
    }

    public function testValidationIsADomainException(): void
    {
        self::assertTrue(is_subclass_of(MiddagValidationException::class, MiddagDomainException::class));
    }

    public function testPersistenceIsAnInfrastructureException(): void
    {
        self::assertTrue(is_subclass_of(MiddagPersistenceException::class, MiddagInfrastructureException::class));
    }

    public function testValidationDefaults(): void
    {
        $exception = new MiddagValidationException();

        self::assertSame('Validation failed', $exception->getMessage());
        self::assertSame([], $exception->errors());
        self::assertNull($exception->getPrevious());
    }

    public function testValidationCarriesErrorMapAndPrevious(): void
    {
        $previous = new RuntimeException('cause');
        $errors = ['email' => ['Required', 'Must be unique'], 'name' => 'Too short'];

        $exception = new MiddagValidationException('Bad input', $errors, $previous);

        self::assertSame('Bad input', $exception->getMessage());
        self::assertSame($errors, $exception->errors());
        self::assertSame($previous, $exception->getPrevious());
    }
}
