<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Auth;

use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Contract\SessionInterface;
use Middag\Framework\Http\Session\ArraySession;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class SessionAuthenticatorTest extends TestCase
{
    #[Test]
    public function startsUnauthenticated(): void
    {
        $auth = new SessionAuthenticator(new ArraySession());

        $this->assertFalse($auth->check());
        $this->assertNull($auth->id());
        $this->assertNull($auth->user());
    }

    #[Test]
    public function loginEstablishesIdentityAndAttributes(): void
    {
        $auth = new SessionAuthenticator(new ArraySession());
        $auth->login(7, ['name' => 'Grace', 'email' => 'grace@example.test']);

        $this->assertTrue($auth->check());
        $this->assertSame(7, $auth->id());
        $this->assertSame(
            ['id' => 7, 'attributes' => ['name' => 'Grace', 'email' => 'grace@example.test']],
            $auth->user(),
        );
    }

    #[Test]
    public function logoutClearsIdentity(): void
    {
        $auth = new SessionAuthenticator(new ArraySession());
        $auth->login(7);
        $auth->logout();

        $this->assertFalse($auth->check());
        $this->assertNull($auth->id());
    }

    #[Test]
    public function identityPersistsAcrossAuthenticatorInstancesSharingTheSession(): void
    {
        $session = new ArraySession();
        (new SessionAuthenticator($session))->login(99);

        $this->assertSame(99, (new SessionAuthenticator($session))->id());
    }

    #[Test]
    public function exposesConfiguredLoginPath(): void
    {
        $this->assertSame('/login', (new SessionAuthenticator(new ArraySession()))->loginPath());
        $this->assertSame('/auth/sign-in', (new SessionAuthenticator(new ArraySession(), '/auth/sign-in'))->loginPath());
    }

    #[Test]
    public function loginRegeneratesSessionIdBeforePersistingIdentity(): void
    {
        $session = $this->recordingSession();

        (new SessionAuthenticator($session))->login(42, ['role' => 'admin']);

        // Order matters: the id must rotate BEFORE the identity is written, or
        // an attacker-fixed id still carries into the authenticated session.
        $this->assertSame(['regenerate', 'set'], $session->calls);
        $this->assertSame(42, (new SessionAuthenticator($session))->id());
    }

    /**
     * @return object{calls: list<string>}&SessionInterface
     */
    private function recordingSession(): SessionInterface
    {
        return new class implements SessionInterface {
            /** @var list<string> */
            public array $calls = [];

            /** @var array<string, mixed> */
            private array $data = [];

            public function start(): void {}

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->data);
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->calls[] = 'set';
                $this->data[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->data[$key]);
            }

            public function clear(): void
            {
                $this->data = [];
            }

            public function regenerate(bool $deleteOld = true): void
            {
                $this->calls[] = 'regenerate';
            }
        };
    }
}
