<?php
/**
 * Vinicius Augusto Cunha
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL).
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Payment (Pagamento)
 * @package    Prestige_BoletoSicob
 * @copyright  Copyright (c) 2015 Vinicius Augusto Cunha
 * @author     Vinicius Augusto Cunha <viniciusaugustocunha@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */



class Prestige_BoletoSicob_Model_Standard extends Mage_Payment_Model_Method_Abstract {

	protected $_code = 'boletosicob';
	protected $_order = null;


	/**
	 * Get checkout session namespace
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	public function getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}

	/**
	 *  Retorna pedido
	 *
	 *  @return	  Mage_Sales_Model_Order
	 */
	public function getOrder($order_id)
	{
		if ($order_id != ""){			
			$this->_order = Mage::getModel('sales/order')->load($order_id);
		}else{
			$orderIncrementId = $this->getCheckout()->getLastRealOrderId();
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		}
		return $this->_order;
	}	

	/**
	 * getCheckoutFormFields
	 *
	 * Gera os campos para o formulário de redirecionamento ao Banco do Brasil
	 *
	 * @return array
	 */
	public function getCheckoutFormFields($order_id,$tpPagamento)
	{
		
		$order = $this->getOrder($order_id);

		$pedido = $order->getData();// order details

		$customer_id = $order->getCustomerId();
		$customerData = Mage::getModel('customer/customer')->load($customer_id); // then load customer by customer id
	
		$cliente = $customerData->getData(); // customer details

		$date = new Zend_Date();
		$dataNascimento = new Zend_Date($cliente['dob'], 'YYYY-MM-dd HH:mm:ss');
				
		// Utiliza endereço de cobrança caso produto seja virtual/para download
		$address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();
		
        $numCliente = $this->getConfigData('numCliente', $order->getStoreId());
        $coopCartao = $this->getConfigData('coopCartao', $order->getStoreId());
        $chaveAcessoWeb = $this->getConfigData('chaveAcessoWeb', $order->getStoreId());
        $codMunicipio = $this->getConfigData('codMunicipio', $order->getStoreId());
       
        $_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $region = $_read->fetchRow('SELECT * FROM '.Mage::getConfig()->getTablePrefix().'directory_country_region WHERE default_name = "'.$address->getRegion().'"');
		
		$telefoneCompleto = $this->limpaTelefone($cliente['telefone']);
		$dd = $telefoneCompleto[0].$telefoneCompleto[1];
		$telefone = str_replace($dd, "", $telefoneCompleto);

		// Monta os dados para o formulário
		$fields = array(
				'numCliente'	   			=> $numCliente,
				'coopCartao'	   			=> $coopCartao,
				'chaveAcessoWeb'   			=> $chaveAcessoWeb,
				'numContaCorrente' 			=> '',//numero da conta corrente
				'codMunicipio' 	   			=> '',//codigo do municipio, fornecido pelo banco
				'nomeSacado' 	   			=> $address->getFirstname() . ' ' . $address->getLastname(),
				'dataNascimento'  			=> $dataNascimento->get('YYYYMMdd'),
				'cpfCGC'   		  			=> str_replace(".", "", $cliente['cpf']),
				'endereco'		   			=> $address->getStreet1(). ', '.$address->getStreet2(),
				'bairro'		   			=> $address->getStreet3(),
				'cidade'		   			=> $address->getCity(),
				'cep'              			=> str_replace('-', '', $address->getPostcode()),
				'uf'			   			=> $region['code'],
				'telefone'         			=> $telefone,
				'ddd'              			=> $dd,//$cliente['telefone'],
				'ramal'            			=> '',
				'bolRecebeBoletoEletronico' => 1,
				'email'						=> $cliente['email'],
				'codEspDocumento'           => 'DM',
				'dataEmissao' 				=> date('Ymd'),
				'seuNumero'					=> '',
				'nomeSacador'               => '',//Nome do Sacador
				'numCGCCPFSacador'          => '',//CNPJ ou CPF do Sacador
				'qntMonetaria'              => 1,
				'valorTitulo'               => number_format($order->getGrandTotal(), 2,'.', ','),
				'codTipoVencimento'			=> '1',
				'dataVencimentoTit'			=> date('Ymd',strtotime("+3 day")),
				'valorAbatimento' 			=> '0',
				'valorIOF' 					=> '0',
				'bolAceite'					=> '1',
				'percTaxaMulta'				=> '0',
				'percTaxaMora'				=> '0',
				'dataPrimDesconto' 			=> NULL,
				'valorSegDesconto'   		=> NULL,
				'descInstrucao1'			=> 'Pedido #'.$pedido["increment_id"].'',
				'descInstrucao2'			=> 'Pedido efetuado na loja seu-site.com.br.',
				'descInstrucao3' 			=> 'Em 2(dois) dias úteis para confirmação',
				'descInstrucao4'			=> 'Não receber aṕos o vencimento',
				'descInstrucao5' 			=> 'Não receber pagamento em cheque'
		);
		
		return $fields;
	}
	
	public function createRedirectForm($order_id,$tpPagamento = 2)
	{
		$form = new Varien_Data_Form();
		$form->setAction($this->getBancoUrl())
		->setId('boletosicob_checkout')
		->setName('pagamento')
		->setMethod('POST')
		->setUseContainer(true);
				
		$fields = $this->getCheckoutFormFields($order_id,$tpPagamento);
		foreach ($fields as $field => $value) {
			$form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
		}
		$html = $form->toHtml();
		$submit_script = 'document.getElementById(\'boletosicob_checkout\').submit();';
	
		$html  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$html .= '<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="pt-BR">';
		$html .= '<head>';
		$html .= '<meta http-equiv="Content-Language" content="pt-br" />';
		$html .= '<meta name="language" content="pt-br" />';
		$html .= '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />';
		$html .= '<style type="text/css">';
		$html .= '* { font-family: Arial; font-size: 16px; line-height: 34px; text-align: center; color: #222222; }';
		$html .= 'small, a, a:link:visited:active, a:hover { font-size: 13px; line-height: normal; font-style: italic; }';
		$html .= 'a, a:link:visited:active { font-weight: bold; text-decoration: none; }';
		$html .= 'a:hover { font-weight: bold; text-decoration: underline; color: #555555; }';
		$html .= '</style>';
		$html .= '</head>';
		$html .= '<body onload="' . $submit_script . '">';
		$html .= 'Você será redirecionado ao <strong>Banco SICOOB</strong> em alguns instantes.<br />';
		$html .= '<small>Se a página não carregar, <a href="#" onclick="' . $submit_script . ' return false;">clique aqui</a>.</small>';
		$html .= $form->toHtml();
		$html .= '</body></html>';
		
		//echo utf8_decode($html);		

		return $html;
	
	}
	
	public function getBancoUrl()	{
		//url para gerar o boleto
		return 'https://geraboleto.sicoobnet.com.br/geradorBoleto/GerarBoleto.do';
	}
	
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl($this->getCode().'/standard/redirect', array('_secure' => true));
	}
        
    protected function _geraRefTran($idconvc,$nrOrder){
        $count_idconv = strlen($idconvc);
        $count_nrOrder = strlen($nrOrder);
        
        $refTran = $idconvc;
        
        for ($i = 0; $i < ((($count_idconv+$count_nrOrder) - 17)*-1); $i++){
            $refTran.= '0';
        }
        
        $refTran.= $nrOrder;
        
        return $refTran;
    }

    public function limpaTelefone($telefone)
    {
    	$str = trim($telefone);

    	$retorno = str_replace("(","", $str);
		$retorno = str_replace(")","", $retorno);
		$retorno = str_replace("-", "", $retorno);
		$retorno = str_replace(" ", "", $retorno);

		return $retorno;
    }
}
?>