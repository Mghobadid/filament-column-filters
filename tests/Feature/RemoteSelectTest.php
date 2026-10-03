<?php

use Zvizvi\FilamentColumnFilters\FilamentColumnFilters;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\RemoteDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\SyncedRemoteDonorsTable;

use function Pest\Livewire\livewire;

it('uses synced Filament callbacks through the dispatcher without a page trait', function () {
    $component = livewire(SyncedRemoteDonorsTable::class)->instance();
    $key = 'tableFiltersForm.fcf_' . sha1('status');
    expect($component->callSchemaComponentMethod($key, 'search', ['term']))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
    $component->tableFilters['status'] = ['values' => ['closed']];
    expect($component->callSchemaComponentMethod($key, 'selectedOptions'))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
});

it('limits preload and server results independently', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    $table = $component->getTable();
    $column = $table->getColumns()['status'];
    $filter = FilamentColumnFilters::getColumnFilter($column);
    $config = $filter->getPopupConfig($column, $table, $table->getFilter('cf_status'));

    expect($config['options'])->toBe([['value' => 'open', 'label' => 'Open']])
        ->and($config['remoteSearch'])->toBeTrue()
        ->and($component->callSchemaComponentMethod($config['remoteComponentKey'], 'search', ['email match']))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
});

it('resolves selected labels outside the preloaded options', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    $component->tableFilters['cf_status'] = ['values' => ['closed']];

    expect($component->callSchemaComponentMethod('tableFiltersForm.fcf_' . sha1('status'), 'selectedOptions'))
        ->toBe([['value' => 'closed', 'label' => 'Closed']]);
});

it('does not expose unknown components or unmarked methods', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    expect($component->callSchemaComponentMethod('tableFiltersForm.unknown', 'search', ['term']))->toBeNull()
        ->and($component->callSchemaComponentMethod('tableFiltersForm.fcf_' . sha1('status'), 'select'))->toBeNull();
});

it('registers the remote transport without a page trait and only once', function () {
    $component = livewire(RemoteDonorsTable::class)->instance();
    expect(class_uses_recursive($component))->not->toContain(\Zvizvi\FilamentColumnFilters\Concerns\HasColumnFilters::class);
    FilamentColumnFilters::processComponent($component);
    FilamentColumnFilters::processComponent($component);
    $transports = array_filter($component->getSchema('tableFiltersForm')->getComponents(), fn ($item) => $item instanceof \Zvizvi\FilamentColumnFilters\Components\RemoteSelectOptions);
    expect($transports)->toHaveCount(1);
});
