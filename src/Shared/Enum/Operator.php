<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Enum;

/**
 * SQL operators supported by the MIDDAG Query Builder.
 *
 * This enum defines all comparison and logical operators that can be used
 * when building SQL WHERE clauses through the query builder. Each operator
 * is mapped to its SQL representation and handles specific value types.
 *
 * @api
 */
enum Operator: string
{
    /**
     * Equality operator (=).
     *
     * Compares if a column value equals the provided value.
     * Usage: WHERE column = value
     * Accepts: scalar values (string, int, float, bool)
     */
    case Eq = '=';

    /**
     * Inequality operator (<>).
     *
     * Compares if a column value is different from the provided value.
     * Usage: WHERE column <> value
     * Accepts: scalar values (string, int, float, bool)
     */
    case Neq = '<>';

    /**
     * Greater than operator (>).
     *
     * Compares if a column value is greater than the provided value.
     * Usage: WHERE column > value
     * Accepts: numeric or comparable values (int, float, string for dates)
     */
    case Gt = '>';

    /**
     * Greater than or equal operator (>=).
     *
     * Compares if a column value is greater than or equal to the provided value.
     * Usage: WHERE column >= value
     * Accepts: numeric or comparable values (int, float, string for dates)
     */
    case Gte = '>=';

    /**
     * Less than operator (<).
     *
     * Compares if a column value is less than the provided value.
     * Usage: WHERE column < value
     * Accepts: numeric or comparable values (int, float, string for dates)
     */
    case Lt = '<';

    /**
     * Less than or equal operator (<=).
     *
     * Compares if a column value is less than or equal to the provided value.
     * Usage: WHERE column <= value
     * Accepts: numeric or comparable values (int, float, string for dates)
     */
    case Lte = '<=';

    /**
     * Pattern matching operator (LIKE).
     *
     * Performs pattern matching using wildcards (% for multiple chars, _ for single char).
     * Usage: WHERE column LIKE '%pattern%'
     * Accepts: string values with optional wildcards
     * Note: Wildcards must be included in the value itself
     */
    case Like = 'LIKE';

    /**
     * Set membership operator (IN).
     *
     * Checks if a column value matches any value in a provided list.
     * Usage: WHERE column IN (value1, value2, value3)
     * Accepts: array of scalar values
     */
    case In = 'IN';

    /**
     * Negated set membership operator (NOT IN).
     *
     * Checks if a column value does not match any value in a provided list.
     * Usage: WHERE column NOT IN (value1, value2, value3)
     * Accepts: array of scalar values
     */
    case NotIn = 'NOT IN';

    /**
     * Range operator (BETWEEN).
     *
     * Checks if a column value falls within a specified range (inclusive).
     * Usage: WHERE column BETWEEN min AND max
     * Accepts: array with exactly two elements [min, max]
     */
    case Between = 'BETWEEN';

    /**
     * NULL comparison operator (IS).
     *
     * Checks if a column value is NULL or a specific boolean value.
     * Usage: WHERE column IS NULL or WHERE column IS TRUE
     * Accepts: NULL, TRUE, FALSE
     * Note: Use this instead of EQ for NULL comparisons
     */
    case Is = 'IS';

    /**
     * Negated NULL comparison operator (IS NOT).
     *
     * Checks if a column value is not NULL or not a specific boolean value.
     * Usage: WHERE column IS NOT NULL or WHERE column IS NOT FALSE
     * Accepts: NULL, TRUE, FALSE
     * Note: Use this instead of NEQ for NULL comparisons
     */
    case IsNot = 'IS NOT';

    /**
     * Raw SQL operator (RAW).
     *
     * Allows insertion of arbitrary custom SQL operations without escaping.
     * This bypasses all query builder safety mechanisms.
     *
     * WARNING: Use with extreme caution — high risk of SQL injection.
     * Should ONLY be used internally when the input is fully trusted and validated.
     * Never use with user-provided input.
     *
     * Usage: Internal use only for complex SQL expressions
     * Accepts: trusted SQL string fragments
     */
    case Raw = 'RAW';

    /**
     * Return the SQL representation of this operator.
     *
     * @return string The SQL operator string
     */
    public function sql(): string
    {
        return $this->value;
    }
}
