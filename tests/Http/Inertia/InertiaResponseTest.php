<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Inertia;

use Closure;
use JsonSerializable;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Inertia\InertiaResponse;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asset-version skew handling. A GET X-Inertia visit whose client
 * version no longer matches the server's must get a 409 + X-Inertia-Location
 * (hard reload) instead of fresh JSON.
 *
 * Partial-aware, lazy prop resolution: plain closures stay eager,
 * `optional()` props resolve only when requested in a partial, unrequested keys
 * skip compute, and shared closures resolve exactly once.
 *
 * @internal
 */
#[CoversClass(InertiaResponse::class)]
final class InertiaResponseTest extends TestCase
{
    protected function setUp(): void
    {
        InertiaVersionManager::setVersion('v2');
        InertiaManager::flush();
    }

    protected function tearDown(): void
    {
        InertiaManager::flush();
        // Clear any custom HTML bootstrap a test installed (no public unsetter).
        (new ReflectionProperty(InertiaFactory::class, 'htmlBootstrap'))->setValue(null, null);
    }

    #[Test]
    public function scalarPropsPassThroughNormalizeUnchanged(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'v2');

        $response = (new InertiaResponse('Dashboard', ['count' => 5, 'label' => 'hi'], $request))->toResponse();

        $page = json_decode((string) $response->getContent(), true);
        self::assertSame(5, $page['props']['count']);
        self::assertSame('hi', $page['props']['label']);
    }

    #[Test]
    public function customHtmlBootstrapClosureRendersTheDocumentResponse(): void
    {
        InertiaFactory::setHtmlBootstrap(
            static fn (array $page, string $json, string $attr): Response => new Response('BOOTSTRAP:' . $page['component'], 200),
        );

        // A non-Inertia GET triggers the HTML document path, which delegates to
        // the registered bootstrap closure and merges the Inertia headers onto it.
        $response = (new InertiaResponse('Dashboard', [], Request::create('/dashboard', 'GET')))->toResponse();

        self::assertSame('BOOTSTRAP:Dashboard', $response->getContent());
        self::assertSame('X-Inertia', $response->headers->get('Vary'));
        self::assertSame('v2', $response->headers->get('X-Inertia-Version'));
    }

    #[Test]
    public function staleVersionGetReturns409WithLocation(): void
    {
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'v1',
        ]);

        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        self::assertSame(409, $response->getStatusCode());
        self::assertSame($request->getUri(), $response->headers->get('X-Inertia-Location'));
        self::assertSame('X-Inertia', $response->headers->get('Vary'));
        // Every Inertia response carries the version header, the 409 included.
        self::assertSame('v2', $response->headers->get('X-Inertia-Version'));
    }

    #[Test]
    public function matchingVersionGetReturnsJson(): void
    {
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'v2',
        ]);

        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->headers->get('X-Inertia'));
    }

    #[Test]
    public function missingVersionHeaderDoesNotForceReload(): void
    {
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function jsonResponseCarriesVaryAndVersionHeaders(): void
    {
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        // The JSON response carries both the Vary and the version headers.
        self::assertSame('X-Inertia', $response->headers->get('Vary'));
        self::assertSame('v2', $response->headers->get('X-Inertia-Version'));
    }

    #[Test]
    public function htmlResponseCarriesVaryAndVersionHeaders(): void
    {
        $request = Request::create('/dashboard', 'GET'); // no X-Inertia → HTML bootstrap

        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        self::assertSame('X-Inertia', $response->headers->get('Vary'));
        self::assertSame('v2', $response->headers->get('X-Inertia-Version'));
    }

    #[Test]
    public function strayBufferedOutputIsPurgedBeforeBody(): void
    {
        // Host noise buffered before the body must not corrupt the payload.
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        ob_start();
        echo 'STRAY NOTICE';
        (new InertiaResponse('Dashboard', [], $request))->toResponse();
        $leftover = ob_get_clean();

        self::assertSame('', $leftover);
    }

    // ── partial-aware, lazy prop resolution ────────────────────────────

    #[Test]
    public function plainClosurePropStaysEagerOnFullLoad(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET'); // full load, no X-Inertia

        (new InertiaResponse('Dashboard', ['stats' => function () use (&$calls): array {
            ++$calls;

            return ['count' => 1];
        }], $request))->toResponse();

        self::assertSame(1, $calls, 'plain closure must resolve on full load (default eager preserved)');
    }

    #[Test]
    public function optionalPropDoesNotResolveOnFullLoad(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET');

        (new InertiaResponse('Dashboard', [
            'stats' => InertiaAdapter::optional(function () use (&$calls): array {
                ++$calls;

                return ['count' => 1];
            }),
        ], $request))->toResponse();

        self::assertSame(0, $calls, 'optional() must never run on a full load');
    }

    #[Test]
    public function optionalPropDoesNotResolveOnNormalInertiaVisit(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', [
            'stats' => InertiaAdapter::optional(function () use (&$calls): array {
                ++$calls;

                return ['count' => 1];
            }),
        ], $request))->toResponse();

        self::assertSame(0, $calls, 'optional() must never run on a normal Inertia visit');
        self::assertArrayNotHasKey('stats', $this->props($response));
    }

    #[Test]
    public function optionalPropResolvesOnlyWhenRequestedInPartial(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'stats',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'stats' => InertiaAdapter::optional(function () use (&$calls): array {
                ++$calls;

                return ['count' => 7];
            }),
        ], $request))->toResponse();

        self::assertSame(1, $calls, 'optional() resolves exactly once when its key is requested');
        self::assertSame(['count' => 7], $this->props($response)['stats']);
    }

    #[Test]
    public function partialReloadSkipsComputeOfUnrequestedKeys(): void
    {
        $aCalls = 0;
        $bCalls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'a',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'a' => function () use (&$aCalls): string {
                ++$aCalls;

                return 'A';
            },
            'b' => function () use (&$bCalls): string {
                ++$bCalls;

                return 'B';
            },
        ], $request))->toResponse();

        $props = $this->props($response);
        self::assertSame(['a' => 'A'], $props, 'only the requested key is serialized');
        self::assertSame(1, $aCalls, 'requested closure runs');
        self::assertSame(0, $bCalls, 'unrequested closure must not run (compute saved, not just wire)');
    }

    #[Test]
    public function partialForAnotherComponentResolvesEagerly(): void
    {
        // Partial addressed to a different component is not a partial for us:
        // plain props stay eager, optional() props stay absent.
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Other',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'stats',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'eager' => fn (): string => 'kept',
            'stats' => InertiaAdapter::optional(function () use (&$calls): string {
                ++$calls;

                return 'lazy';
            }),
        ], $request))->toResponse();

        $props = $this->props($response);
        self::assertSame('kept', $props['eager']);
        self::assertArrayNotHasKey('stats', $props);
        self::assertSame(0, $calls);
    }

    #[Test]
    public function sharedClosureResolvesExactlyOnce(): void
    {
        // Regression: the old double-pass (getShared() resolved, then
        // resolveProps() re-checked) invoked shared closures twice.
        $calls = 0;
        InertiaManager::share('csrf', function () use (&$calls): string {
            ++$calls;

            return 'token';
        });

        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $response = (new InertiaResponse('Dashboard', [], $request))->toResponse();

        self::assertSame(1, $calls, 'shared closure resolves once, not twice');
        self::assertSame('token', $this->props($response)['csrf']);
    }

    #[Test]
    public function getSharedReturnsRawUnresolvedValues(): void
    {
        InertiaManager::share('lazy', fn (): string => 'resolved');

        self::assertInstanceOf(Closure::class, InertiaManager::getShared()['lazy']);
    }

    #[Test]
    public function localPropOverridesSharedProp(): void
    {
        InertiaManager::share('mode', 'shared');

        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $response = (new InertiaResponse('Dashboard', ['mode' => 'local'], $request))->toResponse();

        self::assertSame('local', $this->props($response)['mode'], 'shared-then-local precedence preserved');
    }

    // ── X-Inertia-Partial-Except (deny-set) ────────────────────────────

    #[Test]
    public function partialExceptRemovesKeyEvenWhenAlsoInOnly(): void
    {
        // except wins over only: Partial-Data a,b + Partial-Except b → only a.
        $bCalls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'a,b',
            'HTTP_X_INERTIA_PARTIAL_EXCEPT' => 'b',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'a' => fn (): string => 'A',
            'b' => function () use (&$bCalls): string {
                ++$bCalls;

                return 'B';
            },
        ], $request))->toResponse();

        self::assertSame(['a' => 'A'], $this->props($response), 'except removes b even though only listed it');
        self::assertSame(0, $bCalls, 'excepted closure must not run (compute saved)');
    }

    #[Test]
    public function exceptOnlyPartialKeepsEveryPlainKeyButTheBlacklisted(): void
    {
        // No Partial-Data: an except-only partial returns all plain props minus
        // the blacklist.
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_EXCEPT' => 'b',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'a' => fn (): string => 'A',
            'b' => fn (): string => 'B',
            'c' => fn (): string => 'C',
        ], $request))->toResponse();

        self::assertSame(['a' => 'A', 'c' => 'C'], $this->props($response));
    }

    #[Test]
    public function exceptOnlyPartialStillExcludesOptionalProps(): void
    {
        // optional() is opt-in via `only`; an except-only partial never resolves it.
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_EXCEPT' => 'a',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'a' => fn (): string => 'A',
            'stats' => InertiaAdapter::optional(function () use (&$calls): string {
                ++$calls;

                return 'lazy';
            }),
        ], $request))->toResponse();

        $props = $this->props($response);
        self::assertArrayNotHasKey('a', $props, 'blacklisted plain key dropped');
        self::assertArrayNotHasKey('stats', $props, 'optional() stays absent without an explicit only');
        self::assertSame(0, $calls);
    }

    // ── §6 do-not-break invariants + predicate hygiene ───────────────────────

    #[Test]
    public function pageObjectCarriesComponentUrlAndVersion(): void
    {
        // §6 invariant #4 — page payload shape { component, props, url, version }.
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', ['k' => 'v'], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame('Dashboard', $page['component']);
        self::assertSame($request->getRequestUri(), $page['url']);
        self::assertSame('v2', $page['version']);
        self::assertSame(['k' => 'v'], $page['props']);
    }

    #[Test]
    public function htmlBootstrapHexEscapesPropMetacharacters(): void
    {
        // §6 invariant #5 — JSON_HEX_* must neutralize a prop that tries to break
        // out of the <script type="application/json"> block carrying the Inertia
        // page JSON (stored-XSS guard on the v3 SPA bootstrap).
        $request = Request::create('/dashboard', 'GET'); // no X-Inertia → default HTML shell

        $response = (new InertiaResponse('Dashboard', [
            'evil' => '</script><img src=x onerror=alert(1)>&"\'',
        ], $request))->toResponse();
        $body = (string) $response->getContent();

        // The prop's tag delimiters must never appear raw — JSON_HEX_TAG turns '<'
        // and '>' into unicode escapes, so the prop cannot close the
        // <script type="application/json"> block early and inject markup.
        self::assertStringNotContainsString('<img', $body, 'prop tag must be hex-escaped, never raw');
        self::assertStringNotContainsString('onerror=alert(1)>', $body, 'attribute payload stays inert');
        // The only <script …>/</script> pair in the body is the bootstrap's own
        // page-data tag — the prop did not inject a second pair (no breakout).
        self::assertSame(1, substr_count($body, '<script'), 'no injected opening script tag');
        self::assertSame(1, substr_count($body, '</script>'), 'no injected closing script tag');
    }

    #[Test]
    public function numericPropKeyRequestedInPartialResolves(): void
    {
        // Regression: numeric-string keys coerce to int; the (string) cast keeps
        // resolveProps() in lock-step with the wire net so a requested numeric
        // key is not silently dropped.
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => '0',
        ]);

        $response = (new InertiaResponse('Dashboard', ['0' => function () use (&$calls): string {
            ++$calls;

            return 'ZERO';
        }], $request))->toResponse();

        self::assertSame(['0' => 'ZERO'], $this->props($response));
        self::assertSame(1, $calls);
    }

    #[Test]
    public function malformedInertiaHeaderRoutesToHtmlNotPartialJson(): void
    {
        // Regression: a non-conformant `X-Inertia: 0` must route consistently —
        // the branch gate and isPartial() share one predicate, so a malformed
        // header can never take the partial path in one and the HTML path in the
        // other. Plain props stay eager (not partial-filtered).
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => '0',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'a',
        ]);

        $calls = 0;
        $response = (new InertiaResponse('Dashboard', [
            'a' => fn (): string => 'A',
            'b' => function () use (&$calls): string {
                ++$calls;

                return 'B';
            },
        ], $request))->toResponse();

        self::assertNull($response->headers->get('X-Inertia'), 'falls through to the HTML branch');
        self::assertSame(1, $calls, 'plain props resolve eagerly — not partial-filtered by a malformed header');
    }

    #[Test]
    public function plainObjectPropNormalizesViaObjectNormalizer(): void
    {
        // Regression: normalize()'s ObjectNormalizer branch hard-requires
        // symfony/property-access. Without it, the constructor throws a
        // LogicException and every non-JsonSerializable object prop fatals —
        // the exact "rich domain entities as props" feature the docblock markets.
        $entity = new class {
            public string $name = 'Ada';

            public int $age = 36;
        };

        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $response = (new InertiaResponse('Dashboard', ['user' => $entity], $request))->toResponse();

        self::assertSame(['name' => 'Ada', 'age' => 36], $this->props($response)['user']);
    }

    // ── deferred props ─────────────────────────────────────────────────

    #[Test]
    public function deferPropIsAbsentOnInitialLoadAndAnnouncedUnderItsGroup(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', [
            'teams' => InertiaAdapter::defer(function () use (&$calls): array {
                ++$calls;

                return ['t'];
            }, 'attributes'),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(0, $calls, 'deferred closure must not run on the initial load');
        self::assertArrayNotHasKey('teams', $page['props'], 'deferred prop absent from initial props');
        self::assertSame(['attributes' => ['teams']], $page['deferredProps']);
    }

    #[Test]
    public function deferGroupsAreKeyedByGroupName(): void
    {
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', [
            'teams' => InertiaAdapter::defer(fn (): array => [], 'attributes'),
            'projects' => InertiaAdapter::defer(fn (): array => [], 'attributes'),
            'perms' => InertiaAdapter::defer(fn (): array => []), // default group
        ], $request))->toResponse();

        self::assertSame(
            ['attributes' => ['teams', 'projects'], 'default' => ['perms']],
            $this->page($response)['deferredProps'],
        );
    }

    #[Test]
    public function deferredPropsKeyOmittedWhenNoDeferredProps(): void
    {
        // §6 #4 — the base page shape carries no deferredProps unless one exists.
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Dashboard', ['plain' => 'v'], $request))->toResponse();
        $page = $this->page($response);

        self::assertArrayNotHasKey('deferredProps', $page);
        // §6 #4 — rescuedProps is equally optional, omitted when nothing rescued.
        self::assertArrayNotHasKey('rescuedProps', $page);
    }

    #[Test]
    public function deferPropResolvesOnItsPartialReload(): void
    {
        $calls = 0;
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'teams',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'teams' => InertiaAdapter::defer(function () use (&$calls): array {
                ++$calls;

                return ['a', 'b'];
            }, 'attributes'),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(1, $calls, 'deferred closure resolves on its follow-up partial');
        self::assertSame(['a', 'b'], $page['props']['teams']);
        self::assertArrayNotHasKey('deferredProps', $page, 'no re-announcement on the resolving partial');
    }

    #[Test]
    public function deferRescueDropsPropAndRecordsItInsteadOfFailing(): void
    {
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'risky',
        ]);

        $response = (new InertiaResponse('Dashboard', [
            'risky' => InertiaAdapter::defer(static function (): array {
                throw new RuntimeException('boom');
            }, 'default', rescue: true),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertArrayNotHasKey('risky', $page['props'], 'rescued prop is dropped');
        self::assertSame(['risky'], $page['rescuedProps']);
        self::assertSame(200, $response->getStatusCode(), 'request did not fail');
    }

    #[Test]
    public function deferWithoutRescueLetsTheFailurePropagate(): void
    {
        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'risky',
        ]);

        $response = new InertiaResponse('Dashboard', [
            'risky' => InertiaAdapter::defer(static function (): array {
                throw new RuntimeException('boom');
            }),
        ], $request);

        $this->expectException(RuntimeException::class);
        $response->toResponse();
    }

    #[Test]
    public function deferRescueDoesNotSwallowSerializationFailures(): void
    {
        // rescue scopes to the resolution callback only. A value that resolves
        // fine but throws while serializing is a real defect — it must propagate,
        // not be masked as a benign rescued prop.
        $entity = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new RuntimeException('serialize boom');
            }
        };

        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'risky',
        ]);

        $response = new InertiaResponse('Dashboard', [
            'risky' => InertiaAdapter::defer(static fn (): object => $entity, 'default', rescue: true),
        ], $request);

        $this->expectException(RuntimeException::class);
        $response->toResponse();
    }

    #[Test]
    public function reRenderingDoesNotAccumulateDeferredMetadata(): void
    {
        // resolveProps() resets its per-call metadata, so a second render of the
        // same instance must not double the announced groups.
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $response = new InertiaResponse('Dashboard', [
            'teams' => InertiaAdapter::defer(fn (): array => [], 'attributes'),
        ], $request);

        $first = $this->page($response->toResponse());
        $second = $this->page($response->toResponse());

        self::assertSame(['attributes' => ['teams']], $first['deferredProps']);
        self::assertSame($first['deferredProps'], $second['deferredProps'], 'reset prevents stale accumulation');
    }

    // ── Inertia v3: merge props ──────────────────────────────────────────────

    #[Test]
    public function mergePropResolvesEagerlyAndIsAnnouncedUnderMergeProps(): void
    {
        $request = Request::create('/feed', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Feed', [
            'posts' => InertiaAdapter::merge(['a', 'b']),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(['a', 'b'], $page['props']['posts'], 'merge prop resolves and is present on every render');
        self::assertSame(['posts'], $page['mergeProps']);
        self::assertArrayNotHasKey('deepMergeProps', $page);
    }

    #[Test]
    public function mergePropResolvesAClosureValue(): void
    {
        $request = Request::create('/feed', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Feed', [
            'posts' => InertiaAdapter::merge(fn (): array => ['x']),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(['x'], $page['props']['posts']);
        self::assertSame(['posts'], $page['mergeProps']);
    }

    #[Test]
    public function deepMergePropAnnouncedUnderDeepMergeProps(): void
    {
        $request = Request::create('/feed', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Feed', [
            'filters' => InertiaAdapter::deepMerge(['sort' => 'new']),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(['sort' => 'new'], $page['props']['filters']);
        self::assertSame(['filters'], $page['deepMergeProps']);
        self::assertArrayNotHasKey('mergeProps', $page);
    }

    #[Test]
    public function mergeMatchOnEmittedAsMatchPropsOn(): void
    {
        $request = Request::create('/feed', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Feed', [
            'posts' => InertiaAdapter::merge([['id' => 1]], matchOn: ['id']),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(['posts'], $page['mergeProps']);
        self::assertSame(['posts.id'], $page['matchPropsOn'], 'matchOn is namespaced under the prop key');
    }

    #[Test]
    public function resetHeaderExcludesKeyFromMergeAnnouncementButKeepsValue(): void
    {
        // X-Inertia-Reset opts the key out of merging for this response: the value
        // still resolves, but the client replaces it instead of appending.
        $request = Request::create('/feed', 'GET', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_RESET' => 'posts',
        ]);

        $response = (new InertiaResponse('Feed', [
            'posts' => InertiaAdapter::merge(['a']),
        ], $request))->toResponse();
        $page = $this->page($response);

        self::assertSame(['a'], $page['props']['posts'], 'value still resolved');
        self::assertArrayNotHasKey('mergeProps', $page, 'reset opts the key out of the merge announcement');
    }

    #[Test]
    public function mergeKeysOmittedWhenNoMergeProps(): void
    {
        // §6 #4 — base page shape carries no merge metadata unless a merge prop exists.
        $request = Request::create('/feed', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $response = (new InertiaResponse('Feed', ['plain' => 'v'], $request))->toResponse();
        $page = $this->page($response);

        self::assertArrayNotHasKey('mergeProps', $page);
        self::assertArrayNotHasKey('deepMergeProps', $page);
        self::assertArrayNotHasKey('matchPropsOn', $page);
    }

    /**
     * Decode the full page object from a JSON (X-Inertia) response body.
     *
     * @return array<string, mixed>
     */
    private function page(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decode the props map from a JSON (X-Inertia) response body.
     *
     * @return array<string, mixed>
     */
    private function props(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? ($decoded['props'] ?? []) : [];
    }
}
