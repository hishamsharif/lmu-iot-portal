<x-filament-panels::page>
    <livewire:admin.telemetry-live-stream />

    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                if (window.__telemetryPusherBound || ! window.Pusher) {
                    return;
                }

                window.__telemetryPusherBound = true;

                const pusher = new window.Pusher(@js(config('broadcasting.connections.reverb.key')), {
                    cluster: 'mt1',
                    wsHost: @js(config('broadcasting.connections.reverb.options.host')),
                    wsPort: @js(config('broadcasting.connections.reverb.options.port')),
                    wssPort: @js(config('broadcasting.connections.reverb.options.port')),
                    forceTLS: @js(config('broadcasting.connections.reverb.options.scheme') === 'https'),
                    enabledTransports: ['ws', 'wss'],
                    disableStats: true,
                });

                const channel = pusher.subscribe('telemetry');

                channel.bind('telemetry.incoming', (event) => {
                    const params = new URLSearchParams(window.location.search);
                    const selectedDevice = params.get('device');
                    const selectedTopicSuffix = params.get('topic');

                    if (!selectedDevice || !selectedTopicSuffix) {
                        return;
                    }

                    const deviceMatches = selectedDevice === event?.device_external_id
                        || selectedDevice === event?.device_uuid;

                    const topicMatches = typeof event?.topic === 'string'
                        && event.topic.endsWith('/' + selectedTopicSuffix);

                    if (!deviceMatches || !topicMatches) {
                        return;
                    }

                    Livewire.dispatch('telemetryIncoming', { entry: event });
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
