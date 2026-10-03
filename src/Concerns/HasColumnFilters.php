<?php

namespace Zvizvi\FilamentColumnFilters\Concerns;

use Zvizvi\FilamentColumnFilters\FilamentColumnFilters;

/**
 * Registers the column filters from the component's own boot, for pages whose
 * table is built OUTSIDE a Livewire request.
 *
 * The plugin normally needs nothing from you: it hooks Livewire's mount,
 * hydrate, call and render events and processes the table on each. That covers
 * every path Filament itself takes. It does not cover code that builds the
 * table headlessly — instantiating the page class and calling getTable() with
 * no Livewire request behind it. None of those events fire there, so the
 * generated filters never get pushed onto the table, and applying one fails
 * with "The filter [cf_x] does not exist."
 *
 * A `booted<Trait>` hook runs on both paths — Livewire fires it on a real
 * request, a headless driver replays it — and processComponent() is
 * idempotent, so the work is free on the page and load-bearing off it.
 *
 * The trait has to be applied to the PAGE CLASS, not to a parent: Livewire
 * resolves these hooks in class_uses_recursive() order, and it is being last
 * that guarantees InteractsWithTable already built $this->table.
 *
 *     class ListDonors extends ListRecords
 *     {
 *         use HasColumnFilters;
 *     }
 */
trait HasColumnFilters
{
    #[\Livewire\Attributes\Renderless]
    public function searchColumnFilterOptions(string $columnName, string $search): array
    {
        [$config, $target] = $this->resolveRemoteColumnFilter($columnName);
        abort_if(mb_strlen($search) > 200, 422);
        $field = $target->getFormField();
        $schema = \Filament\Schemas\Schema::make($this)
            ->model($this->getTable()->getModel())
            ->statePath('tableFilters.' . $target->getName())->components([$field]);
        $schema->getComponents();
        return $config->remoteResults(trim($search), $target, $field);
    }

    #[\Livewire\Attributes\Renderless]
    public function getColumnFilterSelectedOptions(string $columnName): array
    {
        [$config, $target] = $this->resolveRemoteColumnFilter($columnName);
        $field = $target->getFormField();
        $schema = \Filament\Schemas\Schema::make($this)
            ->model($this->getTable()->getModel())
            ->statePath('tableFilters.' . $target->getName())
            ->components([$field]);
        // Attach the field to a schema so Filament resolves its state and utility injections.
        $schema->getComponents();
        $options = $field->isMultiple()
            ? $field->getOptionLabels(false)
            : (filled($field->getState()) ? [$field->getState() => $field->getOptionLabel(false)] : []);
        return $config::flattenOptions(array_filter($options, fn ($label) => $label !== null));
    }

    protected function resolveRemoteColumnFilter(string $columnName): array
    {
        FilamentColumnFilters::processComponent($this);
        $table = $this->getTable();
        $column = $table->getColumns()[$columnName] ?? null;
        abort_unless($column !== null && ! $column->isHidden(), 404);
        $config = FilamentColumnFilters::getColumnFilter($column);
        abort_unless($config instanceof \Zvizvi\FilamentColumnFilters\Filters\SelectColumnFilter, 404);
        $target = $config->isSyncingWithExisting()
            ? $table->getFilter($config->getTargetFilterName($column))
            : ($config->findExistingFilter($table, $column)
                ?? $table->getFilter($config->getTargetFilterName($column)));
        abort_unless($target instanceof \Filament\Tables\Filters\SelectFilter && $config->hasRemoteSearch($target), 404);
        return [$config, $target];
    }

    public function bootedHasColumnFilters(): void
    {
        // decorate: true so the header decoration happens here too. A headless
        // driver that hashes the table schema to detect drift would otherwise
        // see the page's decorated labels and its own undecorated ones as two
        // different schemas.
        FilamentColumnFilters::processComponent($this, decorate: true);
    }
}
