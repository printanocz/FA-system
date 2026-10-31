<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Actions\Settings\UpdateDocumentSettings;
use App\Actions\Settings\UpdateEmailSettings;
use App\Domain\Documents\NumberSeriesService;
use App\Domain\Mail\EmailSettings;
use App\Domain\Mail\EmailTemplate;
use App\Domain\Settings\DocumentSettings;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\NumberSeries;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Stránka Nastavení (/nastaveni): firma a výchozí hodnoty dokladů se ukládají společně. */
class SettingsController extends Controller
{
    public function show(Request $request, DocumentSettings $documents, NumberSeriesService $numbers, EmailSettings $emails): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $company = CompanyProfile::current();
        $values = $documents->all();

        return Inertia::render('Settings/Index', [
            'company' => [
                'name' => $company->name ?? '',
                'ico' => $company->ico ?? '',
                'dic' => $company->dic ?? '',
                'street' => $company->street ?? '',
                'zip' => $company->zip ?? '',
                'city' => $company->city ?? '',
                'registration_note' => $company->registration_note ?? '',
                'vat_payer' => $company->vat_payer ?? false,
                'email' => $company->email ?? '',
                'phone' => $company->phone ?? '',
                'web' => $company->web ?? '',
            ],
            'documents' => [
                'due_days' => $values['due_days'],
                'constant_symbol' => $values['constant_symbol'] ?? '',
                'round_total' => $values['round_total'],
                'text_before' => $values['text_before'] ?? '',
                'text_after' => $values['text_after'] ?? '',
            ],
            'emails' => $emails->all(),
            'emailVariables' => EmailTemplate::VARIABLES,
            'bankAccounts' => BankAccount::query()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (BankAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'account_number' => $account->account_number,
                    'bank_code' => $account->bank_code,
                    'iban' => $account->iban,
                    'swift' => $account->swift ?? '',
                    'currency' => $account->currency,
                    'is_default' => $account->is_default,
                ])->values(),
            'numberSeries' => NumberSeries::query()->orderBy('id')->get()
                ->map(fn (NumberSeries $series): array => [
                    'id' => $series->id,
                    'name' => $series->name,
                    'doc_type' => $series->doc_type->value,
                    'prefix' => $series->prefix,
                    'format' => $series->format,
                    'next_number' => $series->next_number,
                    'reset_yearly' => $series->reset_yearly,
                    'is_default' => $series->is_default,
                    'preview' => $numbers->preview($series, Carbon::today()),
                ])->values(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'twoFactor' => $user->hasEnabledTwoFactorAuthentication(),
            ],
        ]);
    }

    public function update(Request $request, UpdateCompanyProfile $company, UpdateDocumentSettings $documents, UpdateEmailSettings $emails): RedirectResponse
    {
        /** @var array<string, mixed> $companyInput */
        $companyInput = (array) $request->input('company', []);
        /** @var array<string, mixed> $documentInput */
        $documentInput = (array) $request->input('documents', []);

        /** @var array<string, mixed> $emailInput */
        $emailInput = (array) $request->input('emails', []);

        DB::transaction(function () use ($company, $documents, $emails, $companyInput, $documentInput, $emailInput): void {
            $errors = [];

            foreach (['company' => fn () => $company($companyInput), 'documents' => fn () => $documents($documentInput), 'emails' => fn () => $emails($emailInput)] as $prefix => $save) {
                try {
                    $save();
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $errors["{$prefix}.{$field}"] = $messages;
                    }
                }
            }

            // Výjimka vrátí transakci, takže se neuloží ani platná polovina formuláře.
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return back();
    }
}
