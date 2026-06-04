<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use DateTimeImmutable;
use Middag\Framework\Logging\Processor\RedactionProcessor;
use Middag\Framework\Logging\Redactor\DefaultSecretRedactor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RedactionProcessor::class)]
#[CoversClass(DefaultSecretRedactor::class)]
final class RedactionProcessorTest extends TestCase
{
    public function testRedactsKnownSensitiveKeysCaseInsensitiveAndNested(): void
    {
        $redactor = new DefaultSecretRedactor();

        $clean = $redactor->redact([
            'user' => 'paulo',
            'password' => 'hunter2',
            'API_KEY' => 'abc',
            'payload' => [
                'authorizationHeader' => 'Bearer xyz',
                'amount' => 100,
            ],
        ]);

        self::assertSame('paulo', $clean['user']);
        self::assertSame(DefaultSecretRedactor::REDACTED, $clean['password']);
        self::assertSame(DefaultSecretRedactor::REDACTED, $clean['API_KEY']);
        self::assertSame(DefaultSecretRedactor::REDACTED, $clean['payload']['authorizationHeader']);
        self::assertSame(100, $clean['payload']['amount']);
    }

    public function testAdditionalKeysExtendTheDefaultList(): void
    {
        $redactor = new DefaultSecretRedactor(['pin']);

        $clean = $redactor->redact(['pin' => '1234', 'token' => 't']);

        self::assertSame(DefaultSecretRedactor::REDACTED, $clean['pin']);
        self::assertSame(DefaultSecretRedactor::REDACTED, $clean['token']);
    }

    public function testRedactIsPureAndLeavesInputUntouched(): void
    {
        $redactor = new DefaultSecretRedactor();
        $input = ['password' => 'secret'];

        $redactor->redact($input);

        self::assertSame('secret', $input['password'], 'input array must not be mutated');
    }

    public function testProcessorRewritesOnlyTheContext(): void
    {
        $processor = new RedactionProcessor(new DefaultSecretRedactor());

        $record = new LogRecord(
            new DateTimeImmutable(),
            'core/system',
            Level::Info,
            'login attempt',
            ['user' => 'paulo', 'password' => 'hunter2'],
            ['origin' => 'cli'],
        );

        $out = $processor($record);

        self::assertSame('paulo', $out->context['user']);
        self::assertSame(DefaultSecretRedactor::REDACTED, $out->context['password']);
        self::assertSame('login attempt', $out->message);
        self::assertSame(['origin' => 'cli'], $out->extra);
    }
}
