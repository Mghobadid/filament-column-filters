<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class HiddenDonorsTable extends DonorsTable
{
    public function table(Table $table): Table
    {
        return parent::table($table)->filtersLayout(FiltersLayout::Hidden);
    }
}
