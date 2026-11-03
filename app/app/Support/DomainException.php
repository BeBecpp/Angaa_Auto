<?php

namespace App\Support;

use RuntimeException;

/**
 * A business-rule rejection whose message is safe to show to the user (Mongolian).
 */
class DomainException extends RuntimeException {}
