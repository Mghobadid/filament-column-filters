<?php

require __DIR__ . '/../src/Filters/ColumnFilter.php';
require __DIR__ . '/../src/Filters/DateColumnFilter.php';

use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

$filter = ColumnFilter::date()->jalali();
foreach (['2024-03-20' => '1403/01/01', '2021-03-20' => '1399/12/30'] as $input => $expected) {
    if ($filter->formatIndicatorDate($input) !== $expected) {
        throw new RuntimeException("Incorrect Jalali indicator for {$input}");
    }
}
if ($filter->jalali(false)->formatIndicatorDate('2024-03-20') !== '2024-03-20') {
    throw new RuntimeException('Gregorian mode changed');
}
echo "Jalali indicator smoke tests passed.\n";
