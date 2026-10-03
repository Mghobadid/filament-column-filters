<?php

use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Zvizvi\FilamentColumnFilters\Components\ColumnFilterPopup;
use Zvizvi\FilamentColumnFilters\FilamentColumnFilters;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\ComposedDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\DonorsTable;

use function Pest\Livewire\livewire;

function composedPopup($component, string $column): ColumnFilterPopup
{
    return $component->getSchemaComponent('tableFiltersForm.fcf_' . sha1($column));
}

it('renders native fields for each factory and directly attached filters without a trait', function () {
    $component = livewire(ComposedDonorsTable::class)->instance();
    expect(class_uses_recursive($component))->not->toContain(\Zvizvi\FilamentColumnFilters\Concerns\HasColumnFilters::class);
    $select = composedPopup($component, 'status')->getChildSchema()->getComponents()[0];
    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->getOptions())->toHaveKey('Statuses')
        ->and($select->getSearchResults('term'))->toBe(['closed' => 'Closed']);
    expect(composedPopup($component, 'name')->getChildSchema()->getComponents()[0])->toBeInstanceOf(TextInput::class)
        ->and(composedPopup($component, 'created_at')->getChildSchema()->getComponents()[0])->toBeInstanceOf(DatePicker::class);
});

it('applies only the current popup and preserves other drafts', function () {
    $component = livewire(ComposedDonorsTable::class)->instance();
    $component->tableDeferredFilters['name'] = ['value' => 'Alice'];
    $component->tableDeferredFilters['status'] = ['values' => ['closed']];
    composedPopup($component, 'status')->apply();
    expect($component->tableFilters['status']['values'])->toBe(['closed'])
        ->and($component->tableFilters['name']['value'] ?? null)->toBeNull()
        ->and($component->tableDeferredFilters['name']['value'])->toBe('Alice');
});

it('validates native fields before applying', function () {
    $component = livewire(ComposedDonorsTable::class)->instance();
    $component->tableDeferredFilters['name'] = ['value' => ''];
    expect(fn () => composedPopup($component, 'name')->apply())->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('resets a required field without blocking filter removal', function () {
    $component = livewire(ComposedDonorsTable::class)->instance();
    $component->tableDeferredFilters['name'] = ['value' => 'Alice'];
    $component->tableFilters['name'] = ['value' => 'Alice'];
    composedPopup($component, 'name')->resetFilter();
    expect($component->tableFilters['name']['value'] ?? null)->toBeNull();
});

it('clones synced fields and does not append duplicate popups', function () {
    $component = livewire(DonorsTable::class)->instance();
    FilamentColumnFilters::processComponent($component);
    $schema = $component->getSchema('tableFiltersForm');
    $normal = $schema->getComponent('tableFiltersForm.status', isAbsoluteKey: true);
    $popup = composedPopup($component, 'status');
    expect($popup->getChildSchema()->getComponents()[0])->not->toBe($normal->getChildSchema()->getComponents()[0]);
    expect(array_filter($schema->getComponents(), fn ($item) => $item instanceof ColumnFilterPopup))->toHaveCount(6);
});
