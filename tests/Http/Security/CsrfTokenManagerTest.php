<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Security;

use Middag\Framework\Http\Security\CsrfTokenManager;
use Middag\Framework\Http\Session\ArraySession;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Session-backed CSRF token generation and constant-time validation.
 *
 * @internal
 */
#[CoversNothing]
final class CsrfTokenManagerTest extends TestCase
{
    #[Test]
    public function generatesAndPersistsAStableToken(): void
    {
        $csrf = new CsrfTokenManager($session = new ArraySession());

        $token = $csrf->token();

        $this->assertNotSame('', $token);
        $this->assertSame($token, $csrf->token(), 'token is stable within a session');
        $this->assertTrue($session->has('_middag_csrf'));
    }

    #[Test]
    public function validatesTheIssuedToken(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());
        $token = $csrf->token();

        $this->assertTrue($csrf->isValid($token));
    }

    #[Test]
    public function rejectsWrongEmptyAndNullCandidates(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());
        $csrf->token();

        $this->assertFalse($csrf->isValid('not-the-token'));
        $this->assertFalse($csrf->isValid(''));
        $this->assertFalse($csrf->isValid(null));
    }
}
