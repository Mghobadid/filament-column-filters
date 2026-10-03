<span class="fcf-header">
    <span class="fcf-header-label">{!! $labelHtml !!}</span>
    <button type="button" id="{{ $config['triggerId'] }}" class="fcf-trigger @if ($isActive) fcf-trigger--active @endif"
        x-on:click.stop.prevent="$dispatch('fcf-toggle', @js($config['triggerId']))"
        aria-label="{{ __('filament-column-filters::filters.tooltip.' . ($type === 'custom' ? 'select' : $type)) }}">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="fcf-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18v3l-7 7v5l-4 2v-7L3 7V4Z" /></svg>
        <span class="fcf-active-dot" aria-hidden="true"></span>
    </button>
</span>
