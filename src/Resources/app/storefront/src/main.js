import DeliveryCountdownPlugin from './plugin/delivery-countdown.plugin';
import ConfettiPlugin from './plugin/confetti.plugin';
import WhitespaceCleanerPlugin from './plugin/whitespace-cleaner.plugin'; // Import the new plugin

const PluginManager = window.PluginManager;

PluginManager.deregister('FlyoutMenu', '[data-flyout-menu]');

PluginManager.register(
    'DeliveryCountdown',
    DeliveryCountdownPlugin,
    'body.junu.is-ctl-product [data-delivery-countdown]'
);

PluginManager.register(
    'Confetti',
    ConfettiPlugin,
    '[data-confetti-plugin]'
);

PluginManager.register(
    'WhitespaceCleaner',
    WhitespaceCleanerPlugin,
    'body.junu.is-act-finishpage .finish-order-subtitle' // Selector for plugin
);

if (module.hot) {
    module.hot.accept();
}