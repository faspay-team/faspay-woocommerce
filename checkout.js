const settings = window.wc.wcSettings.getSetting( 'faspay_gateway_data', {} );
const label = window.wp.i18n.__( 'Pay with', 'faspay_gateway' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || window.wp.i18n.__( 'You will be redirected to Faspay Xpress to pay with online payment. Faspay Secures your payment and protect your financial information.', 'faspay_gateway' ));
};
const icon = '../wp-content/plugins/faspay-woocommerce/assets/img/paywithfaspay.png';
const Block_Gateway = {
    name: 'faspay_gateway',
    label: window.wp.element.createElement(() =>
      window.wp.element.createElement(
        "span",
        null,
        label + " ",
        window.wp.element.createElement("img", {
          src: icon,
          alt: label,
          id: 'payment-icon'
        }),
      )
    ),
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

var style = document.createElement('style');
  document.head.appendChild(style);
  style.sheet.insertRule('#payment-icon {vertical-align: middle}');

window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );