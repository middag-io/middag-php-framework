<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Security\Enum;

/**
 * How an HMAC signature is encoded on the wire. Different webhook providers
 * pick different encodings (Stripe/Jira ship lowercase hex; HubSpot v3 ships
 * base64), so the verifier is told which to produce and compare.
 *
 * @api
 */
enum SignatureEncoding: string
{
    case Hex = 'hex';

    case Base64 = 'base64';
}
