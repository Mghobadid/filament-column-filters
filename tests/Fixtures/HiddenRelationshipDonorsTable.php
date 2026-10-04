<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class HiddenRelationshipDonorsTable extends RelationshipDonorsTable
{
    public function table(Table $table): Table
    {
        $table = parent::table($table)->filtersLayout(FiltersLayout::Hidden);
        $table->getFilter('category')->relationship('category', 'name',
            modifyQueryUsing: fn ($query) => $query->where('name', '!=', 'Excluded'));

        return $table;
    }
}
