<?php

use Filament\Tables\Enums\FiltersLayout;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\HiddenDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\Donor;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\LayoutDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\HiddenRelationshipDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\Category;

use function Pest\Livewire\livewire;

it('renders hidden-layout popup markup exactly once while keeping standard filters hidden', function () {
    $test = livewire(HiddenDonorsTable::class)->assertSuccessful()->assertSeeHtml('fcf-trigger');
    expect(substr_count($test->html(), 'x-data="filamentColumnFilters('))->toBe(6)
        ->and($test->html())->not->toContain('fi-ta-filters-dropdown', 'name_single.value');
});

it('applies and resets hidden popups through Livewire while preserving unrelated drafts', function () {
    $alice = Donor::create(['name' => 'Alice', 'status' => 'open']);
    $bob = Donor::create(['name' => 'Bob', 'status' => 'closed']);
    livewire(HiddenDonorsTable::class)
        ->set('tableDeferredFilters.cf_name.value', 'Ali')
        ->set('tableDeferredFilters.status.values', ['closed'])
        ->call('callSchemaComponentMethod', 'tableFiltersForm.fcf_' . sha1('status'), 'apply')
        ->assertCanSeeTableRecords([$bob])->assertCanNotSeeTableRecords([$alice])
        ->assertSet('tableDeferredFilters.cf_name.value', 'Ali')
        ->assertSet('tableFilters.cf_name.value', null)
        ->assertSee('Closed')
        ->call('callSchemaComponentMethod', 'tableFiltersForm.fcf_' . sha1('status'), 'resetFilter')
        ->assertCanSeeTableRecords([$alice, $bob])
        ->assertSet('tableDeferredFilters.cf_name.value', 'Ali');
});

it('renders each popup once in every filter layout', function (string $layout) {
    $test = livewire(LayoutDonorsTable::class, ['layout' => $layout]);
    expect(substr_count($test->html(), 'x-data="filamentColumnFilters('))->toBe(6);
})->with(array_map(fn ($case) => $case->name, FiltersLayout::cases()));

it('updates hidden live filters and their indicators', function () {
    $alice = Donor::create(['name' => 'Alice']);
    $bob = Donor::create(['name' => 'Bob']);
    $test = livewire(LayoutDonorsTable::class, ['deferred' => false])
        ->set('tableFilters.cf_name.value', 'Ali')
        ->assertCanSeeTableRecords([$alice])->assertCanNotSeeTableRecords([$bob]);
    expect($test->instance()->getTable()->getFilter('cf_name')->getIndicators())->toHaveCount(1);
    $test->call('callSchemaComponentMethod', 'tableFiltersForm.fcf_' . sha1('name'), 'resetFilter')
        ->assertCanSeeTableRecords([$alice, $bob]);
    expect($test->instance()->getTable()->getFilter('cf_name')->getIndicators())->toBe([]);
});

it('retains defaults and scoped limited relationship searches in hidden popups', function () {
    foreach (['Alpha', 'Beta', 'Zebra one', 'Zebra two', 'Zebra three', 'Excluded'] as $name) {
        Category::create(['name' => $name]);
    }
    $test = livewire(HiddenRelationshipDonorsTable::class)->assertSuccessful();
    $component = $test->instance();
    expect($component->tableFilters['status']['value'])->toBe('open');
    $popup = $component->getSchemaComponent('tableFiltersForm.fcf_' . sha1('category.name'));
    $field = $popup->getChildSchema()->getComponents()[0];
    expect($field->getOptions())->toHaveCount(2)
        ->and($field->getSearchResults('Zebra'))->toHaveCount(2)
        ->and($field->getSearchResults('Excluded'))->toBe([]);
    expect(substr_count($test->html(), 'x-data="filamentColumnFilters('))->toBe(2);
});
