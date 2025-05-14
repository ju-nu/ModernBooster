import DeliveryCountdownPlugin from './plugin/delivery-countdown.plugin';

const PluginManager = window.PluginManager;

PluginManager.deregister('FlyoutMenu', '[data-flyout-menu]');

PluginManager.register(
    'DeliveryCountdown',
    DeliveryCountdownPlugin,
    'body.junu.is-ctl-product [data-delivery-countdown]'
);
