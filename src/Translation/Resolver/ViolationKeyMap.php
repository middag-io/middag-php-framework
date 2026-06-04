<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Translation\Resolver;

use ReflectionClass;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Maps a Symfony constraint violation to a short, stable dotted translation key
 * in the `validators` domain — `validation.not_blank`, `validation.type`,
 * `validation.length.too_short`, … Host-friendly (`get_string(key, 'validators')`)
 * and usable as a react-intl / i18next message id.
 *
 * The key is derived from the constraint's class short-name; constraints that
 * report several distinct conditions (Length, Range, Count) are refined by the
 * violation's error code.
 *
 * @api
 */
final class ViolationKeyMap
{
    /**
     * Error-code → refined sub-key, for constraints with more than one outcome.
     *
     * @var array<string, string>
     */
    private const array CODE_REFINEMENTS = [
        Assert\Length::TOO_SHORT_ERROR => 'length.too_short',
        Assert\Length::TOO_LONG_ERROR => 'length.too_long',
        Assert\Range::TOO_LOW_ERROR => 'range.too_low',
        Assert\Range::TOO_HIGH_ERROR => 'range.too_high',
        Assert\Count::TOO_FEW_ERROR => 'count.too_few',
        Assert\Count::TOO_MANY_ERROR => 'count.too_many',
    ];

    public function keyFor(ConstraintViolationInterface $violation): string
    {
        $code = $violation->getCode();

        if ($code !== null && isset(self::CODE_REFINEMENTS[$code])) {
            return 'validation.' . self::CODE_REFINEMENTS[$code];
        }

        $constraint = $violation->getConstraint();

        if ($constraint === null) {
            return 'validation.invalid';
        }

        return 'validation.' . $this->snake((new ReflectionClass($constraint))->getShortName());
    }

    private function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
