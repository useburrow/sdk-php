<?php

declare(strict_types=1);

namespace Burrow\Sdk\Contracts;

final class FormsContractWizardHelpers
{
    public static function labelToCanonicalKey(string $label): string
    {
        return ReservedCanonicalKeys::labelToCanonicalKey($label);
    }

    /**
     * @return array{key:string,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizeCanonicalKey(string $rawKey): array
    {
        return ReservedCanonicalKeys::sanitizeCanonicalKey($rawKey);
    }

    /**
     * @param array<string,mixed> $mapping
     * @return array{mapping:array<string,mixed>,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizeFieldMapping(array $mapping): array
    {
        $warnings = [];
        $sanitized = $mapping;

        if (!isset($mapping['canonicalKey']) || !is_string($mapping['canonicalKey'])) {
            return ['mapping' => $sanitized, 'warnings' => $warnings];
        }

        $result = ReservedCanonicalKeys::sanitizeCanonicalKey($mapping['canonicalKey']);
        $sanitized['canonicalKey'] = $result['key'];
        foreach ($result['warnings'] as $warning) {
            $warnings[] = $warning;
        }

        return ['mapping' => $sanitized, 'warnings' => $warnings];
    }

    /**
     * @param list<array<string,mixed>> $formsContracts
     * @return array{formsContracts:list<array<string,mixed>>,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizeFormsContracts(array $formsContracts): array
    {
        $warnings = [];
        $sanitizedContracts = [];

        foreach ($formsContracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }

            $next = $contract;
            $fieldMappings = is_array($contract['fieldMappings'] ?? null) ? $contract['fieldMappings'] : [];
            $sanitizedMappings = [];

            foreach ($fieldMappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }

                $result = self::sanitizeFieldMapping($mapping);
                $sanitizedMappings[] = $result['mapping'];
                foreach ($result['warnings'] as $warning) {
                    $warnings[] = $warning;
                }
            }

            $next['fieldMappings'] = $sanitizedMappings;
            $sanitizedContracts[] = $next;
        }

        return ['formsContracts' => $sanitizedContracts, 'warnings' => $warnings];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{payload:array<string,mixed>,warnings:list<array{code:string,message:string,originalKey:string,sanitizedKey:string}>}
     */
    public static function sanitizeFormsContractSubmissionPayload(array $payload): array
    {
        $warnings = [];
        $sanitized = $payload;

        if (!isset($payload['formsContracts']) || !is_array($payload['formsContracts'])) {
            return ['payload' => $sanitized, 'warnings' => $warnings];
        }

        $result = self::sanitizeFormsContracts($payload['formsContracts']);
        $sanitized['formsContracts'] = $result['formsContracts'];
        foreach ($result['warnings'] as $warning) {
            $warnings[] = $warning;
        }

        return ['payload' => $sanitized, 'warnings' => $warnings];
    }
}
