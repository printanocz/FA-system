{{--
    PDF faktury podle artboardu „PDF faktury — A4“. mPDF neumí flex ani grid, proto je rozložení
    jen z tabulek. Data jsou výhradně ze snapshotů (připravuje je App\Domain\Pdf\InvoicePdf).
    Značka pdf:payment odděluje platební blok, který se přilepí ke spodku poslední strany.
--}}
<style>
    body { font-family: {{ $f['body'] }}; font-size: 9.5pt; color: #0B130A; line-height: 1.35; }
    table { border-collapse: collapse; }
    td, th { vertical-align: top; }
    .label { font-size: 9pt; color: #4A5346; }
    .muted { color: #4A5346; }
    .mono { font-family: {{ $f['mono'] }}; font-size: 9pt; }
    .name { font-size: 11pt; font-weight: bold; }
    .num { text-align: right; white-space: nowrap; }
    .items th { font-size: 9pt; font-weight: bold; color: #4A5346; text-align: left; padding: 0 0 2mm 0; border-bottom: 0.5mm solid #0B130A; }
    .items td { padding: 3mm 0; border-bottom: 0.25mm solid #C9CEC2; }
</style>

<htmlpagefooter name="invoiceFooter">
    <table width="100%" style="border-top: 0.25mm solid #C9CEC2;">
        <tr>
            <td style="padding-top: 2.5mm; font-size: 9pt; color: #4A5346;">{{ $note }}</td>
            <td width="30mm" style="padding-top: 2.5mm; font-size: 9pt; color: #4A5346; text-align: right;">Strana {PAGENO}/{nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpagefooter name="invoiceFooter" value="on" />

<div style="position: absolute; top: 14mm; right: 15mm; width: 14mm; height: 14mm; background-color: #62D230; border-radius: 3.5mm; text-align: center; font-size: 14pt; font-weight: bold; color: #0B130A; padding-top: 3.8mm; line-height: 1;">{{ $initials }}</div>

<div class="label" style="font-size: 10pt;">Faktura č.</div>
<div style="font-family: {{ $f['display'] }}; font-size: 30pt; font-weight: bold; line-height: 1.1;">{{ $number }}</div>

<div style="margin-top: 7mm;">
    <div style="float: left; width: 47%; padding: 4mm 0;">
        <div class="label">Dodavatel</div>
        <div class="name">{{ $supplier['name'] }}</div>
        @foreach ($supplier['lines'] as $line)
            <div>{{ $line }}</div>
        @endforeach
        @if ($supplier['ids'] !== '')
            <div>{{ $supplier['ids'] }}</div>
        @endif
        @if ($supplier['contact'] !== '')
            <div class="muted">{{ $supplier['contact'] }}</div>
        @endif
        @if ($supplier['web'] !== '')
            <div class="muted">{{ $supplier['web'] }}</div>
        @endif
    </div>
    <div style="float: right; width: 47%; padding: 4mm; background-color: #F2F4EC; border-radius: 3.7mm;">
        <div class="label">Odběratel</div>
        <div class="name">{{ $buyer['name'] }}</div>
        @foreach ($buyer['lines'] as $line)
            <div>{{ $line }}</div>
        @endforeach
        @if ($buyer['ids'] !== '')
            <div>{{ $buyer['ids'] }}</div>
        @endif
    </div>
    <div style="clear: both;"></div>
</div>

@foreach (array_chunk($meta, 4) as $row)
    <table width="100%" style="margin-top: {{ $loop->first ? '5mm' : '0' }}; border-top: 0.25mm solid #C9CEC2; {{ $loop->last ? 'border-bottom: 0.25mm solid #C9CEC2;' : '' }}">
        <tr>
            @foreach ($row as $cell)
                <td width="{{ 100 / min(count($meta), 4) }}%" style="padding: 3mm 3mm 3mm 0;">
                    <div class="label">{{ $cell['label'] }}</div>
                    <div class="{{ $cell['mono'] ? 'mono' : '' }}" style="font-weight: bold; {{ $cell['strong'] ? 'font-size: 11pt;' : '' }}">{{ $cell['value'] }}</div>
                </td>
            @endforeach
            @for ($i = count($row); $i < min(count($meta), 4); $i++)
                <td></td>
            @endfor
        </tr>
    </table>
@endforeach

@if ($textBefore)
    <div style="margin-top: 6mm;">{!! nl2br(e($textBefore)) !!}</div>
@endif

<table class="items" width="100%" style="margin-top: 4mm;">
    <thead>
        <tr>
            <th>Označení dodávky</th>
            <th width="16%" style="text-align: right; padding-left: 3mm;">Počet</th>
            <th width="8%" style="padding-left: 1mm;">M.j.</th>
            <th width="17%" style="text-align: right; padding-left: 3mm;">Cena za m.j.</th>
            <th width="17%" style="text-align: right; padding-left: 3mm;">Celkem</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr style="page-break-inside: avoid;">
                <td>
                    {{ $item['name'] }}
                    @if ($item['description'])
                        <div class="label">{!! nl2br(e($item['description'])) !!}</div>
                    @endif
                </td>
                <td class="num" style="padding-left: 3mm;">{{ $item['qty'] }}</td>
                <td style="padding-left: 1mm;">{{ $item['unit'] }}</td>
                <td class="num" style="padding-left: 3mm;">{{ $item['price'] }}</td>
                <td class="num" style="padding-left: 3mm;">{{ $item['total'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($textAfter)
    <div class="muted" style="margin-top: 4mm;">{!! nl2br(e($textAfter)) !!}</div>
@endif

<!--pdf:payment-->
{{-- Celkem k úhradě: absolutně u spodního okraje poslední strany (v tabulce by se zaoblení ztratilo). --}}
<div style="position: absolute; right: 15mm; bottom: {{ $bottom }}mm; width: 62mm; text-align: right;">
    @if ($discount)
        <div class="label">Sleva {{ $discount }}</div>
    @endif
    @if ($rounding)
        <div class="label">Zaokrouhlení {{ $rounding }}</div>
    @endif
    <div style="margin-top: 2mm; background-color: #62D230; border-radius: 5.8mm; padding: 5mm 5mm; text-align: right; color: #0B130A;">
        <div style="font-size: 10pt; font-weight: bold;">Celkem k úhradě</div>
        <div style="font-family: {{ $f['display'] }}; font-size: {{ $totalSize }}pt; font-weight: bold; line-height: 1.2; white-space: nowrap;">{{ $total }}&nbsp;{{ $symbol }}</div>
    </div>
</div>
@if ($bank)
    <table>
        <tr>
            @if ($qr)
                <td style="width: 32mm; text-align: center; padding-right: 4mm;">
                    <img src="{{ $qr }}" width="32mm" height="32mm" />
                    <div style="font-size: 9pt; font-weight: bold;">QR Platba</div>
                </td>
            @endif
            <td style="vertical-align: bottom;">
                <table>
                    <tr><td class="label" style="padding: 0 3mm 1mm 0; white-space: nowrap;">Bankovní účet</td><td class="mono" style="padding-bottom: 1mm; font-weight: bold;">{{ $bank['account'] }}</td></tr>
                    @if ($bank['iban'])
                        <tr><td class="label" style="padding: 0 3mm 1mm 0; white-space: nowrap;">IBAN</td><td class="mono" style="padding-bottom: 1mm;">{{ $bank['iban'] }}</td></tr>
                    @endif
                    @if ($bank['swift'])
                        <tr><td class="label" style="padding: 0 3mm 1mm 0; white-space: nowrap;">SWIFT</td><td class="mono" style="padding-bottom: 1mm;">{{ $bank['swift'] }}</td></tr>
                    @endif
                    @if ($bank['vs'])
                        <tr><td class="label" style="padding: 0 3mm 1mm 0; white-space: nowrap;">Variabilní symbol</td><td class="mono" style="padding-bottom: 1mm;">{{ $bank['vs'] }}</td></tr>
                    @endif
                    @if ($bank['ks'])
                        <tr><td class="label" style="padding: 0 3mm 1mm 0; white-space: nowrap;">Konstantní symbol</td><td class="mono" style="padding-bottom: 1mm;">{{ $bank['ks'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
@endif
