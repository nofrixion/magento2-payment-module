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
            processing: false,
            afterPlaceOrder: function () {
                var url = baseRedirectUrl + '?bankId=' + encodeURIComponent(payByBankProviderId);
                console.log('Redirecting to : ' + url);
                $.mage.redirect(url);
            },
            initiated: false,
            initiatePayment: function (data, event) {
                payByBankProviderId = data.personalInstitutionID;
                self.placeOrder(data, event);
            },
            setBankButtonsPrompt: function () {
                const prompts = [
                    "Click your bank's logo to commence payment",
                    "A valid email address and billing address are reqiured to enable pay by bank"
                ];
                if (self.isPlaceOrderActionAllowed() || self.initiated) {
                    return prompts[0];
                } else {
                    return prompts[1];
                }
            }
        });
    }
);
