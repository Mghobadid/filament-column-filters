<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Table;

class LiveDonorsTable extends DonorsTable
{
    protected $queryString = ['tableFilters' => ['as' => 'filters']];

    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters(false);
    }
}
