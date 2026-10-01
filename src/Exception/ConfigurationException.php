<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Exception;

/**
 * The library was configured inconsistently (e.g. two color spaces registered under one id).
 */
abstract class ConfigurationException extends \LogicException implements ColorException {}
