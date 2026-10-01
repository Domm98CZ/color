<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

/**
 * The result of a fluent operation: a color that is no longer tied to the notation it started
 * from. Created by the operations of {@see FluentColor} and by {@see FluentColor::of()}.
 */
final readonly class ChainedColor extends FluentColor {}
