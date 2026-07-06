<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Translation\Resolver;

use Middag\Framework\Translation\Resolver\ViolationKeyMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ViolationKeyMap::class)]
final class ViolationKeyMapTest extends TestCase
{
    #[Test]
    public function derivesShortKeyFromConstraintShortName(): void
    {
        $validator = Validation::createValidator();
        $map = new ViolationKeyMap();

        self::assertSame('validation.not_blank', $map->keyFor($validator->validate('', [new Assert\NotBlank()])[0]));
        self::assertSame('validation.type', $map->keyFor($validator->validate('x', [new Assert\Type('int')])[0]));
        self::assertSame('validation.email', $map->keyFor($validator->validate('nope', [new Assert\Email()])[0]));
    }

    #[Test]
    public function refinesMultiOutcomeConstraintsByCode(): void
    {
        $validator = Validation::createValidator();
        $map = new ViolationKeyMap();

        self::assertSame('validation.length.too_short', $map->keyFor($validator->validate('a', [new Assert\Length(min: 3)])[0]));
        self::assertSame('validation.length.too_long', $map->keyFor($validator->validate('abcd', [new Assert\Length(max: 2)])[0]));
        self::assertSame('validation.range.too_low', $map->keyFor($validator->validate(1, [new Assert\Range(min: 5)])[0]));
    }

    #[Test]
    public function fallsBackWhenTheViolationCarriesNoConstraint(): void
    {
        $map = new ViolationKeyMap();
        // A raw violation with no attached constraint (constraint arg omitted → null).
        $violation = new ConstraintViolation('bad', null, [], null, null, null);

        self::assertSame('validation.invalid', $map->keyFor($violation));
    }
}
