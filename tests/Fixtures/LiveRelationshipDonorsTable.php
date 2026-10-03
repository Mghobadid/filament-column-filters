<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Tables\Table;

class LiveRelationshipDonorsTable extends RelationshipDonorsTable
{
    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters(false);
    }
}
