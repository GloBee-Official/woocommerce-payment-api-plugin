# WooCommerce Plugin

The WooCommerce plugin that uses the GloBee Payment API.

## How to install on your website

Please check the [Plugin Page](https://globee.com/woocommerce) for instructions on how to install the plugin on your
WooCommerce website.

## How to make development changes

Clone the repo:
```bash
$ git clone https://github.com/globee-official/woocommerce-payment-api-plugin
$ cd woocommerce-payment-api-plugin
```

Install the dependencies:
```bash
$ npm install
```

Make your changes and then build the plugin:
```bash
$ ./node_modules/.bin/grunt build
```
The built plugin archive is available at `dist/globee-woocommerce-1.0.0.zip`

## Versions
- Tested on WordPress v5.2.2 and WooCommerce v3.7.0
- Tested on WordPress v4.9.6 and WooCommerce v3.4.2
