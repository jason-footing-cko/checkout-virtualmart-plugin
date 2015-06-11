<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('Creditcard')) {
    require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php');
}

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class Model extends vmPSPlugin
{

    public $_currentMethod;

    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $varsToPush = $this->getVarsToPush();

        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->_currentMethod = '';
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getCurrentMethod()
    {


        if (is_null($this->_currentMethod)) {
            $this->_currentMethod = $this->methods[0];
        }
        elseif ($this->_currentMethod->virtuemart_paymentmethod_id != $this->methods[0]->virtuemart_paymentmethod_id) {
            $this->_currentMethod = $this->methods[0];
        }

        return $this->_currentMethod;
    }

    public function getInstance($modetype = null)
    {


        $type = ($modetype) ? $modetype : $this->getCurrentMethod()->mode_type;
        switch ($type) {
            case '1':

                $this->_instance = CheckoutApi_Lib_Factory::getInstance('model_methods_creditcardpci');
                break;
            default :

                $this->_instance = CheckoutApi_Lib_Factory::getInstance('model_methods_creditcard');

                break;
        }


        return $this->_instance;
    }

    public function getTableSQLFields()
    {
        $SQLfields = array
            (
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'transaction_id' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL',
            'payment_currency' => 'smallint(1)',
            'return_context' => 'char(255)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'char(10)',
            'tax_id' => 'smallint(1)',
            'rawOutput' => 'text'
        );
        return $SQLfields;
    }

    // Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
    // The plugin must check first if it is the correct type
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    // This method is fired when showing the order details in the frontend.
    // It displays the method-specific data.
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    // This method is fired when showing when priting an Order
    // It displays the the payment method-specific data.
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('CheckoutApi Table');
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {

        if ($this->getPluginMethods($cart->vendorId) === 0) {
            if (empty($this->_name)) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                return false;
            }
            else {
                return false;
            }
        }
        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return FALSE;
        }

        return $this->getInstance()->plgVmDisplayListFEPayment($cart, $selected, $htmlIn, $this);
    }

    /**
     * This is for checking the input data of the payment method within the checkout
     *
     * @author Valerie Cartan Isaksen
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return FALSE;
        }

        $this->getInstance()->sessionSave($cart, $this);
        return $this->validate(true);
        ;
    }

    public function validate($enqueueMessage)
    {
        $this->getInstance()->validate($enqueueMessage);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {

        $currentObj = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return FALSE;
        }
        $this->getInstance()->sessionSave($cart, $this);

        $this->getInstance($currentObj->mode_type)->plgVmOnSelectCheckPayment($cart, $msg, $this);
        return true;
    }

    public function VmPluginMethod($int, $cache = true)
    {
        return $this->getVmPluginMethod($int, $cache);
    }

    protected function checkConditions($cart, $method, $cart_prices)
    {

        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);


        //vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR ( $method->min_amount <= $amount AND ( $method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            }
            else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return TRUE;
        }

        return FALSE;
    }

    public function getPsType()
    {
        return $this->_psType;
    }

    public function getRenderPluginName($currentMethod)
    {
        return $this->renderPluginName($currentMethod);
    }

    public function pluginHtml($currentMethod, $selected, $methodSalesPrice)
    {
        return $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);
    }

    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$payment_name)
    {


        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return false;
        }

        $this->getInstance()->getSessionData();
        $cart_prices['payment_tax_id'] = 0;
        $cart_prices['payment_value'] = 0;

        if (!$this->checkConditions($cart, $this->_currentMethod, $cart_prices)) {
            return false;
        }
        $payment_name = $this->renderPluginName($this->_currentMethod);

        $this->setCartPrices($cart, $cart_prices, $this->_currentMethod);



        return true;
    }

    public function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
    {

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return false;
        }
        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return false; // Another method was selected, do nothing
        }

        $this->setInConfirmOrder($cart);
        $this->getInstance()->plgVmConfirmedOrder($cart, $order, $this);
        $response_fields = $this->getInstance()->process($cart, $order, $this);
        $currency = CurrencyDisplay::getInstance();
        $cart_currency_code = ShopFunctions::getCurrencyByID($order['details']['BT']->order_currency);

        $payment_currency_id = shopFunctions::getCurrencyByID($cart_currency_code);
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $payment_currency_id);
        $info['payment_name'] = parent::renderPluginName($this->getCurrentMethod());

        if ($response_fields['status']) {



            $dbValues['order_number'] = $order['details']['BT']->order_number;
            $dbValues['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
            $dbValues['payment_method_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
            $dbValues['transaction_id'] = $response_fields['transaction_id'];
            $dbValues['payment_name'] = parent::renderPluginName($this->getCurrentMethod());
            $dbValues['cost_per_transaction'] = $this->getCurrentMethod()->cost_per_transaction;
            $dbValues['cost_percent_total'] = $this->getCurrentMethod()->cost_percent_total;
            $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
            $dbValues['payment_currency'] = $payment_currency_id;
            $this->debugLog("before store", "plgVmConfirmedOrder", 'debug');
            $this->storePSPluginInternalData($dbValues);


            $info['invoice_number'] = $order['details']['BT']->order_number;
            $info['amount'] = $totalInPaymentCurrency;
            $info['currency'] = $cart_currency_code;
            $info['transaction_id'] = $response_fields['transaction_id'];

            $new_status = $this->getCurrentMethod()->payment_approved_status;
            $html = $this->getSucessMessage($info);
            $cart->_inConfirm = true;
            $cart->emptyCart();
        }
        else {

            $info['invoice_number'] = $order['details']['BT']->order_number;
            $info['amount'] = $totalInPaymentCurrency;
            $info['currency'] = $cart_currency_code;
            $info['transaction_id'] = $response_fields['transaction_id'];
            $info['error'] = $response_fields['error'];
            $html = $this->getErrorMessage($info);
            $mainframe = JFactory::getApplication();
            $mainframe->enqueueMessage($html);
            $cart->_confirmDone = false;
            $cart->_dataValidated = false;
            $cart->_inConfirm = false;
            $cart->setCartIntoSession(false, true);

            if ($response_fields['message'] == 'decline') {
                $new_status = $this->getCurrentMethod()->payment_declined_status;
            }
            else {
                $new_status = $this->getCurrentMethod()->payment_held_status;
                $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', FALSE), vmText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID'));
            }
        }

        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = $new_status;
        $order['customer_notified'] = 1;
        $order['comments'] = '';

        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        //We delete the old stuff
        //  $cart->emptyCart();
        vRequest::setVar('html', $html);
    }

    public function getSucessMessage($info)
    {
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlRow('VMPAYMENT_CHECKOUTAPIPAYMENT_PAYMENT_NAME', $info['payment_name']);
        $html .= $this->getHtmlRow('VMPAYMENT_CHECKOUTAPIPAYMENT_ORDER_NUMBER', $info['invoice_number']);
        $html .= $this->getHtmlRow('VMPAYMENT_CHECKOUTAPIPAYMENT_AMOUNT', $info['amount']['display']);
        //$html .= $this->getHtmlRow('VMPAYMENT_CHECKOUTAPIPAYMENT_RESPONSE_AUTHORIZATION_CODE', $info['responde_code']);
        $html .= $this->getHtmlRow('VMPAYMENT_CHECKOUTAPIPAYMENT_RESPONSE_TRANSACTION_ID', $info['transaction_id']);
        $html .= '</table>' . "\n";
        $this->debugLog(vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_ORDER_NUMBER') . " " . $info['invoice_number'] . ' payment approved', '_handleResponse', 'debug');

        return $html;
    }

    public function getErrorMessage($info)
    {
        $html = "\n";



        foreach ($info['error'] as $error) {
            $html .= $error;
        }

        $html .= "\n";

        return $html;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {

        return parent::onStoreInstallPluginTable($jplugin_id);
    }

    //ipn url http://localhost:8888/virtuemart/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm={payment_id}
    function plgVmOnPaymentNotification()
    {

        $pm = vRequest::getString('pm');
        
        $this->_currentMethod = $this->getVmPluginMethod($pm);

        $json = file_get_contents('php://input');

        if ($json) {

            $Api = CheckoutApi_Api::getApi(array('mode' => $this->_currentMethod->sandbox));
            $objectCharge = $Api->chargeToObj($json);

            if ($objectCharge->isValid()) {
                /*
                 * Need to get track id
                 */
                $virtuemart_order_id = $objectCharge->getTrackId();
                $modelOrder = VmModel::getModel('orders');
                $order = $modelOrder->getOrder($virtuemart_order_id);
                $test = true;
                if ($objectCharge->getCaptured()) {
                    if ($order['details']['BT']->order_status != 'C') {
                        $order_history['order_status'] = 'C';
                        $order_history['customer_notified'] = 1;
                        $order_history['comments'] = 'Your payment has been successfully completed';

                        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order_history, false);
                    }
                }
                elseif ($objectCharge->getRefunded()) {
                    if ($order['details']['BT']->order_status != 'R') {
                        $order_history['order_status'] = 'R';
                        $order_history['customer_notified'] = 1;
                        $order_history['comments'] = 'Your payment has been refunded';
                        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order_history, false);
                    }
                }
                elseif(!$objectCharge->getAuthorised()) {
                    if ($order['details']['BT']->order_status != 'X') {
                        $order_history['order_status'] = 'X';
                        $order_history['customer_notified'] = 1;
                        $order_history['comments'] = 'Your order has been cancelled';
                        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order_history, false);
                    }
                }
            }
        }
    }

}
