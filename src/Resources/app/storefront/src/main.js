import DeliveryCountdownPlugin from './plugin/delivery-countdown.plugin';
import ConfettiPlugin from './plugin/confetti.plugin';

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
    'body.is-ctl-checkout.is-act-finishpage'
);

if (module.hot) {
    module.hot.accept();
}