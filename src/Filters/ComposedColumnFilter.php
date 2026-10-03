<?php

namespace Zvizvi\FilamentColumnFilters\Filters;

use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComposedColumnFilter extends ColumnFilter
{
    public function __construct(protected BaseFilter $filter) {}

    public function getType(): string
    {
        return $this->filter instanceof SelectFilter ? 'select' : 'custom';
    }

    public function getTargetFilterName(Column $column): string
    {
        return $this->filter->getName();
    }

    public function makeTableFilter(Column $column): BaseFilter
    {
        return $this->filter;
    }

    public function getDefaultState(): array
    {
        return $this->filter->getResetState();
    }

    public function getPopupConfig(Column $column, Table $table, ?BaseFilter $targetFilter): array
    {
        return ['type' => $this->getType(), 'filterName' => $this->filter->getName()];
    }
}
