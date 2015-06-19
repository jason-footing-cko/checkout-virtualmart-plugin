<?php

class model_methods_creditcard extends model_methods_Abstract
{

  private $cko_paymentToken;

  public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn, $obj) {

    //var_dump(debug_backtrace());

    $toReturn = true;

    JHTML::script('vmcreditcard.js', 'components/com_virtuemart/assets/js/', FALSE);
    VmConfig::loadJLang('com_virtuemart', true);
    vmJsApi::jCreditCard();

    $currentMethod = $obj->getCurrentMethod();
    $method_name = $obj->getPsType() . '_name';

    $cart_prices = array();
    $cart_prices['withTax'] = '';
    $cart_prices['salesPrice'] = '';

    $methodSalesPrice = $obj->setCartPrices($cart, $cart_prices, $currentMethod);

    if ($obj->_currentMethod->sandbox == 'live') {
      $js_src = 'https://www.checkout.com/cdn/js/checkout.js';
    }
    else {
      $js_src = '//sandbox.checkout.com/js/v1/checkout.js';
    }


    $html = array();
    $currentMethod->$method_name = $obj->getRenderPluginName($currentMethod);

    $html[] = $obj->pluginHtml($currentMethod, $selected, $methodSalesPrice);
    if ($selected == $currentMethod->virtuemart_paymentmethod_id) {
      $this->_getSessionData();
      if ($cart->getDataValidated()){
        vmJsApi::addJScript('vm.checkoutApiFormSubmit','
          jQuery(document).ready(function($) {
            var paymentId = jQuery("#payment_id_3:checked").length;
              jQuery(this).vm2front("stopVmLoading");
              jQuery("#checkoutFormSubmit").bind("click dblclick",function(e){
                jQuery("#checkoutForm").one("submit",function(e){
                  jQuery(this).vm2front("stopVmLoading");
                  if(jQuery("[name^=tos]:checked").length && paymentId) {
                    e.preventDefault();
                      CheckoutIntegration.open();
                  } else {
                  jQuery("#checkoutFormSubmit")
                    .removeClass("vm-button")
                    .addClass("vm-button-correct")
                    .prop("disabled", false);
                  }
                });
              });
          });
        ');
      }
    }
    $paymentId = $currentMethod->virtuemart_paymentmethod_id;

    $Api = CheckoutApi_Api::getApi(array('mode' => $obj->getCurrentMethod()->sandbox));

    $amountCents = ceil((float) ($cart->pricesUnformatted['billTotal']) * 100.00);

    $cart_currency_code = ShopFunctions::getCurrencyByID($cart->pricesCurrency, 'currency_code_3');

    $paymentToken = $this->generatePaymentToken($cart, $obj);
    $config = array();
    $config['widgetRenderedEvent'] = isset($config['widgetRenderedEvent']) ? $config['widgetRenderedEvent'] : '';
    $config['cardChargedEvent'] = isset($config['cardChargedEvent']) ? $config['cardChargedEvent'] : '';
    $config['readyEvent'] = isset($config['readyEvent']) ? $config['readyEvent'] : '';

    $config['debug'] = false;
    $config['renderMode'] = 2;
    $config['publicKey'] = $obj->getCurrentMethod()->public_key;
    $config['email'] = $cart->BT['email'];
    $config['name'] = $cart->BT['first_name'] . ' ' . $cart->BT['last_name'];
    $config['amount'] = $amountCents;
    $config['currency'] = $cart_currency_code;
    $config['paymentToken'] = $paymentToken['token'];

    $config['widgetSelector'] = '.widget-container';
    $config['cardChargedEvent'] = "
                        document.getElementById('checkoutForm').submit();
                      ";
    $config['widgetRenderedEvent'] = "";

    $config['readyEvent'] = '';
    $config['lightboxDeactivated'] = 'jQuery("#checkoutFormSubmit")
                                            .removeClass("vm-button")
                                            .addClass("vm-button-correct")
                                            .prop("disabled", false);
                                         ';

    $jsConfig = $this->getJsConfig($config);

    $html[] = '<br/>';
    $html[] = '<span class="vmpayment_cardinfo">';
    $html[] = vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_COMPLETE_FORM');
    $html[] = '<div class="widget-container"></div>';
    $html[] = '<input type="hidden" name="cko_paymentToken_' . $paymentId . '" id="cko_paymentToken" value="' . $paymentToken['token'] . '">';
    $html[] = '<script type="text/javascript">';
    $html[] = $jsConfig;
    $html[] = '</script>';

    $html[] = '<script async src="' . $js_src . '"></script>';

    $html[] = '</span>';

    $htmlIn[] = array(join("\n", $html));

    return $toReturn;
  }

  public function process(VirtueMartCart $cart, $order, $obj) {
    $this->_getSessionData();

    $config = parent::process($cart, $order, $obj);

    $config['postedParam']['paymentToken'] = $this->cko_paymentToken;
    return $this->_placeorder($config, $obj, $order);
  }

  protected function _getSessionData() {

    $session = JFactory::getSession();
    $checkoutSession = $session->get('checkoutapipayment', 0, 'vm');

    if (!empty($checkoutSession)) {
      $sessiontData = (object) json_decode($checkoutSession, true);
      $this->cko_paymentToken = $sessiontData->cko_paymentToken;
    }
  }

  public function sessionSave(VirtueMartCart $cart, $obj) {

    $this->cko_paymentToken = vRequest::getVar('cko_paymentToken_' . $cart->virtuemart_paymentmethod_id, '');

    $this->_setSession();
    return true;
  }

  private function _setSession() {

    $session = JFactory::getSession();
    $sessionObj = new stdClass();

    // card information
    $sessionObj->cko_paymentToken = $this->cko_paymentToken;
    ;
    $session->set('checkoutapipayment', json_encode($sessionObj), 'vm');
  }

  public function validate($enqueueMessage) {

    $this->_getSessionData();
    return $this->cko_paymentToken ? true : false;
  }

  /**
   * @param $config array of configuration
   * @return string script tag
   */
  public function getJsConfig($config) {
    $script = "window.CKOConfig = {
                debugMode: false,
                renderMode:{$config['renderMode']},
                publicKey: '{$config['publicKey']}',
                customerEmail: '{$config['email']}',
                namespace: 'CheckoutIntegration',
                customerName: '{$config['name']}',
                value: '{$config['amount']}',
                currency: '{$config['currency']}',
                namespace: 'CheckoutIntegration',
                paymentToken: '{$config['paymentToken']}',
                paymentMode: 'mixed',
                widgetContainerSelector: '.widget-container',
                cardCharged: function(event) {
                    {$config['cardChargedEvent']}
                },
                widgetRendered: function(event) {
                    {$config['widgetRenderedEvent']}
                },

                ready: function() {
                    {$config['readyEvent']};

                },
                lightboxDeactivated: function() {
                    {$config['lightboxDeactivated']};
                }
                
            } ";
    return $script;
  }

  public function generatePaymentToken(VirtueMartCart $cart, $obj) {

    $amountCents = ceil((float) ($cart->cartPrices['billTotal']) * 100.00);
    $currency_code = ShopFunctions::getCurrencyByID($cart->pricesCurrency, 'currency_code_3');

    $scretKey = $obj->_currentMethod->secret_key;
    $mode = $obj->_currentMethod->sandbox;
    $timeout = $obj->_currentMethod->gateway_timeout;

    $config['authorization'] = $scretKey;
    $config['mode'] = $mode;
    $config['timeout'] = $timeout;

    if ($obj->_currentMethod->order_type == 'AUTH_CAPTURE') {
      $config = array_merge($this->_captureConfig($obj), $config);
    }
    else {
      $config = array_merge($this->_authorizeConfig($obj), $config);
    }

    $products = array();

    foreach ($cart->products as $key => $product) {

      $products[] = array(
          'name' => $product->product_name,
          'sku' => $product->product_sku,
          'price' => $product->allPrices[$product->selectedPrice]['subtotal_with_tax'],
          'quantity' => $product->amount,
      );
    }

    $shippingAdd = isset($cart->ST) && !empty($cart->ST) ? $cart->ST : $cart->BT;

    $shippingAddressConfig = array(
        'addressLine1' => $shippingAdd['address_1'],
        'addressLine2' => $shippingAdd['address_2'],
        'postcode' => $shippingAdd['zip'],
        'country' => ShopFunctions::getCountryByID($shippingAdd['virtuemart_country_id'], 'country_2_code'),
        'city' => $shippingAdd['city'],
        'phone' => array('number' => $shippingAdd['phone_1']),
    );

    $config['postedParam'] = array_merge_recursive($config['postedParam'], array(
        'email' => $cart->BT['email'],
        'value' => $amountCents,
        'currency' => $currency_code,
        'shippingDetails' => $shippingAddressConfig,
        'products' => $products,
        'card' => array(
            'name' => $cart->BT['first_name'] . ' ' . $cart->BT['last_name'],
            'billingDetails' => array(
                'addressLine1' => $cart->BT['address_1'],
                'addressLine2' => $cart->BT['address_2'],
                'postcode' => $cart->BT['zip'],
                'country' => ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code'),
                'city' => $cart->BT['city'],
            )
        )
            )
    );

    $Api = CheckoutApi_Api::getApi(array('mode' => $mode));

    $paymentTokenCharge = $Api->getPaymentToken($config);

    $paymentTokenArray = array(
        'message' => '',
        'success' => '',
        'eventId' => '',
        'token' => '',
    );

    if ($paymentTokenCharge->isValid()) {
      $paymentTokenArray['token'] = $paymentTokenCharge->getId();
      $paymentTokenArray['success'] = true;
    }
    else {

      $paymentTokenArray['message'] = $paymentTokenCharge->getExceptionState()->getErrorMessage();
      $paymentTokenArray['success'] = false;
      $paymentTokenArray['eventId'] = $paymentTokenCharge->getEventId();
    }

    return $paymentTokenArray;
  }

  protected function _createCharge($config, $obj) {
    $config = array();
    $this->_getSessionData();
    $scretKey = $obj->_currentMethod->secret_key;
    $timeout = $obj->_currentMethod->gateway_timeout;

    $config['authorization'] = $scretKey;
    $config['timeout'] = $timeout;
    $config['paymentToken'] = $this->cko_paymentToken;

    $Api = CheckoutApi_Api::getApi(array('mode' => $obj->_currentMethod->sandbox));
    return $Api->verifyChargePaymentToken($config);
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


}