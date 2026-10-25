<?php

namespace App\Services\Ares\Exceptions;

use DomainException;

/** Základ chyb ARES: dotaz nelze vyřídit z doménového důvodu, ne programátorská chyba. */
class AresException extends DomainException {}
