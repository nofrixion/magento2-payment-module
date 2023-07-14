define(
    [
        'jquery',
        'ko',
        'Magento_Customer/js/model/customer',
        'Magento_Ui/js/model/messages',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, ko, Customer, Messages, Component) {
        'use strict';
        var self;
        var baseRedirectUrl = window.checkoutConfig.payment.nofrixion.paymentRedirectUrl;
        var payByBankProviderId = '';
        //var buttonClicked = ko.observable(false);
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
            initiatePayment: function (data, event) {
                payByBankProviderId = data.personalInstitutionID;
                self.isProcessing(true);
                self.placeOrder(data, event);
            },
            getPayByBankProviders: function () {
                return self.payByBankProviders;
            },
            isCustomerLoggedIn: Customer.isLoggedIn,
            isProcessing: ko.observable(false),
            redirectAfterPlaceOrder: false,
            afterPlaceOrder: function () {
                var url = baseRedirectUrl + '?bankId=' + encodeURIComponent(payByBankProviderId);
                console.log('Redirecting to : ' + url);
                $.mage.redirect(url);
            }
        });
    }
);
