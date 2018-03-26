<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.0.5: dotpay.php 2018-03-26
 * @author Dotpay sp. z o.o. < tech@dotpay.pl >
 * @copyright (C) 2018 - Dotpay sp. z o.o.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

defined('_JEXEC') or die('Restricted access');


if (!class_exists('vmPSPlugin'))
	{
		require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
	}
	
class plgVmPaymentDotpay extends vmPSPlugin {

    const PLG_MESSAGE_STATUS_OK = 'Płatność została potwierdzona.';

    const PLG_MESSAGE_STATUS_FAIL = 'Płatność nie została dokonana.';

    const DOTPAY_IP = '195.150.9.37';

    public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; //VM3_dotpay_id';
        $this->_tableId = 'id';   //VM3_dotpay_id';
		$varsToPush = $this->getVarsToPush();
        $varsToPush["payment_logos"] = array("", "char");
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

    /**
     * Wewnetrzna metoda joomli wywolywana po instalacji plugina
     * Tworzy tabele
     *
     * @return string
     */
    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Dotpay Table');
    }

	
  static function getPaymentCurrency (&$paymentMethod, $selectedUserCurrency = false) {

		if (empty($paymentMethod->payment_currency)) {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($paymentMethod->virtuemart_vendor_id);
			$paymentMethod->payment_currency = $vendor->vendor_currency;
			return $paymentMethod->payment_currency;
		} else {

			$vendor_model = VmModel::getModel( 'vendor' );
			$vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies( $paymentMethod->virtuemart_vendor_id );

			if(!$selectedUserCurrency) {
				if($paymentMethod->payment_currency == -1) {
					$mainframe = JFactory::getApplication();
					$selectedUserCurrency = $mainframe->getUserStateFromRequest( "virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt( 'virtuemart_currency_id', $vendor_currencies['vendor_currency'] ) );
				} else {
					$selectedUserCurrency = $paymentMethod->payment_currency;
				}
			}

			$vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
			if(in_array($selectedUserCurrency,$vendor_currencies['all_currencies'])){
				$paymentMethod->payment_currency = $selectedUserCurrency;
			} else {
				$paymentMethod->payment_currency = $vendor_currencies['vendor_currency'];
			}

			return $paymentMethod->payment_currency;
		}

	}
	
	
	
    /**
     * Metoda wywolywana przy instalacji pluginu
     * definiowane sa w niej pola w nowej tabeli
     *
     * @return array
     */
    public function getTableSQLFields()
	{
		return array(
			'id' => ' int(11) UNSIGNED NOT NULL AUTO_INCREMENT ',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
			'tax_id' => 'smallint(1)',
			'dotpay_control' => 'varchar(32) ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
			'payment_currency'  => 'char(3)'
		);
    }

	public function displayLogos($logo_list)
		{
			return "";
		}
	
	
	public function renderPluginName($payment_plugin)
		{
			$name = (empty($payment_plugin->name) ? 'Dotpay' : $payment_plugin->name);
			return
				'<span class="vmCartPaymentLogo" style="width:130px; display: inline-block; text-align: center;">' .
				'<img align="middle" src="' . JURI::root() . '/plugins/vmpayment/dotpay/'.'dp_logo_alpha_110_47.png' .
				'"  alt="' . $name . '" /></span> ' .
				parent::renderPluginName($payment_plugin);
		}
	
		
    /**
     * Zwraca dane do formularza ktory przesyla je pozniej do dotpay
     *
     * @param $paymentMethod
     * @param $order
     * @return array
     */
    private function getOrderData( $paymentMethod, $order)
    {
        $orderDetails = $order['details']['BT'];
		
		$this->getPaymentCurrency($paymentMethod, $order['details']['BT']->payment_currency_id);

		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .$paymentMethod->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		
		
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		
		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$paymentMethod->payment_currency);
        return array(
            'order_number'                  => $orderDetails->order_number,
            'payment_name'                  => $this->renderPluginName($paymentMethod, $order),
            'virtuemart_paymentmethod_id'   => $orderDetails->virtuemart_paymentmethod_id,
            'tax_id'                        => $paymentMethod->tax_id,
            'dotpay_control'                => $orderDetails->order_number,
            'amount'                        => $totalInPaymentCurrency['value'],
            'currency'                      => $currency_code_3,
            'url'                           => $this->getUrl($orderDetails),
            'urlc'                          => $this->getUrlc($orderDetails),
            'dotpay_id'                     => $paymentMethod->dotpay_id,
            'description'                   => 'Zamowienie nr '.$orderDetails->order_number,
            'lang'                          => $this->getLang(),
            'first_name'                    => $orderDetails->first_name,
            'last_name'                     => $orderDetails->last_name,
            'email'                         => $orderDetails->email,
            'city'                          => $orderDetails->city,
            'postcode'                      => $orderDetails->zip,
            'phone'                         => $orderDetails->phone_1,
            'country'                       => $this->getCountryCode($orderDetails),
        );
    }


    /**
     * Metoda wywolywana do wyrenderowania formularza
     *
     * @param $cart
     * @param $order
     * @return bool
     */


   public function plgVmConfirmedOrder($cart, $order)
	{

		if (!($paymentMethod = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($paymentMethod->payment_element)) {
			return FALSE;
		}
		
		if (method_exists('vmLanguage', 'loadJLang')) {
		 	vmLanguage::loadJLang('com_virtuemart',true);
			vmLanguage::loadJLang('com_virtuemart_orders', TRUE);
		}


		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		
        $orderData  = $this->getOrderData($paymentMethod, $order);
        $this->saveOrder($orderData);

		$paymentName = $this->renderPluginName($paymentMethod);
        $html = $this->prepareHtmlForm( $paymentMethod, $orderData);
        $status = $paymentMethod->status_pending;
		$this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $paymentName, $status);
	}


    /**
     *
     * Wewnetrzna metoda, kazdy plugin musi ja miec zaimplementowana
     *
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     * @return null
     */
   	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($paymentMethod = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($paymentMethod->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($paymentMethod);

		$paymentCurrencyId = $paymentMethod->payment_currency;
		return;
	}
   

    /**
     *
     * Strona thank you renderowana po powroceniu do sklepu,
     * w zaleznosci od statusu przekazuje odpowiednia tresc
     *
     * @param $html
     * @return bool
     */
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

    /**
     * Procesowanie odpowiedzi od dotpay
     */
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


            switch($jinput->post->get('operation_status')){
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


    /**
     * Wewnetrzna metoda walidujaca zapisanie konfiguracji w adminie
     *
     * @param VirtueMartCart $cart
     * @param int $method
     * @param array $cart_prices
     * @return bool
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $method->payment_logos = 'dp_logo_alpha_110_47.png';
        if((strlen($method->dotpay_id) < 6) || (strlen($method -> dotpay_id) > 6) ) {
            JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay ID</b>','error' );
            return false;
        };

        if((strlen($method->dotpay_pin) < 16) || (strlen($method -> dotpay_pin) > 32) ) {
            JFactory::getApplication()->enqueueMessage( '<br>Error configuration Payment Methods: <b>BAD Dotpay PIN</b>','error' );
            return false;
        }
        return true;
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

	
 function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		if (method_exists('vmLanguage', 'loadJLang')) {
		 	vmLanguage::loadJLang('com_virtuemart');
		}


		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
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

    /**
     * Wywolywane przy ogladaniu zamowienia na froncie,
     * zwraca nazwe platnosci
     *
     * @param $virtuemart_order_id
     * @param $virtuemart_paymentmethod_id
     * @param $payment_name
     */
    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }
    
    // Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	// The plugin must check first if it is the correct type
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

    /**
     * Metoda wywolywana przy kazdej zmiani konfiguracji
     *
     * @param $jplugin_id
     * @return bool|mixed
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
	    return $this->onStoreInstallPluginTable($jplugin_id);
    }

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
	}

	function plgVmDeclarePluginParamsPaymentVM3(&$data) {
		
		echo '<script type="text/javascript">jQuery(document).ready(function() {jQuery(".control-field h3").css({"background-color":"white","padding":"0"});});</script>';
        return $this->declarePluginParams('payment', $data);
	}

    /**
     * triggerowane kiedy wyswietlany jest koszyk.
     * Decyduje czy wyswietlac dana platnosc
     * zwraca true/false
     *
     * @param VirtueMartCart $cart
     * @param int $selected
     * @param $htmlIn
     * @return bool
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
       return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     *
     * Wywolywana w momencie zaznaczenia platnosci jako listy na radioinputach
     *
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param $cart_prices_name
     * @return bool|null
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }


    /**
     * ustawia nowy status w zamowieniu
     *
     * @param $order_id
     * @param $status
     * @param string $note
     * @param int $notified
     * @return string
     */
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

    /**
     * Sprawdza czy waluta  z notyfikacji dotpaya zgadza sie z ta z
     * orderu
     *
     * @param $post
     * @param $paymentModel
     * @return bool
     */
    private function isCurrencyMatch($post, $paymentModel)
    {
        return $paymentModel->payment_currency == $post->get('operation_original_currency');
    }

    /**
     *
     * Sprawdza czy cena z notyfikacji z dotpaya zgadza sie z cena z orderu
     * @param $post
     * @param $paymentModel
     * @return bool
     */
    private function isPriceMatch($post, $paymentModel)
    {
        return $paymentModel->payment_order_total == $post->get('operation_original_amount');
    }

    /**
     * Zwraca obiekt za platnosci
     *
     * @param $orderId
     * @return mixed
     */
    private function getPaymentModel($orderId)
    {
        $db = JFactory::getDBO();
        $q = 'SELECT dotpay.*, ord.order_status, usr.email  FROM '.$this->_tablename.' as dotpay JOIN `#__virtuemart_orders` as ord using(virtuemart_order_id) JOIN #__virtuemart_order_userinfos  as usr using(virtuemart_order_id)  WHERE dotpay.dotpay_control="' .$orderId. '" ';
        $db->setQuery($q);
        return $db->loadObject();
    }

    /**
     * Sprawdza czy sygnatura jest zwalidowana
     *
     * @param $post
     * @param $paymentMethod
     * @return bool
     */
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

    /**
     * Sprawdza czy ip jest z dotpaya
     *
     * @param $paymentMethod
     * @return bool
     */
    private function isIpValidated($paymentMethod)
    {
        if($_SERVER['REMOTE_ADDR'] == self::DOTPAY_IP){
            return true;
        }
        if($_SERVER['REMOTE_ADDR'] == '127.0.0.1' && $paymentMethod->fake_real == '1'){
            return true;
        }
    }

    /**
     * zapisuje order na podstawie danych z arraya
     *
     * @param $orderData
     */
    private function saveOrder($orderData){
        $dataToSave = array(
            'order_number'                  => $orderData['order_number'],
            'payment_name'                  => $orderData['payment_name'],
            'virtuemart_paymentmethod_id'   => $orderData['virtuemart_paymentmethod_id'],
            'tax_id'                        => $orderData['tax_id'],
            'dotpay_control'                => $orderData['dotpay_control'],
            'payment_order_total'           => $orderData['amount'],
            'payment_currency'              => $orderData['currency'],
        );

        $this->storePSPluginInternalData($dataToSave);
    }

    /**
     * Zwraca aktualny jezyk joomli
     *
     * @return string
     */
    private function getLang()
    {
        $lang = JFactory::getLanguage();
        return  substr($lang->getTag(),0,2);
    }

    /**
     * Zwraca adres url dla strony thank you
     *
     * @param $orderDetails
     * @return string
     */
    private function getUrl($orderDetails)
    {
        return JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm='.$orderDetails->virtuemart_paymentmethod_id;
    }

    /**
     * Zwraca adres url dla notyfikacji z dotpay
     *
     * @param $orderDetails
     * @return string
     */
    private function getUrlc($orderDetails)
    {
        return JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm='.$orderDetails->virtuemart_paymentmethod_id;
    }


    /**
     * Renderuje formularz ktory bedzie wyslany do dotpaya
     *
     * @param $paymentMethod
     * @param $orderData
     * @return string
     */
    private function prepareHtmlForm( $paymentMethod, $orderData)
    {
        $html = '
		<div style="text-align: center; width: 100%; ">
		<form action="'. $this->getDotpayUrl($paymentMethod) .'" method="POST" class="form" name="platnosc_dotpay" id="platnosc_dotpay">';
        $html .= $this->getHtmlInputs($orderData);

        $html .= $this->getHtmlFormEnd();
        return $html;
    }


    /**
     * Na podstawie przygotowanego arraya beda renderowane inputy do formularza
     *
     * @param $orderData
     * @return string
     */
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
            'lastname'      => $orderData['last_name'],
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


    /**
     * Renderuje koncowke formularza i skrypt ktory wywola
     * redirect do strony platnosci
     *
     * @return string
     */
    private function getHtmlFormEnd()
    {
        $src = JURI::root().'plugins/vmpayment/dotpay/'.'dp_logo_alpha_110_47.png';

        $html = '<br /><b>Opłać zamównienie poprzez Dotpay.</b> <br /><br /> Redirecting to the payment page, please wait ...<br /><br />';
        $html .='<input name="submit_send" value="" type="submit" style="border: 0; background: url(\''.$src.'\') no-repeat; width: 200px; height: 100px;padding-top:10px" /> <br /><br /><br />';
        $html .='</form>';
        $html .='</div>';


        $html .= '<script type="text/javascript">';
        $html .=    'jQuery.noConflict();';
        $html .=	'jQuery(document).ready(function() {';
        $html .=           'jQuery("#platnosc_dotpay").submit();';
        $html .=    '});';
        $html .= '</script>';
        return $html;
    }

    /**
     * Wybiera url do dotpaya w zaleznosci od konta testowego
     *
     * @param $paymentMethod
     * @return string
     */
    private function getDotpayUrl($paymentMethod)
    {
        if ($paymentMethod->fake_real === '1') {
            return  'https://ssl.dotpay.pl/test_payment/';
        }
        return 'https://ssl.dotpay.pl/t2/';
    }

    /**
     * Zwraca kod panstwa
     *
     * @param $orderDetails
     * @return mixed
     */
    private function getCountryCode($orderDetails){
        $q = 'SELECT country_3_code FROM #__virtuemart_countries WHERE virtuemart_country_id='. $orderDetails->virtuemart_country_id.' ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        return $db->loadResult();
    }


    /**
     * Metoda skopiowana z innych pluginow ktora testuje czy
     * odpowiednia metoda platnosci zostala wywolana
     *
     * @param $paymentMethod
     * @return bool
     */
    private function isPluginValidated($paymentMethod){
        if (!$paymentMethod){
            return false; // Inna metoda została wybrana, nie rób nic.
        }

        if (!$this->selectedThisElement($paymentMethod->payment_element)){
            return false;
        }
	
		return true;
 
  }

    /**
     * Zwraca walute zamowienia
     *
     * @param $paymentMethod
     * @return mixed
     */
    private function getCurrency($paymentMethod)
    {
        $paymentMethod->payment_currency;

        $q = 'SELECT currency_code_3 FROM #__virtuemart_currencies WHERE virtuemart_currency_id="' .$paymentMethod->payment_currency. '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        return $db->loadResult();
    }
}

