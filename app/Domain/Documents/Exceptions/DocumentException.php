<?php

namespace App\Domain\Documents\Exceptions;

use DomainException;

/** Základ doménových výjimek dokladů: porušení pravidla, ne technická chyba. */
class DocumentException extends DomainException {}
