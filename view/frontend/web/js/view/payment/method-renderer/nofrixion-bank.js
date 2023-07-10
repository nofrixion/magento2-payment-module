define(
    [
        'jquery',
        'Magento_Customer/js/model/customer',
        'Magento_Ui/js/model/messages',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, Customer, Messages, Component) {
        'use strict';
        var self;
        var baseRedirectUrl = window.checkoutConfig.payment.nofrixion.paymentRedirectUrl;
        var payByBankProviderId = '';
        return Component.extend({
            defaults: {
                template: 'Nofrixion_Payments/payment/nofrixion-bank'
            },
            initialize: function () {
                //initialize parent Component
                this._super();
                self = this;
            },
            isCustomerLoggedIn: Customer.isLoggedIn,
            redirectAfterPlaceOrder: false,
            afterPlaceOrder: function () {
                var url = baseRedirectUrl + '?bankId=' + encodeURIComponent(payByBankProviderId);
                console.log('Redirecting to : ' + url);
                $.mage.redirect(url);
            },
            initiatePayment: function (data, event) {
                payByBankProviderId = data.personalInstitutionID;
                self.placeOrder(data, event);
            }
        });
    }
);
