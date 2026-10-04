<?php

namespace Zvizvi\FilamentColumnFilters\Components;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Js;

class RangeInput extends TextInput
{
    public function toEmbeddedHtml(): string
    {
        $path = $this->getStatePath();
        $this->extraAlpineAttributes([
            'x-data' => 'rangeInput(' . Js::from($path) . ', ' . ($this->isLive() ? 'true' : 'false') . ')',
        ], merge: true);

        // Retain Filament's input rendering, validation and mask, while keeping
        // the formatted display separate from the canonical Livewire value.
        return str_replace(
            $this->applyStateBindingModifiers('wire:model') . '="' . $path . '"',
            'x-model="display"',
            parent::toEmbeddedHtml(),
        );
    }
}
