import Plugin from 'src/plugin-system/plugin.class';

export default class DeliveryCountdown extends Plugin {
    init() {
        this.el = document.querySelector('#delivery-message');
        if (!this.el) {
            console.error('DeliveryCountdown: #delivery-message element not found');
            return;
        }

        this.locale = this.el.dataset.locale || 'en-GB';
        this.snippetOrderWithin = this.el.dataset.snippetOrderwithin;
        this.snippetOrderWithinTomorrow = this.el.dataset.snippetOrderwithintomorrow;

        if (!this.snippetOrderWithin || !this.snippetOrderWithinTomorrow) {
            console.error('DeliveryCountdown: Missing snippet data attributes');
            return;
        }

        this.remainingHours = null;
        this.remainingMins = null;
        this.deliveryDate = null;
        this.formattedDate = null;
        this.snippetKey = null;

        this._fetchCountdown();
        this._interval = window.setInterval(() => this._updateCountdown(), 60000); // Update every minute
        this._fetchInterval = window.setInterval(() => this._fetchCountdown(), 300000); // Re-fetch every 5 minutes
    }

    _fetchCountdown() {
        fetch('/JunuModernBooster/delivery-information', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format');
                }

                const { hours, mins, deliveryDate, formattedDate, snippetKey } = data;

                if (typeof hours !== 'string' || typeof mins !== 'string' || !deliveryDate || !formattedDate || !snippetKey) {
                    throw new Error('Missing or invalid response fields');
                }

                // Store response for client-side updates
                this.remainingHours = parseInt(hours);
                this.remainingMins = parseInt(mins);
                this.deliveryDate = deliveryDate;
                this.formattedDate = formattedDate;
                this.snippetKey = snippetKey;

                this._renderCountdown();
            })
            .catch(error => {
                console.error('Error fetching countdown:', error);
                this.el.innerHTML = '<i class="fa-solid fa-exclamation"></i> Delivery information unavailable';
            });
    }

    _updateCountdown() {
        if (this.remainingHours === null || this.remainingMins === null) {
            return; // Wait for initial fetch
        }

        this.remainingMins--;
        if (this.remainingMins < 0) {
            this.remainingMins = 59;
            this.remainingHours--;
        }

        // Re-fetch if approaching cutoff or hours go negative
        if (this.remainingHours < 0 || (this.remainingHours === 0 && this.remainingMins <= 5)) {
            this._fetchCountdown();
            return;
        }

        this._renderCountdown();
    }

    _renderCountdown() {
        // Select snippet based on snippetKey
        const snippet = this.snippetKey === 'junu.product.delivery.orderWithin'
            ? this.snippetOrderWithin
            : this.snippetOrderWithinTomorrow;

        // Replace placeholders
        let html = snippet
            .replace('%hours%', String(this.remainingHours))
            .replace('%mins%', String(this.remainingMins))
            .replace('%date%', this.formattedDate);

        // Remove hours span if hours is 0
        if (this.remainingHours === 0) {
            html = html.replace(/<span class="hours">.*<\/span>/, '');
        }

        // Inject HTML into container
        this.el.innerHTML = html;
    }

    destroy() {
        if (this._interval) {
            window.clearInterval(this._interval);
        }
        if (this._fetchInterval) {
            window.clearInterval(this._fetchInterval);
        }
        super.destroy();
    }
}