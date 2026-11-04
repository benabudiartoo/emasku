<?php

namespace App\Services\Import;

use RuntimeException;

/** A file that cannot be imported; the message is written for the owner (Indonesian) and carries nothing from the file. */
class ImportException extends RuntimeException {}
