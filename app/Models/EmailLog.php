<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Záznam o pokusu odeslat doklad e-mailem (úspěšném i neúspěšném).
 *
 * @property int $id
 * @property int $document_id
 * @property string $type invoice | reminder
 * @property string $to_address
 * @property array<int, string>|null $cc
 * @property array<int, string>|null $bcc
 * @property string $subject
 * @property Carbon|null $sent_at
 * @property string $status sent | failed
 * @property string|null $error
 */
class EmailLog extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_REMINDER = 'reminder';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'email_log';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cc' => 'array', 'bcc' => 'array', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
