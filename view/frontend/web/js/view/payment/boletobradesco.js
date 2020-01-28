define(['uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
], function (Component, rendererList) {
    'use strict';
    rendererList.push({
        type: 'kaisari_boletobradesco',
        component: 'Kaisari_BoletoBradesco/js/view/payment/method-renderer/boletobradesco'
    });

    /** Add view logic here if needed */
    return Component.extend({});
});
