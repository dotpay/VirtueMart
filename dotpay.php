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

    const PLG_MESSAGE_STATUS_OK = 'PLG_DOTPAY_STATUS_OK';

    const PLG_MESSAGE_STATUS_FAIL = 'PLG_DOTPAY_STATUS_FAIL';

    const DOTPAY_IP = '195.150.9.37';

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

	public function plgVmConfirmDotpay($cart, $order, $auto_redirect = false, $form_method = "GET")
	{
        $orderDetails = $order['details']['BT'];
        $paymentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
        $currency = $this->getCurrency($paymentMethod);

        if(!$this->isPluginValidated($paymentMethod)){
            return false;
        }

		if (!class_exists('VirtueMartModelOrders')){
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		}

        if(!$this->isCurrencyAvailable($paymentMethod, $currency)){
            return false;
        }

        $orderData = array(
            'order_number'                  => $orderDetails->order_number,
            'payment_name'                  => $this->renderPluginName($paymentMethod, $order),
            'virtuemart_paymentmethod_id'   => $orderDetails->virtuemart_paymentmethod_id,
            'tax_id'                        => $paymentMethod->tax_id,
            'dotpay_control'                => $orderDetails->order_number,
            'amount'                        => number_format($orderDetails->order_total,2,".",""),
            'currency'                      => $currency,
            'url'                           => $this->getUrl($orderDetails),
            'urlc'                          => $this->getUrlc($orderDetails),
            'dotpay_id'                     => $paymentMethod->dotpay_id,
            'description'                   => 'Zamówienie nr '.$orderDetails->order_number,
            'lang'                          => $this->getLang(),
            'first_name'                    => $orderDetails->first_name,
            'last_name'                     => $orderDetails->last_name,
            'email'                         => $orderDetails->email,
            'city'                          => $orderDetails->city,
            'postcode'                      => $orderDetails->zip,
            'phone'                         => $orderDetails->phone_1,
            'country'                       => $this->getCountryCode($orderDetails),
        );

        $this->saveOrder($orderData);

		return  $this->prepareHtmlForm( $paymentMethod, $orderData);
    }



    //renderowanie formularza z guzikiem accept
    public function plgVmConfirmedOrder($cart, $order)
	{

        if (!($html = $this->plgVmConfirmDotpay($cart, $order, true, "POST"))) {
			return false;
		}

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))){
			return null;
		}
		$nazwa_platnosci = $this->renderPluginName($method);

		return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $nazwa_platnosci, $method->status_pending);
	}

    // weewnetrzna metoda , kazdy plugin to ma wywolywana po zaakceptowaniu
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{
        $paymentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id);

        if(!$this->isPluginValidated($paymentMethod)){
            return null;
        }
		$this->getPaymentCurrency($paymentMethod);
		$paymentCurrencyId = $paymentMethod->payment_currency;
   }

    function uplgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
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

    //thankyou page
	public function plgVmOnPaymentResponseReceived(&$html)
    {
        $jinput = JFactory::getApplication()->input;
        $paymentMethod = $this->getVmPluginMethod($jinput->get->get('pm', 0));

        if(!$this->isPluginValidated($paymentMethod)){
            return false;
        }

        if($jinput->post->get('status') == "OK"){
            JFactory::getApplication()->enqueueMessage( '<br><b>Płatność przebiegła pomyślnie !</b><br>Dziękujemy za dokonanie transakcji za pośrednictwem Dotpay.','message' );
            return true;
        }

        JFactory::getApplication()->enqueueMessage( '<br><b>Płatność nie doszła do skutku !</b><br>Transakcja za pośrednictwem Dotpay nie została przeprowadzona poprawnie.<br>Jeżeli doszło do obciążenia Twojego rachunku bankowego, prosimy o zgłoszenie tego faktu do właściciela sklepu z podaniem numeru zamównienia oraz transakcji.','error' );
        return true;
    }
        
    public function plgVmOnPaymentNotification() {
        $jinput = JFactory::getApplication()->input;
        $paymentMethod = $this->getVmPluginMethod($jinput->get->get('pm', 0));

        if(!$this->isPluginValidated($paymentMethod)){
            exit('plugin error');
        }

        if(!$this->isIpValidated($paymentMethod)){
            exit('untrusted_ip');
        }

        if(!$this->isSingnatureValidated($jinput->post, $paymentMethod)){
            exit('signature mismatch');
        }

        $paymentModel = $this->getPaymentModel($jinput->post->get('control'));

        if(!$this->isCurrencyMatch($jinput->post, $paymentModel)){
            exit('currency mismatch');
        }

        if(!$this->isPriceMatch($jinput->post, $paymentModel)){
            exit('price mismatch');
        }



        $order_id = $paymentModel->virtuemart_order_id;

        if($paymentModel->order_status != "C" && $paymentModel->order_status != 'X'){

            switch($jinput->post->get('status')){
                case 'completed':
                        $this->newStatus($order_id, $paymentMethod->status_success, self::PLG_MESSAGE_STATUS_OK, $paymentMethod->feedback);
                    break;
                case 'rejected':
                        $this->newStatus($order_id, $paymentMethod->status_canceled, self::PLG_MESSAGE_STATUS_FAIL, $paymentMethod->feedback);
                    break;
            }
            exit('OK');
        }

    }



    function plgVmOnUserPaymentCancel() {
        $a = 'a';
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
        $a = 'a';
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


    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total *
            0.01));
    }


    protected function checkConditions($cart, $method, $cart_prices)
    {
        $method->payment_logos = 'dp_logo_alpha_175_50.png';
        if((strlen($method->dotpay_id) < 6) || (strlen($method -> dotpay_id) > 6) ) {
			JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay ID</>','error' );
            return false;
        };
        
        if((strlen($method->dotpay_pin) < 16) || (strlen($method -> dotpay_pin) > 32) ) {
			JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay PIN</b>','error' );
            return false;
        }

		return true;
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

    private function newStatus($order_id, $status, $note = "",  $notified = 1)
    {
        if (!class_exists('VirtueMartModelOrders')){
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        }

        $lang = JFactory::getLanguage();
        $lang->load('com_virtuemart',JPATH_ADMINISTRATOR);
        $modelOrder = VmModel::getModel('orders');

        $orderData = array(
            'order_status'          => $status,
            'virtuemart_order_id'   => $order_id,
            'customer_notified'     => $notified,
            'comments'              => $note
        );


        $modelOrder->updateStatusForOneOrder($order_id, $orderData, true);

        $db = JFactory::getDBO();


        if($status == "C" || $status == "X"){
            $q = 'UPDATE '.$this->_tablename.' SET modified_on=NOW(), locked_on=NOW() WHERE virtuemart_order_id='. $order_id.';   ';
        }else{
            $q = 'UPDATE '.$this->_tablename.' SET modified_on=NOW() WHERE virtuemart_order_id='. $order_id.';   ';
        }

        $db->setQuery($q);

        return 'PLG_DOTPAY_STATUS_CHANGE';
    }

    private function isCurrencyMatch($post, $paymentModel)
    {
        return $paymentModel->waluta_zamowienia == $post->get('operation_original_currency');
    }

    private function isPriceMatch($post, $paymentModel)
    {
        return $paymentModel->kwota_zamowienia == $post->get('operation_original_amount');
    }

    private function getPaymentModel($orderId)
    {
        $db = JFactory::getDBO();
        $q = 'SELECT dotpay.*, ord.order_status, usr.email  FROM '.$this->_tablename.' as dotpay JOIN `#__virtuemart_orders` as ord using(virtuemart_order_id) JOIN #__virtuemart_order_userinfos  as usr using(virtuemart_order_id)  WHERE dotpay.dotpay_control="' .$orderId. '" ';
        $db->setQuery($q);
        return $db->loadObject();
    }

    private function isSingnatureValidated($post, $paymentMethod)
    {

        $string = $paymentMethod->dotpay_pin .
            $post->get('id', '', 'STRING') .
            $post->get('operation_number', '', 'STRING') .
            $post->get('operation_type', '', 'STRING') .
            $post->get('operation_status', '', 'STRING') .
            $post->get('operation_amount', '', 'STRING') .
            $post->get('operation_currency', '', 'STRING') .
            $post->get('operation_withdrawal_amount', '', 'STRING') .
            $post->get('operation_commission_amount', '', 'STRING') .
            $post->get('operation_original_amount', '', 'STRING') .
            $post->get('operation_original_currency', '', 'STRING') .
            $post->get('operation_datetime', '','STRING') .
            $post->get('operation_related_number', '', 'STRING') .
            $post->get('control', '', 'STRING') .
            $post->get('description', '','STRING') .
            $post->get('email', '', 'STRING') .
            $post->get('p_info', '', 'STRING') .
            $post->get('p_email', '', 'STRING') .
            $post->get('channel', '', 'STRING') .
            $post->get('channel_country', '', 'STRING') .
            $post->get('geoip_country','', 'STRING');


        if($post->get('signature') == hash('sha256', $string)){
            return true;
        }
    }

    private function isIpValidated($paymentMethod)
    {
        if($_SERVER['REMOTE_ADDR'] == self::DOTPAY_IP){
            return true;
        }
        if($_SERVER['REMOTE_ADDR'] == '127.0.0.1' && $paymentMethod->fake_real == '1'){
            return true;
        }
    }

    private function saveOrder($orderData){
        $dataToSave = array(
            'order_number'                  => $orderData['order_number'],
            'payment_name'                  => $orderData['payment_name'],
            'virtuemart_paymentmethod_id'   => $orderData['virtuemart_paymentmethod_id'],
            'tax_id'                        => $orderData['tax_id'],
            'dotpay_control'                => $orderData['dotpay_control'],
            'kwota_zamowienia'              => $orderData['amount'],
            'waluta_zamowienia'             => $orderData['currency'],
        );

        $this->storePSPluginInternalData($dataToSave);
    }

    private function getLang()
    {
        $lang = JFactory::getLanguage();
        return  substr($lang->getTag(),0,2);
    }

    private function getUrl($orderDetails)
    {
        return JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm='.$orderDetails->virtuemart_paymentmethod_id;
    }

    private function getUrlc($orderDetails)
    {
        return JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm='.$orderDetails->virtuemart_paymentmethod_id;
    }

    private function prepareHtmlForm( $paymentMethod, $orderData)
    {
        $html = '
		<div style="text-align: center; width: 100%; ">
		<form action="'. $this->getDotpayUrl($paymentMethod) .'" method="Post" class="form" name="platnosc_dotpay" id="platnosc_dotpay">';
        $html .= $this->getHtmlInputs($orderData);

        $html .= $this->getHtmlFormEnd();
        return $html;
    }

    private function getHtmlInputs($orderData)
    {
        $data = array(
            'id'            => $orderData['dotpay_id'],
            'amount'        => $orderData['amount'],
            'currency'      => $orderData['currency'],
            'control'       => $orderData['dotpay_control'],
            'description'   => $orderData['description'],
            'lang'          => $orderData['lang'],
            'type'          => 0,
            'url'           => $orderData['url'],
            'urlc'          => $orderData['urlc'],
            'firstname'     => $orderData['first_name'],
            'lastname'      => $orderData['lastname'],
            'email'         => $orderData['email'],
            'city'          => $orderData['city'],
            'postcode'      => $orderData['postcode'],
            'phone'         => $orderData['phone'],
            'country'       => $orderData['country'],
            'api_version'   => 'dev'
        );

        $html = '';
        foreach($data as $key => $value){
            $html .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
        }
        return $html;
    }

    private function getHtmlFormEnd()
    {
        $src = JURI::root().'media/images/stories/virtuemart/payment/'.'dp_logo_alpha_175_50.png';

        $html = '<br /><b>Opłać zamównienie poprzez Dotpay:<b> <br /><br />';
        $html .='<input name="submit_send" value="" type="submit" style="border: 0; background: url(\''.$src.'\') no-repeat; width: 200px; height: 100px;padding-top:10px" /> <br /><br /><br />';
        $html .='</div>';

        $html .= '<script type="text/javascript">';
        $html .=    'jQuery.noConflict();';
        $html .=	'jQuery(document).ready(function() {';
        $html .=           'jQuery("#platnosc_dotpay").submit();';
        $html .=    '});';
        $html .= '</script>';
        return $html;
    }

    private function getDotpayUrl($paymentMethod)
    {
        if ($paymentMethod->fake_real === '1') {
            return  'https://ssl.dotpay.pl/test_payment/';
        }
        return 'https://ssl.dotpay.pl/';
    }

    private function getCountryCode($orderDetails){
        $q = 'SELECT country_3_code FROM #__virtuemart_countries WHERE virtuemart_country_id='. $orderDetails->virtuemart_country_id.' ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        return $db->loadResult();
    }

    private function isCurrencyAvailable($paymentMethod, $currency)
    {
        return in_array($currency, $paymentMethod->dotpay_waluty);
    }

    private function isPluginValidated($paymentMethod){
        if (!$paymentMethod){
            return false; // Inna metoda została wybrana, nie rób nic.
        }

        if (!$this->selectedThisElement($paymentMethod->payment_element)){
            return false;
        }

        if(!is_array($paymentMethod->dotpay_waluty)){
            return false;
        }
        return true;
    }

    private function getCurrency($paymentMethod)
    {
        $paymentMethod->payment_currency;

        $q = 'SELECT currency_code_3 FROM #__virtuemart_currencies WHERE virtuemart_currency_id="' .$paymentMethod->payment_currency. '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        return $db->loadResult();
    }
}

