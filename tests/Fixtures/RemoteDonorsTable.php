<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

class RemoteDonorsTable extends DonorsTable
{

    public function table(Table $table): Table
    {
        return $table->query(Donor::query())->columns([
            TextColumn::make('status')->columnFilter(
                ColumnFilter::select()
                    ->options(fn (int $limit): array => array_slice(['open' => 'Open', 'closed' => 'Closed'], 0, $limit, true))
                    ->preloadLimit(1)
                    ->optionsLimit(1)
                    ->getSearchResultsUsing(fn (string $search): array => ['closed' => 'Closed', 'other' => $search])
                    ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key(['open' => 'Open', 'closed' => 'Closed'], array_flip($values))),
            ),
        ]);
    }
}
