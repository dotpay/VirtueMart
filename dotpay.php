<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.0.1: dotpay.php 2015-08-24
 * @author Dotpay SA  < tech@dotpay.pl >
 * @copyright (C) 2015 - Dotpay SA
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

defined('_JEXEC') or die('Restricted access');


if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentDotpay extends vmPSPlugin {

    public static $_this = false;

    public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; //VM3_dotpay_id';
        $this->_tableId = 'id';   //VM3_dotpay_id';
		$varsToPush = $this->getVarsToPush();
        $dotpay_element_vars = array("", "char");
        $varsToPush["payment_logos"] = $dotpay_element_vars;
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Dotpay Table');
    }
    
    
    public function getTableSQLFields()
	{
		return array(
			'id' => ' int(11) UNSIGNED NOT NULL AUTO_INCREMENT ',
			'virtuemart_order_id' => ' int(11) UNSIGNED DEFAULT NULL',
			'order_number' => ' char(32) DEFAULT NULL',
			'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
			'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
			'tax_id' => 'int(11) DEFAULT NULL',
			'dotpay_control' => 'varchar(32) ',
            'kwota_zamowienia' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'waluta_zamowienia' => 'varchar(32) ',
            'kwota_platnosci' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'waluta_platnosci' => 'varchar(32) '
		);

    }
    
        // order confirm
	
	public function plgVmConfirmDotpay($cart, $order, $auto_redirect = false, $form_method = "GET")
	{
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Inna metoda została wybrana, nie rób nic.
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		if (!class_exists('VirtueMartModelOrders'))
		{
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		}

		// konwersja z waluty zamówienia, do waluty płatności

        $this->getPaymentCurrency($method);
        $kwota_zamowienia = number_format($order['details']['BT']->order_total,2,".","");

//                // pobranie 3 znakowego kodu waluty

                $q = 'SELECT currency_code_3 FROM #__virtuemart_currencies WHERE virtuemart_currency_id="' .$method->payment_currency. '" ';
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $waluta_zamowienia = $db->loadResult(); //$waluta_zamowienia = $method->payment_currency;  tutaj wywala z id z bazy

                $CurrencyObj = CurrencyDisplay::getInstance($method->payment_currency);

        if((is_array($method->dotpay_waluty) && count($method->dotpay_waluty)>0) )
        {
            if(in_array($waluta_zamowienia, $method->dotpay_waluty))
            {
                 $kwota_platnosci = $kwota_zamowienia;
                 $waluta_platnosci =  $waluta_zamowienia;
            }
            else
            {
                $q = 'SELECT virtuemart_currency_id FROM #__virtuemart_currencies WHERE currency_code_3="' .$method->dotpay_waluty[0]. '" ';
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $currency_id = $db->loadResult();
                $kwota_platnosci = number_format($CurrencyObj->convertCurrencyTo($currency_id, $order['details']['BT']->order_total, false),2,".","");
                $waluta_platnosci = $method->dotpay_waluty[0];
            }
        }
        else if(is_string($method->dotpay_waluty) && !empty($method->dotpay_waluty))
        {

            if($waluta_zamowienia==$method->dotpay_waluty)
            {
                $kwota_platnosci = $kwota_zamowienia;
                $waluta_platnosci =  $waluta_zamowienia;
            }
            else
            {
                $q = 'SELECT virtuemart_currency_id FROM #__virtuemart_currencies WHERE currency_code_3="' .$method->dotpay_waluty. '" ';
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $currency_id = $db->loadResult();
                $kwota_platnosci = number_format($CurrencyObj->convertCurrencyTo($currency_id, $order['details']['BT']->order_total, false),2,".","");
                $waluta_platnosci = $method->dotpay_waluty[0];
                
                
            }
        }
        else
        {
            $kwota_platnosci = number_format($CurrencyObj->convertCurrencyTo(114, $order['details']['BT']->order_total, false),2,".",""); // konwertuj do PLN, 114 - id złotówki
            $waluta_platnosci = "PLN";
        }


//		// zmienne
                $zamowienie = $order['details']['BT'];
                $session_id = md5($zamowienie->order_number.'|'.time());
                $q = 'SELECT country_3_code FROM #__virtuemart_countries WHERE virtuemart_country_id='.$zamowienie->virtuemart_country_id.' ';        // kraj
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $country = $db->loadResult();
                $url = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm='.$order['details']['BT']->virtuemart_paymentmethod_id;
                $urlc = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm='.$order['details']['BT']->virtuemart_paymentmethod_id;

                $this->_virtuemart_paymentmethod_id = $zamowienie->virtuemart_paymentmethod_id;
		$dbWartosci['order_number'] = $zamowienie->order_number;
		$dbWartosci['payment_name'] = $this->renderPluginName($method, $order);
		$dbWartosci['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
		$dbWartosci['tax_id'] = $method->tax_id;

		$dbWartosci['dotpay_control'] = $session_id;
                $dbWartosci['kwota_zamowienia'] = $kwota_zamowienia ;
                $dbWartosci['waluta_zamowienia'] = $waluta_zamowienia;
                $dbWartosci['kwota_platnosci'] = $kwota_platnosci;
                $dbWartosci['waluta_platnosci'] = $waluta_platnosci;

                $this->storePSPluginInternalData($dbWartosci);
              
                $lang = JFactory::getLanguage();
                
                if ($method->fake_real === '1') {
                    $dotpay_address = 'https://ssl.dotpay.pl/test_payment/';
                }
                else {
                        $dotpay_address = 'https://ssl.dotpay.pl/';
                     }
                     
                                                     
                
		$html = '
		<div style="text-align: center; width: 100%; ">
		<form action="'.$dotpay_address .'" method="'.$form_method.'" class="form" name="platnosc_dotpay" id="platnosc_dotpay">
			<input type="hidden" name="id" value="'.$method->dotpay_id.'" />
			<input type="hidden" name="amount" value="'.$kwota_platnosci.'" />
			<input type="hidden" name="currency" value="'.$waluta_platnosci.'" />
            		<input type="hidden" name="control" value="'.$session_id.'" />
			<input type="hidden" name="description" value="Zamówienie nr '.$order['details']['BT']->order_number.'" />
			<input type="hidden" name="lang" value="'.(substr($lang->getTag(),0,2)).'" />
                        <input type="hidden" name="type" value="0" />
                        <input type="hidden" name="buttontext" value="" />
                        <input type="hidden" name="url" value="'.$url.'" />
                        <input type="hidden" name="urlc" value="'.$urlc.'" />
                        <input type="hidden" name="firstname" value="'.$zamowienie->first_name.'" />
                        <input type="hidden" name="lastname" value="'.$zamowienie->last_name.'" />
                        <input type="hidden" name="email" value="'.$zamowienie->email.'" />
                        <input type="hidden" name="city" value="'.$zamowienie->city.'" />
                        <input type="hidden" name="postcode" value="'.$zamowienie->zip.'" />
                        <input type="hidden" name="phone" value="'.$zamowienie->phone_1.'" />
                        <input type="hidden" name="country" value="'.$country.'" />
                        <input type="hidden" name="api_version" value="dev" />';

		if(file_exists(JPATH_BASE.DS.'media/images'.DS.'stories'.DS.'virtuemart'.DS.'payment'.DS.'dp_logo_alpha_175_50.png'))
		{
			$pic = getimagesize(JPATH_BASE.DS.'media/images/stories/virtuemart/payment/'.'dp_logo_alpha_175_50.png');
			$html .= '
		  <br /><b>Opłać zamównienie poprzez Dotpay:<b> <br /><input name="submit_send" value="" type="submit" style="border: 0; background: url(\''.JURI::root().'media/images/stories/virtuemart/payment/'.'dp_logo_alpha_175_50.png'.'\'); width: '.$pic[0].'px; height: '.$pic[1].'px; cursor: pointer;" /> <br /><br /><br />';
		}
		else
		{
			$html .= '<input name="submit_send" value="Zapłać poprzez Dotpay" type="submit"  style="width: 110px; height: 45px;" /> ';
		}

		$html .= '	</form>
		
		</div>
		';
                
                // <!--p style="text-align: center; width: 100%; ">'.'NEXT'.'</p-->

		// automatyczne przerzucenie do płatności
		if($method->autoredirect && $auto_redirect)
		{
			$html .= '
			<script type="text/javascript">
                        jQuery.noConflict();
				jQuery(document).ready(function() {
                                    jQuery("#platnosc_dotpay").submit();
                                });
			</script>';
		}

		return $html;
	}


    function plgVmConfirmedOrder($cart, $order)
	{
		// no $html - false
		if (!($html = $this->plgVmConfirmDotpay($cart, $order, true, "POST"))) {
			return false;
		}

		
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
		{
			return null;
		}
		$nazwa_platnosci = $this->renderPluginName($method);
		
		return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $nazwa_platnosci, $method->status_pending);
	}

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		 $this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
   }


	function plgVmOnPaymentResponseReceived(&$html)
        {

                   $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null;
		}

		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
	}
//
//
//        // info po powrocie
        if(isset($_POST['status']) && $_POST['status']=="OK")
        {
            // pozytywna
            JFactory::getApplication()->enqueueMessage( '<br><b>Płatność przebiegła pomyślnie !</b><br>Dziękujemy za dokonanie transakcji za pośrednictwem Dotpay.','message' );
            return true;
        }
        elseif(isset($_POST['status']) && $_POST['status']=="FAIL")
        {
            // negatywna
            JFactory::getApplication()->enqueueMessage( '<br><b>Płatność nie doszła do skutku !</b><br>Transakcja za pośrednictwem Dotpay nie została przeprowadzona poprawnie.<br>Jeżeli doszło do obciążenia Twojego rachunku bankowego, prosimy o zgłoszenie tego faktu do właściciela sklepu z podaniem numeru zamównienia oraz transakcji.','error' );
			return true;
        }

        }
        
    function plgVmOnPaymentNotification() {
        
        
        
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null;
        }

	if (!$this->selectedThisElement($method->payment_element)) {
			return false;
	}
        
        if(isset($_POST['signature']) && $_SERVER['REMOTE_ADDR']=="195.150.9.37"){
         
        $method = $this->getVmPluginMethod($virtuemart_paymentmethod_id);
        
        $pin = $method-> dotpay_pin;
        
        $sig = $pin;
        
        $check_keys = array(
            'id',
            'operation_number',
            'operation_type',
            'operation_status',
            'operation_amount',
            'operation_currency',
            'operation_withdrawal_amount',
            'operation_commission_amount',
            'operation_original_amount',
            'operation_original_currency',
            'operation_datetime',
            'operation_related_number',
            'control',
            'description',
            'email',
            'p_info',
            'p_email',
            'channel',
            'channel_country',
            'geoip_country'
        );
        
        foreach ($check_keys as $value) {
            
             if(array_key_exists($value, $_POST) === true) {
                 $sig .= $_POST[$value];
             }
            
        }
        
        if($_POST['signature'] == hash('sha256',$sig)) {
         
         
          $db = JFactory::getDBO();
          $q = 'SELECT dotpay.*, ord.order_status, usr.email  FROM '.$this->_tablename.' as dotpay JOIN `#__virtuemart_orders` as ord using(virtuemart_order_id) JOIN #__virtuemart_order_userinfos  as usr using(virtuemart_order_id)  WHERE dotpay.dotpay_control="' .$_POST['control']. '" ';
          $db->setQuery($q);
          $payment_db = $db->loadObject();

          
          
          switch($_POST['operation_status'])
                    {
                        
                        case 'completed':
//                            // status completed
                            if($payment_db->order_status!="C" && $payment_db->order_status!='X')
                            {
                              $virtuemart_order_id = $payment_db->virtuemart_order_id;
                                $message = 'PLG_DOTPAY_STATUS_OK';
                                 $this->NewStatus($virtuemart_order_id,$method->status_success, $message, $method->feedback);
                            }
                            break;
                        case 'rejected':
                            // status canceled
                            if($payment_db->order_status!="C" && $payment_db->order_status!='X')
                            {
                             $virtuemart_order_id = $payment_db->virtuemart_order_id;
                               $message = 'PLG_DOTPAY_STATUS_FAIL';
                                $status = $this->NewStatus($virtuemart_order_id,$method->status_canceled, $message, $method->feedback);
                                
                            }
                            break;
                    }
          
         echo "OK";
         exit();
        }
        else {
            error_log('BAD SIGNATURE');
            echo('FAIL: SIGNATURE ERROR - CHECK PIN');            
            exit();
        }
         
        
        }
        else {
            error_log('BAD IP');
			echo('FAIL: INCORRECT IP - '.($_SERVER['REMOTE_ADDR']));
            exit();
        }
    }    

    function plgVmOnUserPaymentCancel() {
        if (!class_exists('VirtueMartModelOrders')) {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = JRequest::getVar('on');
        if (!$order_number) {
            return false;
        }
        $db = JFactory::getDBO();

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        $prefix = $method->prefix;
         if (preg_match('/' . $prefix . '/', $order_number)) {
           $order_number = str_replace($prefix, '', $order_number);
        }
        $order_number = intval($order_number);


        $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->
            _tablename . " WHERE  `order_number`= '" . $order_number . "'";

        $db->setQuery($query);
        $virtuemart_order_id = $db->loadResult();

        if (!$virtuemart_order_id) {
           return null;
        }
        $this->handlePaymentUserCancel($virtuemart_order_id);

        //JRequest::setVar('paymentResponse', $returnValue);
        return true;
    }


    function _getTablepkeyValue($virtuemart_order_id) {
		$db = JFactory::getDBO();
		$q = 'SELECT ' . $this->_tablepkey . ' FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery($q);

		if (!($pkey = $db->loadResult())) {
			JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $pkey;
    }
//
//    /**
//     * Display stored payment data for an order
//     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
//     */
//
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
	{
		if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
			return null; // Another method was selected, do nothing
		}

		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery($q);
		if (!($paymentTable = $db->loadObject())) {
			vmWarn(500, $q . " " . $db->getErrorMsg());
			return '';
		}
		$this->getPaymentCurrency($paymentTable);

           	$html = '<table class="adminlist">' . "\n";
		$html .=$this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', number_format($paymentTable->kwota_platnosci,2,".","").' '.$paymentTable->waluta_platnosci);
		$html .= '</table>' . "\n";
		return $html;
    }

//
    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total *
            0.01));
    }

//    /**
//     * Check if the payment conditions are fulfilled for this payment method
//     * @author: Valerie Isaksen
//     *
//     * @param $cart_prices: cart prices
//     * @param $payment
//     * @return true: if the conditions are fulfilled, false otherwise
//     *
//
//    protected function checkConditions($cart, $method, $cart_prices)
//	{
//		return true;
//	}
//    */
//
    protected function checkConditions($cart, $method, $cart_prices) {
               
        $method->payment_logos = 'dp_logo_alpha_175_50.png';
        
        if((strlen($method -> dotpay_id) < 6) || (strlen($method -> dotpay_id) > 6) ) {
          //  echo('PLG_DOTPAY_INVALID_ID');
			 JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay ID</>','error' );
            return false;
        };
        
        if((strlen($method -> dotpay_pin) < 16) || (strlen($method -> dotpay_pin) > 32) ) {
           // echo('PLG_DOTPAY_INVALID_PIN');
			JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay PIN</b>','error' );
            return false;
        }

		return true; 
                
                //TODO - check this result in futere.
    }
    

    function getOrderMethodNamebyOrderId ($virtuemart_order_id) {

	$db = JFactory::getDBO ();
	$q = 'SELECT * FROM `' . $this->_tablename . '` '
		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id.  ' ORDER BY id DESC LIMIT 1 ';
	$db->setQuery ($q);
	if (!($pluginInfo = $db->loadObject ())) {
		vmWarn ('Attention, ' . $this->_tablename . ' has not any entry for the order ' . $db->getErrorMsg ());
		return NULL;
	}

        $idName = $this->_psType . '_name';

		return $pluginInfo->$idName;
	}

    function NewStatus($virtuemart_order_id, $new_stat, $note = "",  $send_info=1)
	{
			if (!class_exists('VirtueMartModelOrders'))
			{
				require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			}


			$lang = JFactory::getLanguage();
			$lang->load('com_virtuemart',JPATH_ADMINISTRATOR);

        		$modelOrder = VmModel::getModel('orders');
			
		///	if(empty($modelOrder->getOrder($virtuemart_order_id)))
		//	{
	//			return false;
	//		}

			$order['order_status'] = $new_stat;
			$order['virtuemart_order_id'] = $virtuemart_order_id;
			$order['customer_notified'] = $send_info;
			$order['comments'] = $note;
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

			$db = JFactory::getDBO();


			if($new_stat=="C" || $new_stat=="X")
			{
				$q = 'UPDATE '.$this->_tablename.' SET modified_on=NOW(), locked_on=NOW() WHERE virtuemart_order_id='.$virtuemart_order_id.';   ';
			}
			else
			{
				$q = 'UPDATE '.$this->_tablename.' SET modified_on=NOW() WHERE virtuemart_order_id='.$virtuemart_order_id.';   ';
			}

			$db->setQuery($q);
			
		//	if(empty($db->query($q)))
		//	{
		//		return false;
		//	}

			$message = 'PLG_DOTPAY_STATUS_CHANGE';

			return $message;
	}
//
    function onShowOrderFE($virtuemart_order_id, $virtuemart_method_id, &$method_info)
	 {
	 	if (!($this->selectedThisByMethodId($virtuemart_method_id))) {
			return null;
		}

		// ograniczenie generowania się dodatkowego formularza, jeśli klient nie opłacił jeszcze zamówienia, tylko do szczegółów produktu

		if(isset($_REQUEST['view']) && $_REQUEST['view']=='orders' && isset($_REQUEST['layout']) && $_REQUEST['layout']=='details')
		{
			// wywołaj cały formularz
			if (!class_exists('VirtueMartModelOrders'))
		{
				require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			}
		if (!class_exists('VirtueMartCart'))
			{
				require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
			}
			if (!class_exists('CurrencyDisplay'))
			{
				require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
			}
        		$modelOrder = new VirtueMartModelOrders();
			$cart = VirtueMartCart::getCart();
			$order = $modelOrder->getOrder($virtuemart_order_id);


 		if (!($html = $this->plgVmConfirmDotpay($cart, $order, false ,"POST")) || $order['details']['BT']->order_status=='C' || $order['details']['BT']->order_status=='U' )
			{
				$method_info = $this->getOrderMethodNamebyOrderId($virtuemart_order_id);
			}
			else
 		{
				$method_info = $html;
			}
		}
		else
		{
			$method_info = 'Dotpay';
        	}
	 }



//TODO Check why after implemented system not storing data form xml install file.
//         
      function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return $this->onStoreInstallPluginTable($jplugin_id);
        }         

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
                return $this->setOnTablePluginParams($name, $id, $table);
	}

	function plgVmDeclarePluginParamsPaymentVM3(&$data) {
           $data->payment_params .= 'payment_logos="dp_logo_alpha_175_50.png"|payment_image="dp_logo_alpha_175_50.png"';
            return $this->declarePluginParams('payment', $data);
	}
        
        public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn){
         return $this->displayListFE($cart, $selected, $htmlIn);
        }
        
        public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
        }
        
        function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush){
                return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
        }


}

// No closing tag
