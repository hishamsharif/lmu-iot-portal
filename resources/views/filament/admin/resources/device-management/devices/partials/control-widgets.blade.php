@if(count($this->controlSchema) > 0)
    <div class="dc-control-grid">
        @foreach($this->controlSchema as $control)
            <div class="dc-control-card">
                <label class="dc-control-label">
                    {{ $control['label'] }}
                    @if($control['unit'])
                        <span class="dc-control-unit">{{ $control['unit'] }}</span>
                    @endif
                </label>

                @if($control['widget'] === 'slider')
                    <input
                        type="range"
                        min="{{ $control['min'] ?? 0 }}"
                        max="{{ $control['max'] ?? 100 }}"
                        step="{{ $control['step'] ?? 1 }}"
                        class="dc-control-slider"
                        wire:model.live="controlValues.{{ $control['key'] }}"
                    />
                    <div class="dc-control-meta">
                        <span>{{ $control['min'] ?? '—' }} to {{ $control['max'] ?? '—' }}</span>
                        <span>{{ data_get($this->controlValues, $control['key']) }}</span>
                    </div>
                @elseif($control['widget'] === 'toggle')
                    <input
                        type="checkbox"
                        class="dc-control-toggle"
                        wire:model.live="controlValues.{{ $control['key'] }}"
                    />
                @elseif($control['widget'] === 'select')
                    <select class="dc-control-select" wire:model.live="controlValues.{{ $control['key'] }}">
                        @foreach($control['options'] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                @elseif($control['widget'] === 'button')
                    <x-filament::button
                        size="sm"
                        color="warning"
                        wire:click="sendButtonCommand('{{ $control['key'] }}')"
                    >
                        Trigger
                    </x-filament::button>
                @elseif($control['widget'] === 'color')
                    <input
                        type="color"
                        class="dc-control-input"
                        wire:model.live="controlValues.{{ $control['key'] }}"
                    />
                @elseif($control['widget'] === 'json')
                    <textarea
                        class="dc-control-textarea"
                        rows="4"
                        wire:model.blur="controlValues.{{ $control['key'] }}"
                    ></textarea>
                @else
                    <input
                        type="{{ in_array($control['widget'], ['number'], true) ? 'number' : 'text' }}"
                        class="dc-control-input"
                        @if($control['widget'] === 'number')
                            step="{{ $control['step'] ?? 1 }}"
                            @if($control['min'] !== null) min="{{ $control['min'] }}" @endif
                            @if($control['max'] !== null) max="{{ $control['max'] }}" @endif
                        @endif
                        wire:model.live="controlValues.{{ $control['key'] }}"
                    />
                @endif
            </div>
        @endforeach
    </div>
@endif
