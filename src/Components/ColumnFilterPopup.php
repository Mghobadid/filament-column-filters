<?php

namespace Zvizvi\FilamentColumnFilters\Components;

use Filament\Schemas\Components\Group;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Filament\Tables\Filters\BaseFilter;

class ColumnFilterPopup extends Group
{
    protected string $view = 'filament-column-filters::composed-filter-popup';

    protected BaseFilter $filter;

    protected array $popupConfig = [];

    public function toEmbeddedHtml(): string
    {
        return view($this->view, [
            'getPopupConfig' => $this->getPopupConfig(...),
            'getChildSchema' => $this->getChildSchema(...),
        ])->render();
    }

    public function filter(BaseFilter $filter, array $config): static
    {
        $this->filter = $filter;
        $this->popupConfig = $config;

        return $this;
    }

    public function getPopupConfig(): array
    {
        return $this->popupConfig;
    }

    #[ExposedLivewireMethod]
    public function apply(): void
    {
        // Validate only this popup, preserving drafts in other table filters.
        $state = $this->getChildSchema()->getState();
        $this->publishState($state);
    }

    protected function publishState(array $state): void
    {
        $livewire = $this->getLivewire();
        $drafts = $livewire->tableDeferredFilters;
        $livewire->tableFilters[$this->filter->getName()] = $state;
        $livewire->updatedTableFilters();
        if ($livewire->getTable()->hasDeferredFilters()) {
            $livewire->tableDeferredFilters = $drafts;
        }
        // The schema dispatcher schedules only the deferred filters form.
        // Applying or resetting a filter must also update rows and indicators.
        app(PartialsComponentHook::class)->forceRender($livewire);
    }

    #[ExposedLivewireMethod]
    public function resetFilter(): void
    {
        $this->getChildSchema()->fill($this->filter->getResetState());
        // Clearing a filter must work even if its fields are marked required.
        $this->publishState((array) data_get($this->getLivewire(), $this->getStatePath()));
    }
}
