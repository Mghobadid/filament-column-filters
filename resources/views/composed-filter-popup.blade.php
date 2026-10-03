@php($popup = $getPopupConfig())
<div x-load x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-column-filters', 'zvizvi/filament-column-filters') }}"
    x-data="filamentColumnFilters(@js($popup))"
    x-on:fcf-toggle.window="if ($event.detail === @js($popup['triggerId'])) toggle()">
    <template x-teleport="body">
        <div class="fcf-panel" x-ref="panel" x-bind:style="panelStyle" x-bind:class="{ 'fcf-panel--open': open }"
            x-on:click.outside="if (open && !$event.target.closest('.fi-fo-select, .fi-fo-date-time-picker')) close()"
            x-on:keydown.escape.window="open && close()">
            @if (($popup['type'] ?? '') === 'date')
                <div class="fcf-section"><div class="fcf-presets">
                    @foreach ($popup['presets'] ?? [] as $preset)
                        <button type="button" class="fcf-chip" x-on:click="applyPreset('{{ $preset }}')">{{ __('filament-column-filters::filters.presets.' . $preset) }}</button>
                    @endforeach
                </div></div>
            @endif
            <div class="fcf-section">{{ $getChildSchema() }}</div>
            <div class="fcf-footer">
                @if ($popup['deferred'])
                    <button type="button" class="fcf-btn fcf-btn--primary" x-on:click="apply">{{ __('filament-column-filters::filters.apply') }}</button>
                @endif
                <button type="button" class="fcf-btn" x-on:click="clear">{{ __('filament-column-filters::filters.reset') }}</button>
                <button type="button" class="fcf-link" x-on:click="close">{{ __('filament-column-filters::filters.close') }}</button>
            </div>
        </div>
    </template>
</div>
