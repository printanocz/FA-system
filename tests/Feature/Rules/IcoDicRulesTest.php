<?php

use App\Rules\Dic;
use App\Rules\Ico;
use Illuminate\Support\Facades\Validator;

function rulePasses(object $rule, mixed $value): bool
{
    return Validator::make(['v' => $value], ['v' => [$rule]])->passes();
}

it('accepts valid IČO', function (string $ico) {
    expect(rulePasses(new Ico, $ico))->toBeTrue();
})->with(['27082440', '45274649', '00000001', '25596641']);

it('rejects invalid IČO', function (mixed $ico) {
    expect(rulePasses(new Ico, $ico))->toBeFalse();
})->with(['bad checksum' => '27082441', 'seven digits' => '2708244', 'nine digits' => '270824400', 'letters' => '2708244A', 'zeros' => '00000000', 'null' => [null], 'array' => [[1]]]);

it('tolerates spaces in IČO only through normalize', function () {
    expect(Ico::normalize('270 82 440'))->toBe('27082440')
        ->and(Ico::isValid('270 82 440'))->toBeFalse();
});

it('accepts valid DIČ', function (string $dic) {
    expect(rulePasses(new Dic, $dic))->toBeTrue();
})->with(['CZ27082440', 'CZ6005151234', 'CZ600515123', 'cz27082440']);

it('rejects invalid DIČ', function (mixed $dic) {
    expect(rulePasses(new Dic, $dic))->toBeFalse();
})->with(['no prefix' => '27082440', 'SK prefix' => 'SK27082440', 'too short' => 'CZ1234567', 'too long' => 'CZ12345678901', 'letters' => 'CZ2708244A']);

it('normalizes DIČ to upper case without spaces', function () {
    expect(Dic::normalize(' cz 27082440 '))->toBe('CZ27082440');
});

it('explains the failure in Czech', function () {
    $validator = Validator::make(['ico' => '1'], ['ico' => [new Ico]]);

    expect($validator->errors()->first('ico'))->toContain('IČO');
});
