# Contributing

## Setup

```bash
composer install
composer check        # validate, coding standard, static analysis, tests
```

Individual commands:

| Command | Purpose |
|---|---|
| `composer test` | all test suites (unit, conformance, property, examples) |
| `composer test:unit` / `test:conformance` / `test:property` / `test:examples` | a single suite |
| `composer phpstan` | PHPStan, level max, strict rules, no baseline |
| `composer cs` / `composer cs:fix` | PER-CS 2.0 coding standard |
| `composer bench` | timings of common operations (`tools/benchmark.php`) |
| `composer api:snapshot` | regenerate the public API snapshot after an intended API change |

Property tests use a fixed seed. To explore other inputs: `COLORS_TEST_SEED=123 composer test:property`.
A failing property test prints the seed needed to reproduce it.

Every script in `examples/` is run by the examples suite and its output must match
`examples/expected/<name>.txt` exactly. After changing an example, regenerate its output with
`php examples/<name>.php > examples/expected/<name>.txt` and review the diff.

## Rules

1. **Scope first.** The library is deliberately small; open an issue before proposing a new
   feature or a change of a public API decision, so it can be checked against the project scope.
2. **Every algorithm cites its source** with the exact version (specification, paper, data set) and
   has reference tests; a source that has not been verified is not implemented.
3. **No bare tolerances in tests.** Use a named constant from `tests/Support/Tolerances.php` with a
   written rationale, or the tolerance of a reference fixture.
4. **Reference data needs provenance.** Fixtures in `fixtures/reference` follow the schema in
   `fixtures/reference/README.md`; data with an unclear licence is not committed.
5. **Numeric behaviour is API.** Any change of computed values increments the `AlgorithmId` revision
   and is listed under "Numeric changes" in CHANGELOG.md.
6. **No silent guesses.** Assumptions are recorded in the diagnostics of a result; missing input
   is an `InsufficientDataException`, never a guess.
7. **Immutability, `final` classes, `declare(strict_types=1)`, no global mutable state.** The one
   exception is a bounded memo of a pure computation inside an `@internal` class (e.g. the
   chromatic adaptation matrices in `Colorimetry\Internal\ConeSpaceScaling`): it returns the value
   that would be computed, so no result can depend on it.
8. **The public API is reviewed as a diff.** `tests/Unit/Architecture/public-api.txt` lists every
   public class, signature, parameter name, default, constant and enum value. An intended change
   needs `composer api:snapshot`, an entry in CHANGELOG.md and, after 1.0, in UPGRADE.md.
9. Code, PHPDoc and public documentation are in English.
