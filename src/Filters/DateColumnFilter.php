<?php

namespace Zvizvi\FilamentColumnFilters\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

class DateColumnFilter extends ColumnFilter
{
    public const PRESETS = [
        'today',
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
        'last_7_days',
        'last_30_days',
        'this_year',
        'last_year',
    ];

    /**
     * @var list<string>
     */
    protected array $presets = self::PRESETS;

    protected int $weekStartsOn = 0;

    protected bool $isJalali = false;

    public function jalali(bool $condition = true): static
    {
        $this->isJalali = $condition;

        return $this;
    }

    public function getType(): string
    {
        return 'date';
    }

    /**
     * Limit or reorder the quick-select presets shown in the popup.
     *
     * @param  list<string>  $presets
     */
    public function presets(array $presets): static
    {
        $this->presets = array_values(array_intersect($presets, self::PRESETS));

        return $this;
    }

    /**
     * First day of the week for the "this week" / "last week" presets.
     * 0 = Sunday (default), 1 = Monday.
     */
    public function weekStartsOn(int $day): static
    {
        $this->weekStartsOn = $day;

        return $this;
    }

    public function makeTableFilter(Column $column): BaseFilter
    {
        $attribute = $this->getAttribute($column);
        $label = $this->getLabel($column);
        $applyUsing = $this->getApplyCallback();

        return Filter::make($this->getTargetFilterName($column))
            ->label($label)
            ->schema([
                DatePicker::make('from')
                    ->label(__('filament-column-filters::filters.from_date')),
                DatePicker::make('until')
                    ->label(__('filament-column-filters::filters.until_date')),
            ])
            ->query(function (Builder $query, array $data) use ($attribute, $applyUsing): Builder {
                if ($applyUsing !== null) {
                    return $applyUsing($query, $data) ?? $query;
                }

                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;

                return $this->applyToAttribute(
                    $query,
                    $attribute,
                    function (Builder $subQuery, string $qualifiedAttribute) use ($from, $until): Builder {
                        if ($from) {
                            $subQuery->whereDate($qualifiedAttribute, '>=', $from);
                        }

                        if ($until) {
                            $subQuery->whereDate($qualifiedAttribute, '<=', $until);
                        }

                        return $subQuery;
                    },
                );
            })
            ->indicateUsing(function (array $data) use ($label): array {
                // Returned as a list of Indicator objects — string-keyed
                // arrays collide across filters when Filament merges them.
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators[] = Indicator::make(__('filament-column-filters::filters.indicator_from', [
                        'label' => $label,
                        'date' => $this->formatIndicatorDate($data['from']),
                    ]))->removeField('from');
                }

                if (filled($data['until'] ?? null)) {
                    $indicators[] = Indicator::make(__('filament-column-filters::filters.indicator_until', [
                        'label' => $label,
                        'date' => $this->formatIndicatorDate($data['until']),
                    ]))->removeField('until');
                }

                return $indicators;
            });
    }

    public function getDefaultState(): array
    {
        return ['from' => null, 'until' => null];
    }

    public function formatIndicatorDate(string $date): string
    {
        if (! $this->isJalali) {
            return $date;
        }

        $formatter = new IntlDateFormatter(
            'en_US@calendar=persian;numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd',
        );

        return $formatter->format(new DateTimeImmutable($date, new DateTimeZone('UTC'))) ?: $date;
    }

    public function getPopupConfig(Column $column, Table $table, ?BaseFilter $targetFilter): array
    {
        return [
            'type' => 'date',
            'filterName' => $this->getTargetFilterName($column),
            'fields' => [
                'from' => $this->getStateKey('from'),
                'until' => $this->getStateKey('until'),
            ],
            'presets' => $this->presets,
            'weekStartsOn' => $this->weekStartsOn,
            'jalali' => $this->isJalali,
            'locale' => app()->getLocale(),
        ];
    }
}
