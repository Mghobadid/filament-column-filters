<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListDonorsTable extends DonorsTable
{
    public function table(Table $table): Table
    {
        return $table->query(Donor::query())->columns([
            TextColumn::make('status')->columnFilter(
                SelectFilter::make('status')->options(DonorStatus::class)->default(DonorStatus::Open->value),
                presentation: 'radio',
            ),
            TextColumn::make('name')->columnFilter(
                SelectFilter::make('name')->options(['Alice' => 'Alice', 'Bob' => 'Bob'])->multiple()->default(['Alice']),
                presentation: 'checkbox',
            ),
        ])->filters([
            SelectFilter::make('status')->options(DonorStatus::class)->default(DonorStatus::Open->value),
        ]);
    }
}
