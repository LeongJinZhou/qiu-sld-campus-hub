<?php

namespace App\Exceptions;

use RuntimeException;

/** Google account is outside @qiu.edu.my, or not in camsys and not a registered staff account. */
class NotInRecordsException extends RuntimeException {}
