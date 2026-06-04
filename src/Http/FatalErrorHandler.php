<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Middag\Framework\Observability\Contract\ProfileCollectorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Last-resort shutdown guard for fatal errors the request pipeline cannot catch
 * (memory exhaustion, parse errors, uncaught throwables outside the kernel).
 *
 * Registered via {@see self::register()}, it runs on PHP shutdown, inspects
 * {@see error_get_last()}, and — only for fatal error types — turns a would-be
 * blank "white screen of death" into a clean, content-negotiated 500:
 *
 *   - API/XHR/JSON request  → `{"error":{"code":"…","message":"…"}}`
 *   - browser request       → minimal HTML page showing the same error code
 *
 * Every fatal gets a short **error code** (correlation id) that is logged at
 * `critical`, recorded on the {@see ProfileCollectorInterface} (when wired), and
 * shown to the user — so support can trace a screenshot back to the log line.
 * In production (`$debug = false`) the technical detail is never sent to the
 * client, only the code; with `$debug = true` the message/file/line are included.
 *
 * Registration is a global side-effect, so it is opt-in: the host adapter (or
 * standalone front controller) calls {@see self::register()} early in the
 * request — not auto-wired by the container.
 *
 * @api
 */
final readonly class FatalErrorHandler
{
    /** Error types treated as fatal (unrecoverable) at shutdown. */
    private const FATAL_MASK = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private ?ProfileCollectorInterface $profile = null,
        private bool $debug = false,
    ) {}

    /**
     * Register the shutdown guard. The host adapter / front controller should
     * call this once, early in the request.
     */
    public function register(): void
    {
        register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * Shutdown callback: emit a clean 500 when the last error was fatal.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || ($error['type'] & self::FATAL_MASK) === 0) {
            return;
        }

        $code = $this->generateErrorCode();
        $this->report($error, $code);

        // Output already started — can't send headers/body cleanly. The fatal is
        // logged and recorded above; nothing safe to render here.
        if (headers_sent()) {
            return;
        }

        [$status, $contentType, $body] = $this->buildResponse($error, $code, $this->serverWantsJson($_SERVER));

        http_response_code($status);
        header('Content-Type: ' . $contentType);
        echo $body;
    }

    /**
     * Log the fatal at `critical` and record it on the profiler (when wired),
     * both stamped with the correlation code.
     *
     * @param array{type: int, message: string, file: string, line: int} $error
     */
    public function report(array $error, string $code): void
    {
        $context = [
            'code' => $code,
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ];

        $this->logger->critical('[fatal] {code} {message} at {file}:{line}', $context);
        $this->profile?->record('fatal', $code, $context);
    }

    /**
     * Build the negotiated 500 response triple: [status, contentType, body].
     *
     * @param array{type: int, message: string, file: string, line: int} $error
     *
     * @return array{0: int, 1: string, 2: string}
     */
    public function buildResponse(array $error, string $code, bool $wantsJson): array
    {
        if ($wantsJson) {
            $payload = ['error' => ['code' => $code, 'message' => 'Internal Server Error']];

            if ($this->debug) {
                $payload['error']['detail'] = sprintf('%s at %s:%d', $error['message'], $error['file'], $error['line']);
            }

            return [
                500,
                'application/json',
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        return [500, 'text/html; charset=UTF-8', $this->renderHtml($error, $code)];
    }

    /**
     * Decide whether the client expects a JSON body, from request metadata.
     *
     * @param array<string, mixed> $server the `$_SERVER` superglobal (or a stub in tests)
     */
    public function serverWantsJson(array $server): bool
    {
        // Inertia visits expect an HTML reload on a hard failure, not raw JSON.
        if (isset($server['HTTP_X_INERTIA'])) {
            return false;
        }

        if (strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }

        if (str_contains(strtolower((string) ($server['HTTP_ACCEPT'] ?? '')), 'application/json')) {
            return true;
        }

        return str_contains(strtolower((string) ($server['CONTENT_TYPE'] ?? '')), 'application/json');
    }

    /**
     * @param array{type: int, message: string, file: string, line: int} $error
     */
    private function renderHtml(array $error, string $code): string
    {
        $detail = '';

        if ($this->debug) {
            $detail = sprintf(
                '<pre>%s</pre>',
                htmlspecialchars(
                    sprintf('%s at %s:%d', $error['message'], $error['file'], $error['line']),
                    ENT_QUOTES,
                    'UTF-8',
                ),
            );
        }

        return sprintf(
            '<!doctype html><meta charset="UTF-8"><title>500 — Internal Server Error</title>'
            . '<h1>Internal Server Error</h1>'
            . '<p>Something went wrong. Report this code to support: <strong>%s</strong></p>%s',
            htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            $detail,
        );
    }

    /**
     * Short, user-facing correlation code (8 uppercase hex chars).
     */
    private function generateErrorCode(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }
}
