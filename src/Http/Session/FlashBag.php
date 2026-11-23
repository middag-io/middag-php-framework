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
use Middag\Ui\Form\FormErrorBags;
use Middag\Ui\Form\FormErrors;

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
 * `useForm().errors` consumes. The errors bucket has one writer
 * ({@see self::setErrors()}) and one reader ({@see self::pullErrors()}), and
 * holds a single envelope (`bag` and the serialized `errors` map), so a bag
 * name never outlives the errors it scoped.
 *
 * @api
 */
final readonly class FlashBag
{
    private const string FLASH_KEY = '_middag_flash';

    private const string ERRORS_KEY = '_middag_errors';

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
     * Without a bag the next request reads them back as a flat
     * {@see FormErrors}. With a bag name (the `X-Inertia-Error-Bag` header of
     * the request that failed) it reads them back as a {@see FormErrorBags}
     * holding that one bag. The errors are stored in their serialized form, so
     * the session never holds objects.
     *
     * @param ?string $bag Inertia error bag name; null or '' for the flat map
     */
    public function setErrors(FormErrors $errors, ?string $bag = null): void
    {
        $this->session->set(self::ERRORS_KEY, [
            'bag' => $bag === '' ? null : $bag,
            'errors' => (array) $errors->jsonSerialize(),
        ]);
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
     * Returns a {@see FormErrorBags} with the one bag when the errors were
     * queued under a bag name, a {@see FormErrors} otherwise. Nothing queued,
     * or a session value that is not an envelope written by
     * {@see self::setErrors()}, reads as an empty {@see FormErrors}.
     */
    public function pullErrors(): FormErrorBags|FormErrors
    {
        $envelope = $this->bucket(self::ERRORS_KEY);
        $this->session->remove(self::ERRORS_KEY);

        $errors = $envelope['errors'] ?? null;

        if (!is_array($errors)) {
            return new FormErrors();
        }

        /** @var array<string, mixed> $errors */
        $formErrors = new FormErrors($errors);
        $bag = $envelope['bag'] ?? null;

        return is_string($bag) && $bag !== ''
            ? new FormErrorBags([$bag => $formErrors])
            : $formErrors;
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
