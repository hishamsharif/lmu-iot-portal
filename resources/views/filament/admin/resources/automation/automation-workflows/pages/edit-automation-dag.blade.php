<x-filament-panels::page>
    <div class="automation-dag-shell">
        <div
            class="automation-dag-canvas"
            wire:ignore
            data-automation-dag-builder
            data-livewire-id="{{ $this->getId() }}"
            data-initial-graph="{{ json_encode($this->getInitialGraphForBuilder(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
        ></div>
    </div>
</x-filament-panels::page>
