# JunuModernBooster

**JUNU ModernBooster | Premium-Theme** is a Shopware 6 plugin that enhances your online store with exclusive design and conversion-boosting features. Built on ThemeWare® Modern Pro, this plugin elevates your shop to the next level.

## Features

- **Exclusive Design**: Modern and sleek storefront enhancements.
- **Offcanvas Cart Cross-Selling**: Display recommended products in the cart to boost sales.
- **Delivery Information**: Configurable cutoff times, weekend shipping support, and ignored dates for accurate delivery estimates.
- **Free Shipping Progress Bar**: Visual indicator in the cart to encourage higher order values.
- **Confetti Effect**: Celebratory animation on the checkout confirmation page.
- **Customizable Settings**: Admin panel configuration for products, shipping, and more.

## Requirements

- **Shopware**: 6.6.10.*
- **ThemeWare® Modern**: <=3.6.5
- **PHP**: Compatible with Shopware 6 requirements (typically PHP 8.1+)

## Installation

1. Clone or download the repository to your Shopware `custom/plugins/` directory:

2. Install and activate the plugin:

   ```bash
   bin/console plugin:install --activate JunuModernBooster
   ```

3. Clear the cache:

   ```bash
   bin/console cache:clear
   ```

4. Compile the storefront (if necessary):

   ```bash
   bin/console theme:compile
   ```

## Configuration

1. Navigate to **Extensions > My Extensions** in the Shopware admin panel.
2. Find **JUNU ModernBooster | Premium-Theme** and click **Configure**.
3. Adjust settings under:
   - **Basic Configuration**: Select products for plugin features.
   - **Delivery Settings**: Set cutoff times, weekend shipping, and ignored dates.
   - **Separated Products**: Choose products or product groups for cross-selling.
   - **Confetti Effect**: Enable/disable the checkout confetti animation.

## Usage

- **Cross-Selling**: Products configured in the admin will appear in the offcanvas cart slider.
- **Delivery Information**: Displayed on product pages, respecting cutoff times and ignored dates.
- **Free Shipping**: Progress bar appears in the cart, based on the configured threshold.
- **Confetti Effect**: Automatically triggered on the checkout confirmation page if enabled.

## Support

For issues, feature requests, or inquiries:
- **Website**: [https://ju.nu](https://ju.nu)
- **Support**: [https://support.ju.nu](https://support.ju.nu)
- **Email**: support@ju.nu

## License

This plugin is proprietary software. See the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are not accepted for this proprietary plugin. For custom development or feature requests, contact [support@ju.nu](mailto:support@ju.nu).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed list of changes and updates.

---

**JUNU ModernBooster** is developed by [JUNU Marketing Group LTD](https://ju.nu). Elevate your Shopware store today!


