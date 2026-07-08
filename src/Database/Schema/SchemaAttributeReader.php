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
use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Index;
use Middag\Framework\Database\Attribute\Key;
use Middag\Framework\Database\Attribute\Table;
use ReflectionClass;

/**
 * Builds a platform-agnostic schema descriptor from a class's schema attributes.
 *
 * Reflects the class-level `#[Table]` plus its repeatable `#[Column]`, `#[Key]`
 * and `#[Index]` attributes into the same `array<string,mixed>` descriptor that
 * {@see SchemaBuilder::register()} and the DDL adapters already consume — so the
 * typed attribute objects are the only new layer and everything downstream
 * (adapters, migration runners, renderers) stays on the array wire format.
 *
 * Optional fields are emitted only when set (a text column omits `length`, a
 * column without a comment omits `comment`), mirroring the sparsity of the
 * hand-written descriptor files. Results are cached per class, so reflection
 * runs once.
 *
 * @api
 */
final class SchemaAttributeReader
{
    /** @var array<class-string, array<string, mixed>> */
    private array $cache = [];

    /**
     * Read the descriptor for a `#[Table]`-annotated class.
     *
     * @param class-string $class
     *
     * @return array<string, mixed> descriptor in `SchemaBuilder::register()` shape
     *
     * @throws InvalidArgumentException when the class carries no `#[Table]`
     */
    public function read(string $class): array
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $reflection = new ReflectionClass($class);
        $tableAttributes = $reflection->getAttributes(Table::class);

        if ($tableAttributes === []) {
            throw new InvalidArgumentException(sprintf(
                'Schema class "%s" must declare a #[Table] attribute.',
                $class,
            ));
        }

        $table = $tableAttributes[0]->newInstance();

        $descriptor = ['name' => $table->name];

        if ($table->comment !== null) {
            $descriptor['comment'] = $table->comment;
        }

        $descriptor['columns'] = $this->columns($reflection);
        $descriptor['keys'] = $this->keys($reflection);
        $descriptor['indexes'] = $this->indexes($reflection);

        return $this->cache[$class] = $descriptor;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<array<string, mixed>>
     */
    private function columns(ReflectionClass $reflection): array
    {
        $columns = [];

        foreach ($reflection->getAttributes(Column::class) as $attribute) {
            $column = $attribute->newInstance();

            $entry = ['name' => $column->name, 'type' => $column->type];

            if ($column->length !== null) {
                $entry['length'] = $column->length;
            }

            $entry['notnull'] = $column->notnull;
            $entry['sequence'] = $column->sequence;

            if ($column->default !== null) {
                $entry['default'] = $column->default;
            }

            if ($column->comment !== null) {
                $entry['comment'] = $column->comment;
            }

            if ($column->decimals !== null) {
                $entry['decimals'] = $column->decimals;
            }

            $columns[] = $entry;
        }

        return $columns;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<array<string, mixed>>
     */
    private function keys(ReflectionClass $reflection): array
    {
        $keys = [];

        foreach ($reflection->getAttributes(Key::class) as $attribute) {
            $key = $attribute->newInstance();

            $entry = ['name' => $key->name, 'type' => $key->type, 'fields' => $key->fields];

            if ($key->reftable !== null) {
                $entry['reftable'] = $key->reftable;
            }

            if ($key->reffields !== null) {
                $entry['reffields'] = $key->reffields;
            }

            $keys[] = $entry;
        }

        return $keys;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<array<string, mixed>>
     */
    private function indexes(ReflectionClass $reflection): array
    {
        $indexes = [];

        foreach ($reflection->getAttributes(Index::class) as $attribute) {
            $index = $attribute->newInstance();

            $indexes[] = ['name' => $index->name, 'unique' => $index->unique, 'fields' => $index->fields];
        }

        return $indexes;
    }
}
