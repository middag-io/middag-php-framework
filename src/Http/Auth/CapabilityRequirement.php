<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Auth;

use InvalidArgumentException;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;

/**
 * Rich capability requirement carried by #[Auth] until a host adapter resolves it.
 *
 * @api
 */
final readonly class CapabilityRequirement
{
    /**
     * @param null|class-string<CapabilityDefinitionInterface> $definitionClass
     * @param array<string, mixed>                             $options
     */
    public function __construct(
        public ?CapabilityReference $reference = null,
        public ?string $definitionClass = null,
        public ?CapabilityDefinitionInterface $definition = null,
        public array $options = [],
    ) {
        if (!$this->reference instanceof CapabilityReference && $this->definitionClass === null && !$this->definition instanceof CapabilityDefinitionInterface) {
            throw new InvalidArgumentException('CapabilityRequirement requires a reference, definition class, or definition object.');
        }
    }

    /**
     * @param CapabilityDefinitionInterface|CapabilityReference|class-string<CapabilityDefinitionInterface>|self|string $capability
     * @param array<string, mixed>                                                                                      $options
     */
    public static function from(
        CapabilityDefinitionInterface|CapabilityReference|self|string $capability,
        array $options = [],
    ): self {
        if ($capability instanceof self) {
            return $options === [] ? $capability : $capability->withOptions($options);
        }

        if ($capability instanceof CapabilityReference) {
            return new self(reference: $capability, options: $options);
        }

        if ($capability instanceof CapabilityDefinitionInterface) {
            return new self(
                reference: $capability->capabilityReference(),
                definition: $capability,
                options: array_replace($capability->capabilityOptions(), $options),
            );
        }

        if (is_a($capability, CapabilityDefinitionInterface::class, true)) {
            return new self(definitionClass: $capability, options: $options);
        }

        return new self(reference: new CapabilityReference($capability), options: $options);
    }

    /**
     * @param iterable<CapabilityDefinitionInterface|CapabilityReference|class-string<CapabilityDefinitionInterface>|self|string> $capabilities
     *
     * @return list<self>
     */
    public static function listFrom(iterable $capabilities): array
    {
        $requirements = [];

        foreach ($capabilities as $capability) {
            $requirements[] = self::from($capability);
        }

        return $requirements;
    }

    public function key(): ?string
    {
        return $this->reference?->key;
    }

    public function legacyCapability(): ?string
    {
        return $this->reference?->key;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function withOptions(array $options): self
    {
        return new self(
            reference: $this->reference,
            definitionClass: $this->definitionClass,
            definition: $this->definition,
            options: array_replace($this->options, $options),
        );
    }
}
