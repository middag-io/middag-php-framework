<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Mapper;

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Contract\MapperInterface;

/**
 * Abstract base mapper for persistence adapters.
 *
 * Carries no behaviour by design: it is the stable, @template-typed base that
 * downstream mappers (host/core) extend so repositories can type-hint a single
 * class. Concrete mappers implement the {@see MapperInterface} methods.
 *
 * @api
 *
 * @template T of EntityInterface
 *
 * @implements MapperInterface<T>
 *
 * @see MapperInterface
 */
abstract class AbstractMapper implements MapperInterface {}
