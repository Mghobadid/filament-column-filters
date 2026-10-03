<?php

namespace Zvizvi\FilamentColumnFilters\Components;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Attributes\Renderless;
use Zvizvi\FilamentColumnFilters\Filters\SelectColumnFilter;

/** A stateless transport registered in Filament's existing filters schema. */
class RemoteSelectOptions extends Component
{
    protected string $view = 'filament-column-filters::remote-select-options';

    protected SelectColumnFilter $filter;

    protected SelectFilter $target;

    public static function make(SelectColumnFilter $filter, SelectFilter $target): static
    {
        $component = app(static::class);
        $component->filter = $filter;
        $component->target = $target;
        $component->configure();

        return $component;
    }

    protected function select(): \Filament\Forms\Components\Select
    {
        $field = $this->target->getFormField();
        $schema = Schema::make($this->getLivewire())
            ->model($this->getLivewire()->getTable()->getModel())
            ->statePath('tableFilters.' . $this->target->getName())
            ->components([$field]);
        $schema->getComponents();

        return $field;
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function search(string $search): array
    {
        abort_if(mb_strlen($search) > 200, 422);

        return $this->filter->remoteResults(trim($search), $this->target, $this->select());
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function selectedOptions(): array
    {
        $field = $this->select();
        $options = $field->isMultiple()
            ? $field->getOptionLabels(false)
            : (filled($field->getState()) ? [$field->getState() => $field->getOptionLabel(false)] : []);

        return SelectColumnFilter::flattenOptions(array_filter($options, fn ($label) => $label !== null));
    }
}
