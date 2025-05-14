// <plugin root>/src/Resources/app/storefront/src/plugin/delivery-countdown.plugin.js

import Plugin from 'src/plugin-system/plugin.class';

export default class DeliveryCountdown extends Plugin {
    init() {
        // Select the delivery message element
        this.el = document.querySelector('#delivery-message');
        if (!this.el) {
            return;
        }

        // Read data-* attributes
        this.cutoffTime      = this.el.dataset.cutoffTime;
        this.today           = this.el.dataset.today;
        this.tomorrow        = this.el.dataset.tomorrow;
        this.locale          = this.el.dataset.locale;
        this.supportWeekends = this.el.dataset.supportWeekends === '1';
        this.ignoredDates    = JSON.parse(this.el.dataset.ignoredDates || '[]');

        // Calculate UTC timestamps (in seconds)
        this.cutoffTodayTs    = Date.parse(`${this.today}T${this.cutoffTime}Z`) / 1000;
        this.cutoffTomorrowTs = Date.parse(`${this.tomorrow}T${this.cutoffTime}Z`) / 1000;

        // Initial render and start interval
        this._updateCountdown();
        this._interval = window.setInterval(() => this._updateCountdown(), 20_000);
    }

    _formatTimeDiff(seconds) {
        const hours   = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const en      = this.locale.startsWith('en');

        if (hours === 0) {
            return en
                ? `${minutes} minutes`
                : `${minutes} Minuten`;
        }

        return en
            ? `${hours} hours and ${minutes} minutes`
            : `${hours} Stunden und ${minutes} Minuten`;
    }

    _updateCountdown() {
        const now    = Math.floor(Date.now() / 1000);
        const timeEl = this.el.querySelector('.delivery-time');
        let diff;

        if (now < this.cutoffTodayTs) {
            diff = this.cutoffTodayTs - now;
        } else {
            diff = this.cutoffTomorrowTs - now;
        }

        if (timeEl) {
            timeEl.textContent = this._formatTimeDiff(diff);
        }
    }

    destroy() {
        window.clearInterval(this._interval);
        super.destroy();
    }
}
