<?php

namespace Database\Factories;

use App\Domain\Documents\DocumentType;
use App\Models\NumberSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NumberSeries> */
class NumberSeriesFactory extends Factory
{
    protected $model = NumberSeries::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'doc_type' => DocumentType::Invoice,
            'prefix' => '',
            'format' => '{YYYY}{NNN}',
            'next_number' => 1,
            'reset_yearly' => true,
            'last_year' => null,
            'is_default' => true,
        ];
    }

    public function forType(DocumentType $type): static
    {
        return $this->state(['doc_type' => $type]);
    }
}
