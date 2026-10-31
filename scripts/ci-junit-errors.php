<?php

/**
 * CI: z JUnit XML (Pest --log-junit) vypíše každý padlý test jako GitHub anotaci
 * `::error title=<třída::test>::<první 3 řádky zprávy>`. Logy z Actions jdou stáhnout jen
 * ručně, anotace jsou dostupné přes API (gh api repos/…/check-runs/<id>/annotations).
 *
 * Použití: php scripts/ci-junit-errors.php soubor.xml [další.xml …]
 */
$escape = static fn (string $value, bool $property = false): string => strtr($value, array_filter([
    '%' => '%25', "\r" => '%0D', "\n" => '%0A',
    ':' => $property ? '%3A' : null, ',' => $property ? '%2C' : null,
]));

$total = 0;
foreach (array_slice($argv, 1) as $file) {
    if (! is_file($file)) {
        continue;
    }

    $xml = @simplexml_load_file($file);
    if ($xml === false) {
        echo "::error title=junit::Soubor {$file} nejde přečíst.\n";

        continue;
    }

    $label = count($argv) > 2 ? basename($file).' ' : '';

    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        $problem = $case->failure ?? $case->error;
        $text = trim((string) $problem !== '' ? (string) $problem : (string) ($problem['message'] ?? ''));
        $lines = array_slice(array_values(array_filter(array_map('trim', explode("\n", $text)))), 0, 3);
        $title = $label.($case['classname'] ?? $case['class'] ?? '').'::'.($case['name'] ?? '');

        echo '::error title='.$escape($title, true).'::'.$escape(implode("\n", $lines))."\n";
        $total++;
    }
}

echo $total === 0 ? "Žádný padlý test v JUnit.\n" : "Padlých testů: {$total}\n";
