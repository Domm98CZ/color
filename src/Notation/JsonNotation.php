<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation;

use Domm98CZ\Color\Notation\Exception\InvalidColorJsonException;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use Domm98CZ\Color\Space\Exception\UnknownColorSpaceException;

/**
 * Lossless JSON form of a color, schema version 1:
 *
 *     {"v":1,"space":"oklch","coords":[0.7,0.12,250],"alpha":1}
 *
 * - `space` is the color space id; decoding looks it up in a {@see ColorSpaceRegistry}.
 * - `coords` has one number per channel, or null for a missing component.
 * - `alpha` is a number in [0, 1], or null for a missing alpha.
 * - No other keys are allowed in version 1. A later schema gets a new `v`; older versions stay readable.
 *
 * Numbers are written by `json_encode()`, i.e. with the `serialize_precision` ini setting
 * (PHP default -1: the shortest form that round-trips binary64 exactly).
 */
final readonly class JsonNotation
{
    public const int VERSION = 1;

    public function __construct(private ?ColorSpaceRegistry $registry = null) {}

    public function encode(ColorValue $color): string
    {
        return \json_encode(
            ['v' => self::VERSION, 'space' => $color->space->id(), 'coords' => $color->coordinates, 'alpha' => $color->alpha],
            \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @throws InvalidColorJsonException when the document does not follow the schema
     * @throws UnknownColorSpaceException when the space is not in the registry
     * @throws InvalidColorValueException when the values are invalid (wrong count, alpha outside [0, 1])
     */
    public function decode(string $json): ColorValue
    {
        try {
            // Depth 3 = object > coords array > numbers; anything deeper is not a color.
            $data = \json_decode($json, true, 3, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidColorJsonException(\sprintf('Invalid JSON: %s.', $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($data) || \array_is_list($data)) {
            throw new InvalidColorJsonException('A color must be a JSON object.');
        }

        $unknown = \array_diff(\array_keys($data), ['v', 'space', 'coords', 'alpha']);
        if ($unknown !== []) {
            throw new InvalidColorJsonException(\sprintf('Unknown key(s) in a version %d color: %s.', self::VERSION, \implode(', ', $unknown)));
        }

        if (($data['v'] ?? null) !== self::VERSION) {
            throw new InvalidColorJsonException(\sprintf('Unsupported or missing schema version "v"; this library reads version %d.', self::VERSION));
        }

        $space = $data['space'] ?? null;
        if (!\is_string($space)) {
            throw new InvalidColorJsonException('"space" must be a color space id (string).');
        }

        if (!\array_key_exists('alpha', $data) || !\array_key_exists('coords', $data)) {
            throw new InvalidColorJsonException('"coords" and "alpha" are required (use null for a missing value).');
        }

        return new ColorValue(
            ($this->registry ?? ColorSpaceRegistry::default())->get($space),
            self::coordinates($data['coords']),
            self::number($data['alpha'], '"alpha"'),
        );
    }

    /**
     * @return list<float|null>
     */
    private static function coordinates(mixed $coordinates): array
    {
        if (!\is_array($coordinates) || !\array_is_list($coordinates)) {
            throw new InvalidColorJsonException('"coords" must be an array of numbers or nulls.');
        }

        return \array_map(static fn(mixed $value): ?float => self::number($value, 'Every coordinate'), $coordinates);
    }

    private static function number(mixed $value, string $name): ?float
    {
        if ($value === null || \is_float($value) || \is_int($value)) {
            return $value; // an int is widened to float by the return type
        }

        throw new InvalidColorJsonException(\sprintf('%s must be a number or null, %s given.', $name, \get_debug_type($value)));
    }
}
