<?php

namespace App\Domain\Backup;

use RuntimeException;

/** Chyba zálohy se srozumitelnou českou zprávou (klíč, šifrování, dump, úložiště). */
class BackupException extends RuntimeException {}
