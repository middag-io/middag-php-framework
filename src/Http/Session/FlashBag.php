<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Session;

use Middag\Framework\Http\Contract\SessionInterface;
use Middag\Framework\Http\Middleware\ShareFlashMiddleware;

/**
 * One-shot flash store for the post-redirect-get cycle.
 *
 * Messages and validation errors queued on the request that handles a write
 * survive exactly one redirect: the next request reads them (clearing them in
 * the same move) so they render once and never again. The companion
 * {@see ShareFlashMiddleware} pulls both
 * buckets into the Inertia `flash` / `errors` shared props at the front of the
 * pipeline.
 *
 * Two buckets, because the client binds them differently: free-form flash
 * messages (`success`, `error`, …) vs the field→message validation map that
 * `useForm().errors` consumes.
 *
 * @api
 */
final readonly class FlashBag
{
    private const FLASH_KEY = '_middag_flash';

    private const ERRORS_KEY = '_middag_errors';

    public function __construct(
        private SessionInterface $session,
    ) {}

    /**
     * Queue a one-shot flash message (e.g. `add('success', 'Saved.')`).
     */
    public function add(string $key, mixed $value): void
    {
        $flash = $this->bucket(self::FLASH_KEY);
        $flash[$key] = $value;
        $this->session->set(self::FLASH_KEY, $flash);
    }

    /**
     * Queue field-level validation errors for the next request.
     *
     * Usually a flat field→message map, but may be nested one level under a
     * named error bag (`[bag => [field => message]]`) for Inertia error bags.
     *
     * @param array<string, mixed> $errors
     */
    public function setErrors(array $errors): void
    {
        $this->session->set(self::ERRORS_KEY, $errors);
    }

    /**
     * Read and clear the queued flash messages.
     *
     * @return array<string, mixed>
     */
    public function pull(): array
    {
        $flash = $this->bucket(self::FLASH_KEY);
        $this->session->remove(self::FLASH_KEY);

        return $flash;
    }

    /**
     * Read and clear the queued validation errors.
     *
     * @return array<string, mixed>
     */
    public function pullErrors(): array
    {
        $errors = $this->bucket(self::ERRORS_KEY);
        $this->session->remove(self::ERRORS_KEY);

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function bucket(string $key): array
    {
        $value = $this->session->get($key, []);

        return is_array($value) ? $value : [];
    }
}
