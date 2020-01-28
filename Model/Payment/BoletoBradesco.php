<?php

namespace Kaisari\BoletoBradesco\Model\Payment;

use Magento\Framework\App\RequestInterface;

class BoletoBradesco extends \Magento\Payment\Model\Method\AbstractMethod {
    protected $_code                   = 'kaisari_boletobradesco';
    protected $_supportedCurrencyCodes = ['BRL'];
    protected $_canOrder               = TRUE;
    protected $_canCapture             = TRUE;
    protected $_canAuthorize           = TRUE;

    protected $_infoBlockType = 'Kaisari\BoletoBradesco\Block\Payment\Info\BoletoBradesco';

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        if ($this->canOrder()) {
            $info = $this->getInfoInstance();

            $order = $payment->getOrder();
            $data  = $this->helper()->createOrderArray($order, $payment);

            if (!isset($data['error'])) {
                $generate = $this->helper()->generate($data);
                if (isset($generate['success']) && $generate['success']) {
                    $this->helper()->addInformation($order, $generate['additional']);
                } else if ($generate['message']) {
                    throw new \Magento\Framework\Exception\CouldNotSaveException(
                        __($generate['message'])
                    );
                } else {
                    $this->log('Erro ao else if;');
                }
            } else {
                $message = isset($data['error_message']) ? $data['error_message'] : 'Erro ao gerar boleto.';
                throw new \Magento\Framework\Exception\CouldNotSaveException(
                    __($message)
                );
            }
        } else {
            $this->log('Não entrou no canOrder');
        }
    }

    protected function helper() {
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Kaisari\BoletoBradesco\Helper\Order');
    }

    protected function log($msg) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/boletobradesco.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }

}

