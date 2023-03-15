define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Nofrixion_Payments/payment/nofrixion',
            },
            redirectAfterPlaceOrder: false,
            afterPlaceOrder: function () {
                $.mage.redirect(window.checkoutConfig.payment.nofrixion.paymentRedirectUrl);
            }
        });
    }
);
