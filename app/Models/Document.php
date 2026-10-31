<?php

namespace App\Models;

use App\Domain\Documents\ComputedStatus;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentLockedException;
use App\Domain\Documents\Exceptions\DocumentNotDeletableException;
use App\Domain\Documents\NumberSeriesService;
use App\Domain\Documents\PaymentMethod;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property DocumentType $type
 * @property DocumentStatus $status
 * @property PaymentMethod|null $payment_method
 * @property string $subtotal
 * @property string $vat_total
 * @property string $total
 * @property string $rounding
 * @property string $paid_total
 * @property string $discount_percent
 * @property bool $round_total
 * @property bool $show_taxable_date
 * @property Carbon|null $issue_date
 * @property Carbon|null $taxable_date
 * @property Carbon|null $due_date
 * @property Carbon|null $valid_until
 * @property Carbon|null $paid_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $locked_at
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property array<string, mixed>|null $buyer_snapshot
 * @property array<string, mixed>|null $supplier_snapshot
 * @property array<string, mixed>|null $bank_account_snapshot
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Pole, která po vystavení (locked_at) nejdou změnit: identita dokladu, snapshoty
     * a všechna finanční pole. Mění se jen stav úhrad, odeslání, poznámky, splatnost apod.
     * Změny po vystavení s auditním záznamem přijdou s T-12.
     */
    public const LOCKED_ATTRIBUTES = [
        'type', 'number', 'series_id', 'number_year', 'number_seq',
        'contact_id', 'buyer_snapshot', 'supplier_snapshot', 'bank_account_snapshot',
        'issue_date', 'taxable_date', 'show_taxable_date', 'currency', 'exchange_rate', 'vs',
        'discount_percent', 'round_total', 'rounding', 'subtotal', 'vat_total', 'total',
        'locked_at',
    ];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'payment_method' => PaymentMethod::class,
            'number_year' => 'integer',
            'number_seq' => 'integer',
            'buyer_snapshot' => 'array',
            'supplier_snapshot' => 'array',
            'bank_account_snapshot' => 'array',
            'issue_date' => 'date',
            'taxable_date' => 'date',
            'show_taxable_date' => 'boolean',
            'due_date' => 'date',
            'valid_until' => 'date',
            'paid_at' => 'date',
            'exchange_rate' => 'decimal:6',
            'discount_percent' => 'decimal:2',
            'round_total' => 'boolean',
            'rounding' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'sent_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'reminders_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Document $document): void {
            if ($document->getOriginal('locked_at') === null) {
                return;
            }

            $changed = array_values(array_intersect(self::LOCKED_ATTRIBUTES, array_keys($document->getDirty())));

            if ($changed !== []) {
                throw DocumentLockedException::forFields($changed);
            }
        });
    }

    /** @return HasMany<DocumentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at')->orderBy('id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<NumberSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'series_id');
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_document_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Zbývá uhradit (celkem − uhrazeno). */
    public function remainingAmount(): BigDecimal
    {
        return BigDecimal::of($this->total)->minus(BigDecimal::of($this->paid_total));
    }

    /**
     * Po splatnosti: splatnost < dnes a uhrazeno < celkem. Nikdy u konceptů, stornovaných
     * dokladů a nabídek.
     */
    public function isOverdue(?CarbonInterface $today = null): bool
    {
        if (! $this->type->isPayable() || $this->due_date === null) {
            return false;
        }

        if ($this->status === DocumentStatus::Draft || $this->status === DocumentStatus::Cancelled) {
            return false;
        }

        $today ??= Carbon::today();

        return $this->due_date->toDateString() < $today->toDateString()
            && BigDecimal::of($this->paid_total)->isLessThan(BigDecimal::of($this->total));
    }

    /** Expirovaná nabídka: platnost < dnes a stav created nebo sent. */
    public function isExpired(?CarbonInterface $today = null): bool
    {
        if (! $this->type->isQuote() || $this->valid_until === null) {
            return false;
        }

        if ($this->status !== DocumentStatus::Created && $this->status !== DocumentStatus::Sent) {
            return false;
        }

        $today ??= Carbon::today();

        return $this->valid_until->toDateString() < $today->toDateString();
    }

    public function computedStatus(?CarbonInterface $today = null): ?ComputedStatus
    {
        return match (true) {
            $this->isOverdue($today) => ComputedStatus::Overdue,
            $this->isExpired($today) => ComputedStatus::Expired,
            default => null,
        };
    }

    /** Je to poslední doklad své řady (nejvyšší rok a pořadí čísla)? */
    public function isLastInSeries(): bool
    {
        if ($this->series_id === null || $this->number_year === null || $this->number_seq === null) {
            return false;
        }

        $year = $this->number_year;
        $sequence = $this->number_seq;

        return ! static::query()
            ->where('series_id', $this->series_id)
            ->where(function (Builder $query) use ($year, $sequence): void {
                $query->where('number_year', '>', $year)
                    ->orWhere(function (Builder $same) use ($year, $sequence): void {
                        $same->where('number_year', $year)->where('number_seq', '>', $sequence);
                    });
            })
            ->exists();
    }

    /**
     * Smazat jde jen koncept nebo poslední doklad v řadě, který nebyl odeslán ani uhrazen.
     * Storno vystaveného dokladu řeší dobropis.
     */
    public function assertDeletable(): void
    {
        if (! $this->isLocked()) {
            return;
        }

        if ($this->status === DocumentStatus::Cancelled) {
            throw DocumentNotDeletableException::cancelled();
        }

        if (
            $this->status->isSentOrPaid()
            || $this->sent_at !== null
            || BigDecimal::of($this->paid_total)->isPositive()
            || $this->payments()->exists()
        ) {
            throw DocumentNotDeletableException::sentOrPaid();
        }

        if (! $this->isLastInSeries()) {
            throw DocumentNotDeletableException::notLastInSeries();
        }
    }

    /**
     * Smaže doklad podle pravidel neměnnosti. U posledního dokladu v řadě vrátí číslo
     * do řady, aby v číslování nevznikla díra.
     */
    public function delete(): ?bool
    {
        $this->assertDeletable();

        return DB::transaction(function (): ?bool {
            $series = $this->series_id === null ? null : NumberSeries::query()->find($this->series_id);
            $year = $this->number_year;
            $sequence = $this->number_seq;

            $deleted = parent::delete();

            if ($deleted && $series !== null && $year !== null && $sequence !== null) {
                app(NumberSeriesService::class)->release($series, $year, $sequence);
            }

            return $deleted;
        });
    }
}
