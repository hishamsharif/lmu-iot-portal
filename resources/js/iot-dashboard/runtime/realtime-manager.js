export class RealtimeManager {
    constructor(onPayload, onSubscriptionStatusChanged = null) {
        this.onPayload = onPayload;
        this.onSubscriptionStatusChanged = onSubscriptionStatusChanged;
        this.widgets = new Map();
        this.organizationId = null;
        this.channelName = null;
        this.channel = null;
        this.isSubscribed = false;
        this.connectionStatusUnsubscribe = null;
    }

    setWidgets(widgets) {
        this.widgets.clear();

        widgets.forEach((widget) => {
            this.widgets.set(widget.id, widget);
        });
    }

    update(organizationId, widgets) {
        this.setWidgets(widgets);

        const resolvedOrganizationId = Number(organizationId || 0);
        const canUseRealtime = Number.isInteger(resolvedOrganizationId)
            && resolvedOrganizationId > 0
            && this.hasRealtimeWidget();

        if (!canUseRealtime) {
            this.leave();

            return;
        }

        if (!window.Echo) {
            this.setSubscribed(false);

            return;
        }

        const nextChannelName = `iot-dashboard.organization.${resolvedOrganizationId}`;

        if (this.channel && this.channelName === nextChannelName) {
            return;
        }

        this.leave();

        this.organizationId = resolvedOrganizationId;
        this.channelName = nextChannelName;
        this.channel = window.Echo.private(nextChannelName);
        this.setSubscribed(false);
        this.channel.listen('.telemetry.received', this.onPayload);

        if (typeof this.channel.subscribed === 'function') {
            this.channel.subscribed(() => {
                this.setSubscribed(true);
            });
        }

        if (typeof this.channel.error === 'function') {
            this.channel.error((error) => {
                console.warn('IoT dashboard websocket subscription failed', error);
                this.setSubscribed(false);
            });
        }

        const connector = window.Echo?.connector;

        if (connector && typeof connector.onConnectionChange === 'function') {
            this.connectionStatusUnsubscribe = connector.onConnectionChange((status) => {
                if (status !== 'connected') {
                    this.setSubscribed(false);
                }
            });
        }
    }

    shouldPollWidget(widget) {
        if (!widget.use_polling) {
            return false;
        }

        if (!widget.use_websocket) {
            return true;
        }

        return !this.isSubscribed;
    }

    hasRealtimeWidget() {
        return Array.from(this.widgets.values()).some((widget) => Boolean(widget.use_websocket));
    }

    leave() {
        if (this.channel && typeof this.channel.stopListening === 'function') {
            this.channel.stopListening('.telemetry.received');
        }

        if (this.channelName && window.Echo) {
            window.Echo.leave(this.channelName);
        }

        if (typeof this.connectionStatusUnsubscribe === 'function') {
            this.connectionStatusUnsubscribe();
            this.connectionStatusUnsubscribe = null;
        }

        this.channel = null;
        this.channelName = null;
        this.organizationId = null;
        this.setSubscribed(false);
    }

    destroy() {
        this.leave();
        this.widgets.clear();
    }

    setSubscribed(nextState) {
        const normalizedState = Boolean(nextState);

        if (this.isSubscribed === normalizedState) {
            return;
        }

        this.isSubscribed = normalizedState;

        if (typeof this.onSubscriptionStatusChanged === 'function') {
            this.onSubscriptionStatusChanged(this.isSubscribed);
        }
    }
}
