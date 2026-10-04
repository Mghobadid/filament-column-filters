@foreach ($schema?->getComponents() ?? [] as $component)
    @if ($component instanceof \Zvizvi\FilamentColumnFilters\Components\ColumnFilterPopup)
        {{ $component }}
    @endif
@endforeach
