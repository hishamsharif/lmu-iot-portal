<x-filament-panels::page>
    <style>
        .dc-log {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8125rem;
            line-height: 1.6;
        }
        .dark .dc-log {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .dc-log-header {
            display: grid;
            grid-template-columns: 6rem 10rem 1fr;
            padding: 0.5rem 0.75rem;
            background: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgb(107, 114, 128);
        }
        .dark .dc-log-header {
            background: rgba(255, 255, 255, 0.03);
            border-bottom-color: rgba(255, 255, 255, 0.08);
            color: rgb(156, 163, 175);
        }
        .dc-log-body {
            max-height: 20rem;
            overflow-y: auto;
        }
        .dc-log-row {
            display: grid;
            grid-template-columns: 6rem 10rem 1fr;
            padding: 0.375rem 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            align-items: start;
        }
        .dark .dc-log-row {
            border-bottom-color: rgba(255, 255, 255, 0.04);
        }
        .dc-log-row:last-child {
            border-bottom: none;
        }
        .dc-log-time {
            color: rgb(156, 163, 175);
            font-variant-numeric: tabular-nums;
        }
        .dc-log-detail {
            color: rgb(75, 85, 99);
            white-space: pre-wrap;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .dark .dc-log-detail {
            color: rgb(209, 213, 219);
        }
        .dc-badge {
            display: inline-block;
            padding: 0.0625rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.6875rem;
            font-weight: 600;
            line-height: 1.5;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .dc-badge[data-type="command.dispatched"] { background: rgba(59, 130, 246, 0.1); color: rgb(37, 99, 235); }
        .dc-badge[data-type="command.sent"] { background: rgba(6, 182, 212, 0.1); color: rgb(8, 145, 178); }
        .dc-badge[data-type="device.state.received"] { background: rgba(16, 185, 129, 0.1); color: rgb(5, 150, 105); }
        .dc-badge[data-type="info"] { background: rgba(107, 114, 128, 0.1); color: rgb(75, 85, 99); }
        .dc-badge[data-type="error"] { background: rgba(239, 68, 68, 0.1); color: rgb(220, 38, 38); }
        .dark .dc-badge[data-type="command.dispatched"] { background: rgba(96, 165, 250, 0.15); color: rgb(147, 197, 253); }
        .dark .dc-badge[data-type="command.sent"] { background: rgba(34, 211, 238, 0.15); color: rgb(165, 243, 252); }
        .dark .dc-badge[data-type="device.state.received"] { background: rgba(52, 211, 153, 0.15); color: rgb(167, 243, 208); }
        .dark .dc-badge[data-type="info"] { background: rgba(156, 163, 175, 0.12); color: rgb(209, 213, 219); }
        .dark .dc-badge[data-type="error"] { background: rgba(248, 113, 113, 0.15); color: rgb(252, 165, 165); }
        .dc-separator {
            padding: 0.25rem 0.75rem;
            text-align: center;
            font-size: 0.6875rem;
            color: rgb(156, 163, 175);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }
        .dark .dc-separator {
            border-bottom-color: rgba(255, 255, 255, 0.04);
            color: rgb(107, 114, 128);
        }
        .dc-empty {
            padding: 2rem 0.75rem;
            text-align: center;
            color: rgb(156, 163, 175);
            font-size: 0.8125rem;
        }
        .dc-state-grid {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 1rem;
            align-items: start;
        }
        @media (max-width: 768px) {
            .dc-state-grid {
                grid-template-columns: 1fr;
            }
        }
        .dc-state-json {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8125rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
            overflow-wrap: break-word;
            padding: 0.75rem;
            border-radius: 0.375rem;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.08);
            color: rgb(55, 65, 81);
            min-height: 8rem;
            max-height: 20rem;
            overflow-y: auto;
        }
        .dark .dc-state-json {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
            color: rgb(229, 231, 235);
        }
        .dc-state-empty {
            padding: 2rem 0.75rem;
            text-align: center;
            color: rgb(156, 163, 175);
            font-size: 0.8125rem;
            font-style: italic;
        }
        .dc-state-meta {
            font-size: 0.6875rem;
            color: rgb(156, 163, 175);
            margin-top: 0.5rem;
        }
        .dc-state-dot {
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            margin-right: 0.375rem;
            vertical-align: middle;
        }
        .dc-state-dot-active { background: rgb(16, 185, 129); }
        .dc-state-dot-idle { background: rgb(156, 163, 175); }
    </style>

    <x-filament::section>
        <x-slot name="heading">Send Command</x-slot>
        <x-slot name="description">Select a topic and send a JSON command to this device via NATS.</x-slot>

        @if(count($this->subscribeTopicOptions) > 0)
            {{ $this->form }}

            <x-filament::button wire:click="sendCommand" icon="heroicon-o-paper-airplane" style="margin-top: 1rem;">
                Send Command
            </x-filament::button>
        @else
            <p style="color: rgb(107, 114, 128); font-size: 0.875rem;">
                No subscribe topics configured for this device's schema version.
            </p>
        @endif
    </x-filament::section>

    <div class="dc-state-grid"
         x-data="deviceControlStream()"
         x-init="init()"
         x-on:clear-device-log.window="events = []"
    >
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <span>Message Flow</span>
                    <x-filament::button
                        size="xs"
                        color="gray"
                        icon="heroicon-m-trash"
                        x-on:click="$dispatch('clear-device-log')"
                    >
                        Clear
                    </x-filament::button>
                </div>
            </x-slot>
            <x-slot name="description">Real-time events via WebSocket. Start the mock device, then send a command.</x-slot>

            <div class="dc-log">
                <div class="dc-log-header">
                    <span>Time</span>
                    <span>Event</span>
                    <span>Details</span>
                </div>
                <div class="dc-log-body" x-ref="eventLog">
                    <template x-if="events.length === 0">
                        <div class="dc-empty">Waiting for events&hellip; Send a command or start the mock device.</div>
                    </template>
                    <template x-for="(event, index) in events" :key="index">
                        <div>
                            <template x-if="event.type === 'cycle-separator'">
                                <div class="dc-separator">&mdash; cycle complete &mdash;</div>
                            </template>
                            <template x-if="event.type !== 'cycle-separator'">
                                <div class="dc-log-row">
                                    <span class="dc-log-time" x-text="event.time"></span>
                                    <span><span class="dc-badge" :data-type="event.type" x-text="event.label"></span></span>
                                    <span class="dc-log-detail" x-text="event.detail"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="dc-state-dot" :class="deviceState ? 'dc-state-dot-active' : 'dc-state-dot-idle'"></span>
                    <span>Device State</span>
                </div>
            </x-slot>
            <x-slot name="description">Last known state from the device.</x-slot>

            <template x-if="!deviceState">
                <div class="dc-state-empty">No state received yet.</div>
            </template>
            <template x-if="deviceState">
                <div>
                    <div class="dc-state-json" x-text="JSON.stringify(deviceState, null, 2)"></div>
                    <div class="dc-state-meta">
                        <span x-show="deviceStateTopic">Topic: <span x-text="deviceStateTopic"></span></span>
                        <span x-show="deviceStateTime"> &middot; <span x-text="deviceStateTime"></span></span>
                    </div>
                </div>
            </template>
        </x-filament::section>
    </div>

    {{ $this->table }}

    @push('scripts')
        <script>
            function deviceControlStream() {
                const initialState = @js($this->initialDeviceState);

                return {
                    events: [],
                    deviceState: initialState?.payload ?? null,
                    deviceStateTopic: initialState?.topic ?? null,
                    deviceStateTime: initialState?.stored_at ? new Date(initialState.stored_at).toLocaleTimeString() : null,

                    init() {
                        const deviceUuid = @js($this->deviceUuid);

                        this.handleIncoming = (payload) => {
                            if (payload?.deviceUuid !== deviceUuid) {
                                return;
                            }

                            switch (payload?.type) {
                                case 'command.dispatched':
                                    this.addEvent('command.dispatched', 'Dispatched',
                                        `Log #${payload.data.command_log_id} | Topic: ${payload.data.topic} | Payload: ${JSON.stringify(payload.data.command_payload)}`
                                    );
                                    break;
                                case 'command.sent':
                                    this.addEvent('command.sent', 'Sent',
                                        `Log #${payload.data.command_log_id} | NATS: ${payload.data.nats_subject} | ${payload.data.sent_at}`
                                    );
                                    break;
                                case 'device.state.received':
                                    this.addEvent('device.state.received', 'Received',
                                        `Topic: ${payload.data.topic} | Payload: ${JSON.stringify(payload.data.payload)} | ${payload.data.received_at}`
                                    );
                                    this.deviceState = payload.data.payload;
                                    this.deviceStateTopic = payload.data.topic;
                                    this.deviceStateTime = new Date().toLocaleTimeString();
                                    this.addSeparator();
                                    break;
                                case 'info':
                                    this.addEvent('info', 'Info', payload.data?.message ?? 'WebSocket connected.');
                                    break;
                                case 'error':
                                    this.addEvent('error', 'Error', payload.data?.message ?? 'WebSocket error.');
                                    break;
                            }
                        };

                        if (this._boundHandler) {
                            window.removeEventListener('device-control-event', this._boundHandler);
                        }
                        this._boundHandler = (e) => this.handleIncoming(e.detail);
                        window.addEventListener('device-control-event', this._boundHandler);

                        if (window.__deviceControlPusherBound && window.__deviceControlDeviceUuid === deviceUuid) {
                            return;
                        }

                        if (!window.Pusher) {
                            this.addEvent('error', 'Error', 'WebSocket client not available.');
                            return;
                        }

                        window.__deviceControlDeviceUuid = deviceUuid;
                        window.__deviceControlPusherBound = true;

                        if (!window.__deviceControlPusher) {
                            window.__deviceControlPusher = new window.Pusher(@js(config('broadcasting.connections.reverb.key')), {
                                cluster: 'mt1',
                                wsHost: @js(config('broadcasting.connections.reverb.options.host')),
                                wsPort: @js(config('broadcasting.connections.reverb.options.port')),
                                wssPort: @js(config('broadcasting.connections.reverb.options.port')),
                                forceTLS: @js(config('broadcasting.connections.reverb.options.scheme') === 'https'),
                                enabledTransports: ['ws', 'wss'],
                                disableStats: true,
                            });
                        }

                        window.__deviceControlPusher.connection.bind('connected', () => {
                            window.dispatchEvent(new CustomEvent('device-control-event', {
                                detail: { type: 'info', deviceUuid, data: { message: 'WebSocket connected.' } },
                            }));
                        });

                        if (window.__deviceControlChannel) {
                            window.__deviceControlPusher.unsubscribe(window.__deviceControlChannel.name);
                        }

                        window.__deviceControlChannel = window.__deviceControlPusher.subscribe('device-control.' + deviceUuid);

                        ['command.dispatched', 'command.sent', 'device.state.received'].forEach((evt) => {
                            window.__deviceControlChannel.bind(evt, (data) => {
                                window.dispatchEvent(new CustomEvent('device-control-event', {
                                    detail: { type: evt, deviceUuid, data },
                                }));
                            });
                        });
                    },

                    addEvent(type, label, detail) {
                        this.events.push({ type, label, detail, time: new Date().toLocaleTimeString() });
                        this.$nextTick(() => {
                            if (this.$refs.eventLog) {
                                this.$refs.eventLog.scrollTop = this.$refs.eventLog.scrollHeight;
                            }
                        });
                    },

                    addSeparator() {
                        this.events.push({ type: 'cycle-separator', time: '' });
                        this.$nextTick(() => {
                            if (this.$refs.eventLog) {
                                this.$refs.eventLog.scrollTop = this.$refs.eventLog.scrollHeight;
                            }
                        });
                    },
                };
            }
        </script>
    @endpush
</x-filament-panels::page>
