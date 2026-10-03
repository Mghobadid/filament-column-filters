<?php

use Zvizvi\FilamentColumnFilters\FilamentColumnFilters;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\RemoteDonorsTable;

use function Pest\Livewire\livewire;

it('limits preload and server results independently', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    $table = $component->getTable();
    $column = $table->getColumns()['status'];
    $filter = FilamentColumnFilters::getColumnFilter($column);
    $config = $filter->getPopupConfig($column, $table, $table->getFilter('cf_status'));

    expect($config['options'])->toBe([['value' => 'open', 'label' => 'Open']])
        ->and($config['remoteSearch'])->toBeTrue()
        ->and($component->searchColumnFilterOptions('status', 'email match'))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
});

it('resolves selected labels outside the preloaded options', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    $component->tableFilters['cf_status'] = ['values' => ['closed']];

    expect($component->getColumnFilterSelectedOptions('status'))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
});

it('rejects access to columns without a configured remote select filter', function () {
    livewire(RemoteDonorsTable::class)
        ->call('searchColumnFilterOptions', 'unknown', 'term')
        ->assertStatus(404);
});
