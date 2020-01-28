define([
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote'
    ],
    function ($, Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Kaisari_BoletoBradesco/payment/boletobradesco',
            },
            isAvailable: function () {
                return true;
            },
            context: function() {
                return this;
            },
            getCode: function() {
                return 'kaisari_boletobradesco';
            },
            isActive: function() {
                return true;
            }
        });
    }
);

