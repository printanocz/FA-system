<?php

namespace App\Actions\Contacts;

use App\Models\Contact;

/** Smaže kontakt bez dokladů. Kontakt s doklady se jen archivuje, doklady na něj dál odkazují. */
final class DeleteContact
{
    /** @return bool true = smazáno, false = archivováno */
    public function __invoke(Contact $contact): bool
    {
        if ($contact->documents()->exists()) {
            $contact->forceFill(['archived_at' => now()])->save();

            return false;
        }

        $contact->delete();

        return true;
    }
}
