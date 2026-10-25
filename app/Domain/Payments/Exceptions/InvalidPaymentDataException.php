<?php

namespace App\Domain\Payments\Exceptions;

use InvalidArgumentException;

/** Chybný vstup platebních služeb (číslo účtu, IBAN, údaje QR platby). */
class InvalidPaymentDataException extends InvalidArgumentException {}
