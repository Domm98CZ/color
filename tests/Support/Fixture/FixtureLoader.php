<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Support\Fixture;

use Domm98CZ\Color\Math\Tolerance;

/**
 * Loads and validates reference fixtures (schema: fixtures/reference/README.md).
 *
 * A fixture without a source, a license statement or a tolerance rationale is rejected:
 * reference data whose origin or precision is unknown cannot justify a test.
 */
final class FixtureLoader
{
    public const string REFERENCE_DIRECTORY = __DIR__ . '/../../../fixtures/reference';

    /**
     * Parsed fixtures by path. Fixture files are immutable during a test run, and data providers
     * of large fixtures would otherwise parse the same file once per test.
     *
     * @var array<string, ReferenceFixture>
     */
    private static array $cache = [];

    private function __construct() {}

    public static function fromFile(string $path): ReferenceFixture
    {
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $json = @\file_get_contents($path);

        if ($json === false) {
            throw new InvalidFixtureException(\sprintf('Fixture "%s" cannot be read.', $path));
        }

        return self::$cache[$path] = self::fromJson($json, $path);
    }

    /**
     * Loads a fixture by its path relative to fixtures/reference, e.g. 'css-color-4/matrices.json'.
     */
    public static function reference(string $relativePath): ReferenceFixture
    {
        return self::fromFile(self::REFERENCE_DIRECTORY . '/' . $relativePath);
    }

    /**
     * @return list<string> absolute paths of all reference fixtures
     */
    public static function allReferenceFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::REFERENCE_DIRECTORY, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }

        \sort($files);

        return $files;
    }

    public static function fromJson(string $json, string $origin): ReferenceFixture
    {
        try {
            $data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidFixtureException(\sprintf('Fixture "%s" is not valid JSON: %s', $origin, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($data)) {
            throw self::invalid($origin, 'the root must be an object');
        }

        $meta = self::object($data, 'meta', $origin);
        $tolerance = self::object($meta, 'tolerance', $origin, 'meta.');
        $cases = $data['cases'] ?? null;

        if (!\is_array($cases) || $cases === [] || !\array_is_list($cases)) {
            throw self::invalid($origin, '"cases" must be a non-empty list');
        }

        $validCases = [];
        foreach ($cases as $index => $case) {
            if (!\is_array($case) || \array_is_list($case)) {
                throw self::invalid($origin, \sprintf('case #%d must be an object', $index));
            }

            $validCase = [];
            foreach ($case as $key => $value) {
                $validCase[(string) $key] = $value;
            }
            $validCases[] = $validCase;
        }

        $retrieved = self::string($meta, 'retrieved', $origin, 'meta.');
        if (\preg_match('~^\d{4}-\d{2}-\d{2}$~', $retrieved) !== 1) {
            throw self::invalid($origin, '"meta.retrieved" must be a YYYY-MM-DD date');
        }

        $metaWithStringKeys = [];
        foreach ($meta as $key => $value) {
            $metaWithStringKeys[(string) $key] = $value;
        }

        return new ReferenceFixture(
            id: self::string($meta, 'id', $origin, 'meta.'),
            source: self::string($meta, 'source', $origin, 'meta.'),
            retrieved: $retrieved,
            license: self::string($meta, 'license', $origin, 'meta.'),
            tolerance: Tolerance::of(
                self::nonNegativeNumber($tolerance, 'abs', $origin),
                \array_key_exists('rel', $tolerance) ? self::nonNegativeNumber($tolerance, 'rel', $origin) : 0.0,
            ),
            toleranceRationale: self::string($tolerance, 'rationale', $origin, 'meta.tolerance.'),
            cases: $validCases,
            meta: $metaWithStringKeys,
        );
    }

    /**
     * Converts a fixture number to float. Accepts JSON numbers and exact rationals written
     * as strings ("506752/1228815"), which is how CSS Color 4 publishes some matrices.
     */
    public static function number(mixed $value): float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }

        if (\is_string($value) && \preg_match('~^(-?\d+)/(\d+)$~', $value, $match) === 1 && (int) $match[2] !== 0) {
            return (int) $match[1] / (int) $match[2];
        }

        throw new InvalidFixtureException(\sprintf('Expected a number or a "p/q" rational string, got %s.', \get_debug_type($value)));
    }

    /**
     * @return list<list<float>>
     */
    public static function matrix(mixed $rows): array
    {
        if (!\is_array($rows) || !\array_is_list($rows)) {
            throw new InvalidFixtureException('Expected a list of rows.');
        }

        $result = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !\array_is_list($row)) {
                throw new InvalidFixtureException('Expected each row to be a list of numbers.');
            }
            $result[] = \array_map(self::number(...), $row);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function object(array $data, string $key, string $origin, string $prefix = ''): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw self::invalid($origin, \sprintf('"%s%s" must be an object', $prefix, $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return non-empty-string
     */
    private static function string(array $data, string $key, string $origin, string $prefix): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value) || \trim($value) === '') {
            throw self::invalid($origin, \sprintf('"%s%s" must be a non-empty string', $prefix, $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function nonNegativeNumber(array $data, string $key, string $origin): float
    {
        $value = $data[$key] ?? null;

        if ((!\is_int($value) && !\is_float($value)) || $value < 0 || !\is_finite((float) $value)) {
            throw self::invalid($origin, \sprintf('"meta.tolerance.%s" must be a finite number >= 0', $key));
        }

        return (float) $value;
    }

    private static function invalid(string $origin, string $reason): InvalidFixtureException
    {
        return new InvalidFixtureException(\sprintf('Fixture "%s" is invalid: %s.', $origin, $reason));
    }
}
