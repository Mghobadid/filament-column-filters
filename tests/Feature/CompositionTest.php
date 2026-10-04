<?php

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\ValidationException;
use Zvizvi\FilamentColumnFilters\Components\ColumnFilterPopup;
use Zvizvi\FilamentColumnFilters\Concerns\HasColumnFilters;
use Zvizvi\FilamentColumnFilters\FilamentColumnFilters;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\Category;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\ComposedDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\Donor;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\DonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\LiveRelationshipDonorsTable;
use Zvizvi\FilamentColumnFilters\Tests\Fixtures\RelationshipDonorsTable;

use function Pest\Livewire\livewire;

function composedPopup($component, string $column): ColumnFilterPopup
{
    return $component->getSchemaComponent('tableFiltersForm.fcf_' . sha1($column));
}

it('validates and applies grouped range inputs as plain numbers', function () {
    Donor::create(['name' => 'Below', 'amount' => 999]);
    Donor::create(['name' => 'Inside', 'amount' => 1500]);
    Donor::create(['name' => 'Above', 'amount' => 2001]);
    $component = livewire(ComposedDonorsTable::class)->instance();
    $component->tableDeferredFilters['cf_amount'] = ['from' => '1,000', 'until' => '2,000'];
    composedPopup($component, 'amount')->apply();

    expect($component->tableFilters['cf_amount'])->toBe(['from' => 1000.0, 'until' => 2000.0])
        ->and($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Inside']);
    composedPopup($component, 'amount')->resetFilter();
    expect($component->getFilteredTableQuery()->count())->toBe(3);
});

it('renders native fields for each factory and directly attached filters without a trait', function () {
    $component = livewire(ComposedDonorsTable::class)->instance();
    expect(class_uses_recursive($component))->not->toContain(HasColumnFilters::class);
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
    expect(fn () => composedPopup($component, 'name')->apply())->toThrow(ValidationException::class);
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

it('attaches every factory popup to the cached schema before resolving child fields', function () {
    $component = livewire(DonorsTable::class)->assertSuccessful()->instance();
    $schema = $component->getSchema('tableFiltersForm');

    foreach (['name', 'amount', 'created_at'] as $column) {
        $popup = composedPopup($component, $column);
        expect($popup->getContainer())->toBe($schema);
        foreach ($popup->getChildSchema()->getFlatFields() as $field) {
            expect($field->getStatePath())->toStartWith('tableDeferredFilters.cf_' . $column . '.')
                ->and($field->getId())->toStartWith('fcf_' . sha1($column) . '_');
        }
    }
    expect(composedPopup($component, 'name')->getChildSchema()->getComponents()[0])->toBeInstanceOf(TextInput::class);
    foreach (composedPopup($component, 'amount')->getChildSchema()->getComponents() as $field) {
        expect($field)->toBeInstanceOf(TextInput::class);
    }
    foreach (composedPopup($component, 'created_at')->getChildSchema()->getComponents() as $field) {
        expect($field)->toBeInstanceOf(DatePicker::class);
    }
});

it('searches relationships beyond the limited native preload and resolves selected labels', function () {
    foreach (['Alpha', 'Beta', 'Zebra'] as $name) {
        Category::create(['name' => $name]);
    }
    $zebra = Category::where('name', 'Zebra')->firstOrFail();
    $component = livewire(RelationshipDonorsTable::class)->assertSuccessful()->instance();
    $select = composedPopup($component, 'category.name')->getChildSchema()->getComponents()[0];

    expect($select->getOptions())->toHaveCount(2)->not->toHaveKey($zebra->id)
        ->and($select->getSearchResults('Zebra'))->toBe([$zebra->id => 'Zebra']);
    $component->tableDeferredFilters['category']['value'] = $zebra->id;
    expect($select->getOptionLabel())->toBe('Zebra');
});

it('shares draft state with standard fields and applies and resets both views', function () {
    $alpha = Category::create(['name' => 'Alpha']);
    $beta = Category::create(['name' => 'Beta']);
    Donor::create(['name' => 'Alice', 'category_id' => $alpha->id]);
    Donor::create(['name' => 'Bob', 'category_id' => $beta->id]);
    $component = livewire(RelationshipDonorsTable::class)->instance();
    $popup = composedPopup($component, 'category.name');
    $normal = $component->getSchema('tableFiltersForm')
        ->getComponent('tableFiltersForm.category', isAbsoluteKey: true)
        ->getChildSchema()->getComponents()[0];
    $field = $popup->getChildSchema()->getComponents()[0];

    $normal->state($alpha->id);
    expect($field->getState())->toBe($alpha->id)
        ->and($component->getFilteredTableQuery()->count())->toBe(2);
    $field->state($beta->id);
    expect($normal->getState())->toBe($beta->id);
    $popup->apply();
    expect($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Bob']);
    $popup->resetFilter();
    expect($normal->getState())->toBeNull()
        ->and($field->getState())->toBeNull()
        ->and($component->getFilteredTableQuery()->count())->toBe(2);
});

it('hydrates native defaults and keeps them when resetting the popup', function (string $table, string $root) {
    Donor::create(['name' => 'Alice', 'status' => 'open']);
    Donor::create(['name' => 'Bob', 'status' => 'closed']);
    $component = livewire($table)->assertSuccessful()->instance();
    $popup = composedPopup($component, 'status');
    $field = $popup->getChildSchema()->getComponents()[0];

    expect($field->getStatePath())->toBe($root . '.status.value')
        ->and($field->getState())->toBe('open')
        ->and($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Alice']);
    $field->state('closed');
    if ($root === 'tableFilters') {
        $component->updatedTableFilters();
        expect($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Bob']);
    } else {
        expect($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Alice']);
        $popup->apply();
        expect($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Bob']);
    }
    $popup->resetFilter();
    expect($field->getState())->toBe('open')
        ->and($component->tableFilters['status']['value'])->toBe('open')
        ->and($component->getFilteredTableQuery()->pluck('name')->all())->toBe(['Alice']);
})->with([
    'deferred' => [RelationshipDonorsTable::class, 'tableDeferredFilters'],
    'live' => [LiveRelationshipDonorsTable::class, 'tableFilters'],
]);
