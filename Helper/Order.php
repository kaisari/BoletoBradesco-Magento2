<?php

namespace Kaisari\BoletoBradesco\Helper;

use \Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\RequestInterface;

class Order extends \Magento\Framework\App\Helper\AbstractHelper {
    protected $orderFactory;
    protected $request;

    public function __construct(OrderFactory $orderFactory, RequestInterface $request) {
        $this->orderFactory = $orderFactory;
        $this->request      = $request;
    }

    public function createOrderArray($order, $payment) {
        if ($order && $order->getRealOrderId()) {
            $customerId      = $order->getCustomerId();
            $objectManager   = \Magento\Framework\App\ObjectManager::getInstance();
            $customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory')->create();
            $customer        = $customerFactory->load($customerId);
            $objectManager   = \Magento\Framework\App\ObjectManager::getInstance();

            $taxvat     = preg_replace("/[^0-9]/", "", $customer->getTaxvat());
            $address    = $order->getBillingAddress();
            $name       = $address->getFirstname() . ' ' . $address->getLastname();
            $total      = $order->getGrandTotal();
            $vencimento = (int)($this->helper()->getConfig('days_due_date'));
            $region     = $objectManager->create('Magento\Directory\Model\Region')->load($address->getRegionId());
            $logo       = $objectManager->get('\Magento\Theme\Block\Html\Header\Logo')->getLogoSrc();

            if(!$this->validateTaxvat($taxvat)) {
                return ['error' => TRUE, 'error_message' => 'CPF/CNPJ inválido. ' . $taxvat];
            }

            $data = [
                'merchant_id'    => $this->helper()->getConfig('merchant_id'),
                'meio_pagamento' => '300',
                'pedido'         => [
                    'numero'    => $order->getIncrementId(),
                    'valor'     => number_format($total, 2, '', ''),
                    'descricao' => 'Pedido #' . $order->getIncrementId()
                ],
                'comprador'      => [
                    'nome'       => $name,
                    'documento'  => $taxvat,
                    'ip'         => $this->get_client_ip(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'',
                    'endereco'   => [
                        'cep'         => preg_replace("/[^0-9]/", "", $address->getPostcode()),
                        'logradouro'  => (isset($address->getStreet()[0]))?$address->getStreet()[0]:'',
                        'numero'      => (isset($address->getStreet()[1]))?$address->getStreet()[1]:'',
                        'complemento' => '',
                        'bairro'      => (isset($address->getStreet()[2]))?$address->getStreet()[2]:'',
                        'cidade'      => $address->getCity(),
                        'uf'          => $region->getCode()
                    ]
                ],
                'boleto'         => [
                    'beneficiario'       => $this->helper()->getConfig('beneficiario'),
                    'carteira'           => $this->helper()->getConfig('carteira'),
                    'nosso_numero'       => str_pad($order->getIncrementId(), 11, "0", STR_PAD_LEFT),
                    'data_emissao'       => date('Y-m-d'),
                    'data_vencimento'    => date('Y-m-d', strtotime("+" . $vencimento . " days")),
                    'valor_titulo'       => number_format($total, 2, '', ''),
                    //'url_logotipo'       => $logo,
                    'mensagem_cabecalho' => $this->helper()->getConfig('mensagem'),
                    'tipo_renderizacao'  => '2',
                    'instrucoes'         => NULL,
                    'registro'           => NULL
                ],
                'token_request_confirmacao_pagamento' => md5(sha1($this->helper()->getConfig('merchant_id')))
            ];

            return $data;
        } else {
            return ['error' => TRUE];
        }
    }

    public function get_client_ip() {
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    public function validateTaxvat($taxvat) {
        //Caso seja CNPJ
        if (strlen($taxvat) == 14) {
            return $this->validateCnpj($taxvat);
        }

        //Caso seja CPF
        if (strlen($taxvat) == 11) {
            return $this->validateCpf($taxvat);
        }
    }

    public function generate($data) {
        $url = $this->helper()->getApiUrl() . '/apiboleto/transacao';
        $json = json_encode($data);

        //auth
        $mid = $this->helper()->getConfig('merchant_id');
        $chave = $this->helper()->getConfig('secret');
        $AuthorizationHeader = trim($mid).":".trim($chave);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8'
        ];
        $ch      = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $AuthorizationHeader);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $curl_response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG']) {
            $this->log("\n" . print_r($data, TRUE) . "\nRETURN CODE: {$httpcode}\nRESPONSE: {$curl_response}");
        }

        if($httpcode == 200 || $httpcode == 201) {
            $resposta_boleto = json_decode($curl_response, TRUE);

            if(isset($resposta_boleto['status']) && $resposta_boleto['status']['codigo'] == 0) {
                return ['success' => TRUE, 'additional' => [
                    'boleto_url'      => $resposta_boleto['boleto']['url_acesso'],
                    'linha_digitavel' => $resposta_boleto['boleto']['linha_digitavel'],
                    'vencimento'      => $data['boleto']['data_vencimento']
                ]];
            } elseif(isset($resposta_boleto['status'])) {
                $erro_boleto = isset($resposta_boleto['resposta']['status']['mensagem'])?$resposta_boleto['resposta']['status']['mensagem']:'Erro ao gerar Boleto junto ao Bradesco!';
                $this->log($erro_boleto);
                return ['success' => FALSE, 'message' => $erro_boleto];
            } else {
                $erro_boleto = 'Erro ao gerar Boleto junto ao Bradesco!';
                $this->log($erro_boleto);
                return ['success' => FALSE, 'message' => $erro_boleto];
            }
        } else {
            $this->log('Problema ao gerar boleto #' . $data['order_id'] . ': ' . $curl_response);
            return ['success' => FALSE, 'message' => $curl_response];
        }
    }

    public function addInformation($order, $additional) {
        if ($order && is_array($additional) && count($additional) >= 1) {
            $_additional = $order->getPayment()->getAdditionalInformation();
            foreach ($additional as $key => $value) {
                $_additional[$key] = $value;
            }
            $this->log($_additional);
            $order->getPayment()->setAdditionalInformation($_additional);
        } else {
            $this->log('Problema no IF');
            $this->log(var_export($additional));
        }
    }

    protected function helper() {
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Kaisari\BoletoBradesco\Helper\Data');
    }

    private function validateCpf($taxvat) {
        if (empty($taxvat)) {
            return FALSE;
        }

        $taxvat = preg_replace('#[^0-9]#', '', $taxvat);
        $taxvat = str_pad($taxvat, 11, '0', STR_PAD_LEFT);

        if (strlen($taxvat) != 11) {
            return FALSE;
        }

        if ($taxvat == '00000000000' ||
            $taxvat == '11111111111' ||
            $taxvat == '22222222222' ||
            $taxvat == '33333333333' ||
            $taxvat == '44444444444' ||
            $taxvat == '55555555555' ||
            $taxvat == '66666666666' ||
            $taxvat == '77777777777' ||
            $taxvat == '88888888888' ||
            $taxvat == '99999999999'
        ) {
            return FALSE;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $taxvat{$c} * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;

            if ($taxvat{$c} != $d) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function validateCnpj($taxvat) {
        $taxvat = preg_replace('/[^0-9]/', '', (string)$taxvat);

        if (strlen($taxvat) != 14) {
            return FALSE;
        }

        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $taxvat{$i} * $j;
            $j    = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        if ($taxvat{12} != ($resto < 2 ? 0 : 11 - $resto)) {
            return FALSE;
        }

        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $taxvat{$i} * $j;
            $j    = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        return $taxvat{13} == ($resto < 2 ? 0 : 11 - $resto);
    }

    private function log($msg) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/boletobradesco.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }

}
