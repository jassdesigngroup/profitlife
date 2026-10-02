<?php

use App\Support\Money;

it('guarda el valor en centavos', function () {
    $money = Money::of(150000, 'COP');

    expect($money->cents)->toBe(15000000)->and($money->currency)->toBe('COP');
});

it('opera solo con la misma moneda', function () {
    $total = Money::ofCents(1000, 'COP')->add(Money::ofCents(500, 'COP'))->subtract(Money::ofCents(200, 'COP'));

    expect($total->cents)->toBe(1300)
        ->and(fn () => Money::ofCents(1, 'COP')->add(Money::ofCents(1, 'USD')))->toThrow(InvalidArgumentException::class);
});

it('calcula impuestos en puntos básicos con redondeo al centavo', function () {
    expect(Money::ofCents(15000000, 'COP')->percentageBps(1900)->cents)->toBe(2850000)
        ->and(Money::ofCents(333, 'COP')->percentageBps(1900)->cents)->toBe(63);
});

it('formatea en pesos colombianos', function () {
    $formatted = Money::ofCents(15000000, 'COP')->format('es_CO');

    expect($formatted)->toContain('150.000')->and($formatted)->toContain('$');
});

it('rechaza monedas no válidas', function () {
    Money::ofCents(100, 'pesos');
})->throws(InvalidArgumentException::class);

it('es inmutable y comparable', function () {
    $a = Money::ofCents(100, 'COP');
    $b = $a->multiply(3);

    expect($a->cents)->toBe(100)
        ->and($b->equals(Money::ofCents(300, 'COP')))->toBeTrue()
        ->and(Money::zero('COP')->isZero())->toBeTrue()
        ->and(Money::ofCents(-1, 'COP')->isNegative())->toBeTrue();
});
