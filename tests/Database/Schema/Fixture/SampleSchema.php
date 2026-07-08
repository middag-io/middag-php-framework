<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Database\Schema\Fixture;

use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Index;
use Middag\Framework\Database\Attribute\Key;
use Middag\Framework\Database\Attribute\Table;

/**
 * Exercises every reader branch: sequence PK, char with default+comment, text
 * without length, decimal with decimals, nullable FK column, primary/foreign/
 * foreign-unique keys, composite and unique indexes.
 *
 * @internal
 */
#[Table('sample_table', comment: 'A sample table')]
#[Column('id', 'int', length: 10, notnull: true, sequence: true)]
#[Column('label', 'char', length: 100, notnull: true, default: 'draft', comment: 'The label')]
#[Column('body', 'text', notnull: false, comment: 'Body text')]
#[Column('score', 'decimal', length: 10, notnull: false, decimals: 2)]
#[Column('ownerid', 'int', length: 10, notnull: false, comment: 'Owner')]
#[Key('primary', ['id'], name: 'primary')]
#[Key('foreign', ['ownerid'], name: 'ownerid', reftable: 'user', reffields: ['id'])]
#[Key('foreign-unique', ['label'], name: 'label', reftable: 'other', reffields: ['id'])]
#[Index('labelscore', ['label', 'score'])]
#[Index('body_idx', ['body'], unique: true)]
final class SampleSchema {}
