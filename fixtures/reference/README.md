# Reference fixtures

Reference data used by conformance tests (`tests/Conformance`). Every file is validated by
`tests/Support/Fixture/FixtureLoader.php`; the conformance suite fails if any file is invalid.

## Schema

```json
{
  "meta": {
    "id": "kebab-case-unique-id",
    "source": "Full citation of the source (document, section, table/function)",
    "edition": "Exact version or dated snapshot (optional but strongly recommended)",
    "url": "https://… (optional)",
    "doi": "10.xxxx/… (optional)",
    "retrieved": "YYYY-MM-DD",
    "license": "Licence under which the data may be redistributed",
    "values_precision": "How many digits the source publishes (optional)",
    "tolerance": {
      "abs": 1e-12,
      "rel": 0,
      "rationale": "Why this tolerance: source rounding + our numeric error"
    },
    "generator": {
      "tool": "only for generated fixtures",
      "version": "pinned version",
      "script": "tools/…"
    }
  },
  "cases": [ { "…": "case-specific fields" } ]
}
```

Required: `meta.id`, `meta.source`, `meta.retrieved`, `meta.license`, `meta.tolerance.abs`,
`meta.tolerance.rationale`, a non-empty `cases` list of objects.

Numbers may be JSON numbers or exact rationals written as strings (`"506752/1228815"`).

## Rules

- Data with an unclear licence is **not** committed. The test that needs it downloads it through a
  script in `tools/` and is skipped (with a reason) when the data is absent.
- Generated fixtures record the generating tool, its pinned version and the script. Changing the
  tool version means regenerating and reviewing the diff.
- A tolerance is never widened to make a test pass without updating its rationale.

## Catalogue

| File | Source | Used by |
|---|---|---|
| `css-color-4/conversion-matrix-pairs.json` | CSS Color 4 (CRD 2026-09-26), sample code | `tests/Conformance/CssColor4/ConversionMatrixPairsTest.php` |
| `css-color-4/srgb-conversions.json` | CSS Color 4 (CRD 2026-09-26), sample code, evaluated once (frozen data, do not edit; no generator is kept – the project has no JavaScript tooling) | `tests/Conformance/CssColor4/SrgbConversionsTest.php` |
| `css-color-4/rgb-xyz-matrices.json` | CSS Color 4 (CRD 2026-09-26), RGB ↔ XYZ matrix literals of 5 spaces | `tests/Conformance/CssColor4/RgbXyzMatricesTest.php` |
| `css-color-4/notation-examples.json` | CSS Color 4 (CRD 2026-09-26), examples in §5.2, §7, §8, §15, §16 | `tests/Conformance/CssColor4/NotationExamplesTest.php` |
| `wpt/css-color-parsing.json` | web-platform-tests `css/css-color/parsing` at a pinned commit, extracted by `tools/wpt/extract-css-color-parsing.php` (3-Clause BSD, `wpt/LICENSE.md`) | `tests/Conformance/Wpt/CssColorParsingTest.php` |
| `sharma-2005/ciede2000-test-data.json` | Sharma, Wu & Dalal (2005), Table 1 – 34 CIEDE2000 pairs | `tests/Conformance/Sharma/Ciede2000TestDataTest.php` |
| `ottosson-2020/oklab-examples.json` | B. Ottosson, *A perceptual color space for image processing* (2020), table of test values | `tests/Conformance/Ottosson/OklabExamplesTest.php` |
