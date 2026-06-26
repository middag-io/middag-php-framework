<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Contract;

/**
 * Contract for module loaders.
 *
 * @api
 */
interface ModuleLoaderInterface extends LoaderInterface
{
    /**
     * Return all module definitions discovered by the loader.
     *
     * The returned array MUST be normalized:
     *
     * [
     *   [
     *      'class'    => string FQCN implementing ModuleInterface,
     *      'slug'     => string identifier (directory name),
     *      'group'    => string grouping category,
     *      'priority' => int load order,
     *      'hidden'   => bool
     *   ],
     *   ...
     * ]
     *
     * @return array<int, array{
     *     class: string,
     *     slug: string,
     *     group: string,
     *     priority: int,
     *     hidden: bool
     * }>
     */
    public function getDefinitions(): array;
}
