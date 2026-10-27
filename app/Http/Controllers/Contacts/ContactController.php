<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\RefreshContactFromAres;
use App\Actions\Contacts\SaveContact;
use App\Domain\Contacts\ContactStatistics;
use App\Domain\Documents\DocumentType;
use App\Domain\Settings\DocumentSettings;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Adresář: seznam vlevo, karta vybraného kontaktu vpravo. Vyhledávání a přepínač
 * archivovaných jdou přes query (`q`, `archivovane`), vybraný kontakt přes adresu `/adresar/{id}`.
 */
class ContactController extends Controller
{
    /** Počet dokladů v seznamu na kartě. */
    private const CARD_DOCUMENTS = 10;

    public function index(Request $request, ContactStatistics $statistics, DocumentSettings $settings, ?Contact $contact = null): Response
    {
        $query = trim((string) $request->query('q', ''));
        $archived = $request->boolean('archivovane');
        $year = now()->year;
        [$from, $to] = ContactStatistics::yearRange($year);

        $contacts = Contact::query()
            ->search($query)
            ->when(! $archived, fn (Builder $q) => $q->whereNull('archived_at'))
            ->withSum(['documents as turnover' => fn (Builder $q) => $q
                ->where('type', DocumentType::Invoice)
                ->whereIn('status', [...ContactStatistics::ISSUED_STATUSES])
                ->whereBetween('issue_date', [$from, $to]),
            ], 'total')
            ->orderByDesc('turnover')
            ->orderBy('name')
            ->get();

        // Bez zvoleného kontaktu ukáže desktop kartu prvního v seznamu; mobil ukáže jen seznam.
        $selected = $contact ?? $contacts->first();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts->map(fn (Contact $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'ico' => $c->ico,
                'turnover' => $c->getAttribute('turnover') === null ? null : number_format((float) $c->getAttribute('turnover'), 2, '.', ''),
                'archived' => $c->isArchived(),
            ])->values(),
            'filters' => ['q' => $query, 'archived' => $archived],
            'year' => $year,
            'selectedId' => $contact?->id,
            'selected' => $selected === null ? null : $this->card($selected, $statistics),
            'defaultDueDays' => $settings->dueDays(),
        ]);
    }

    public function store(Request $request, SaveContact $save): RedirectResponse
    {
        $contact = $save($request->all());

        return redirect()->route('contacts.show', $contact);
    }

    public function update(Request $request, Contact $contact, SaveContact $save): RedirectResponse
    {
        $save($request->all(), $contact);

        return back();
    }

    /** Smaže kontakt bez dokladů, jinak ho archivuje. */
    public function destroy(Contact $contact, DeleteContact $delete): RedirectResponse
    {
        $delete($contact);

        return redirect()->route('contacts.index');
    }

    public function restore(Contact $contact): RedirectResponse
    {
        $contact->forceFill(['archived_at' => null])->save();

        return back();
    }

    public function refreshFromAres(Contact $contact, RefreshContactFromAres $refresh): RedirectResponse
    {
        $refresh($contact);

        return back();
    }

    /** @return array<string, mixed> */
    private function card(Contact $contact, ContactStatistics $statistics): array
    {
        $documents = Document::query()
            ->with('items')
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->limit(self::CARD_DOCUMENTS)
            ->get();

        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'name' => $contact->name,
            'ico' => $contact->ico ?? '',
            'dic' => $contact->dic ?? '',
            'street' => $contact->street ?? '',
            'zip' => $contact->zip ?? '',
            'city' => $contact->city ?? '',
            'country' => $contact->country,
            'email' => $contact->email ?? '',
            'email_cc' => $contact->email_cc ?? [],
            'phone' => $contact->phone ?? '',
            'default_due_days' => $contact->default_due_days,
            'default_discount_percent' => $contact->default_discount_percent,
            'note' => $contact->note ?? '',
            'ares_synced_at' => $contact->ares_synced_at?->toDateString(),
            'archived' => $contact->isArchived(),
            'has_documents' => $contact->documents()->exists(),
            'stats' => $statistics->forContact($contact)->toArray(),
            'documents' => $documents->map(fn (Document $document): array => [
                'id' => $document->id,
                'type' => $document->type->value,
                'number' => $document->number,
                'description' => $document->items->first()?->name,
                'total' => $document->total,
                'currency' => $document->currency,
                'status' => $document->status->value,
                'computed_status' => $document->computedStatus()?->value,
                'issue_date' => $document->issue_date?->toDateString(),
            ])->values(),
        ];
    }
}
