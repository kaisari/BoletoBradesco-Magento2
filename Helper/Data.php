<?php

namespace Kaisari\BoletoBradesco\Helper;

use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {
    protected $storeManager;
    protected $scopeConfig;

    public function __construct(StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig) {
        $this->storeManager = $storeManager;
        $this->scopeConfig  = $scopeConfig;
    }

    public function getApiUrl() {
        if ($this->getConfig('sandbox')) {
            return 'https://homolog.meiosdepagamentobradesco.com.br/apiboleto/transacao';
        } else {
            return 'https://meiosdepagamentobradesco.com.br/apiboleto/transacao';
        }
    }

    public function getConfig($config) {
        return $this->scopeConfig->getValue('payment/kaisari_boletobradesco/' . $config, \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
    }

    public function getNotificationUrl() {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . 'boletobradesco/order/update';
    }
}
