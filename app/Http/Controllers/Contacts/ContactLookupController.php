<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\SaveContact;
use App\Domain\Contacts\ContactSummary;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Rules\Ico;
use App\Services\Ares\AresClient;
use App\Services\Ares\Exceptions\AresException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Výběr odběratele v editoru dokladu: našeptávač nad nearchivovanými kontakty a založení z ARES. */
class ContactLookupController extends Controller
{
    /** Nejvyšší počet kontaktů v našeptávači. */
    private const LIMIT = 8;

    public function search(Request $request): JsonResponse
    {
        $contacts = Contact::query()
            ->whereNull('archived_at')
            ->search((string) $request->query('q', ''))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return response()->json(['contacts' => $contacts->map(fn (Contact $c): array => ContactSummary::from($c))->values()]);
    }

    /** Založí kontakt podle IČO z ARES (nebo vrátí existující se stejným IČO) a vrátí ho k výběru. */
    public function createFromAres(Request $request, AresClient $ares, SaveContact $save): JsonResponse
    {
        $ico = Ico::normalize($request->validate(
            ['ico' => ['required', 'string', 'max:20', new Ico]],
            [],
            ['ico' => 'IČO'],
        )['ico']);

        $existing = Contact::query()->where('ico', $ico)->orderBy('archived_at')->orderBy('id')->first();
        if ($existing !== null) {
            if ($existing->isArchived()) {
                $existing->forceFill(['archived_at' => null])->save();
            }

            return response()->json(['contact' => ContactSummary::from($existing), 'created' => false]);
        }

        try {
            $subject = $ares->find($ico);
        } catch (AresException $e) {
            throw ValidationException::withMessages(['ico' => $e->getMessage()]);
        }

        $contact = $save([
            'type' => 'company',
            'name' => $subject->name,
            'ico' => $subject->ico,
            'dic' => $subject->dic,
            'street' => $subject->street,
            'city' => $subject->city,
            'zip' => $subject->zip,
            'country' => $subject->country,
        ]);
        $contact->forceFill(['ares_synced_at' => now()])->save();

        return response()->json(['contact' => ContactSummary::from($contact), 'created' => true], 201);
    }
}
