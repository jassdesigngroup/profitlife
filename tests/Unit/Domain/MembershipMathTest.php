<?php

use App\Domain\Billing\DTOs\InvoiceLine;
use App\Domain\Memberships\Enums\DurationUnit;
use Carbon\CarbonImmutable;

it('calcula el último día de cada periodo', function (DurationUnit $unit, int $count, string $start, string $end) {
    expect($unit->endOfPeriod(CarbonImmutable::parse($start), $count)->toDateString())->toBe($end);
})->with([
    'un mes' => [DurationUnit::Month, 1, '2026-01-15', '2026-02-14'],
    'tres meses' => [DurationUnit::Month, 3, '2026-10-02', '2027-01-01'],
    'un año' => [DurationUnit::Year, 1, '2026-03-01', '2027-02-28'],
    'mes desde el 31 sin desbordar' => [DurationUnit::Month, 1, '2026-01-31', '2026-02-27'],
    'dos semanas' => [DurationUnit::Week, 2, '2026-10-05', '2026-10-18'],
    'diez días' => [DurationUnit::Day, 10, '2026-10-01', '2026-10-10'],
]);

it('calcula el IVA incluido en el precio', function () {
    $line = new InvoiceLine('Plan', 11900000, taxRateBps: 1900);

    expect($line->totalCents())->toBe(11900000)
        ->and($line->taxCents())->toBe(1900000);
});

it('aplica el descuento antes del IVA incluido', function () {
    $line = new InvoiceLine('Plan', 15000000, discountCents: 3000000);

    expect($line->grossCents())->toBe(15000000)
        ->and($line->totalCents())->toBe(12000000)
        ->and($line->taxCents())->toBe(0);
});
