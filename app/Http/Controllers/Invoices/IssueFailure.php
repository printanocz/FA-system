<?php

namespace App\Http\Controllers\Invoices;

use App\Domain\Documents\Exceptions\DocumentException;

/**
 * Převede doménovou výjimku při vystavení na zprávu pro uživatele. Chyby, které řeší
 * Nastavení (firma, účet, řada), dostanou odkaz na příslušnou sekci.
 */
final class IssueFailure
{
    /** @return array{message: string, href?: string, label?: string} */
    public static function describe(DocumentException $e): array
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'údaje o firmě') => [
                'message' => 'Faktura se nedá vystavit, v Nastavení chybí údaje o vaší firmě (dodavateli).',
                'href' => '/nastaveni#firma',
                'label' => 'Doplnit firmu v Nastavení',
            ],
            str_contains($message, 'číselná řada') => [
                'message' => 'Faktura se nedá vystavit, v Nastavení chybí číselná řada pro faktury.',
                'href' => '/nastaveni#rady',
                'label' => 'Nastavit číselnou řadu',
            ],
            str_contains($message, 'bankovní účet') => [
                'message' => 'Faktura s platbou převodem potřebuje bankovní účet. V Nastavení žádný není.',
                'href' => '/nastaveni#ucty',
                'label' => 'Přidat bankovní účet',
            ],
            str_contains($message, 'bez položek') => ['message' => 'Přidejte aspoň jednu položku, doklad bez položek nejde vystavit.'],
            str_contains($message, 'nemá odběratele') => ['message' => 'Vyberte odběratele, doklad bez odběratele nejde vystavit.'],
            str_contains($message, 'jen koncept') => ['message' => 'Tento doklad už je vystavený.'],
            default => ['message' => $message],
        };
    }
}
