<?php

namespace App\Services\Zakat;

use InvalidArgumentException;

/** A madhhab with no complete, referenced rule sheet is never offered and never calculated. */
class UnsupportedMadhhabException extends InvalidArgumentException {}
