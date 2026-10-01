<?php

use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

it('formats Jalali indicator dates without changing Gregorian filter values', function () {
    $filter = ColumnFilter::date()->jalali();

    expect($filter->formatIndicatorDate('2024-03-20'))->toBe('1403/01/01')
        ->and($filter->formatIndicatorDate('2021-03-20'))->toBe('1399/12/30')
        ->and($filter->jalali(false)->formatIndicatorDate('2024-03-20'))->toBe('2024-03-20');
});
