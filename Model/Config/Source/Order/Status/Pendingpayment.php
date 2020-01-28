<?php

namespace Kaisari\BoletoBradesco\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;


class Pendingpayment extends Status {
    /**
     * @var string[]
     */
    protected $_stateStatuses = [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW];
}
