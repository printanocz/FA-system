<?php

use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** Vystavená faktura s pevným datem, částkou a úhradou (bez generování čísla a PDF). */
function dashInvoice(string $issued, string $total, array $attributes = []): Document
{
    static $sequence = 0;
    $sequence++;
    $year = (int) substr($issued, 0, 4);

    return Document::factory()->create(array_merge([
        'type' => DocumentType::Invoice,
        'status' => DocumentStatus::Issued,
        'number' => sprintf('%d%03d', $year, $sequence),
        'number_year' => $year,
        'number_seq' => $sequence,
        'issue_date' => $issued,
        'due_date' => Carbon::parse($issued)->addDays(14)->toDateString(),
        'total' => $total,
        'paid_total' => '0.00',
        'locked_at' => now(),
        'buyer_snapshot' => ['name' => 'Pekárna Hrubý s.r.o.'],
        'contact_id' => null,
    ], $attributes));
}

function dashboard(string $today, string $period = 'rok'): array
{
    return (new DashboardQuery(Carbon::parse($today)))->data($period);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-31 10:00:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

describe('empty state', function () {
    it('is empty without an issued invoice', function () {
        Document::factory()->create(['type' => DocumentType::Invoice, 'status' => DocumentStatus::Draft]);
        dashInvoice('2026-03-01', '1000', ['status' => DocumentStatus::Cancelled]);

        expect(dashboard('2026-10-31'))->toMatchArray(['empty' => true]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('empty', true)->missing('cards'));
    });

    it('shows data once an invoice is issued', function () {
        dashInvoice('2026-03-01', '1000');

        expect(dashboard('2026-10-31')['empty'])->toBeFalse();
    });
});

describe('periods and year over year', function () {
    beforeEach(function () {
        dashInvoice('2025-01-10', '10000');
        dashInvoice('2025-10-05', '4000');
        dashInvoice('2025-10-31', '1000');
        dashInvoice('2025-11-15', '7000');
        dashInvoice('2026-01-20', '20000');
        dashInvoice('2026-07-01', '5000');
        dashInvoice('2026-10-03', '6000');
        dashInvoice('2026-10-31', '2000');
    });

    it('sums the year to date against the same days last year', function () {
        $card = dashboard('2026-10-31', 'rok')['cards']['billed'];

        // Letos 1. 1.–31. 10.: 20000 + 5000 + 6000 + 2000; loni 1. 1.–31. 10.: 10000 + 4000 + 1000 (listopad se nepočítá).
        expect($card)->toMatchArray(['amount' => '33000.00', 'previous' => '15000.00', 'change' => 120, 'from' => '2026-01-01', 'to' => '2026-10-31']);
    });

    it('sums the quarter and the month', function () {
        expect(dashboard('2026-10-31', 'kvartal')['cards']['billed'])
            ->toMatchArray(['amount' => '8000.00', 'previous' => '5000.00', 'change' => 60, 'from' => '2026-10-01'])
            ->and(dashboard('2026-10-31', 'mesic')['cards']['billed'])
            ->toMatchArray(['amount' => '8000.00', 'previous' => '5000.00', 'change' => 60]);
    });

    it('compares only up to the same day of last year', function () {
        // 15. 10.: letos 6000 (3. 10.), loni 4000 (5. 10.); faktura z 31. 10. loni se ještě nepočítá.
        expect(dashboard('2026-10-15', 'mesic')['cards']['billed'])
            ->toMatchArray(['amount' => '6000.00', 'previous' => '4000.00', 'change' => 50]);
    });

    it('fills the month card independently of the selected period', function () {
        $data = dashboard('2026-10-31', 'rok')['cards'];

        expect($data['month'])->toMatchArray(['amount' => '8000.00', 'previous' => '5000.00', 'change' => 60]);
    });

    it('hides the percentage without last year data', function () {
        expect(dashboard('2026-07-31', 'mesic')['cards']['billed'])
            ->toMatchArray(['amount' => '5000.00', 'previous' => '0.00', 'change' => null]);
    });

    it('rounds the change to whole percent and allows a decline', function () {
        // 1. 1.–15. 1.: letos 0, loni 10000 → -100 %.
        expect(dashboard('2026-01-15', 'rok')['cards']['billed']['change'])->toBe(-100);
        expect(DashboardQuery::change(BigDecimal::of('1005'), BigDecimal::of('1000')))->toBe(1)
            ->and(DashboardQuery::change(BigDecimal::of('1004'), BigDecimal::of('1000')))->toBe(0)
            ->and(DashboardQuery::change(BigDecimal::of('1'), BigDecimal::of('0')))->toBeNull();
    });
});

describe('period boundaries', function () {
    it('handles the year boundary on 1 January', function () {
        dashInvoice('2025-01-01', '3000');
        dashInvoice('2025-12-31', '9000');
        dashInvoice('2026-01-01', '4000');

        expect(dashboard('2026-01-01', 'rok')['cards']['billed'])
            ->toMatchArray(['amount' => '4000.00', 'previous' => '3000.00', 'change' => 33, 'from' => '2026-01-01', 'to' => '2026-01-01']);
        // Prosinec loni patří jen do měsíčního grafu; 31. 12. 2025 není v období 1. 1.–1. 1.
    });

    it('handles the first day of a quarter and the end of the year', function () {
        dashInvoice('2025-12-31', '1000');
        dashInvoice('2026-12-31', '2000');

        expect(dashboard('2026-12-31', 'rok')['cards']['billed'])->toMatchArray(['amount' => '2000.00', 'previous' => '1000.00', 'change' => 100])
            ->and(dashboard('2026-12-31', 'kvartal')['cards']['billed']['from'])->toBe('2026-10-01')
            ->and(dashboard('2026-04-01', 'kvartal')['cards']['billed']['from'])->toBe('2026-04-01');
    });

    it('maps 29 February to 28 February of the previous year', function () {
        dashInvoice('2027-02-27', '1000');
        dashInvoice('2027-02-28', '2000');
        dashInvoice('2027-03-01', '5000');
        dashInvoice('2028-02-29', '4000');

        $ranges = (new DashboardQuery(Carbon::parse('2028-02-29')))->ranges('rok');
        expect($ranges['current'][1]->toDateString())->toBe('2028-02-29')
            ->and($ranges['previous'][1]->toDateString())->toBe('2027-02-28');

        expect(dashboard('2028-02-29', 'mesic')['cards']['billed'])
            ->toMatchArray(['amount' => '4000.00', 'previous' => '3000.00', 'change' => 33]);
    });

    it('counts invoices issued on the last day of the period', function () {
        dashInvoice('2026-10-31', '1000');
        dashInvoice('2026-11-01', '9999');

        expect(dashboard('2026-10-31', 'mesic')['cards']['billed']['amount'])->toBe('1000.00');
    });
});

describe('what is counted', function () {
    it('ignores drafts, cancelled invoices, other types and unfinished invoices', function () {
        dashInvoice('2026-10-02', '1000');
        dashInvoice('2026-10-02', '2000', ['status' => DocumentStatus::Cancelled]);
        dashInvoice('2026-10-02', '3000', ['status' => DocumentStatus::Draft]);
        dashInvoice('2026-10-02', '4000', ['type' => DocumentType::Proforma]);
        dashInvoice('2026-10-02', '5000', ['type' => DocumentType::CreditNote]);
        dashInvoice('2026-10-02', '6000', ['type' => DocumentType::Quote, 'status' => DocumentStatus::Created]);
        dashInvoice('2026-10-03', '700', ['status' => DocumentStatus::Paid, 'paid_total' => '700.00']);

        $data = dashboard('2026-10-31');

        expect($data['cards']['billed']['amount'])->toBe('1700.00')
            ->and($data['vat']['turnover'])->toBe('1700.00')
            ->and($data['cards']['unpaid'])->toMatchArray(['amount' => '1000.00', 'count' => 1])
            ->and(collect($data['recent'])->pluck('status')->all())->not->toContain('draft');
    });

    it('counts partially paid invoices in full as billed but only the rest as unpaid', function () {
        dashInvoice('2026-10-02', '10000', ['status' => DocumentStatus::PartiallyPaid, 'paid_total' => '4000.00', 'due_date' => '2026-11-10']);
        dashInvoice('2026-10-05', '2000', ['status' => DocumentStatus::Paid, 'paid_total' => '2000.00']);

        $cards = dashboard('2026-10-31')['cards'];

        expect($cards['billed']['amount'])->toBe('12000.00')
            ->and($cards['unpaid'])->toMatchArray(['amount' => '6000.00', 'count' => 1])
            ->and($cards['overdue'])->toMatchArray(['amount' => '0.00', 'count' => 0]);
    });

    it('sums amounts exactly with decimals', function () {
        dashInvoice('2026-10-02', '0.10');
        dashInvoice('2026-10-03', '0.20');
        dashInvoice('2026-10-04', '12345.67');

        expect(dashboard('2026-10-31', 'mesic')['cards']['billed']['amount'])->toBe('12345.97');
    });
});

describe('unpaid and overdue', function () {
    it('reports unpaid with the nearest upcoming due date', function () {
        dashInvoice('2026-09-01', '1000', ['due_date' => '2026-09-15']);
        dashInvoice('2026-10-20', '2000', ['due_date' => '2026-11-03']);
        dashInvoice('2026-10-25', '4000', ['due_date' => '2026-11-01']);

        $unpaid = dashboard('2026-10-31')['cards']['unpaid'];

        expect($unpaid)->toMatchArray(['amount' => '7000.00', 'count' => 3, 'nearest_due' => ['date' => '2026-11-01', 'days' => 1]]);
    });

    it('treats a due date of today as not overdue and as the nearest due date', function () {
        dashInvoice('2026-10-17', '1500', ['due_date' => '2026-10-31']);

        $cards = dashboard('2026-10-31')['cards'];

        expect($cards['unpaid']['nearest_due'])->toBe(['date' => '2026-10-31', 'days' => 0])
            ->and($cards['overdue']['count'])->toBe(0);
    });

    it('has no nearest due date when everything is overdue', function () {
        dashInvoice('2026-09-01', '1000', ['due_date' => '2026-09-15']);

        expect(dashboard('2026-10-31')['cards']['unpaid']['nearest_due'])->toBeNull();
    });

    it('lists the overdue invoices, longest overdue first, at most three', function () {
        foreach (range(1, 7) as $i) {
            dashInvoice('2026-08-0'.$i, (string) ($i * 1000), ['due_date' => '2026-09-0'.$i, 'buyer_snapshot' => ['name' => "Klient {$i}"]]);
        }

        $data = dashboard('2026-10-31');

        expect($data['cards']['overdue'])->toMatchArray(['amount' => '28000.00', 'count' => 7])
            ->and($data['overdue_list'])->toHaveCount(3)
            ->and($data['overdue_list'][0])->toMatchArray(['contact' => 'Klient 1', 'overdue_days' => 60, 'remaining' => '1000.00', 'due_date' => '2026-09-01'])
            ->and(collect($data['overdue_list'])->pluck('contact')->all())->toBe(['Klient 1', 'Klient 2', 'Klient 3']);
    });

    it('uses the remaining amount of a partially paid overdue invoice', function () {
        dashInvoice('2026-09-01', '10000', ['status' => DocumentStatus::PartiallyPaid, 'paid_total' => '2500.00', 'due_date' => '2026-09-15']);

        $data = dashboard('2026-10-31');

        expect($data['cards']['overdue'])->toMatchArray(['amount' => '7500.00', 'count' => 1])
            ->and($data['overdue_list'][0]['remaining'])->toBe('7500.00');
    });
});

describe('currencies', function () {
    it('leaves foreign currency invoices out of every sum and notes them', function () {
        dashInvoice('2026-10-02', '1000');
        dashInvoice('2026-10-03', '50000', ['currency' => 'EUR']);
        dashInvoice('2026-10-04', '70000', ['currency' => 'EUR']);
        dashInvoice('2026-10-05', '9000', ['currency' => 'USD']);
        dashInvoice('2025-10-05', '400', ['currency' => 'EUR']);

        $data = dashboard('2026-10-31');

        expect($data['cards']['billed'])->toMatchArray(['amount' => '1000.00', 'previous' => '0.00', 'change' => null])
            ->and($data['cards']['billed']['foreign'])->toBe([['currency' => 'EUR', 'count' => 2], ['currency' => 'USD', 'count' => 1]])
            ->and($data['cards']['month']['foreign'])->toBe([['currency' => 'EUR', 'count' => 2], ['currency' => 'USD', 'count' => 1]])
            ->and($data['vat']['turnover'])->toBe('1000.00')
            ->and($data['chart']['total'])->toBe('1000.00')
            ->and(collect($data['chart']['months'])->firstWhere('month', 10)['current'])->toBe('1000.00');
    });

    it('has no foreign note when everything is in CZK', function () {
        dashInvoice('2026-10-02', '1000');

        expect(dashboard('2026-10-31')['cards']['billed']['foreign'])->toBe([]);
    });

    it('counts only CZK in unpaid and overdue and notes the rest', function () {
        dashInvoice('2026-09-01', '1000', ['due_date' => '2026-09-15']);
        dashInvoice('2026-09-01', '99000', ['due_date' => '2026-09-15', 'currency' => 'EUR']);
        dashInvoice('2026-10-20', '5000', ['due_date' => '2026-11-10', 'currency' => 'EUR']);

        $data = dashboard('2026-10-31');

        expect($data['cards']['unpaid'])->toMatchArray(['amount' => '1000.00', 'count' => 1])
            ->and($data['cards']['unpaid']['foreign'])->toBe([['currency' => 'EUR', 'count' => 2]])
            ->and($data['cards']['overdue'])->toMatchArray(['amount' => '1000.00', 'count' => 1])
            ->and($data['cards']['overdue']['foreign'])->toBe([['currency' => 'EUR', 'count' => 1]])
            ->and($data['overdue_list'])->toHaveCount(1)
            ->and($data['cards']['unpaid']['nearest_due'])->toBeNull();
    });
});

describe('chart', function () {
    it('has twelve months, this year and last year, with future months empty', function () {
        dashInvoice('2025-03-10', '1000');
        dashInvoice('2025-12-10', '3000');
        dashInvoice('2026-03-05', '2000');
        dashInvoice('2026-03-25', '500');
        dashInvoice('2026-10-02', '800');

        $chart = dashboard('2026-10-31')['chart'];
        $months = collect($chart['months'])->keyBy('month');

        expect($chart['months'])->toHaveCount(12)
            ->and($chart['year'])->toBe(2026)
            ->and($months[3])->toMatchArray(['current' => '2500.00', 'previous' => '1000.00'])
            ->and($months[10])->toMatchArray(['current' => '800.00', 'previous' => '0.00'])
            ->and($months[12])->toMatchArray(['current' => null, 'previous' => '3000.00'])
            ->and($months[11]['current'])->toBeNull()
            ->and($months[1]['current'])->toBe('0.00')
            ->and($chart['strongest'])->toBe(['month' => 3, 'amount' => '2500.00'])
            ->and($chart['total'])->toBe('3300.00');
    });

    it('has no strongest month without a billed month this year', function () {
        dashInvoice('2025-03-10', '1000');
        dashInvoice('2026-10-02', '5', ['status' => DocumentStatus::Cancelled]);

        expect(dashboard('2026-10-31')['chart']['strongest'])->toBeNull();
    });
});

describe('VAT registration limit', function () {
    it('reports percent, remaining amount and no warning under 80 %', function () {
        dashInvoice('2026-02-01', '1000000.00');

        $vat = dashboard('2026-10-31')['vat'];

        expect($vat)->toMatchArray(['percent' => 50, 'remaining' => '1000000.00', 'warning' => false, 'exceeded' => false, 'limit' => '2000000', 'immediate_limit' => '2536500']);
    });

    it('warns from 80 % exactly', function () {
        dashInvoice('2026-02-01', '1599999.99');
        expect(dashboard('2026-10-31')['vat']['warning'])->toBeFalse();

        dashInvoice('2026-02-02', '0.01');
        $vat = dashboard('2026-10-31')['vat'];

        expect($vat)->toMatchArray(['percent' => 80, 'warning' => true, 'exceeded' => false, 'remaining' => '400000.00']);
    });

    it('counts only the calendar year and shows nothing remaining above the limit', function () {
        dashInvoice('2025-11-01', '1900000');
        dashInvoice('2026-01-02', '2000000');
        expect(dashboard('2026-10-31')['vat'])->toMatchArray(['turnover' => '2000000.00', 'exceeded' => false, 'warning' => true]);

        dashInvoice('2026-01-03', '0.01');
        $vat = dashboard('2026-10-31')['vat'];
        expect($vat)->toMatchArray(['exceeded' => true, 'exceeded_immediate' => false, 'remaining' => '0.00']);

        dashInvoice('2026-01-04', '536500');
        expect(dashboard('2026-10-31')['vat']['exceeded_immediate'])->toBeTrue();
    });

    it('reads both limits from config/vat.php', function () {
        expect(config('vat.registration_limit'))->toBe('2000000')
            ->and(config('vat.immediate_limit'))->toBe('2536500');

        config(['vat.registration_limit' => '1000000']);
        dashInvoice('2026-02-01', '500000');

        expect(dashboard('2026-10-31')['vat'])->toMatchArray(['percent' => 50, 'remaining' => '500000.00']);
    });
});

describe('VAT turnover by taxable date', function () {
    it('counts an invoice by its taxable date, not by the issue date', function () {
        // Vystaveno loni, zdanitelné plnění letos: do limitu letošního roku patří, do karet loňska také (podle vystavení).
        dashInvoice('2025-12-30', '300000', ['taxable_date' => '2026-01-02']);
        // Vystaveno letos, zdanitelné plnění loni: do limitu nepatří, do karet letos ano.
        dashInvoice('2026-01-03', '200000', ['taxable_date' => '2025-12-30']);
        // Bez data plnění platí datum vystavení.
        dashInvoice('2026-02-01', '1000');

        $data = dashboard('2026-10-31');

        expect($data['vat']['turnover'])->toBe('301000.00')
            ->and($data['cards']['billed']['amount'])->toBe('201000.00')
            ->and($data['cards']['billed']['previous'])->toBe('0.00')
            ->and($data['chart']['total'])->toBe('201000.00');
    });

    it('ignores taxable dates in other years and counts a later date in the same year', function () {
        dashInvoice('2026-03-01', '5000', ['taxable_date' => '2026-12-15']);
        dashInvoice('2026-03-02', '7000', ['taxable_date' => '2027-01-05']);

        expect(dashboard('2026-10-31')['vat']['turnover'])->toBe('5000.00');
    });

    it('does not let an invoice issued in the future leak into the cards', function () {
        dashInvoice('2026-11-20', '9000', ['taxable_date' => '2026-10-20']);

        $data = dashboard('2026-10-31');

        expect($data['vat']['turnover'])->toBe('9000.00')
            ->and($data['cards']['billed']['amount'])->toBe('0.00')
            ->and($data['chart']['total'])->toBe('0.00');
    });
});

describe('recent documents', function () {
    it('lists the five latest issued invoices with the list formats', function () {
        foreach (range(1, 6) as $i) {
            dashInvoice("2026-10-0{$i}", (string) ($i * 100), ['buyer_snapshot' => ['name' => "Odběratel {$i}"]]);
        }
        dashInvoice('2026-10-20', '1', ['status' => DocumentStatus::Draft]);
        dashInvoice('2026-09-01', '3000', ['due_date' => '2026-09-15', 'buyer_snapshot' => ['name' => 'Dlužník']]);

        $recent = dashboard('2026-10-31')['recent'];

        expect($recent)->toHaveCount(5)
            ->and($recent[0])->toHaveKey('description')
            ->and(collect($recent)->pluck('contact')->all())->toBe(['Odběratel 6', 'Odběratel 5', 'Odběratel 4', 'Odběratel 3', 'Odběratel 2'])
            ->and($recent[0])->toHaveKeys(['id', 'number', 'status', 'computed_status', 'overdue_days', 'contact', 'issue_date', 'due_date', 'total', 'currency']);
    });

    it('uses the name of the first item as the description', function () {
        // Položky jdou přidat jen do nezamčeného dokladu, zamkne se až potom.
        $invoice = dashInvoice('2026-10-01', '3000', ['locked_at' => null]);
        DocumentItem::factory()->create(['document_id' => $invoice->id, 'position' => 2, 'name' => 'Druhá položka']);
        DocumentItem::factory()->create(['document_id' => $invoice->id, 'position' => 1, 'name' => 'Návrh obalů, 3 varianty']);
        DB::table('documents')->where('id', $invoice->id)->update(['locked_at' => now()]);
        dashInvoice('2026-09-01', '100');

        $recent = dashboard('2026-10-31')['recent'];

        expect($recent[0]['description'])->toBe('Návrh obalů, 3 varianty')
            ->and($recent[1]['description'])->toBeNull();
    });

    it('marks an overdue invoice with the days overdue', function () {
        dashInvoice('2026-10-01', '3000', ['due_date' => '2026-10-15']);

        $row = dashboard('2026-10-31')['recent'][0];

        expect($row)->toMatchArray(['computed_status' => 'overdue', 'overdue_days' => 16]);
    });
});

describe('the page', function () {
    it('uses the year by default and honors ?obdobi=', function () {
        dashInvoice('2026-03-01', '1000');
        dashInvoice('2026-10-02', '500');

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('period', 'rok')->where('cards.billed.amount', '1500.00'));
        $this->get('/?obdobi=mesic')->assertInertia(fn (Assert $page) => $page->where('period', 'mesic')->where('cards.billed.amount', '500.00'));
        $this->get('/?obdobi=kvartal')->assertInertia(fn (Assert $page) => $page->where('period', 'kvartal')->where('cards.billed.amount', '500.00'));
        $this->get('/?obdobi=nesmysl')->assertInertia(fn (Assert $page) => $page->where('period', 'rok'));
    });

    it('requires a login', function () {
        auth()->logout();

        $this->get('/')->assertRedirect();
    });

    it('loads in at most eight queries without N+1', function () {
        foreach (range(1, 12) as $i) {
            dashInvoice(sprintf('2026-%02d-05', min($i, 10)), '1000', ['due_date' => '2026-09-01', 'buyer_snapshot' => ['name' => "Klient {$i}"]]);
        }

        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(13, 40) as $i) {
            dashInvoice('2026-08-05', '1000', ['due_date' => '2026-09-01', 'buyer_snapshot' => ['name' => "Klient {$i}"]]);
        }
        DB::flushQueryLog();
        $this->get('/')->assertOk();
        $many = count(DB::getQueryLog());

        expect($many)->toBe($few)->and($many)->toBeLessThanOrEqual(8);
    });
});

describe('reminder draft', function () {
    it('returns the prepared reminder for an overdue invoice', function () {
        CompanyProfile::current() ?? CompanyProfile::factory()->create(['name' => 'Adam Fuják', 'email' => 'jsem@adamfujak.cz']);
        $contact = Contact::factory()->create(['name' => 'Pekárna Hrubý s.r.o.', 'email' => 'objednavky@pekarna.example']);
        $invoice = dashInvoice('2026-09-01', '24500', ['due_date' => '2026-09-15', 'contact_id' => $contact->id, 'vs' => '2026061', 'bank_account_snapshot' => ['account_number' => '123456789', 'bank_code' => '0100']]);

        $this->getJson("/faktury/{$invoice->id}/email-navrh")
            ->assertOk()
            ->assertJsonPath('can_remind', true)
            ->assertJsonPath('to', 'objednavky@pekarna.example')
            ->assertJsonStructure(['reminder' => ['subject', 'body'], 'invoice' => ['subject', 'body'], 'attachment', 'last_reminder_at']);
    });

    it('is 404 for something that is not an invoice', function () {
        $quote = Document::factory()->quote()->create();

        $this->getJson("/faktury/{$quote->id}/email-navrh")->assertNotFound();
    });
});
