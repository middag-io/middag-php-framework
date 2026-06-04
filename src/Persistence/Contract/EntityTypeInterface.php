<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

/**
 * Describes a registered entity type.
 *
 * @api
 */
interface EntityTypeInterface
{
    /** Unique type identifier (e.g. 'widget', 'user'). */
    public function getKey(): string;

    /** Fully-qualified class name of the entity. */
    public function getEntityClass(): string;

    /**
     * Label for UI display. Implementations default to the key when no explicit
     * label is set, so this never returns an empty string.
     */
    public function getLabel(): string;
}
