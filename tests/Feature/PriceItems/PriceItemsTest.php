<?php

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\PriceItem;
use Inertia\Testing\AssertableInertia as Assert;

function priceItemPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Správa webu',
        'note' => 'Úpravy obsahu, zálohy',
        'code' => 'WEB-H',
        'unit' => 'hod.',
        'currency' => 'CZK',
        'unit_price' => '1 400,50',
    ], $overrides);
}

function useItem(PriceItem $item, string $qty, array $document = []): DocumentItem
{
    $doc = Document::factory()->create(array_merge([
        'type' => DocumentType::Invoice,
        'status' => DocumentStatus::Issued,
        'issue_date' => now()->toDateString(),
    ], $document));

    return DocumentItem::factory()->create([
        'document_id' => $doc->id,
        'price_item_id' => $item->id,
        'qty' => $qty,
    ]);
}

beforeEach(function () {
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

function listedNames($response): array
{
    return collect($response->viewData('page')['props']['items'])->pluck('name')->all();
}

describe('access', function () {
    it('redirects guests to login', function () {
        auth()->logout();

        $this->get('/cenik')->assertRedirect('/login');
        $this->getJson('/cenik/hledat')->assertUnauthorized();
    });

    it('renders the price list', function () {
        PriceItem::factory()->create(['name' => 'Logo']);

        $this->get('/cenik')->assertInertia(fn (Assert $page) => $page
            ->component('PriceItems/Index')
            ->has('items', 1)
            ->where('items.0.name', 'Logo')
            ->where('year', (int) now()->format('Y')));
    });
});

describe('create and update', function () {
    it('creates an item with a normalised price', function () {
        $this->post('/cenik', priceItemPayload(['code' => ' WEB-H ']))->assertSessionDoesntHaveErrors();

        $item = PriceItem::firstOrFail();
        expect($item->name)->toBe('Správa webu')
            ->and($item->code)->toBe('WEB-H')
            ->and($item->unit)->toBe('hod.')
            ->and($item->unit_price)->toBe('1400.50')
            ->and($item->currency)->toBe('CZK')
            ->and($item->archived_at)->toBeNull();
    });

    it('accepts a decimal comma and empty optional fields', function () {
        $this->post('/cenik', priceItemPayload(['code' => '', 'note' => '', 'unit' => '', 'unit_price' => '12,5', 'currency' => 'eur']));

        $item = PriceItem::firstOrFail();
        expect($item->code)->toBeNull()
            ->and($item->note)->toBeNull()
            ->and($item->unit)->toBeNull()
            ->and($item->unit_price)->toBe('12.50')
            ->and($item->currency)->toBe('EUR');
    });

    it('validates required fields, currency and price', function () {
        $this->post('/cenik', priceItemPayload(['name' => '', 'currency' => 'USD', 'unit_price' => 'abc']))
            ->assertSessionHasErrors(['name', 'currency', 'unit_price']);
        $this->post('/cenik', priceItemPayload(['unit_price' => '-1']))
            ->assertSessionHasErrors('unit_price');
        $this->post('/cenik', priceItemPayload(['unit_price' => '']))
            ->assertSessionHasErrors('unit_price');

        expect(PriceItem::count())->toBe(0);
    });

    it('keeps the code unique but allows several items without one', function () {
        PriceItem::factory()->create(['code' => 'WEB-H']);

        $this->post('/cenik', priceItemPayload())
            ->assertSessionHasErrors(['code' => 'Tento kód už používá jiná položka.']);

        $this->post('/cenik', priceItemPayload(['code' => '']))->assertSessionDoesntHaveErrors();
        $this->post('/cenik', priceItemPayload(['code' => '', 'name' => 'Jiná']))->assertSessionDoesntHaveErrors();
        expect(PriceItem::count())->toBe(3);
    });

    it('updates an item and lets it keep its own code', function () {
        $item = PriceItem::factory()->create(['code' => 'WEB-H', 'unit_price' => '400.00']);

        $this->put("/cenik/{$item->id}", priceItemPayload(['unit_price' => '450']))
            ->assertSessionDoesntHaveErrors();

        expect($item->fresh()->unit_price)->toBe('450.00');
    });

    it('does not let an update take over another code', function () {
        PriceItem::factory()->create(['code' => 'A']);
        $item = PriceItem::factory()->create(['code' => 'B']);

        $this->put("/cenik/{$item->id}", priceItemPayload(['code' => 'A']))->assertSessionHasErrors('code');
    });

    it('does not change issued documents when the price changes', function () {
        $item = PriceItem::factory()->create(['unit_price' => '400.00']);
        $line = useItem($item, '2');
        $line->forceFill(['unit_price' => '400.00'])->saveQuietly();

        $this->put("/cenik/{$item->id}", priceItemPayload(['code' => 'X', 'unit_price' => '999']));

        expect($line->fresh()->unit_price)->toBe('400.00');
    });
});

describe('search and order', function () {
    it('searches by name and code', function () {
        PriceItem::factory()->create(['name' => 'Návrh loga', 'code' => 'LOGO']);
        PriceItem::factory()->create(['name' => 'Sazba stránky', 'code' => 'DTP-STR']);

        expect(listedNames($this->get('/cenik?q=loga')))->toBe(['Návrh loga'])
            ->and(listedNames($this->get('/cenik?q=dtp')))->toBe(['Sazba stránky'])
            ->and(listedNames($this->get('/cenik?q=nic')))->toBe([])
            ->and(listedNames($this->get('/cenik?q=%25')))->toBe([]);
    });

    it('orders by usage this year, then alphabetically', function () {
        $a = PriceItem::factory()->create(['name' => 'Alfa']);
        $b = PriceItem::factory()->create(['name' => 'Beta']);
        $c = PriceItem::factory()->create(['name' => 'Cecil']);
        PriceItem::factory()->create(['name' => 'Delta']);
        useItem($b, '5');
        useItem($c, '10');
        useItem($a, '99', ['issue_date' => now()->subYear()->toDateString()]);

        expect(listedNames($this->get('/cenik')))->toBe(['Cecil', 'Beta', 'Alfa', 'Delta']);
    });

    it('hides archived items unless asked', function () {
        PriceItem::factory()->create(['name' => 'Aktivní']);
        PriceItem::factory()->create(['name' => 'Starý', 'archived_at' => now()]);

        expect(listedNames($this->get('/cenik')))->toBe(['Aktivní'])
            ->and(listedNames($this->get('/cenik?archivovane=1')))->toHaveCount(2);
    });
});

describe('usage on invoices', function () {
    it('sums quantities on issued invoices of this year only', function () {
        $item = PriceItem::factory()->create(['unit' => 'hod.']);
        useItem($item, '10.5');
        useItem($item, '4', ['status' => DocumentStatus::Paid]);
        useItem($item, '100', ['status' => DocumentStatus::Draft]);
        useItem($item, '100', ['status' => DocumentStatus::Cancelled]);
        useItem($item, '100', ['type' => DocumentType::Quote]);
        useItem($item, '100', ['issue_date' => now()->subYear()->toDateString()]);

        $this->get('/cenik')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.used_qty', '14.5')
            ->where('items.0.is_used', true));
    });

    it('marks unused items', function () {
        PriceItem::factory()->create();

        $this->get('/cenik')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.used_qty', null)
            ->where('items.0.is_used', false));
    });

    it('counts an item used only on a draft as used for archiving', function () {
        $item = PriceItem::factory()->create();
        useItem($item, '1', ['status' => DocumentStatus::Draft]);

        $this->get('/cenik')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.used_qty', null)
            ->where('items.0.is_used', true));
    });
});

describe('delete, archive and restore', function () {
    it('deletes an unused item', function () {
        $item = PriceItem::factory()->create();

        $this->delete("/cenik/{$item->id}")->assertRedirect();

        expect(PriceItem::count())->toBe(0);
    });

    it('archives an item that is used on a document', function () {
        $item = PriceItem::factory()->create();
        $line = useItem($item, '1', ['status' => DocumentStatus::Draft]);

        $this->delete("/cenik/{$item->id}");

        expect($item->fresh()->archived_at)->not->toBeNull()
            ->and($line->fresh()->price_item_id)->toBe($item->id);
    });

    it('restores an archived item', function () {
        $item = PriceItem::factory()->create(['archived_at' => now()]);

        $this->post("/cenik/{$item->id}/obnovit");

        expect($item->fresh()->archived_at)->toBeNull();
    });
});

describe('suggest endpoint', function () {
    it('returns JSON with the fields the invoice editor needs', function () {
        PriceItem::factory()->create(['name' => 'Logo', 'note' => 'Tři koncepty', 'unit' => 'ks', 'unit_price' => '12000.00', 'currency' => 'CZK']);

        $this->getJson('/cenik/hledat')
            ->assertOk()
            ->assertExactJson(['items' => [[
                'id' => PriceItem::first()->id,
                'name' => 'Logo',
                'description' => 'Tři koncepty',
                'unit' => 'ks',
                'unit_price' => '12000.00',
                'currency' => 'CZK',
            ]]]);
    });

    it('returns at most eight items', function () {
        PriceItem::factory()->count(12)->create();

        expect($this->getJson('/cenik/hledat')->json('items'))->toHaveCount(8);
    });

    it('orders by usage and leaves out archived items', function () {
        $low = PriceItem::factory()->create(['name' => 'Málo']);
        $high = PriceItem::factory()->create(['name' => 'Hodně']);
        $archived = PriceItem::factory()->create(['name' => 'Archiv', 'archived_at' => now()]);
        PriceItem::factory()->create(['name' => 'Nepoužito']);
        useItem($low, '1');
        useItem($high, '20');
        useItem($archived, '500');

        expect(collect($this->getJson('/cenik/hledat')->json('items'))->pluck('name')->all())
            ->toBe(['Hodně', 'Málo', 'Nepoužito']);
    });

    it('filters by name and code', function () {
        PriceItem::factory()->create(['name' => 'Návrh loga', 'code' => 'LOGO']);
        PriceItem::factory()->create(['name' => 'Sazba', 'code' => 'DTP']);

        expect($this->getJson('/cenik/hledat?q=dtp')->json('items'))->toHaveCount(1)
            ->and($this->getJson('/cenik/hledat?q=návrh')->json('items.0.name'))->toBe('Návrh loga');
    });
});
