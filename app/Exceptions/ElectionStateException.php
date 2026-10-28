<?php

namespace App\Exceptions;

use RuntimeException;

/** An election operation is not allowed in the election's current state. */
class ElectionStateException extends RuntimeException {}
