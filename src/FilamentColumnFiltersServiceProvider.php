<?php

namespace Zvizvi\FilamentColumnFilters;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\View\TablesRenderHook;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentColumnFiltersServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-column-filters';

    public static string $viewNamespace = 'filament-column-filters';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews(static::$viewNamespace);
    }

    public function packageBooted(): void
    {
        FilamentView::registerRenderHook(TablesRenderHook::CONTENT_AFTER, function (array $data): string {
            $table = $data['table'] ?? null;
            if ($table?->getFiltersLayout() !== FiltersLayout::Hidden) {
                return '';
            }

            return view('filament-column-filters::hidden-layout-popups', [
                'schema' => $table->getLivewire()->getSchema('tableFiltersForm'),
            ])->render();
        });

        // Assets are registered globally so they are available to any panel,
        // but the column filter behaviour itself is only activated when the
        // plugin is registered on a panel — see FilamentColumnFiltersPlugin.
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );
    }

    protected function getAssetPackageName(): string
    {
        return 'zvizvi/filament-column-filters';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('filament-column-filters', __DIR__ . '/../resources/dist/components/filament-column-filters.js'),
            Css::make('filament-column-filters-styles', __DIR__ . '/../resources/dist/filament-column-filters.css'),
        ];
    }
}
