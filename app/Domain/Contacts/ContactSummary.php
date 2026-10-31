<?php

namespace App\Domain\Contacts;

use App\Models\Contact;

/** Údaje kontaktu pro výběr odběratele v editoru dokladu (našeptávač i karta odběratele). */
final class ContactSummary
{
    /** @return array<string, mixed> */
    public static function from(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'ico' => $contact->ico ?? '',
            'dic' => $contact->dic ?? '',
            'street' => $contact->street ?? '',
            'zip' => $contact->zip ?? '',
            'city' => $contact->city ?? '',
            'country' => $contact->country,
            'email' => $contact->email ?? '',
            'default_due_days' => $contact->default_due_days,
            'default_discount_percent' => $contact->default_discount_percent,
            'ares_synced_at' => $contact->ares_synced_at?->toDateString(),
        ];
    }
}
