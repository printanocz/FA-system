<?php

use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/** Vystavená faktura s pevným datem vystavení a splatnosti (výchozí jedna položka za 1 000 Kč). */
function listInvoice(string $issued, string $due, array $items = [[1, 1000]], array $attributes = []): Document
{
    $draft = draftInvoice($items, array_merge(['issue_date' => $issued, 'due_date' => $due], $attributes));

    return issueInvoice($draft, $issued);
}

function listDraft(array $items = [[1, 500]], array $attributes = []): Document
{
    return draftInvoice($items, array_merge(['issue_date' => '2026-10-26', 'due_date' => '2026-11-09'], $attributes));
}

function listIds(Assert $page): array
{
    return collect($page->toArray()['props']['invoices'])->pluck('id')->all();
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-27 10:00:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

/** Šest faktur v každém stavu, na kterém záleží segmentům. */
function seedSegments(): array
{
    $paid = listInvoice('2026-10-01', '2026-10-15');
    app(RecordPayment::class)($paid, '1000.00', '2026-10-10');

    $open = listInvoice('2026-10-20', '2026-11-03');

    $overdue = listInvoice('2026-10-01', '2026-10-14');

    $partial = listInvoice('2026-09-20', '2026-10-04', [[1, 2000]]);
    app(RecordPayment::class)($partial, '500.00', '2026-10-02');

    $draft = listDraft();

    $cancelled = listInvoice('2026-10-02', '2026-10-16');
    $cancelled->forceFill(['status' => DocumentStatus::Cancelled])->save();

    return compact('paid', 'open', 'overdue', 'partial', 'draft', 'cancelled');
}

describe('access', function () {
    it('keeps the list behind login', function () {
        auth()->logout();

        $this->get('/faktury')->assertRedirect('/login');
        $this->post('/faktury/hromadne-uhrazeni', ['ids' => [1]])->assertRedirect('/login');
    });
});

describe('segments and counts', function () {
    it('counts every segment for the current filter', function () {
        seedSegments();

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('counts.vse', 6)
            ->where('counts.neuhrazene', 3)
            ->where('counts.po-splatnosti', 2)
            ->where('counts.uhrazene', 1)
            ->where('counts.koncepty', 1)
            ->where('filters.stav', 'vse')
            ->where('filters.rok', '2026'));
    });

    it('lists only the invoices of the chosen segment', function (string $segment, array $names) {
        $rows = seedSegments();

        $this->get('/faktury?stav='.$segment)->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toEqualCanonicalizing(array_map(fn (string $name): int => $rows[$name]->id, $names)),
        );
    })->with([
        'unpaid' => ['neuhrazene', ['open', 'overdue', 'partial']],
        'overdue' => ['po-splatnosti', ['overdue', 'partial']],
        'paid' => ['uhrazene', ['paid']],
        'drafts' => ['koncepty', ['draft']],
    ]);

    it('falls back to all invoices for an unknown segment', function () {
        seedSegments();

        $this->get('/faktury?stav=nesmysl')->assertInertia(fn (Assert $page) => $page
            ->where('filters.stav', 'vse')
            ->where('pagination.total', 6));
    });

    it('does not count an overdue invoice that is fully paid', function () {
        $invoice = listInvoice('2026-10-01', '2026-10-14');
        app(RecordPayment::class)($invoice, '1000.00', '2026-10-20');

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('counts.po-splatnosti', 0)
            ->where('counts.uhrazene', 1));
    });

    it('does not list quotes or other types', function () {
        listInvoice('2026-10-20', '2026-11-03');
        Document::factory()->quote()->create(['issue_date' => '2026-10-20']);
        Document::factory()->create(['type' => DocumentType::Proforma, 'issue_date' => '2026-10-20']);

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page->where('pagination.total', 1));
    });
});

describe('filters', function () {
    it('shows the current year by default and lets the user pick another', function () {
        $current = listInvoice('2026-10-20', '2026-11-03');
        $old = listInvoice('2025-05-10', '2025-05-24');

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('years', [2026, 2025])
            ->tap(fn ($page) => expect(listIds($page))->toBe([$current->id])));

        $this->get('/faktury?rok=2025')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$old->id]),
        );

        $this->get('/faktury?rok=vse')->assertInertia(fn (Assert $page) => $page
            ->where('filters.rok', 'vse')
            ->tap(fn ($page) => expect(listIds($page))->toEqualCanonicalizing([$current->id, $old->id])));
    });

    it('never hides a draft without a date and filters dated drafts like any invoice', function () {
        $undated = Document::factory()->create(['issue_date' => null]);
        $dated = listDraft();

        $this->get('/faktury?rok=2025')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$undated->id]),
        );

        $this->get('/faktury')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toEqualCanonicalizing([$undated->id, $dated->id]),
        );
    });

    it('filters by customer and keeps the counts for that customer', function () {
        $first = Contact::factory()->create(['name' => 'Pekárna Hrubý']);
        $second = Contact::factory()->create(['name' => 'Kavárna U Mlýna']);
        $one = listInvoice('2026-10-20', '2026-11-03', [[1, 1000]], ['contact_id' => $first->id]);
        listInvoice('2026-10-21', '2026-11-04', [[1, 1000]], ['contact_id' => $second->id]);
        listInvoice('2026-10-22', '2026-11-05', [[1, 1000]], ['contact_id' => $second->id]);

        $this->get('/faktury?odberatel='.$first->id)->assertInertia(fn (Assert $page) => $page
            ->where('counts.vse', 1)
            ->where('filters.odberatel', $first->id)
            ->tap(fn ($page) => expect(listIds($page))->toBe([$one->id])));

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('contacts', fn ($contacts) => collect($contacts)->pluck('name')->all() === ['Kavárna U Mlýna', 'Pekárna Hrubý']));
    });
});

describe('search', function () {
    beforeEach(function () {
        $this->pekarna = Contact::factory()->create(['name' => 'Pekárna Hrubý s.r.o.']);
        $this->kavarna = Contact::factory()->create(['name' => 'Kavárna U Mlýna']);
        $this->a = listInvoice('2026-10-12', '2026-10-26', [[1, 24500]], ['contact_id' => $this->pekarna->id]);
        $this->b = listInvoice('2026-10-20', '2026-11-03', [[1, 3200]], ['contact_id' => $this->kavarna->id]);
        DocumentItem::query()->where('document_id', $this->b->id)->update([
            'name' => 'Aktualizace webu a menu',
            'description' => 'Fotografie sortimentu',
        ]);
    });

    it('finds by invoice number', function () {
        $this->get('/faktury?q='.$this->a->number)->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->a->id]),
        );
    });

    it('finds by customer name, also by the name on the issued invoice', function () {
        $this->get('/faktury?q=pekárna')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->a->id]),
        );

        // Přejmenovaný kontakt: na faktuře zůstává jméno ze snapshotu.
        $this->pekarna->update(['name' => 'Nový název']);
        $this->get('/faktury?q=Hrubý')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->a->id]),
        );
    });

    it('finds by item name and description', function () {
        $this->get('/faktury?q=aktualizace')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->b->id]),
        );
        $this->get('/faktury?q=sortimentu')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->b->id]),
        );
    });

    it('finds by amount typed in any common form', function (string $term) {
        $this->get('/faktury?q='.urlencode($term))->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$this->a->id]),
        );
    })->with(['24 500,00 Kč', '24500', '24500.00', '24 500', '24500,00']);

    it('returns nothing for text that matches nothing', function () {
        $this->get('/faktury?q=neexistuje')->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 0)
            ->where('hasInvoices', true));
    });

    it('treats a percent sign in the search as a plain character', function () {
        $this->get('/faktury?q='.urlencode('%'))->assertInertia(fn (Assert $page) => $page->where('pagination.total', 0));
    });
});

describe('totals', function () {
    it('sums issued invoices of the filter and leaves out drafts and cancelled ones', function () {
        seedSegments();

        // issued: paid 1000 (1000 paid), open 1000, overdue 1000, partial 2000 (500 paid)
        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('totals.count', 4)
            ->where('totals.total', '5000.00')
            ->where('totals.paid', '1500.00')
            ->where('totals.unpaid', '3500.00')
            // po splatnosti: neuhrazená část dvou faktur (1000 + 1500)
            ->where('totals.overdue', '2500.00'));
    });

    it('follows the segment and the search', function () {
        seedSegments();

        $this->get('/faktury?stav=po-splatnosti')->assertInertia(fn (Assert $page) => $page
            ->where('totals.count', 2)
            ->where('totals.total', '3000.00')
            ->where('totals.unpaid', '2500.00')
            ->where('totals.overdue', '2500.00'));

        $this->get('/faktury?stav=koncepty')->assertInertia(fn (Assert $page) => $page
            ->where('totals.count', 0)
            ->where('totals.total', '0.00')
            ->where('totals.overdue', '0.00'));
    });

    it('is zero without any invoice', function () {
        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', '0.00')
            ->where('hasInvoices', false));
    });
});

describe('ordering and paging', function () {
    it('puts drafts first, then newest issue date and highest number', function () {
        $older = listInvoice('2026-10-05', '2026-10-19');
        $newerSameDay = listInvoice('2026-10-05', '2026-10-19');
        $latest = listInvoice('2026-10-20', '2026-11-03');
        $draft = listDraft();

        $this->get('/faktury')->assertInertia(
            fn (Assert $page) => expect(listIds($page))->toBe([$draft->id, $latest->id, $newerSameDay->id, $older->id]),
        );
    });

    it('pages by 25', function () {
        foreach (range(1, 27) as $day) {
            Document::factory()->create([
                'status' => DocumentStatus::Issued,
                'issue_date' => '2026-09-'.str_pad((string) (($day % 28) + 1), 2, '0', STR_PAD_LEFT),
                'due_date' => '2026-12-01',
                'total' => '100.00',
                'number' => '2026'.str_pad((string) $day, 3, '0', STR_PAD_LEFT),
                'locked_at' => now(),
            ]);
        }

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 25)
            ->where('pagination.total', 27)
            ->where('pagination.last_page', 2)
            ->where('pagination.from', 1)
            ->where('pagination.to', 25));

        $this->get('/faktury?strana=2')->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 2)
            ->where('pagination.page', 2)
            ->where('pagination.from', 26)
            ->where('pagination.to', 27));
    });
});

describe('rows', function () {
    it('exposes the computed status and days overdue', function () {
        $rows = seedSegments();

        $this->get('/faktury')->assertInertia(function (Assert $page) use ($rows) {
            $byId = collect($page->toArray()['props']['invoices'])->keyBy('id');

            expect($byId[$rows['overdue']->id]['computed_status'])->toBe('overdue')
                ->and($byId[$rows['overdue']->id]['overdue_days'])->toBe(13)
                ->and($byId[$rows['open']->id]['computed_status'])->toBeNull()
                ->and($byId[$rows['draft']->id]['computed_status'])->toBeNull()
                ->and($byId[$rows['cancelled']->id]['computed_status'])->toBeNull()
                ->and($byId[$rows['paid']->id]['computed_status'])->toBeNull()
                ->and($byId[$rows['partial']->id]['overdue_days'])->toBe(23);
        });
    });

    it('takes the description from the first item and the name from the snapshot', function () {
        $invoice = listInvoice('2026-10-20', '2026-11-03');
        DocumentItem::query()->where('document_id', $invoice->id)->update(['name' => 'Návrh obalů, 3 varianty']);

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page
            ->where('invoices.0.description', 'Návrh obalů, 3 varianty')
            ->where('invoices.0.contact', $invoice->buyer_snapshot['name']));
    });

    it('allows deleting only what the rules allow', function () {
        $rows = seedSegments();
        $last = listInvoice('2026-10-25', '2026-11-08');

        $this->get('/faktury?rok=vse')->assertInertia(function (Assert $page) use ($rows, $last) {
            $byId = collect($page->toArray()['props']['invoices'])->keyBy('id');

            expect($byId[$last->id]['can_delete'])->toBeTrue()          // poslední v řadě, neodeslaná
                ->and($byId[$rows['draft']->id]['can_delete'])->toBeTrue()
                ->and($byId[$rows['open']->id]['can_delete'])->toBeFalse() // není poslední v řadě
                ->and($byId[$rows['paid']->id]['can_delete'])->toBeFalse()
                ->and($byId[$rows['partial']->id]['can_delete'])->toBeFalse();
        });
    });

    it('marks which rows can be paid', function () {
        $rows = seedSegments();

        $this->get('/faktury')->assertInertia(function (Assert $page) use ($rows) {
            $byId = collect($page->toArray()['props']['invoices'])->keyBy('id');

            expect($byId[$rows['open']->id]['can_pay'])->toBeTrue()
                ->and($byId[$rows['partial']->id]['can_pay'])->toBeTrue()
                ->and($byId[$rows['paid']->id]['can_pay'])->toBeFalse()
                ->and($byId[$rows['draft']->id]['can_pay'])->toBeFalse()
                ->and($byId[$rows['cancelled']->id]['can_pay'])->toBeFalse();
        });
    });
});

describe('overdue count in the menu', function () {
    it('shares one number of overdue invoices across all years', function () {
        seedSegments();
        listInvoice('2025-03-01', '2025-03-15');

        $this->get('/faktury')->assertInertia(fn (Assert $page) => $page->where('overdueInvoices', 3));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('overdueInvoices', 3));
    });

    it('drops when the invoice is paid', function () {
        $rows = seedSegments();
        app(RecordPayment::class)($rows['overdue'], '1000.00', '2026-10-27');

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('overdueInvoices', 1));
    });
});

describe('bulk payment', function () {
    it('pays the remaining amount of every payable invoice today and reports the skipped ones', function () {
        $rows = seedSegments();

        $this->from('/faktury')
            ->post('/faktury/hromadne-uhrazeni', ['ids' => [
                $rows['open']->id, $rows['partial']->id, $rows['paid']->id, $rows['draft']->id,
            ]])
            ->assertRedirect('/faktury')
            ->assertSessionHas('status', 'Označeno jako uhrazené: 2 faktury. Přeskočeno 2 (koncept, uhrazená nebo stornovaná)');

        $open = $rows['open']->fresh();
        $partial = $rows['partial']->fresh();

        expect($open->status)->toBe(DocumentStatus::Paid)
            ->and($open->paid_total)->toBe('1000.00')
            ->and($open->paid_at->toDateString())->toBe('2026-10-27')
            ->and($partial->status)->toBe(DocumentStatus::Paid)
            ->and($partial->paid_total)->toBe('2000.00')
            ->and($partial->payments()->count())->toBe(2)
            ->and($partial->payments()->get()->last()->amount)->toBe('1500.00')
            ->and($rows['draft']->fresh()->status)->toBe(DocumentStatus::Draft)
            ->and($rows['paid']->fresh()->payments()->count())->toBe(1);
    });

    it('says so for a single invoice', function () {
        $rows = seedSegments();

        $this->from('/faktury')->post('/faktury/hromadne-uhrazeni', ['ids' => [$rows['open']->id]])
            ->assertSessionHas('status', 'Faktura označena jako uhrazená');
    });

    it('reports an error when nothing can be paid', function () {
        $rows = seedSegments();

        $this->from('/faktury')
            ->post('/faktury/hromadne-uhrazeni', ['ids' => [$rows['paid']->id, $rows['draft']->id, $rows['cancelled']->id]])
            ->assertSessionHas('error', ['message' => 'Žádnou z vybraných faktur nejde označit jako uhrazenou (koncepty, uhrazené nebo stornované).'])
            ->assertSessionMissing('status');

        expect($rows['draft']->fresh()->payments()->count())->toBe(0);
    });

    it('ignores unknown ids and other document types', function () {
        $rows = seedSegments();
        $quote = Document::factory()->quote()->create();

        $this->from('/faktury')
            ->post('/faktury/hromadne-uhrazeni', ['ids' => [$quote->id, 999999, $rows['open']->id]])
            ->assertSessionHas('status', 'Faktura označena jako uhrazená. Přeskočeno 2 (koncept, uhrazená nebo stornovaná)');
    });

    it('requires at least one invoice', function () {
        $this->from('/faktury')->post('/faktury/hromadne-uhrazeni', ['ids' => []])
            ->assertSessionHasErrors('ids');
        $this->from('/faktury')->post('/faktury/hromadne-uhrazeni', [])
            ->assertSessionHasErrors('ids');
    });
});
