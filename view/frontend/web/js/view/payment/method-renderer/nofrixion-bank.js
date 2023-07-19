define(
    [
        'jquery',
        'ko',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, ko, customer, Component) {
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
                self.payByBankProviders = window.checkoutConfig.payment.nofrixion.payByBankProviders;
            },
            isCustomerLoggedIn: customer.isLoggedIn,
            isProcessing: ko.observable(false),
            redirectAfterPlaceOrder: false,
            initiatePayment: function (data, event) {
                payByBankProviderId = data.personalInstitutionID;
                if (self.placeOrder(data, event)) {
                    // placeOrder returns true if form validates
                    self.isProcessing(true);
                };
            },
            getPayByBankProviders: function () {
                return self.payByBankProviders;
            },
            afterPlaceOrder: function () {
                var url = baseRedirectUrl + '?bankId=' + encodeURIComponent(payByBankProviderId);
                console.log('Redirecting to : ' + url);
                $.mage.redirect(url);
            }
        });
    }
);
