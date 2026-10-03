<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

class RelationshipDonorsTable extends DonorsTable
{
    public function table(Table $table): Table
    {
        return $table->query(Donor::query())->columns([
            TextColumn::make('category.name')->columnFilter(ColumnFilter::select()->syncWith('category')),
            TextColumn::make('status')->columnFilter(
                SelectFilter::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])
                    ->default('open')->resetState(['value' => 'open']),
            ),
        ])->filters([
            SelectFilter::make('category')->relationship('category', 'name')->searchable()->preload()->optionsLimit(2),
        ]);
    }
}
