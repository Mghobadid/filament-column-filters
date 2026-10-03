<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

class ComposedDonorsTable extends DonorsTable
{
    public function table(Table $table): Table
    {
        return $table->query(Donor::query())->columns([
            TextColumn::make('status')->columnFilter(
                SelectFilter::make('status')->multiple()->searchable()
                    ->options(['Statuses' => ['open' => 'Open', 'closed' => 'Closed']])
                    ->getSearchResultsUsing(fn (string $search): array => ['closed' => 'Closed'])
                    ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key(['closed' => 'Closed'], array_flip($values))),
            ),
            TextColumn::make('name')->columnFilter(
                Filter::make('name')->schema([TextInput::make('value')->required()])
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $value) => $query->where('name', $value))),
            ),
            TextColumn::make('created_at')->columnFilter(ColumnFilter::date()->jalali()),
            TextColumn::make('amount')->columnFilter(ColumnFilter::range()->step(0.01)),
        ]);
    }
}
