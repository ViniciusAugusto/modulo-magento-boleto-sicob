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
 * @package    ViniciusCunha_BoletoSicob
 * @copyright  Copyright (c) 2015 Vinicius Augusto Cunha
 * @author     Vinicius Augusto Cunha <viniciusaugustocunha@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class ViniciusCunha_BoletoSicob_StandardController extends Mage_Core_Controller_Front_Action
{
    
	/**
	 * Retorna o singleton do Boleto Sicoob
	 *
	 * @return ViniciusCunhas_BoletoSicob_Model_Standard
	 */
	protected  function getBoletoSicob()
	{
		return Mage::getSingleton('boletosicob/standard');
	}
	
	/**
	 * Retorna o Checkout
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected  function getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}
	
	/**
	 * Redireciona o cliente ao Banco SICOOB na finalização do pedido
	 *
	 */
	public function redirectAction()
	{
		
		$boleto_sicob = $this->getBoletoSicob();

		$session = $this->getCheckout();		
		
		$orderIncrementId = $session->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);


		if ($order->getId()) {
			
			// Envia email de confirmação ao cliente
			if(!$order->getEmailSent()) {
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				$order->save();
			}
						
			$html = $boleto_sicob->createRedirectForm();			
			
			$this->getResponse()->setHeader("Content-Type", "text/html; charset=ISO-8859-1", true);
			$this->getResponse()->setBody($html);
		}else {
            $this->_redirect('');
        }
	}
	
	
	
	protected function _loadValidOrder($orderId = null) {
		if ($orderId == null) {
			$orderId = (int) $this->getRequest()->getParam('order_id');
		}
		if (!$orderId) {
			$this->_forward('noRoute');
			return false;
		}
	
		$order = Mage::getModel('sales/order')->load($orderId);
		if ($this->_canViewOrder($order)) {
			Mage::register('current_order', $order);
			return true;
		} else {
			$this->_redirect('sales/order/view/order_id/'.$orderId);
			return false;
		}
	}
	
	
	protected function _canViewOrder($order) {
		$customerId = Mage::getSingleton('customer/session')->getCustomerId();
		$availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
		$method = $order->getPayment()->getMethod();
		if ($order->getCustomerId() == $customerId && in_array($order->getState(), $availableStates, true) && strpos($method, 'boletobb') !== false) {
			return true;
		}
		return false;
	}
	
	protected function _loadValidOrderAdmin($orderId = null) {
		if ($orderId == null) {
			$orderId = (int) $this->getRequest()->getParam('order_id');
		}
		if (!$orderId) {
			$this->_forward('noRoute');
			return false;
		}
	
		$order = Mage::getModel('sales/order')->load($orderId);
		if ($this->_canViewOrderAdmin($order)) {
			Mage::register('current_order', $order);
			if (!$order->getCustomerId()) true;
			$customer = Mage::getModel('customer/customer')->load( $order->getCustomerId());
			Mage::register('order_customer', $customer);
			return true;
		} else {
			$this->_redirect('/sales_order/view/order_id/'.$orderId);
			return false;
		}
	}
	
	protected function _canViewOrderAdmin($order) {
		$availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
		$method = $order->getPayment()->getMethod();
		if (in_array($order->getState(), $availableStates, true) && strpos($method, 'boletosicob') !== false) {			
			return true;
		}
		return false;
	}
}
