<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Dto;

use JsonSerializable;
use Middag\Framework\Shared\Contract\DtoInterface;
use Middag\Framework\Shared\Dto\AbstractDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDto::class)]
final class AbstractDtoTest extends TestCase
{
    #[Test]
    public function concreteDtoCanBeInstantiated(): void
    {
        $dto = new class extends AbstractDto {
            public function __construct(
                public string $name = 'test',
                public int $value = 42,
            ) {}

            public function toArray(): array
            {
                return [
                    'name' => $this->name,
                    'value' => $this->value,
                ];
            }
        };

        $this->assertSame('test', $dto->name);
        $this->assertSame(42, $dto->value);
    }

    #[Test]
    public function jsonSerializeDelegatesToToArray(): void
    {
        $dto = new class extends AbstractDto {
            public function __construct(
                public string $foo = 'bar',
            ) {}

            public function toArray(): array
            {
                return ['foo' => $this->foo];
            }
        };

        $this->assertSame(['foo' => 'bar'], $dto->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeProducesValidJson(): void
    {
        $dto = new class extends AbstractDto {
            public function __construct(
                public string $key = 'value',
                public int $num = 10,
            ) {}

            public function toArray(): array
            {
                return ['key' => $this->key, 'num' => $this->num];
            }
        };

        $json = json_encode($dto);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertSame('value', $decoded['key']);
        $this->assertSame(10, $decoded['num']);
    }

    #[Test]
    public function implementsDtoInterface(): void
    {
        $dto = new class extends AbstractDto {
            public function toArray(): array
            {
                return [];
            }
        };

        $this->assertInstanceOf(DtoInterface::class, $dto);
        $this->assertInstanceOf(JsonSerializable::class, $dto);
    }
}
