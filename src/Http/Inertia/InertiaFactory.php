<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Inertia;

use Closure;
use Middag\Ui\Envelope\Contract\ContractEnvelopeInterface;
use Middag\Ui\Page\Contract\PageContractInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factory responsible for creating Inertia responses.
 *
 * Platform-agnostic: also exposes the optional host HTML bootstrap
 * registration point used by {@see InertiaResponse::toResponse()} for the
 * first-visit branch. The Moodle/WP adapter registers its bootstrap closure
 * during boot; the closure receives the response payload plus its safely
 * encoded JSON forms (`array $page, string $json, string $attr`) and returns
 * a {@see Response}.
 *
 * Static by design (configuration seam, invariant protocol) — see the rationale
 * on {@see InertiaAdapter}.
 *
 * Host-facing: the boot-time registration point plus {@see self::page()} (the
 * contract→Inertia response bridge consumed by controllers) form the supported
 * integration surface.
 *
 * @api
 */
class InertiaFactory
{
    /** @var null|Closure(array, string, string): Response */
    private static ?Closure $htmlBootstrap = null;

    /**
     * Build an Inertia response wrapper for the given component and props.
     *
     * @param array<string, mixed> $props
     */
    public static function render(string $component, array $props = [], ?Request $request = null): InertiaResponse
    {
        return new InertiaResponse($component, $props, $request);
    }

    /**
     * Build an Inertia response from a middag-io/ui contract envelope.
     *
     * THE bridge the framework exists for: a server-built PageContract / Fragment
     * becomes an Inertia response with the envelope folded into a reserved
     * `contract` prop. Full visit → HTML shell, X-Inertia → JSON, partial reloads
     * and entity normalization are all inherited from {@see InertiaResponse} (the
     * envelope is JsonSerializable, so it serializes via jsonSerialize(), which
     * already carries the contract `version` for the client to route on).
     *
     * The component name is derived from the envelope so the JS renderer can pick
     * its full-page vs fragment renderer: a PageContract resolves to `Page`, any
     * other envelope (Fragment) to `Fragment`. Page-local props merge alongside
     * the reserved `contract` prop.
     *
     * @param array<string, mixed> $extraProps merged alongside the contract
     */
    public static function page(
        ContractEnvelopeInterface $contract,
        array $extraProps = [],
        ?Request $request = null,
    ): InertiaResponse {
        $component = $contract instanceof PageContractInterface ? 'Page' : 'Fragment';

        return new InertiaResponse($component, ['contract' => $contract] + $extraProps, $request);
    }

    /**
     * Register the host-specific HTML bootstrap closure.
     *
     * @param Closure(array, string, string): Response $bootstrap
     */
    public static function setHtmlBootstrap(Closure $bootstrap): void
    {
        self::$htmlBootstrap = $bootstrap;
    }

    /**
     * Returns the registered HTML bootstrap closure, or null if none configured.
     *
     * @return null|Closure(array, string, string): Response
     */
    public static function getHtmlBootstrap(): ?Closure
    {
        return self::$htmlBootstrap;
    }
}
