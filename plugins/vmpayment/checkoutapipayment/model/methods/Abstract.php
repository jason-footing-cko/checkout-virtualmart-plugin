<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('Creditcard')) {
  require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php');
}

if (!class_exists('vmPSPlugin')) {
  require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

abstract class model_methods_Abstract
{

  public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn, $obj) {

    return $obj->displayListFE($cart, $selected, $htmlIn);
  }

  public function process(VirtueMartCart $cart, $order, $obj) {

    $amountCents = ceil((float) ($order['details']['BT']->order_total) * 100.00);
    $config['authorization'] = $obj->_currentMethod->secret_key;
    $config['mode'] = $obj->_currentMethod->mode_type;
    $currency = CurrencyDisplay::getInstance();
    $cart_currency_code = ShopFunctions::getCurrencyByID($cart->pricesCurrency, 'currency_code_3');
    $shippingAdd = isset($order['details']['ST']) ? $order['details']['ST'] : $order['details']['BT'];
    $shippingAddressConfig = array(
        'addressLine1' => $shippingAdd->address_1,
        'addressLine2' => $shippingAdd->address_2,
        'postcode' => $shippingAdd->zip,
        'country' => ShopFunctions::getCountryByID($shippingAdd->virtuemart_country_id, 'country_2_code'),
        'city' => $shippingAdd->city,
        'phone' => array('number' => $shippingAdd->phone_1),
    );
    $products = array();
    if ($items) {

      foreach ($items as $product) {

        $products[] = array(
            'name' => $product->order_item_name,
            'sku' => $product->order_item_sku,
            'price' => $currency->roundForDisplay($product->product_final_price),
            'quantity' => $product->product_quantity,
        );
      }
    }

    $config['postedParam'] = array(
        'email' => $order['details']['BT']->email,
        'value' => $amountCents,
        'trackId' => $order['details']['BT']->virtuemart_order_id,
        'shippingDetails' => $shippingAddressConfig,
        'currency' => $cart_currency_code,
        'products' => $products,
        'card' => array(
            'billingDetails' => array(
                'addressLine1' => $order['details']['BT']->address_1,
                'addressLine2' => $order['details']['BT']->address_2,
                'postcode' => $order['details']['BT']->zip,
                'country' => ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code'),
                'city' => $order['details']['BT']->city,
                'phone' => array('number' => $order['details']['BT']->phone_1),
            )
        )
    );

    if ($obj->_currentMethod->order_type == 'AUTH_CAPTURE') {
      $config = array_merge($this->_captureConfig($obj), $config);
    }
    else {
      $config = array_merge($this->_authorizeConfig($obj), $config);
    }
    return $config;
  }

  protected function _placeorder($config, $obj, $order) {


    $respondCharge = $this->_createCharge($config, $obj);

    $response_fields = array();

    if ($respondCharge->isValid()) {
      if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {

        // update charge metadata
        $Api = CheckoutApi_Api::getApi(array('mode' => $obj->_currentMethod->mode_type));
        $chargeUpdated = $Api->updateTrackId($respondCharge, $order['details']['BT']->virtuemart_order_id);

        $response_fields['virtuemart_order_id'] = $config['postedParam']['trackId'];
        $response_fields['transaction_id'] = $respondCharge->getId();
        $response_fields['gateway_id'] = '';
        $response_fields['rawOutput'] = json_encode(serialize($respondCharge));
        $response_fields['status'] = true;
        $response_fields['message'] = 'Sucessful';
      }
      else {
        $respondCharge->getRespondCode();
        $response_fields['virtuemart_order_id'] = $config['postedParam']['trackId'];
        $response_fields['transaction_id'] = $respondCharge->getId();
        $response_fields['gateway_id'] = '';
        $response_fields['rawOutput'] = json_encode(serialize($respondCharge));
        $response_fields['status'] = false;
        $response_fields['message'] = 'decline';
        $response_fields['error']['message'] = $respondCharge->getExceptionState()->getErrorMessage();
      }
    }
    else {



      $respondCharge->getRespondCode();
      $response_fields['virtuemart_order_id'] = $config['postedParam']['trackId'];
      $response_fields['rawOutput'] = json_encode(serialize($respondCharge));
      $response_fields['status'] = false;
      $response_fields['message'] = 'fail';
      $response_fields['error']['message'] = $respondCharge->getExceptionState()->getErrorMessage();
    }

    return $response_fields;
  }

  protected function _createCharge($config, $obj) {

    $currentMethod = $obj->getCurrentMethod()->sandbox;
    $Api = CheckoutApi_Api::getApi(array('mode' => $obj->_currentMethod->sandbox));

    return $Api->createCharge($config);
  }

  protected function _captureConfig($obj) {
    $to_return['postedParam'] = array(
        'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE,
        'autoCapTime' => $obj->_currentMethod->autocaptime
    );

    return $to_return;
  }

  protected function _authorizeConfig($obj) {
    $to_return['postedParam'] = array(
        'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH,
        'autoCapTime' => 0
    );

    return $to_return;
  }

  protected function _getSessionData() {
    $toReturn = null;

    if (!class_exists('vmCrypt')) {
      require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
    }

    $session = JFactory::getSession();
    $_session = $session->get('checkoutapipayment', 0, 'vm');

    if (!empty($_session)) {
      $toReturn = (object) json_decode($_session, true);
    }

    return $toReturn;
  }

  protected function _clearSession() {
    $toReturn = null;
    if (!class_exists('vmCrypt')) {
      require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
    }

    $session = JFactory::getSession();
    $session->clear('checkoutapipayment', 'vm');
  }

  public function sessionSave(VirtueMartCart $cart, $obj) {
    
  }

  public function getSessionData() {
    return $this->_getSessionData();
  }

  public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg, $obj) {
    $this->sessionSave($cart, $obj);
    if (!$obj->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
      return false; // Another method was selected, do nothing
    }
    return true;
  }

  public function plgVmConfirmedOrder(VirtueMartCart $cart, $order, $obj) {

    $usrBT = $order['details']['BT'];
    $usrST = ((isset($order['details']['ST'])) ? $order['details']['ST'] : '');
    $session = JFactory::getSession();
    $return_context = $session->getId();
  }

  public function validate($toValidate) {
    return true;
  }

}
