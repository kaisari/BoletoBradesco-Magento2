<?php

namespace Kaisari\BoletoBradesco\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;


class Completepayment extends Status {
    /**
     * @var string[]
     */
    protected $_stateStatuses = [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED, Order::STATE_PAYMENT_REVIEW];
}
