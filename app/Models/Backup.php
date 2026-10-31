<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Záznam o jednom běhu zálohy (úspěšném i neúspěšném).
 *
 * @property int $id
 * @property string|null $filename
 * @property int $size
 * @property string|null $sha256
 * @property string $status ok | failed
 * @property bool $stored_local
 * @property bool $stored_remote
 * @property string|null $error
 * @property Carbon $created_at
 */
class Backup extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'stored_local' => 'boolean',
            'stored_remote' => 'boolean',
        ];
    }
}
