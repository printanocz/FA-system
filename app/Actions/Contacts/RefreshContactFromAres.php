<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Services\Ares\AresClient;
use App\Services\Ares\Exceptions\AresException;
use Illuminate\Validation\ValidationException;

/** „Aktualizovat z ARES“ na kartě: načte čerstvá data (mimo cache), přepíše údaje a uloží. */
final class RefreshContactFromAres
{
    public function __construct(private readonly AresClient $ares) {}

    /** @throws ValidationException */
    public function __invoke(Contact $contact): Contact
    {
        if ($contact->ico === null || $contact->ico === '') {
            throw ValidationException::withMessages(['ico' => 'Kontakt nemá vyplněné IČO, nelze ho aktualizovat z ARES.']);
        }

        try {
            $subject = $this->ares->find($contact->ico, fresh: true);
        } catch (AresException $e) {
            throw ValidationException::withMessages(['ico' => $e->getMessage()]);
        }

        $contact->fillFromAres($subject)->save();

        return $contact;
    }
}
