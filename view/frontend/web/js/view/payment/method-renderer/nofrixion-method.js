define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
    ],
    function (
        Component,
        quote
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Nofrixion_Payments/payment/nofrixion',

            },
            redirectAfterPlaceOrder: false,
            externalRedirectUrl: null,
            // getMailingAddress: function () {
            //     return window.checkoutConfig.payment.checkmo.mailingAddress;
            // },
            // getInstructions: function () {
            //     return window.checkoutConfig.payment.instructions[this.item.method];
            // },


            initObservable: function () {
                this._super()
                    .observe([
                        'paymentElement',
                        'isPaymentFormComplete',
                        'isPaymentFormVisible',
                        'isLoading',
                        'stripePaymentsError',
                        'permanentError',
                        'isOrderPlaced',
                        'isInitializing',
                        'isInitialized',
                        'useQuoteBillingAddress',

                        // Saved payment methods dropdown
                        'dropdownOptions',
                        'selection',
                        'isDropdownOpen'
                    ]);

                var self = this;

                // this.initParams = window.checkoutConfig.payment["stripe_payments"].initParams;
                this.isPaymentFormVisible(false);
                this.isOrderPlaced(false);
                this.isInitializing(true);
                this.isInitialized(false);
                // this.useQuoteBillingAddress(false);
                // this.collectCvc = ko.computed(this.shouldCollectCvc.bind(this));
                // this.isAmex = ko.computed(this.isAmexSelected.bind(this));
                // this.cardCvcElement = null;
                //
                // trialingSubscriptions().refresh(quote); // This should be initially retrieved via a UIConfig
                //
                // var currentTotals = quote.totals();
                // var currentShippingAddress = quote.shippingAddress();
                // var currentBillingAddress = quote.billingAddress();
                //
                quote.totals.subscribe(function (totals) {
                        this.initPaymentForm();
                    }
                    , this);

                quote.paymentMethod.subscribe(function (method) {
                    // && !this.isInitializing()
                    if (method.method === this.getCode()) {
                        // We intentionally re-create the element because its container element may have changed

                        this.initPaymentForm();
                    }
                }, this);

                // quote.billingAddress.subscribe(function(address)
                // {
                //     if (address && self.paymentElement && self.paymentElement.update && !self.isPaymentFormComplete())
                //     {
                //         // Remove the postcode & country fields if a billing address has been specified
                //         var params = window.checkoutConfig.payment["stripe_payments"].initParams;
                //         self.paymentElement.update(self.getPaymentElementUpdateOptions(params));
                //     }
                // });


                return this;
            },

            getCode: function () {
                return 'nofrixion';
            },

            newPaymentMethod: function () {
                console.log('newPaymentMethod...');
                this.messageContainer.clear();

                this.selection({
                    type: 'new',
                    value: 'new',
                    icon: false,
                    label: $t('New payment method')
                });
                this.isDropdownOpen(false);
                this.isPaymentFormVisible(true);
                if (!this.isInitialized()) {
                    this.onContainerRendered();
                    this.isInitialized(true);
                }
            },

            initPaymentForm: function () {
                let params = window.checkoutConfig.payment["nofrixion"].initParams;
                this.isInitializing(false);
                console.log('Initializing Nofrixion payment form...');

                require(['https://api-sandbox.nofrixion.com/js/payelement.js']);

                let createPaymentRequestUrl = params.createPaymentRequestUrl;


                fetch(createPaymentRequestUrl)
                    .then(response => {
                        if (!response.ok) console.warn('GET request failed');
                        return response.json()
                    })
                    .then(data => {
                        let paymentRequestID = data.id;
                        let nfPayElement = new NoFrixionPayElement(paymentRequestID, 'nf-payelement', params.apiBaseUrl);
                        nfPayElement.load();

                    })
                    .catch(error => {
                        // optionally catch errors and display them
                    })
                    .finally(() => {
                        // optionally run some code that is always executed when done.
                        // for example, hiding a loading indicator
                    })


            },

            showError: function (message) {
                this.isLoading(false);
                //this.isPlaceOrderEnabled(true);
                this.messageContainer.addErrorMessage({"message": message});
            },

            placeOrder: function()
            {
                debugger;
                this.messageContainer.clear();

                if (!this.isPaymentFormComplete() && !this.getPaymentMethodId())
                    return this.showError($t('Please complete your payment details.'));

                if (!this.validate())
                    return;

                this.clearErrors();
                this.isPlaceOrderActionAllowed(false);
                this.isLoading(true);
                var placeNewOrder = this.placeNewOrder.bind(this);
                var reConfirmPayment = this.onOrderPlaced.bind(this);
                var self = this;

                if (this.isOrderPlaced()) // The order was already placed once but the payment failed
                {
                    updateCartAction(this.getPaymentMethodId(), function(result, outcome, response)
                    {
                        self.isLoading(false);
                        try
                        {
                            var data = JSON.parse(result);
                            if (data.error)
                            {
                                self.showError(data.error);
                            }
                            else if (data.redirect)
                            {
                                $.mage.redirect(data.redirect);
                            }
                            else if (data.placeNewOrder)
                            {
                                placeNewOrder();
                            }
                            else
                            {
                                reConfirmPayment();
                            }
                        }
                        catch (e)
                        {
                            self.showError($t("The order could not be placed. Please contact us for assistance."));
                            console.error(e.message);
                        }
                    });
                }
                else
                {
                    try
                    {
                        placeNewOrder();
                    }
                    catch (e)
                    {
                        self.showError($t("The order could not be placed. Please contact us for assistance."));
                        console.error(e.message);
                    }
                }

                return false;
            },

            placeNewOrder: function()
            {
                var self = this;

                this.isLoading(false); // Needed for the terms and conditions checkbox
                this.getPlaceOrderDeferredObject()
                    .fail(this.handlePlaceOrderErrors.bind(this))
                    .done(this.onOrderPlaced.bind(this))
                    .always(function(response, status, xhr)
                    {
                        if (status != "success")
                        {
                            self.isLoading(false);
                            self.isPlaceOrderEnabled(true);
                        }
                    });
            },

            onOrderPlaced: function(result, outcome, response)
            {
                if (!this.isOrderPlaced() && isNaN(result))
                    return this.softCrash("The order was placed but the response from the server did not include a numeric order ID.");
                else
                    this.isOrderPlaced(true);

                this.isLoading(true);
                var onConfirm = this.onConfirm.bind(this);
                var onFail = this.onFail.bind(this);

                // Non-card based confirms may redirect the customer externally. We restore the quote just before it in case the
                // customer clicks the back button on the browser before authenticating the payment.
                var self = this;
                restoreQuoteAction(function()
                {
                    // If we are confirming the payment with a saved method, we need a client secret and a payment method ID
                    var selectedMethod = self.getSelectedMethod("type");

                    var clientSecret = self.getStripeParam("clientSecret");
                    if (!clientSecret)
                        return self.softCrash("To confirm the payment, a client secret is necessary, but we don't have one.");

                    var isSetup = false;
                    if (clientSecret.indexOf("seti_") === 0)
                        isSetup = true;

                    var confirmParams = {
                        payment_method: self.getSelectedMethod("value"),
                        return_url: self.getStripeParam("successUrl")
                    };

                    var dropDownSelection = self.selection();
                    if (dropDownSelection && dropDownSelection.type == "card" && dropDownSelection.cvc == 1 && !isSetup)
                    {
                        confirmParams.payment_method_options = {
                            card: {
                                cvc: self.cardCvcElement
                            }
                        };
                    }

                    self.confirm.bind(self)(selectedMethod, confirmParams, clientSecret, isSetup, onConfirm, onFail);
                });
            },

            confirm: function(methodType, confirmParams, clientSecret, isSetup, onConfirm, onFail)
            {
                if (!clientSecret)
                    return this.softCrash("To confirm the payment, a client secret is necessary, but we don't have one.");

                if (methodType && methodType != 'new')
                {
                    if (!confirmParams.payment_method)
                        return this.softCrash("To confirm the payment, a saved payment method must be selected, but we don't have one.");

                    if (isSetup)
                    {
                        if (methodType == "card")
                            stripe.stripeJs.confirmCardSetup(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "sepa_debit")
                            stripe.stripeJs.confirmSepaDebitSetup(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "boleto")
                            stripe.stripeJs.confirmBoletoSetup(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "acss_debit")
                            stripe.stripeJs.confirmAcssDebitSetup(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "us_bank_account")
                            stripe.stripeJs.confirmUsBankAccountSetup(clientSecret, confirmParams).then(onConfirm, onFail);
                        else
                            this.showError($t("This payment method is not supported."));
                    }
                    else
                    {
                        if (methodType == "card")
                            stripe.stripeJs.confirmCardPayment(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "sepa_debit")
                            stripe.stripeJs.confirmSepaDebitPayment(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "boleto")
                            stripe.stripeJs.confirmBoletoPayment(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "acss_debit")
                            stripe.stripeJs.confirmAcssDebitPayment(clientSecret, confirmParams).then(onConfirm, onFail);
                        else if (methodType == "us_bank_account")
                            stripe.stripeJs.confirmUsBankAccountPayment(clientSecret, confirmParams).then(onConfirm, onFail);
                        else
                            this.showError($t("This payment method is not supported."));
                    }
                }
                else
                {
                    customerData.invalidate(['cart']);

                    var confirmParams = this.getConfirmParams();

                    // Confirm the payment using element
                    if (isSetup)
                    {
                        stripe.stripeJs.confirmSetup(confirmParams).then(onConfirm, onFail);
                    }
                    else
                    {
                        stripe.stripeJs.confirmPayment(confirmParams).then(onConfirm, onFail);
                    }
                }
            },

        });
    }
);


// initPaymentForm
