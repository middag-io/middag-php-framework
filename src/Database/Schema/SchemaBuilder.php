<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Schema;

use InvalidArgumentException;
use RuntimeException;

/**
 * Loads and provides access to PHP schema descriptors from db/schema/*.php.
 *
 * Each descriptor is a plain PHP file returning an array with keys:
 *   name, comment, columns, keys, indexes.
 *
 * Platform adapters (Moodle xmldb, WordPress dbDelta) consume the
 * descriptors to generate platform-specific DDL.
 *
 * @api
 */
class SchemaBuilder
{
    /** @var array<string, array<string, mixed>> table_name → descriptor */
    private array $schemas = [];

    private ?SchemaAttributeReader $reader = null;

    /**
     * Load all *.php descriptor files from a directory.
     *
     * @param string $schemaDir absolute path to the directory containing descriptor files
     *
     * @throws RuntimeException when the directory does not exist or is unreadable
     */
    public function loadFromDirectory(string $schemaDir): static
    {
        if (!is_dir($schemaDir) || !is_readable($schemaDir)) {
            throw new RuntimeException('Schema directory not found or unreadable: ' . $schemaDir);
        }

        $files = glob(rtrim($schemaDir, '/') . '/*.php');

        // glob() only returns false on system-level failure; with the
        // directory already validated readable above there is no way to
        // force this branch from a test (R-05).
        // @codeCoverageIgnoreStart
        if ($files === false) {
            return $this;
        }
        // @codeCoverageIgnoreEnd

        foreach ($files as $file) {
            $descriptor = require $file;

            if (!is_array($descriptor) || !isset($descriptor['name'])) {
                throw new RuntimeException('Invalid schema descriptor in: ' . $file);
            }

            $this->schemas[$descriptor['name']] = $descriptor;
        }

        return $this;
    }

    /**
     * Register a single descriptor array directly.
     *
     * @param array<string, mixed> $descriptor
     *
     * @throws InvalidArgumentException when descriptor is missing the 'name' key
     */
    public function register(array $descriptor): static
    {
        if (!isset($descriptor['name']) || $descriptor['name'] === '') {
            throw new InvalidArgumentException('Schema descriptor must have a non-empty "name" key.');
        }

        $this->schemas[$descriptor['name']] = $descriptor;

        return $this;
    }

    /**
     * Register a descriptor authored as a schema-attribute class.
     *
     * Reflects the class's `#[Table]`/`#[Column]`/`#[Key]`/`#[Index]` attributes
     * into a descriptor array via {@see SchemaAttributeReader} and registers it.
     * The typed authoring counterpart of {@see self::register()}.
     *
     * @param class-string $class
     *
     * @throws InvalidArgumentException when the class carries no `#[Table]`
     */
    public function registerClass(string $class): static
    {
        return $this->register($this->reader()->read($class));
    }

    /**
     * Register every schema-attribute class in the given list.
     *
     * The typed authoring counterpart of {@see self::loadFromDirectory()}.
     *
     * @param iterable<class-string> $classes
     *
     * @throws InvalidArgumentException when a class carries no `#[Table]`
     */
    public function loadFromClasses(iterable $classes): static
    {
        foreach ($classes as $class) {
            $this->registerClass($class);
        }

        return $this;
    }

    /**
     * Return the descriptor for a named table, or null when not found.
     *
     * @return null|array<string, mixed>
     */
    public function table(string $name): ?array
    {
        return $this->schemas[$name] ?? null;
    }

    /**
     * Return all registered table names.
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * Return all registered descriptors indexed by table name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->schemas;
    }

    /**
     * Check whether a table descriptor is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->schemas[$name]);
    }

    /**
     * Return the column list for a named table, or an empty array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(string $table): array
    {
        return $this->schemas[$table]['columns'] ?? [];
    }

    /**
     * Return the keys (primary + foreign) for a named table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function keys(string $table): array
    {
        return $this->schemas[$table]['keys'] ?? [];
    }

    /**
     * Return the index definitions for a named table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function indexes(string $table): array
    {
        return $this->schemas[$table]['indexes'] ?? [];
    }

    private function reader(): SchemaAttributeReader
    {
        return $this->reader ??= new SchemaAttributeReader();
    }
}
