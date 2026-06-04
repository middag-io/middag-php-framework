<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Attribute;

use Attribute;
use Middag\Framework\Kernel\ContainerFactory;

/**
 * Marks a discovered service as lazy: the container hands out a ghost proxy and
 * only builds the real object the first time a method is called on it.
 *
 * Use on heavy services that are wired as a constructor dependency of something
 * built early but rarely exercised on a given request, so paying their cost is
 * deferred until actually used. Plain `$container->get()` is already on-demand;
 * `#[Lazy]` additionally defers a service injected into another service.
 *
 * Requires the container to run with a lazy instantiator (the framework wires
 * `LazyServiceInstantiator` in {@see ContainerFactory}). The target must be
 * proxyable: on PHP 8.2/8.3 the ghost is a generated subclass, so mark only
 * non-final classes; PHP 8.4+ uses native lazy objects (which also ghost
 * `final` classes), but keep targets non-final for portability across the
 * supported PHP range.
 *
 * Deliberately shadows Symfony's own `#[Lazy]`: a framework-owned marker keeps a
 * consumer's domain class from importing a `symfony/dependency-injection`
 * attribute (the OSS-stays-agnostic invariant); the proxy machinery is reused,
 * not reimplemented.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Lazy {}
