export class PollingManager {
    constructor() {
        this.timers = new Map();
    }

    destroy() {
        this.stopAll();
    }

    stopAll() {
        this.timers.forEach((timerId) => {
            clearInterval(timerId);
        });

        this.timers.clear();
    }

    sync(widgets, shouldPoll, fetchSnapshot) {
        const activeIds = new Set();

        widgets.forEach((widget) => {
            activeIds.add(widget.id);

            if (!shouldPoll(widget)) {
                this.stopForWidget(widget.id);

                return;
            }

            this.startForWidget(widget, fetchSnapshot);
        });

        Array.from(this.timers.keys()).forEach((widgetId) => {
            if (!activeIds.has(widgetId)) {
                this.stopForWidget(widgetId);
            }
        });
    }

    stopForWidget(widgetId) {
        const timerId = this.timers.get(widgetId);

        if (!timerId) {
            return;
        }

        clearInterval(timerId);
        this.timers.delete(widgetId);
    }

    startForWidget(widget, fetchSnapshot) {
        if (this.timers.has(widget.id)) {
            return;
        }

        const intervalSeconds = Math.max(2, Number(widget.polling_interval_seconds || 10));

        const timerId = window.setInterval(() => {
            fetchSnapshot(widget);
        }, intervalSeconds * 1000);

        this.timers.set(widget.id, timerId);
    }
}
