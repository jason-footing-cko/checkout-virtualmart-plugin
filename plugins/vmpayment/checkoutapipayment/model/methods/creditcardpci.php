<?php
class model_methods_creditcardpci extends model_methods_Abstract
{

    private $_cc_type;
    private $_cc_number;
    private $_cc_cvv;
    private $_cc_expire_year;
    private $_cc_expire_month;

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0,&$htmlIn,$obj)
    {

       $toReturn = true;

        JHTML::script('vmcreditcard.js', 'components/com_virtuemart/assets/js/', FALSE);
        VmConfig::loadJLang('com_virtuemart', true);
        vmJsApi::jCreditCard();

        $currentMethod =  $obj->getCurrentMethod();
        $method_name = $obj->getPsType() . '_name';
        
        $cart_prices = array();
        $cart_prices['withTax'] = '';
        $cart_prices['salesPrice'] = '';
        
        $methodSalesPrice = $obj->setCartPrices($cart, $cart_prices, $currentMethod);

        $html = array();
        $currentMethod->$method_name = $obj->getRenderPluginName($currentMethod);
        $html[] = $obj->pluginHtml($currentMethod, $selected, $methodSalesPrice);

        if ($selected == $currentMethod->virtuemart_paymentmethod_id) {
              $this->_getSessionData();
        }

        if (empty($currentMethod->creditcards)) {
            $currentMethod->creditcards = self::getCreditCards();
        } elseif (!is_array($currentMethod->creditcards)) {
            $currentMethod->creditcards = (array)$currentMethod->creditcards;
        }
        $creditCards = $currentMethod->creditcards;
        $creditCardList = '';
        if ($creditCards) {
            $creditCardList = ($this->_renderCreditCardList($creditCards, $this->_cc_type, $currentMethod->virtuemart_paymentmethod_id, false));
        }
        $cvv_images = $this->_displayCVVImages($currentMethod,$obj);

        $paymentId = $currentMethod->virtuemart_paymentmethod_id;

        $html[] = '<br/>';
        $html[] = '<span class="vmpayment_cardinfo">';
        $html[] =  vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_COMPLETE_FORM');


            $html[] = '<table border="0" cellspacing="0" cellpadding="2" width="100%">';
                $html[] = '<tr valign="top">';

                    $html[] = '<td nowrap width="10%" align="right">';

                            $html[] = '<label for="creditcardtype">';
                            $html[] =  vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_CCTYPE');
                            $html[] = '</label>';
                    $html[] = '</td>';

                    $html[] = '<td>';
                    $html[] = $creditCardList;
                    $html[] = '</td>';

                $html[] = '</tr>';

                $html[] = '<tr valign="top">';

                    $html[] = '<td nowrap width="10%" align="right">';

                    $html[] = '<label for="cc_type">';
                    $html[] =  vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_CCNUM');
                    $html[] = '</label>';

                    $html[] = '</td>';

                    $html[] = '<td>';
                    $html[] = <<<EOD
                                <script type='text/javascript'>
                                                //<![CDATA[
                                                  function checkAPICheckoutCom(id, el)
                                                   {
                                                       ccError=razCCerror(id);

                                                       CheckCreditCardNumber(el.value, id);
                                                       if (!ccError) {
                                                           el.value='';}
                                                   }
                                                //]]></script>
EOD;
                    $html[] = '<input type="text" class="inputbox" id="cc_number_' . $paymentId .
                        '" name="cc_number_' . $paymentId . '" value="' . $this->_cc_number .
                        '"    autocomplete="off"   onchange="javascript:checkAPICheckoutCom(' . $paymentId . ', this);"  />';

                    $html[] = ' <div id="cc_cardnumber_errormsg_' . $paymentId . '"></div>';


                    $html[] = '</td>';

                $html[] = '</tr>';

                $html[] = '<tr valign="top">';

                        $html[] = '<td nowrap width="10%" align="right">';

                        $html[] = '<label for="cc_cvv">';
                        $html[] =  vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_CVV2');
                        $html[] = '</label>';

                        $html[] = '</td>';

                        $html[] = '<td>';
                        $html[] = '<input type="text" class="inputbox" id="cc_cvv_' . $paymentId . '" name="cc_cvv_'
                            . $paymentId. '" maxlength="4" size="5" value="' . $this->_cc_cvv . '" autocomplete="off" />';
                        $html[] = '<span class="hasTip" title="' . vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_WHATISCVV') . '::'
                            . vmText::sprintf("VMPAYMENT_CHECKOUTAPIPAYMENT_WHATISCVV_TOOLTIP", $cvv_images) . ' ">' .
                            vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_WHATISCVV') . '
			</span>';
                        $html[] = '</td>';

                $html[] = '</tr>';

                $html[] = '<tr valign="top">';

                    $html[] = '<td nowrap width="10%" align="right">';

                        $html[] = '<label for="creditcardtype">';
                        $html[] =  vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_EXDATE');
                        $html[] = '</label>';
                        $html[] = '</td>';

                    $html[] = '<td>';
                    $html[] = shopfunctions::listMonths('cc_expire_month_' . $paymentId, $this->_cc_expire_month);
                    $html[] = '/';
                    $html[] = <<<EOD
                    <script type="text/javascript">
                        //<![CDATA[
                          function changeDateCj(id, el)
                           {
                             var month = document.getElementById('cc_expire_month_'+id); if(!CreditCardisExpiryDate(month.value,el.value, id))
                             {el.value='';
                             month.value='';}
                           }
                        //]]>
                    </script>
EOD;

                    $html[] = shopfunctions::listYears('cc_expire_year_' . $paymentId, $this->_cc_expire_year, NULL, (date('Y')+20),
                        ' onchange="javascript:changeDateCj('. $paymentId . ', this);');


                    $html[] = '<div id="cc_expiredate_errormsg_' . $paymentId . '"></div>';

                    $html[] = '</td>';

                $html[] = '</tr>';


            $html[] = '</table>';
            $html[] = '</span>';

        $htmlIn[] = array(join("\n",$html));


        return $toReturn;
    }

    protected function _getSessionData()
    {
        if (!class_exists('vmCrypt')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
         }
        $session = JFactory::getSession();
        $checkoutSession = $session->get('checkoutapipayment', 0, 'vm');

        if (!empty($checkoutSession)) {
            $sessiontData = (object)json_decode($checkoutSession,true);
            $this->_cc_type = $sessiontData->cc_type;
            $this->_cc_number =  vmCrypt::decrypt($sessiontData->cc_number);
            $this->_cc_cvv =  vmCrypt::decrypt($sessiontData->cc_cvv);
            $this->_cc_expire_month = $sessiontData->cc_expire_month;
            $this->_cc_expire_year = $sessiontData->cc_expire_year;
            $this->_cc_valid = $sessiontData->cc_valid;
        }

    }

    public function process(VirtueMartCart $cart, $order,$obj)
    {
        $this->_getSessionData();
        $config = parent::process($cart, $order,$obj);
        $config['postedParam']['card']['phoneNumber'] =  $order['details']['BT']->phone_1;
        $config['postedParam']['card']['name'] = $order['details']['BT']->first_name.' '. $order['details']['BT']->last_name;
        $config['postedParam']['card']['number'] =  $this->_cc_number;
        $config['postedParam']['card']['expiryMonth'] = (int)$this->_cc_expire_month;
        $config['postedParam']['card']['expiryYear'] = (int)$this->_cc_expire_year;
        $config['postedParam']['card']['cvv'] = $this->_cc_cvv;

        return $this->_placeorder($config,$obj,$order);
    }

    public function sessionSave(VirtueMartCart $cart,$obj)
    {

        $cart->creditcard_id = vRequest::getVar('creditcard', '0');
        $this->_cc_type = vRequest::getVar('cc_type_' . $cart->virtuemart_paymentmethod_id, '');
        $this->_cc_name = vRequest::getVar('cc_name_' . $cart->virtuemart_paymentmethod_id, '');
        $this->_cc_number = str_replace(" ", "", vRequest::getVar('cc_number_' . $cart->virtuemart_paymentmethod_id, ''));
        $this->_cc_cvv = vRequest::getVar('cc_cvv_' . $cart->virtuemart_paymentmethod_id, '');
        $this->_cc_expire_month = vRequest::getVar('cc_expire_month_' . $cart->virtuemart_paymentmethod_id, '');
        $this->_cc_expire_year = vRequest::getVar('cc_expire_year_' . $cart->virtuemart_paymentmethod_id, '');
        $this->_setSession();

        return true;
    }


    private  function _setSession()
    {
        if (!class_exists('vmCrypt')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
        }
        $session = JFactory::getSession();
        $sessionObj = new stdClass();
        // card information
        $sessionObj->cc_type = $this->_cc_type;
        $sessionObj->cc_number = vmCrypt::encrypt($this->_cc_number);
        $sessionObj->cc_cvv = vmCrypt::encrypt($this->_cc_cvv);
        $sessionObj->cc_expire_month = $this->_cc_expire_month;
        $sessionObj->cc_expire_year = $this->_cc_expire_year;
        $sessionObj->cc_valid = $this->_cc_valid;
        $session->set('checkoutapipayment', json_encode($sessionObj), 'vm');
    }
    /**
     * drawing form utilities
     */

    static function getCreditCards()
    {
        return array(
            'Visa',
            'Mastercard',
            'AmericanExpress',
            'Discover',
            'DinersClub',
            'JCB',
        );

    }

    /**
     * Creates a Drop Down list of available Creditcards
     *
     * @author Valerie Isaksen
     */
    function _renderCreditCardList($creditCards, $selected_cc_type, $paymentmethod_id, $multiple = FALSE, $attrs = '')
    {

        $idA = $id = 'cc_type_' . $paymentmethod_id;
        //$options[] = JHTML::_('select.option', '', vmText::_('VMPAYMENT_AUTHORIZENET_SELECT_CC_TYPE'), 'creditcard_type', $name);
        if (!is_array($creditCards)) {
            $creditCards = (array)$creditCards;
        }
        foreach ($creditCards as $creditCard) {
            $options[] = JHTML::_('select.option', $creditCard, vmText::_('VMPAYMENT_CHECKOUTAPIPAYMENT_' . strtoupper($creditCard)));
        }
        if ($multiple) {
            $attrs = 'multiple="multiple"';
            $idA .= '[]';
        }
        return JHTML::_('select.genericlist', $options, $idA, $attrs, 'value', 'text', $selected_cc_type);
    }

    public function _displayCVVImages($method,$obj) {

        $cvv_images = $method->cvv_images;
        $img = '';

        if ($cvv_images) {
            $img = $obj->displayLogos($cvv_images);
            $img = str_replace('"', "'", $img);
        }
        return $img;
    }



    private function _validate_creditcard_data($enqueueMessage = TRUE) {

        $html = '';
        $this->_cc_valid = TRUE;

        if (!Creditcard::validate_credit_card_number($this->_cc_type, $this->_cc_number)) {
            $this->_errormessage[] = 'VMPAYMENT_CHECKOUTAPIPAYMENT_CARD_NUMBER_INVALID';
            $this->_cc_valid = FALSE;
        }

        if (!Creditcard::validate_credit_card_cvv($this->_cc_type, $this->_cc_cvv)) {
            $this->_errormessage[] = 'VMPAYMENT_CHECKOUTAPIPAYMENT_CARD_CVV_INVALID';
            $this->_cc_valid = FALSE;
        }
        if (!Creditcard::validate_credit_card_date($this->_cc_type, $this->_cc_expire_month, $this->_cc_expire_year)) {
            $this->_errormessage[] = 'VMPAYMENT_CHECKOUTAPIPAYMENT_CARD_EXPIRATION_DATE_INVALID';
            $this->_cc_valid = FALSE;
        }

        if (!$this->_cc_valid) {
            //$html.= "<ul>";
            foreach ($this->_errormessage as $msg) {
                //$html .= "<li>" . vmText::_($msg) . "</li>";
                $html .= vmText::_($msg) . "<br/>";
            }
            //$html.= "</ul>";
        }

        if (!$this->_cc_valid && $enqueueMessage) {
            $app = JFactory::getApplication();
            $app->enqueueMessage($html);
        }


        return $this->_cc_valid;
    }

    public  function validate($enqueueMessage)
    {
        $this->_getSessionData();
        return $this->_validate_creditcard_data($enqueueMessage);
    }
}