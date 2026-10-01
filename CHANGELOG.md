# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Numeric behaviour is part of compatibility: any change of computed values is listed under
**Numeric changes**, together with the new `AlgorithmId` revision.

## [0.1.0] - 2026-10-01

Initial release.

### Added

- `Color`: an immutable value object for a color in any built-in or custom space, with named
  constructors (`fromHex`, `fromRgb`, `fromRgba`, `fromHsl`, `fromHwb`, `fromHsv`, `fromLab`,
  `fromLch`, `fromOklab`, `fromOklch`, `fromXyz`, `parse`, `fromJson`), conversion (`to()`, `in()`),
  gamut checking (`isInGamut()`) and comparison (`equals()`, `isCloseTo()`).
- 21 built-in color spaces and conversions between them: sRGB (+ linear), Display P3 (+ linear),
  A98 RGB (+ linear), ProPhoto RGB (+ linear), Rec. 2020 (+ linear), HSL, HWB, HSV, CIE XYZ D65/D50,
  CIE Lab/LCh (D50 and D65), OKLab, OKLCh; a custom RGB space in a few lines via the `ColorSpace`
  contract (`examples/custom-space.php`).
- CSS Color 4/5 notation: parsing (hex, named colors, `rgb()`, `hsl()`, `hwb()`, `lab()`, `lch()`,
  `oklab()`, `oklch()`, `color()`, relative colors, `color-mix()`), serialization that always parses
  back to the same color, hex, and lossless versioned JSON.
- Color difference: CIE76 (`DeltaE::cie76()`), CIEDE2000 (`DeltaE::ciede2000()`) and ΔEOK
  (`DeltaE::ok()`), each with a named `AlgorithmId` revision.
- WCAG 2 contrast: `Contrast::wcag2()` and its relative luminance, with a `LayerStack` for
  compositing a translucent foreground over a background before measuring it.
- Gamut mapping behind the `GamutMapper` contract: the CSS Color 4 binary search, ray trace, chroma
  reduction and plain clipping.
- Mixing, gradients and adjustments: `Mixer`, `Gradient`, `Adjuster` (lightness, chroma and hue
  adjustments with the computation model always explicit), `TintShadeTone`, `Quantizer`.
- Compositing: Porter-Duff operators and CSS blend modes behind the `CompositeOperator` contract.
- Harmonies and color wheels behind the `HueWheel` contract: complementary, split-complementary,
  analogous, triadic, tetradic, square and custom schemes, on the OKLCh, HSL and RYB wheels.
- Palettes: lightness scales, tints/shades/tones, palette analysis (closest/farthest pair, nearest
  neighbours, contrast matrix) and the nearest CSS named color.
- A fluent layer (`HexColor`, `RgbColor`, `RgbaColor`, `HslColor`, `OklchColor`, `CssColor`) with
  chainable operations, visible defaults (`FluentConfig`) and a step-by-step `trace()`.
- No runtime dependencies; PHP 8.3-8.5.

### Numeric changes

- None (first release). The numeric behaviour of every conversion and algorithm is covered by the
  conformance test suite.
