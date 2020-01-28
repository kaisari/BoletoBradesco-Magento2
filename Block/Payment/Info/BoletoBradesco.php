<?php

namespace Kaisari\BoletoBradesco\Block\Payment\Info;

use Magento\Payment\Block\Info;
use Magento\Framework\DataObject;

class BoletoBradesco extends Info {
    const TEMPLATE = 'Kaisari_BoletoBradesco::info/boletobradesco.phtml';

    public function _construct() {
        $this->setTemplate(self::TEMPLATE);
    }

    public function getTitle() {
        return $this->getInfo()->getMethodInstance()->getTitle();
    }
}
