<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Persistence\Contract\ConnectionResolverInterface;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Persistence\Relation\BelongsTo;
use Middag\Framework\Persistence\Relation\BelongsToMany;
use Middag\Framework\Persistence\Relation\HasMany;
use Middag\Framework\Persistence\Relation\HasOne;
use Middag\Framework\Persistence\Relation\Relation;
use ReflectionMethod;

/**
 * Vanilla Eloquent-like Active Record base (OSS).
 *
 * A thin, host-agnostic ActiveRecord built on the {@see QueryBuilder} (reads)
 * and the {@see ConnectionAdapterInterface} record helpers (writes). It ships plain
 * attribute fill/cast, fluent finders (find/all/where/first), and a save()
 * that routes to performInsert()/performUpdate().
 *
 * Deliberately gold-free: there are NO domain events here. save() is NOT final
 * and persistence lives in the protected performInsert()/performUpdate() seams
 * so the MIDDAG core can subclass and wrap them with audit/revision/events
 * without the OSS layer ever knowing. External developers get vanilla Eloquent;
 * MIDDAG models get the gold via the core subclass. Same API, gold only in core.
 *
 * @api
 */
abstract class Model implements JsonSerializable
{
    protected static ?ConnectionResolverInterface $resolver = null;

    protected string $table = '';

    protected string $primaryKey = 'id';

    protected bool $incrementing = true;

    /**
     * Auto-manage created_at/updated_at on save. Opt-in: Eloquent defaults
     * true, but the OSS base defaults false so vanilla tables without the
     * columns keep working. Set true on models whose table has both columns.
     */
    protected bool $timestamps = false;

    protected ?string $connectionName = null;

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = [];

    /** @var array<string, string> */
    protected array $casts = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $relations = [];

    protected bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->getAttribute($name);
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if ($this->isRelationMethod($name)) {
            $relation = $this->{$name}();

            if ($relation instanceof Relation) {
                return $this->relations[$name] = $relation->getResults();
            }
        }

        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Forward undefined static calls to a fresh query — this is what lets a
     * local scope `scopeActive()` be invoked as Widget::active().
     *
     * @param array<int, mixed> $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->{$method}(...$parameters); // @phpstan-ignore-line — dynamic local-scope dispatch resolved by ModelQuery::__call
    }

    // ========================================================================
    // CONNECTION RESOLUTION
    // ========================================================================

    public static function setConnectionResolver(?ConnectionResolverInterface $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function setConnection(ConnectionAdapterInterface $connection): void
    {
        self::$resolver = new SingleConnectionResolver($connection);
    }

    public static function getConnectionResolver(): ?ConnectionResolverInterface
    {
        return self::$resolver;
    }

    public function resolveConnection(): ConnectionAdapterInterface
    {
        if (!self::$resolver instanceof ConnectionResolverInterface) {
            throw new LogicException(
                'No connection resolver set. Call Model::setConnection() or Model::setConnectionResolver() first.'
            );
        }

        return self::$resolver->connection($this->connectionName);
    }

    // ========================================================================
    // QUERY ENTRY POINTS
    // ========================================================================

    /**
     * @return ModelQuery<static>
     */
    public function newQuery(): ModelQuery
    {
        return new ModelQuery(QueryBuilder::on($this->resolveConnection(), $this->getTable()), $this); // @phpstan-ignore-line — $this IS static at runtime; phpstan invariance limitation
    }

    /**
     * @return ModelQuery<static>
     */
    public static function query(): ModelQuery
    {
        return (new static())->newQuery(); // @phpstan-ignore-line — called on a concrete subclass
    }

    /**
     * @return ModelQuery<static>
     */
    public static function onConnection(ConnectionAdapterInterface $connection): ModelQuery
    {
        $model = new static(); // @phpstan-ignore-line — called on a concrete subclass

        return new ModelQuery(QueryBuilder::on($connection, $model->getTable()), $model);
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new MiddagNotFoundException(sprintf('%s not found for the given key.', static::class));
        }

        return $model;
    }

    /**
     * @return array<int, static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function first(): ?static
    {
        return static::query()->first();
    }

    /**
     * @return ModelQuery<static>
     */
    public static function where(Closure|string $column, mixed $operator = null, mixed $value = null): ModelQuery
    {
        return match (func_num_args()) {
            1 => static::query()->where($column),
            2 => static::query()->where($column, $operator),
            default => static::query()->where($column, $operator, $value),
        };
    }

    // ========================================================================
    // CREATORS
    // ========================================================================

    /**
     * Mass-assign $attributes and persist a new record.
     *
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes); // @phpstan-ignore-line — concrete subclass at runtime
        $model->save();

        return $model;
    }

    /**
     * First record matching $attributes, or a NEW (unsaved) instance filled
     * with $attributes + $values.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $existing = static::queryWhereAll($attributes)->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        return new static(array_merge($attributes, $values)); // @phpstan-ignore-line — concrete subclass at runtime
    }

    /**
     * First record matching $attributes, or one created from $attributes + $values.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $existing = static::queryWhereAll($attributes)->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Update the first record matching $attributes with $values, or create one
     * from $attributes + $values.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $existing = static::queryWhereAll($attributes)->first();

        if ($existing instanceof Model) {
            $existing->fill($values)->save();

            return $existing;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Eager-load the given relations onto this already-loaded instance.
     */
    public function load(string ...$relations): static
    {
        if ($relations !== []) {
            static::query()->with(...$relations)->eagerLoadRelations([$this]);
        }

        return $this;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    // ========================================================================
    // ATTRIBUTES
    // ========================================================================

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Whitelist/blacklist gate for mass assignment.
     *
     * A non-empty $fillable is a strict allow-list (only listed keys pass). When
     * $fillable is empty the model is mass-assignable by default, except keys in
     * $guarded; setting $guarded to ['*'] locks every attribute.
     */
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->fillable !== []) {
            return false;
        }

        return !in_array($key, $this->guarded, true) && $this->guarded !== ['*'];
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Read an attribute, applying any cast from $casts lazily (never on write).
     *
     * Missing keys return null. Recognised cast tokens: int|integer, float|double|real,
     * bool|boolean, string, datetime|timestamp (DateTimeImmutable); unknown tokens
     * pass the value through unchanged.
     */
    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        return $this->castAttribute($key, $this->attributes[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getTable(): string
    {
        if ($this->table === '') {
            throw new LogicException(sprintf('Model %s must define a non-empty $table.', static::class));
        }

        return $this->table;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // ========================================================================
    // HYDRATION
    // ========================================================================
    /**
     * Build a model instance from a raw persistence row (no events, no casts on
     * write — attributes are stored verbatim and cast lazily on read).
     *
     * @param array<string, mixed> $attributes
     */
    public function newFromBuilder(array $attributes): static
    {
        $model = new static(); // @phpstan-ignore-line — runtime class of $this is concrete
        $model->attributes = $attributes;
        $model->exists = true;

        return $model;
    }

    // ========================================================================
    // PERSISTENCE: save() is intentionally overridable so the core subclass adds the governed gold; the OSS base stays event-free
    // ========================================================================

    public function save(): bool
    {
        return $this->exists ? $this->performUpdate() : $this->performInsert();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $key = $this->requireKey();
        $this->newQuery()->where($this->primaryKey, $key)->delete();
        $this->exists = false;

        return true;
    }

    /**
     * Re-read this record from the database into a NEW instance, or null when
     * it no longer exists (or was never persisted).
     */
    public function fresh(): ?static
    {
        $key = $this->getKey();

        if (!$this->exists || $key === null) {
            return null;
        }

        return static::query()->find($key);
    }

    /**
     * Reload this instance's attributes from the database in place.
     */
    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::query()->find($this->requireKey());

        if ($fresh instanceof Model) {
            $this->attributes = $fresh->getAttributes();
        }

        return $this;
    }

    /**
     * Clone into a new UNSAVED instance, dropping the primary key (and
     * timestamps when managed) plus any extra $except columns.
     *
     * @param list<string> $except
     */
    public function replicate(array $except = []): static
    {
        $drop = array_merge(
            [$this->primaryKey],
            $this->timestamps ? [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()] : [],
            $except,
        );

        $attributes = $this->attributes;
        foreach ($drop as $key) {
            unset($attributes[$key]);
        }

        $clone = new static(); // @phpstan-ignore-line — concrete subclass at runtime
        $clone->attributes = $attributes;
        $clone->exists = false;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        return $result;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // ========================================================================
    // TIMESTAMPS
    // ========================================================================

    public function getCreatedAtColumn(): string
    {
        return 'created_at';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updated_at';
    }

    /**
     * Build a query with every attribute applied as an equality WHERE.
     *
     * @param array<string, mixed> $attributes
     *
     * @return ModelQuery<static>
     */
    protected static function queryWhereAll(array $attributes): ModelQuery
    {
        $query = static::query();

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    // ========================================================================
    // RELATIONSHIPS
    // ========================================================================

    /**
     * @param class-string<Model> $related
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related(); // @phpstan-ignore-line — $related is a concrete Model subclass
        $foreignKey ??= $this->guessForeignKey(static::class, $this->primaryKey);
        $localKey ??= $this->primaryKey;

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @param class-string<Model> $related
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related(); // @phpstan-ignore-line — $related is a concrete Model subclass
        $foreignKey ??= $this->guessForeignKey(static::class, $this->primaryKey);
        $localKey ??= $this->primaryKey;

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @param class-string<Model> $related
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related(); // @phpstan-ignore-line — $related is a concrete Model subclass
        $ownerKey ??= $instance->getKeyName();
        $foreignKey ??= $this->guessForeignKey($related, $ownerKey);

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey);
    }

    /**
     * @param class-string<Model> $related
     */
    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        $instance = new $related(); // @phpstan-ignore-line — $related is a concrete Model subclass
        $parentKey ??= $this->primaryKey;
        $relatedKey ??= $instance->getKeyName();
        $foreignPivotKey ??= $this->guessForeignKey(static::class, $this->primaryKey);
        $relatedPivotKey ??= $this->guessForeignKey($related, $relatedKey);
        $table ??= $this->guessPivotTable(static::class, $related);

        return new BelongsToMany($instance->newQuery(), $this, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    /**
     * @param class-string<Model> $class
     */
    protected function guessForeignKey(string $class, string $key): string
    {
        return $this->snakeShortName($class) . '_' . $key;
    }

    /**
     * @param class-string<Model> $a
     * @param class-string<Model> $b
     */
    protected function guessPivotTable(string $a, string $b): string
    {
        $names = [$this->snakeShortName($a), $this->snakeShortName($b)];
        sort($names);

        return implode('_', $names);
    }

    protected function snakeShortName(string $class): string
    {
        $position = strrpos($class, '\\');
        $short = $position === false ? $class : substr($class, $position + 1);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    protected function performInsert(): bool
    {
        if ($this->timestamps) {
            $now = $this->freshTimestamp();
            $this->attributes[$this->getCreatedAtColumn()] ??= $now;
            $this->attributes[$this->getUpdatedAtColumn()] ??= $now;
        }

        $id = $this->resolveConnection()->insert($this->getTable(), $this->attributesForInsert());

        if ($this->incrementing) {
            $this->attributes[$this->primaryKey] = $id;
        }

        $this->exists = true;

        return true;
    }

    protected function performUpdate(): bool
    {
        if ($this->timestamps) {
            $this->attributes[$this->getUpdatedAtColumn()] = $this->freshTimestamp();
        }

        $key = $this->requireKey();
        $this->newQuery()->where($this->primaryKey, $key)->update($this->attributesForUpdate());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributesForInsert(): array
    {
        $attributes = $this->attributes;

        if ($this->incrementing) {
            unset($attributes[$this->primaryKey]);
        }

        return $this->castAttributesForStorage($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributesForUpdate(): array
    {
        $attributes = $this->attributes;
        unset($attributes[$this->primaryKey]);

        return $this->castAttributesForStorage($attributes);
    }

    protected function freshTimestamp(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    /**
     * A relationship method is a zero-arg, non-static method declared on a
     * subclass (not on Model itself) and not a query scope.
     */
    private function isRelationMethod(string $name): bool
    {
        if ($name === '' || str_starts_with($name, 'scope') || !method_exists($this, $name)) {
            return false;
        }

        $method = new ReflectionMethod($this, $name);

        return $method->getDeclaringClass()->getName() !== self::class
            && !$method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0;
    }

    private function requireKey(): mixed
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new LogicException(sprintf('Cannot persist %s without a "%s" value.', static::class, $this->primaryKey));
        }

        return $key;
    }

    // ========================================================================
    // CASTS & SERIALIZATION
    // ========================================================================

    private function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $cast = $this->casts[$key];

        if (enum_exists($cast)) {
            return $value instanceof $cast ? $value : $cast::from($value); // @phpstan-ignore-line — $cast is a backed enum; $value is its scalar DB value
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => $this->asString($value),
            'datetime', 'timestamp' => $this->asDateTime($value),
            'array', 'json' => $this->asArray($value),
            default => $value,
        };
    }

    private function asString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Cannot cast a non-scalar value to string.');
    }

    private function asDateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value);
        }

        if (is_string($value) && !is_numeric($value)) {
            return new DateTimeImmutable($value);
        }

        if (is_numeric($value)) {
            return (new DateTimeImmutable())->setTimestamp((int) $value);
        }

        throw new InvalidArgumentException('Cannot cast the given value to a datetime.');
    }

    /**
     * @return array<int|string, mixed>
     */
    private function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }

    /**
     * Serialise rich attribute values (backed enums, arrays) to their DB-storable
     * scalar form before insert/update. Plain scalars pass through untouched.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function castAttributesForStorage(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[$key] = $this->castAttributeForStorage($key, $value);
        }

        return $result;
    }

    private function castAttributeForStorage(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
