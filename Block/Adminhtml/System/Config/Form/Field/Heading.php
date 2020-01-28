<?php

namespace Kaisari\BoletoBradesco\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Heading extends Field {
    /**
     * @var string
     */
//    protected $_template = 'system/config/form/field/heading.phtml';

    /**
     * @var ModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Activation constructor.
     *
     * @param Context $context
     * @param mixed[] $data
     */
    public function __construct(Context $context, \Magento\Store\Model\StoreManagerInterface $storeManager, array $data = []) {
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve HTML markup for given form element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element) {
        $html = sprintf(
            '<td colspan="%d" id="%s">%s</td>',
            3 + (int)$this->_isInheritCheckboxRequired($element),
            $element->getHtmlId(),
            $this->_renderValue($element)
        );

        $url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . 'boletobradesco/order/update';
        $url = "<p><b>URL de confirmação do pedido:</b> {$url}</p>";

        return $this->_decorateRowHtml($element, $html).$url;
    }

    /**
     * Render element value
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _renderValue(AbstractElement $element) {
        return $this->_toHtml();
    }
}
