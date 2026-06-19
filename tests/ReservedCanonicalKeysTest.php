<?php

declare(strict_types=1);

namespace Burrow\Sdk\Tests;

use Burrow\Sdk\Contracts\ReservedCanonicalKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservedCanonicalKeysTest extends TestCase
{
    public function testFeedPrefixConstant(): void
    {
        $this->assertSame('feed_', ReservedCanonicalKeys::FEED_PREFIX);
    }

    #[DataProvider('reservedKeyProvider')]
    public function testDetectsReservedKeys(string $key): void
    {
        $this->assertTrue(ReservedCanonicalKeys::isReserved($key));
    }

    /**
     * @return list<array{0:string}>
     */
    public static function reservedKeyProvider(): array
    {
        return [
            ['channel'],
            ['submissionId'],
            ['formName'],
            ['provider'],
            ['orderId'],
            ['properties'],
        ];
    }

    public function testFeedPrefixedKeysAreNotReserved(): void
    {
        $this->assertFalse(ReservedCanonicalKeys::isReserved('feed_channel'));
        $this->assertFalse(ReservedCanonicalKeys::isReserved('feed_customField'));
    }

    public function testPrefixesReservedKeysWithFeedNamespace(): void
    {
        $result = ReservedCanonicalKeys::sanitizeCanonicalKey('channel');

        $this->assertSame('feed_channel', $result['key']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('RESERVED_CANONICAL_KEY_PREFIXED', $result['warnings'][0]['code']);
        $this->assertSame('channel', $result['warnings'][0]['originalKey']);
        $this->assertSame('feed_channel', $result['warnings'][0]['sanitizedKey']);
    }

    public function testDoesNotDoublePrefixFeedKeys(): void
    {
        $result = ReservedCanonicalKeys::sanitizeCanonicalKey('feed_channel');

        $this->assertSame('feed_channel', $result['key']);
        $this->assertSame([], $result['warnings']);
    }

    public function testDefaultsEmptyKeysToField(): void
    {
        $result = ReservedCanonicalKeys::sanitizeCanonicalKey('   ');

        $this->assertSame('field', $result['key']);
        $this->assertSame('EMPTY_CANONICAL_KEY', $result['warnings'][0]['code']);
    }

    public function testLabelToCanonicalKeyMatchesCraftStyle(): void
    {
        $this->assertSame('serviceInterest', ReservedCanonicalKeys::labelToCanonicalKey('What service are you interested in?'));
        $this->assertSame('field', ReservedCanonicalKeys::labelToCanonicalKey('   '));
    }

    public function testSanitizesRuntimePropertyAndTagMaps(): void
    {
        $result = ReservedCanonicalKeys::sanitizePropertyAndTagKeys([
            'serviceInterest' => 'Web Design',
            'channel' => 'email',
            'feed_customField' => 'kept',
        ]);

        $this->assertSame([
            'serviceInterest' => 'Web Design',
            'feed_channel' => 'email',
            'feed_customField' => 'kept',
        ], $result['map']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('RESERVED_CANONICAL_KEY_PREFIXED', $result['warnings'][0]['code']);
    }

    public function testFixtureParityCases(): void
    {
        $fixturePath = self::specContractsDir() . '/reserved-canonical-keys.fixtures.json';
        $contents = file_get_contents($fixturePath);
        self::assertNotFalse($contents);

        $decoded = json_decode($contents, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('cases', $decoded);

        foreach ($decoded['cases'] as $case) {
            self::assertIsArray($case);
            $result = ReservedCanonicalKeys::sanitizeCanonicalKey((string) $case['input']);
            $this->assertSame($case['expectedKey'], $result['key'], (string) $case['input']);
            $this->assertSame(
                $case['expectedWarningCodes'],
                array_map(static fn (array $warning): string => $warning['code'], $result['warnings']),
                (string) $case['input']
            );
        }
    }

    private static function specContractsDir(): string
    {
        $standalone = dirname(__DIR__) . '/spec/contracts';
        if (is_dir($standalone)) {
            return $standalone;
        }

        return dirname(__DIR__, 2) . '/spec/contracts';
    }
}
