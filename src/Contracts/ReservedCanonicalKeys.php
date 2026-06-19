<?php

declare(strict_types=1);

namespace Burrow\Sdk\Contracts;

final class ReservedCanonicalKeys
{
    public const FEED_PREFIX = 'feed_';

    /** @var list<string> */
    public const ENVELOPE = [
        'organizationId',
        'clientId',
        'projectId',
        'integrationId',
        'projectSourceId',
        'clientSourceId',
        'channel',
        'event',
        'timestamp',
        'source',
        'description',
        'icon',
        'schemaVersion',
        'isLifecycle',
        'entityType',
        'externalEntityId',
        'externalEventId',
        'state',
        'stateChangedAt',
        'properties',
        'tags',
    ];

    /** @var list<string> */
    public const FORMS = [
        'submissionId',
        'submittedAt',
        'formName',
        'formHandle',
        'formId',
        'externalFormId',
        'provider',
        'contractId',
        'countOnly',
        'mode',
        'enabled',
    ];

    /** @var list<string> */
    public const ECOMMERCE = [
        'orderId',
        'orderTotal',
        'total',
        'currency',
        'itemCount',
        'tax',
        'subtotal',
        'shippingTotal',
        'shipping',
        'shippingMethod',
        'productId',
        'productName',
        'quantity',
        'unitPrice',
        'lineTotal',
        'cartTotal',
        'cartItemCount',
        'variantName',
        'failureReason',
        'paymentMethod',
        'minutesSinceCheckout',
        'minutesSinceLastActivity',
        'minutesSinceAbandonment',
        'originalCartTotal',
        'customerToken',
        'isGuest',
        'orderSequence',
        'isNewCustomer',
        'shippingCountry',
        'shippingRegion',
        'couponCode',
        'category',
        'productType',
        'isBackfill',
    ];

    /** @var list<string> */
    public const CONTRACT_METADATA = [
        'externalFieldId',
        'sourceLabel',
        'target',
        'dataType',
        'reportable',
        'displayLabelOverride',
        'canonicalKey',
        'fieldMappings',
        'formsContracts',
    ];

    /** @var array<string, true> */
    private const LOOKUP = self::buildLookup();

    public static function isReserved(string $key): bool
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, self::FEED_PREFIX)) {
            return false;
        }

        return isset(self::LOOKUP[strtolower($trimmed)]);
    }

    /**
     * @return array{key:string,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizeCanonicalKey(string $rawKey): array
    {
        $warnings = [];
        $trimmed = trim($rawKey);
        if ($trimmed === '') {
            return [
                'key' => 'field',
                'warnings' => [[
                    'code' => 'EMPTY_CANONICAL_KEY',
                    'message' => 'Canonical key was empty; defaulted to "field".',
                    'originalKey' => $rawKey,
                    'sanitizedKey' => 'field',
                ]],
            ];
        }

        $key = self::normalizeIdentifier($trimmed);
        if ($key !== $trimmed) {
            $warnings[] = [
                'code' => 'INVALID_CANONICAL_KEY_NORMALIZED',
                'message' => sprintf('Canonical key "%s" was normalized to "%s".', $rawKey, $key),
                'originalKey' => $rawKey,
                'sanitizedKey' => $key,
            ];
        }

        if (str_starts_with($key, self::FEED_PREFIX)) {
            return ['key' => $key, 'warnings' => $warnings];
        }

        if (!self::isReserved($key)) {
            return ['key' => $key, 'warnings' => $warnings];
        }

        $sanitized = self::FEED_PREFIX . $key;
        $warnings[] = [
            'code' => 'RESERVED_CANONICAL_KEY_PREFIXED',
            'message' => sprintf(
                'Canonical key "%s" is reserved by Burrow event feeds; prefixed with "%s" as "%s".',
                $key,
                self::FEED_PREFIX,
                $sanitized
            ),
            'originalKey' => $key,
            'sanitizedKey' => $sanitized,
        ];

        return ['key' => $sanitized, 'warnings' => $warnings];
    }

    /**
     * @param array<string,mixed> $map
     * @return array{map:array<string,mixed>,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizePropertyAndTagKeys(array $map): array
    {
        $sanitized = [];
        $warnings = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $result = self::sanitizeCanonicalKey($key);
            $sanitizedKey = $result['key'];
            if (array_key_exists($sanitizedKey, $sanitized)) {
                $warnings[] = [
                    'code' => 'DUPLICATE_CANONICAL_KEY',
                    'message' => sprintf(
                        'Duplicate canonical key "%s" after sanitization; later value retained.',
                        $sanitizedKey
                    ),
                    'originalKey' => $key,
                    'sanitizedKey' => $sanitizedKey,
                ];
            }
            $sanitized[$sanitizedKey] = $value;
            foreach ($result['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }

        return ['map' => $sanitized, 'warnings' => $warnings];
    }

    public static function normalizeIdentifier(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^[a-z][a-zA-Z0-9]*$/', $trimmed) === 1) {
            return $trimmed;
        }

        return self::labelToCanonicalKey($trimmed);
    }

    public static function labelToCanonicalKey(string $label): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', trim($label)) ?? '';
        $words = preg_split('/\s+/', strtolower(trim($normalized))) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        if ($words === []) {
            return 'field';
        }

        $first = array_shift($words);
        if ($first === null) {
            return 'field';
        }

        $result = $first;
        foreach ($words as $word) {
            $result .= ucfirst($word);
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private static function buildLookup(): array
    {
        $lookup = [];
        foreach ([self::ENVELOPE, self::FORMS, self::ECOMMERCE, self::CONTRACT_METADATA] as $group) {
            foreach ($group as $key) {
                $lookup[strtolower($key)] = true;
            }
        }

        return $lookup;
    }
}
