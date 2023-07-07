define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'nofrixion',
                component: 'Nofrixion_Payments/js/view/payment/method-renderer/nofrixion-bank'
            }
        );
        return Component.extend({});
    }
);