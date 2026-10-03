<?php

namespace Zvizvi\FilamentColumnFilters\Filters;

use Closure;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SelectColumnFilter extends ColumnFilter
{
    /**
     * @var array<int | string, string | array<string, string>> | Closure | null
     */
    protected array | Closure | null $options = null;

    /**
     * Null until multiple() is called explicitly; defaults to multiple.
     */
    protected ?bool $isMultiple = null;

    /**
     * Null = automatic: the popup shows a search field when the number of
     * options exceeds the threshold.
     */
    protected ?bool $isSearchable = null;

    protected int $searchThreshold = 8;

    protected ?Closure $searchResultsUsing = null;
    protected ?Closure $optionLabelUsing = null;
    protected ?Closure $optionLabelsUsing = null;
    protected bool $isPreloaded = true;
    protected int $preloadLimit = 50;
    protected int $optionsLimit = 50;
    protected int $searchDebounce = 500;

    public function getSearchResultsUsing(Closure $callback): static
    {
        $this->searchResultsUsing = $callback;
        return $this;
    }

    public function getOptionLabelUsing(Closure $callback): static
    {
        $this->optionLabelUsing = $callback;
        return $this;
    }

    public function getOptionLabelsUsing(Closure $callback): static
    {
        $this->optionLabelsUsing = $callback;
        return $this;
    }

    public function preload(bool $condition = true): static
    {
        $this->isPreloaded = $condition;
        return $this;
    }

    public function preloadLimit(int $limit): static
    {
        $this->preloadLimit = max(1, $limit);
        return $this;
    }

    public function optionsLimit(int $limit): static
    {
        $this->optionsLimit = max(1, $limit);
        return $this;
    }

    public function searchDebounce(int $milliseconds): static
    {
        $this->searchDebounce = max(0, $milliseconds);
        return $this;
    }

    public static function flattenOptions(array $options): array
    {
        $result = [];
        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $result = array_merge($result, static::flattenOptions($label));
            } else {
                $result[] = ['value' => (string) $value, 'label' => (string) $label];
            }
        }
        return $result;
    }

    public function hasRemoteSearch(?BaseFilter $target): bool
    {
        return $this->searchResultsUsing !== null
            || ($target instanceof SelectFilter && $target->getFormField()->hasDynamicSearchResults());
    }

    public function remoteResults(string $search, SelectFilter $target, ?\Filament\Forms\Components\Select $field = null): array
    {
        $field ??= $target->getFormField();
        $field->optionsLimit(min($this->optionsLimit, $field->getOptionsLimit()));
        $results = $this->searchResultsUsing !== null
            ? $field->evaluate($this->searchResultsUsing, ['search' => $search, 'limit' => $this->optionsLimit])
            : $field->getSearchResults($search);
        return array_slice(static::flattenOptions((array) $results), 0, $this->optionsLimit);
    }

    public function getType(): string
    {
        return 'select';
    }

    /**
     * @param  array<int | string, string | array<string, string>> | Closure  $options
     */
    public function options(array | Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple ?? true;
    }

    /**
     * Force the option search field on or off. Without calling this, the
     * field shows automatically when there are more options than the
     * threshold.
     */
    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    /**
     * Number of options above which the search field shows automatically.
     */
    public function searchThreshold(int $threshold): static
    {
        $this->searchThreshold = $threshold;

        return $this;
    }

    /**
     * @return array<int | string, string | array<string, string>>
     */
    public function getOptions(?BaseFilter $targetFilter = null): array
    {
        if ($this->options !== null) {
            $options = $this->options instanceof Closure ? ($this->options)($this->preloadLimit) : $this->options;

            return (array) $options;
        }

        if ($targetFilter instanceof SelectFilter) {
            return $targetFilter->getOptions();
        }

        return [];
    }

    /**
     * A select column filter can only sync automatically with a SelectFilter
     * (its state shape is known): one named like the column, or any one
     * filtering the same attribute. When multiple() was set explicitly, a
     * filter with a different selection mode is not a match — a separate
     * filter is generated instead of forcing the popup into the other mode.
     */
    public function findExistingFilter(Table $table, Column $column): ?BaseFilter
    {
        $matchesMode = fn (SelectFilter $filter): bool => $this->isMultiple === null
            || $filter->isMultiple() === $this->isMultiple;

        $named = $table->getFilter($column->getName());

        if ($named instanceof SelectFilter && $matchesMode($named)) {
            return $named;
        }

        $attribute = $this->getAttribute($column);

        foreach ($table->getFilters() as $filter) {
            if ($filter instanceof SelectFilter && $filter->getAttribute() === $attribute && $matchesMode($filter)) {
                return $filter;
            }
        }

        return null;
    }

    public function makeTableFilter(Column $column): BaseFilter
    {
        $attribute = $this->getAttribute($column);

        $filter = SelectFilter::make($this->getTargetFilterName($column))
            ->label($this->getLabel($column))
            ->options(fn (): array => $this->getOptions())
            ->attribute($attribute);

        if ($this->isMultiple()) {
            $filter->multiple();
        }

        if ($this->searchResultsUsing !== null) {
            $filter->searchable()->getSearchResultsUsing($this->searchResultsUsing)->optionsLimit($this->optionsLimit);
        }
        if ($this->optionLabelUsing !== null) {
            $filter->getOptionLabelUsing($this->optionLabelUsing);
        }
        if ($this->optionLabelsUsing !== null) {
            $filter->getOptionLabelsUsing($this->optionLabelsUsing);
        }

        $applyUsing = $this->getApplyCallback();

        if ($applyUsing !== null) {
            $filter->query($applyUsing);
        } elseif (str_contains($attribute, '.')) {
            // SelectFilter does not handle dotted attributes on its own, so
            // constrain through the relationship instead.
            $isMultiple = $this->isMultiple();

            $filter->query(function (Builder $query, array $data) use ($attribute, $isMultiple): Builder {
                $values = $isMultiple ? ($data['values'] ?? []) : ($data['value'] ?? null);

                if (blank(array_filter((array) $values, fn ($value): bool => filled($value)))) {
                    return $query;
                }

                return $this->applyToAttribute(
                    $query,
                    $attribute,
                    fn (Builder $subQuery, string $qualifiedAttribute): Builder => $isMultiple
                        ? $subQuery->whereIn($qualifiedAttribute, (array) $values)
                        : $subQuery->where($qualifiedAttribute, $values),
                );
            });
        }

        return $filter;
    }

    public function getDefaultState(): array
    {
        return $this->isMultiple() ? ['values' => []] : ['value' => null];
    }

    public function getPopupConfig(Column $column, Table $table, ?BaseFilter $targetFilter): array
    {
        $isMultiple = $targetFilter instanceof SelectFilter
            ? $targetFilter->isMultiple()
            : $this->isMultiple();

        $options = [];

        $remote = $this->hasRemoteSearch($targetFilter);
        if ($remote && ! method_exists($table->getLivewire(), 'searchColumnFilterOptions')) {
            throw new \LogicException('Remote column select filters require the HasColumnFilters trait on the table component.');
        }
        $initialOptions = [];
        if ($remote && $this->options === null && $targetFilter instanceof SelectFilter && $this->isPreloaded) {
            $field = $targetFilter->getFormField()->preload()->optionsLimit($this->preloadLimit);
            $schema = \Filament\Schemas\Schema::make($table->getLivewire())->model($table->getModel())->components([$field]);
            $schema->getComponents();
            $initialOptions = $field->getOptions();
        } elseif (! $remote || $this->isPreloaded) {
            $initialOptions = $this->getOptions($targetFilter);
        }
        foreach ($initialOptions as $value => $label) {
            if (is_array($label)) {
                // Flatten grouped options for the popup list.
                foreach ($label as $groupedValue => $groupedLabel) {
                    $options[] = ['value' => (string) $groupedValue, 'label' => (string) $groupedLabel];
                }

                continue;
            }

            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return [
            'type' => 'select',
            'filterName' => $this->getTargetFilterName($column),
            'fields' => [
                'value' => $this->getStateKey($isMultiple ? 'values' : 'value'),
            ],
            'multiple' => $isMultiple,
            'options' => $remote ? array_slice($options, 0, $this->preloadLimit) : $options,
            'searchable' => $remote || ($this->isSearchable ?? (count($options) > $this->searchThreshold)),
            'remoteSearch' => $remote,
            'columnName' => $column->getName(),
            'searchDebounce' => $this->searchDebounce,
        ];
    }
}
