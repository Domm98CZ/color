<?php

declare(strict_types=1);

// "What is this color called?" The 148 CSS named colors are searched with an explicit color
// difference (CIEDE2000; about 2.3 is a just noticeable difference, above 10 is clearly another
// color). rank() lists the closest few, which is useful when the nearest name is a poor match.
// Run: php examples/nearest-named-color.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Palette\Catalogs;

require __DIR__ . '/../vendor/autoload.php';

$catalog = Catalogs::cssNamed();
$metric = DeltaE::ciede2000();

foreach (['#3366cc', '#ffcc00', '#916300', '#5085ee', '#123456'] as $value) {
    $match = $catalog->nearest(Color::fromHex($value), $metric);
    printf("%s -> %-16s dE00 %5.2f\n", $value, $match->entry->name, $match->difference->value);
}

// The three closest names for a derived color: the lighter tint of the brand color.
$tint = (new HexColor('#3366cc'))->tint(0.5)->toColor();
echo "\n", 'closest to ', $tint->toHex(), ":\n";
foreach ($catalog->rank($tint, $metric, 3) as $rank) {
    printf("  %-16s %s  dE00 %5.2f\n", $rank->entry->name, $rank->entry->color->toHex(), $rank->difference->value);
}
