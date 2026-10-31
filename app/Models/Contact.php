<?php

namespace App\Models;

use App\Services\Ares\AresSubject;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $ares_synced_at
 * @property Carbon|null $archived_at
 * @property int|null $default_due_days
 * @property string|null $default_discount_percent
 * @property array<int, string>|null $email_cc
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_cc' => 'array',
            'default_due_days' => 'integer',
            'default_discount_percent' => 'decimal:2',
            'ares_synced_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Vyhledávání v adresáři: název, IČO (i s mezerami) a e-mail. Prázdný dotaz nic nefiltruje.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';
        $digits = '%'.addcslashes(preg_replace('/\s+/', '', $term) ?? $term, '\\%_').'%';

        return $query->where(function (Builder $query) use ($like, $digits): void {
            $query->where('name', 'like', $like)
                ->orWhere('ico', 'like', $digits)
                ->orWhere('email', 'like', $like);
        });
    }

    /** Přepíše identifikační a adresní údaje daty z ARES (DIČ jen když ho ARES vrátí) a zapíše čas synchronizace (neukládá). */
    public function fillFromAres(AresSubject $subject): static
    {
        $this->fill([
            'name' => $subject->name,
            'ico' => $subject->ico,
            'street' => $subject->street,
            'city' => $subject->city,
            'zip' => $subject->zip,
            'country' => $subject->country,
        ]);
        // ARES DIČ často nevrací (mají ho i neplátci), proto prázdné DIČ stávající nepřepíše.
        if ($subject->dic !== null) {
            $this->dic = $subject->dic;
        }
        $this->ares_synced_at = now();

        return $this;
    }

    /**
     * Údaje odběratele uložené na dokladu při vystavení. Pozdější změna kontaktu
     * už starý doklad nezmění.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'street' => $this->street,
            'city' => $this->city,
            'zip' => $this->zip,
            'country' => $this->country,
            'email' => $this->email,
            'email_cc' => $this->email_cc,
            'phone' => $this->phone,
        ];
    }
}
