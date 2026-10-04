<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class LayoutDonorsTable extends DonorsTable
{
    public string $layout = 'Hidden';

    public bool $deferred = true;

    public function table(Table $table): Table
    {
        $layout = collect(FiltersLayout::cases())->first(fn ($case) => $case->name === $this->layout);

        return parent::table($table)->filtersLayout($layout)->deferFilters($this->deferred);
    }
}
