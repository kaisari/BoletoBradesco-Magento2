<?php

namespace Kaisari\BoletoBradesco\Controller\Order;

class Update extends \Magento\Framework\App\Action\Action {
    protected $_context;
    protected $_pageFactory;
    protected $_jsonEncoder;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\EncoderInterface $encoder,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $creditmemoFactory
    ) {
        $this->_objectManager      = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_invoiceService     = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->_context            = $context;
        $this->_pageFactory        = $pageFactory;
        $this->_jsonEncoder        = $encoder;
        parent::__construct($context);
    }

    public function execute() {
        if($numero_pedido = $this->_context->getRequest()->getParam('numero_pedido')) {
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($numero_pedido);
            if($order->getId()) {
                $auth = $this->autenticar();
                if(isset($auth['resposta']['token']['token'])) {
                    $token = $auth['resposta']['token']['token'];
                    $this->updatePayment($order, $token);
                }
            }
        } else {
            $auth = $this->autenticar();
            if(isset($auth['resposta']['token']['token'])) {
                $token      = $auth['resposta']['token']['token'];
                $status     = $this->helper()->getConfig('order_status');
                $collection = $this->_objectManager->create('Magento\Sales\Model\Order')->getCollection();
                $collection->getSelect()->join(
                    ["sop" => "sales_order_payment"],
                    'main_table.entity_id = sop.parent_id',
                    ['method']
                )->where('sop.method = ?', 'kaisari_boletobradesco')
                           ->where('main_table.status = ?', $status);

                foreach($collection as $order) {
                    $this->updatePayment($order, $token);
                }
            }
        }
    }

    protected function autenticar() {
        $mid                 = $this->helper()->getConfig('merchant_id');
        $chave               = $this->helper()->getConfig('secret');
        $user                = $this->helper()->getConfig('user');
        $url                 = $this->helper()->getApiUrl() . '/SPSConsulta/Authentication/' . $mid;
        $AuthorizationHeader = trim($user) . ":" . trim($chave);

        //curl
        $headers = array('Accept: application/json', 'Accept-Charset: UTF-8', 'Content-Type: application/json');
        $curl    = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $AuthorizationHeader);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_response = curl_exec($curl);
        $httpcode      = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $resposta = @json_decode($curl_response, TRUE);
        if(!$resposta) {
            $resposta = $curl_response;
        }
        return array('status' => $httpcode, 'resposta' => $resposta);
    }

    protected function updatePayment($order, $token) {
        $mid         = $this->helper()->getConfig('merchant_id');
        $chave       = $this->helper()->getConfig('secret');
        $user        = $this->helper()->getConfig('user');
        $orderId     = $order->getIncrementId();
        $service_url = $this->helper()->getApiUrl() . "/SPSConsulta/GetOrderById/{$mid}?token={$token}&orderId={$orderId}";

        $AuthorizationHeader = trim($user) . ":" . trim($chave);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8'
        ];
        $ch      = curl_init();
        curl_setopt($ch, CURLOPT_URL, $service_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $AuthorizationHeader);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);
        $httpcode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $resposta = @json_decode($curl_response, TRUE);
        if(!$resposta) {
            return;
        }

        if(isset($resposta['pedidos'][0])) {
            $pedido = $resposta['pedidos'][0];
            //se pago
            if(isset($pedido['status']) && ($pedido['status'] == 21 || $pedido['status'] == 23 || TRUE)) { //TODO
                echo 'Boleto - ' . $pedido['numero'] . ' pago!<br>';
                if($order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(FALSE);
                    $invoice->getOrder()->setIsInProcess(TRUE);
                    $order->addStatusHistoryComment('Pagamento aprovado Bradesco ShopFácil.', 'processing');
                    $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                }
            } elseif(isset($pedido['status']) && $pedido['status'] == 22) {//se pago menor
                $order->addStatusHistoryComment('Bradesco Boleto API - ' . $pedido['numero'] . ' Pago Menor!');
                echo 'Boleto - ' . $pedido['numero'] . ' pago menor!<br>';
            } else {//verifica se expirado ou aguardando pagamento
                $fator_expira = (int)($this->helper()->getConfig('days_due_date') + 2);
                $data_pedido  = $order->getCreatedAt();
                if(time() > strtotime("+{$fator_expira} day", strtotime($data_pedido))) {
                    if($order->canCancel()) {
                        $order->cancel()->save();
                    }
                    echo 'Boleto - ' . $pedido['numero'] . ' expirado!<br>';
                } else {
                    echo 'Boleto - ' . $pedido['numero'] . ' aguardando pagamento!<br>';
                }
            }
        }
    }

    protected function helper() {
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Kaisari\BoletoBradesco\Helper\Data');
    }

    private function log($msg) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/boletobradesco.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }
}
