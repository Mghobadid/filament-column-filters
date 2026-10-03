<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

class SyncedRemoteDonorsTable extends DonorsTable
{
    public function table(Table $table): Table
    {
        return $table->query(Donor::query())
            ->columns([TextColumn::make('status')->columnFilter(ColumnFilter::select()->syncWith('status'))])
            ->filters([
                SelectFilter::make('status')->multiple()->searchable()
                    ->options(['open' => 'Open'])
                    ->getSearchResultsUsing(fn (string $search): array => ['closed' => 'Closed'])
                    ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key(['closed' => 'Closed'], array_flip($values))),
            ]);
    }
}
