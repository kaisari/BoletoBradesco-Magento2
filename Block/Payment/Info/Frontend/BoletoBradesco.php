<?php

namespace Kaisari\BoletoBradesco\Block\Payment\Info\Frontend;

class BoletoBradesco extends \Magento\Checkout\Block\Onepage\Success {
    public function getOrder() {
        return $this->_checkoutSession->getLastRealOrder();
    }
}
