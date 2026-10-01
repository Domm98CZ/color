# Tools

Developer scripts: generators of reference fixtures from published data, the public API snapshot
and the benchmark. They use nothing beyond PHP and the Composer dev dependencies; the project uses
no JavaScript tooling and no external color management system.

Rules (fixtures/reference/README.md):

- every generator pins the exact version of the tool it uses;
- every generated fixture records the tool, its version and the script in `meta.generator`;
- regenerating a fixture is reviewed like a code change.

| Tool | Fixture |
|---|---|
| [`wpt/extract-css-color-parsing.php`](wpt/README.md) | `fixtures/reference/wpt/css-color-parsing.json` |
| [`api/snapshot.php`](api/snapshot.php) (`composer api:snapshot`) | `tests/Unit/Architecture/public-api.txt` – the public API snapshot, not a reference fixture |
| [`benchmark.php`](benchmark.php) (`composer bench`) | none – prints timings of common operations |
| [`playground/index.php`](playground/index.php) | none – a visual playground: `php -S 0.0.0.0:8080 -t tools/playground` and open `http://localhost:8080/?color=%233366cc`, or `php tools/playground/index.php '#3366cc' > out.html` |

`fixtures/reference/css-color-4/srgb-conversions.json` is frozen data generated once from the CSS
Color 4 sample code (no generator is kept); see its `meta.generator`.
