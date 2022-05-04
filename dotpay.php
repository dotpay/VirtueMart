<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! >= 3.4
 * @version $1.2.0: dotpay.php 2022-05-04
 * @author PayPro S.A.. < tech@dotpay.pl >
 * @copyright (C) 2022 - PayPro S.A.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

defined('_JEXEC') or die('Restricted access');


if (!class_exists('vmPSPlugin'))
	{
		require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
	}
	
class plgVmPaymentDotpay extends vmPSPlugin {

	/** Version information */
    const DP_RELDATE = '2022-05-04';
    const DOTPAY_MODULE_VERSION = '1.2.0';



	/** Dotpay IP allowed */    
    //const DOTPAY_IP = '195.150.9.37';

    const DOTPAY_IP_WHITE_LIST = array(
        '195.150.9.37',
        '91.216.191.181',
        '91.216.191.182',
        '91.216.191.183',
        '91.216.191.184',
        '91.216.191.185',
        '5.252.202.255',
      );



    const DP_SUPPORT_IP = '77.79.195.34';



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
			'payment_name' => 'VARCHAR(500) NOT NULL DEFAULT \'\' ',
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
				'"  alt="' . $name . '" /></span>'.
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
            'description'                   => vmText::_('PLG_DOTPAY_ORDER_TITLE').' '.$orderDetails->order_number,
            'lang'                          => $this->getLang(),
            'first_name'                    => $orderDetails->first_name,
            'last_name'                     => $orderDetails->last_name,
            'email'                         => $orderDetails->email,
            'city'                          => $orderDetails->city,
            'address_1'                     => $orderDetails->address_1,
            'address_2'                     => $orderDetails->address_2,
            'postcode'                      => $orderDetails->zip,
            'phone_1'                       => $orderDetails->phone_1,
            'phone_2'                       => $orderDetails->phone_2,
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


        $modelOrder = VmModel::getModel ('orders');

        $order['customer_notified'] = 1;
        $order['comments'] = vmText::_('PLG_DOTPAY_ORDER_COMMENT');
        $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
        //We delete the old stuff
        $cart->emptyCart();
        vRequest::setVar ('html', $html);
        return true;


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

        $this->loadVmClass('VirtueMartCart', JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

		if(!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		$this->loadVmClass('VirtueMartModelOrders', JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');


		vmLanguage::loadJLang('com_virtuemart_orders', TRUE);

		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);

		if(!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if(!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}


            $jinput = JFactory::getApplication()->input;
            $DP_post_array = $jinput->getArray($_POST);
            $oid = JFactory::getApplication()->input->getString('oid');

                if(preg_match('/^[A-Za-z0-9 _-]+$/',($oid))){
                    $NR_zam = $oid ; 
                }else{ 
                    $NR_zam = ''; 
                }

    //jesli w linku powrotu jest nr transakcji
    if($NR_zam != ''){  

        // dane zamowienia z bazy

        $q = 'SELECT `virtuemart_order_id`,`order_number`,`order_status`,`modified_on`, TIMESTAMPDIFF(MINUTE,`modified_on`, NOW()) as minute, TIMESTAMPDIFF(SECOND,`modified_on`, NOW()) as second, (TIMEDIFF(NOW(), UTC_TIMESTAMP)) as time_diff FROM `#__virtuemart_orders` WHERE `order_number`="' .$NR_zam . '"';

        $db = JFactory::getDBO();
        $db->setQuery($q);
        $data_order1 = $db->loadAssocList();	

    // szukam zamówienia po numerze
    if(isset($data_order1[0])) {

            $data_order = $data_order1[0]; 

            $timesplit=explode(':',$data_order['time_diff']);
            $min=($timesplit[0]*60)+($timesplit[1])+($timesplit[2]>30?1:0);
            
            $timezone_utc_min = (int)$min;
            $timezone_utc_sec = (int)$min*60;

                if($data_order['minute'] - $timezone_utc_min > 1){

                    $timediff = $data_order['minute'] - $timezone_utc_min .' '.vmText::_('PLG_MESSAGE_NOTIFY_TIME_MINUTES'); 
                }else {
                    $timediff = $data_order['second'] - $timezone_utc_sec.' '.vmText::_('PLG_MESSAGE_NOTIFY_TIME_SECONDS');
                }
                if((int)$data_order['virtuemart_order_id']){
                    $ID_zam = (int)$data_order['virtuemart_order_id'];
                }else{
                    $ID_zam = '';
                }
                
                $html_info_notification_when = vmText::_('PLG_MESSAGE_NOTIFY_TIME2').' '.$timediff .' '.vmText::_('PLG_MESSAGE_NOTIFY_TIME3');
    

     /**  notifications */

        // sprawdzam w historii zamówienia czy jest już informacja o poprawnej platnosci
        //$q2 = 'SELECT `comments`,count(`comments`) as `wykonane`,`modified_on`, TIMESTAMPDIFF(MINUTE,`modified_on`, NOW()) as minute, TIMESTAMPDIFF(SECOND,`modified_on`, NOW()) as second FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`= "' .$ID_zam . '" AND `order_status_code` LIKE "C" AND `comments` LIKE "%- completed)%" ORDER BY `#__virtuemart_order_histories`.`virtuemart_order_history_id` DESC LIMIT 1';


        // sprawdzam w historii zamówienia jaka jest ostatnia informacja o notyfikacji do zamówienia do 180 minut wstecz
        $q3 = 'SELECT `virtuemart_order_id`, `order_status_code`, `comments`, TIMESTAMPDIFF(MINUTE,`modified_on`, NOW()) as `minut` FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`= "' .$ID_zam . '" AND (TIMESTAMPDIFF(MINUTE,`modified_on`, NOW())) < 180 ORDER BY `#__virtuemart_order_histories`.`virtuemart_order_history_id` DESC LIMIT 1';

        $db3 = JFactory::getDBO();
        $db3->setQuery($q3);
        $stat_last = $db3->loadAssocList();	
    
    
            if(count($stat_last) >0 ){
                $order_status_c = $stat_last[0]['order_status_code'];
                $b = explode(' - ',$stat_last[0]['comments']);
                
                if(isset($b[1])){
                    $status_1 = str_replace(')', '', $b[1]);  
                    $dp_status = str_replace('.', '', $status_1);
                }else{
                    $dp_status = '';
                }
    
                if (preg_match("/^[a-z]{3,15}$/", $dp_status)) {
                    $dp_last_st = $dp_status; 
                }else{ 
                    $dp_last_st  = ''; 
                }
          
            }else{
                $dp_last_st  = '';
                $order_status_c = '';
            }

              
            // jesli nie znaleziono  statusu dla tego zamowienia
            if(!isset($data_order['order_status'])){

                JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK_TXT1').'','alert',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );

            } else {  //jesli notyfikacja w ciagu ostatnich 180 minut byla dostarczona i zarejestrowano w historii zamowienia

                    
                    if($order_status_c !=''){

                        if($data_order['order_status'] == "C" && $ID_zam == $data_order['virtuemart_order_id']){

                            if($data_order['order_status'] == "C" && $dp_last_st == "rejected" && $ID_zam == $data_order['virtuemart_order_id']){
                                JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_REJECT1').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_COMPLETED_BEFORE_TXT1').'<br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_COMPLETED_BEFORE_TXT2').'<br>','alert',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                        
                            }
                        
                            else if($data_order['order_status'] == "C" && $dp_last_st != "rejected" && $ID_zam == $data_order['virtuemart_order_id']){
                                JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED_THANKS').'<br><br>','message',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                        
                            }else{
                                JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED_THANKS').'<br><br>','message',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                            }
                    
                        }
                        
                        else if($data_order['order_status'] == "P" && $order_status_c == "P" && $dp_last_st == "new" && $ID_zam == $data_order['virtuemart_order_id']){
                            
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT1').'<br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT2').' <b>'.$data_order['order_number'].'</b> '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_NUMBER_DP').'','warning',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                    
                        } else if($data_order['order_status'] == "P" && $order_status_c == "P" && $dp_last_st != "new" && $dp_last_st != "rejected" && $ID_zam == $data_order['virtuemart_order_id']){
                            
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_WAITING').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT1').'<br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT2').' <b>'.$data_order['order_number'].'</b> '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_NUMBER_DP').'','warning',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );

                        } else if($data_order['order_status'] == "P" && $order_status_c == "P" && $dp_last_st == "rejected" && $ID_zam == $data_order['virtuemart_order_id']){
                            
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_REJECT2').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_REJECT3').'<br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT2').' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_NUMBER_DP').'','error',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );

                        
                        }else{
                            
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT2').' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_NUMBER_DP').'','alert',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                        }
                    
                    } else{  //// jesli w ciagu ostanich 180 minut nie ma notyfikacji do tego zamówienia
            
                        if($data_order['order_status'] == "P"  && $ID_zam == $data_order['virtuemart_order_id']){
                    
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_REJECT2').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_REJECT3').'<br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_INITIATED_TXT2').' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_NUMBER_DP').'','error',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                    
                        }
                        if($data_order['order_status'] == "C" && $dp_last_st != "rejected" && $ID_zam == $data_order['virtuemart_order_id']){
                            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER').': '.$data_order['order_number'].' '.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED').'</b><br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_PAYMENT_FOR_ORDER_COMPLETED_THANKS').'<br><br>','message',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
                    
                            }
                        
                        }
 
            
            }

           // return true;
        } else {
            $timediff = ''; 
            $ID_zam = ''; 
            $html_info_notification_when = vmText::_('PLG_MESSAGE_NOTIFY_TIME1');  
    
            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK').'</b><br>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK_TXT2').'<br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK_TXT1').'','alert',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
        // return true;
        }   


    }else{  //jesli w linku powrotu nie ma nr transakcji
            $timediff = ''; 
            $ID_zam = ''; 
            $html_info_notification_when = vmText::_('PLG_MESSAGE_NOTIFY_TIME1');

            JFactory::getApplication()->enqueueMessage( '<br><b>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK').'</b><br>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK_TXT2').'<br><br>'.vmText::_('PLG_MESSAGE_NOTIFY_LACK_TXT1').'','alert',vmText::_('PLG_MESSAGE_NOTIFY_TITLE') );
          //  return true;

    }



        $html = "<em>". $html_info_notification_when."</em>";

        vRequest::setVar('display_title', false);
        vRequest::setVar('html', $html);
        return true;
}


	function loadVmClass($className, $fileName) {
		if(!class_exists($className)) {
			if(file_exists($fileName)) {
				require($fileName);
			} else {
				vmError('Programming error:' . __FUNCTION__ . ' trying to load:' . $fileName);
			}
		}
	}


   /**
    * tools: zmiana tablicy wynków z bazy - historia zamówienia - 'comments' 
    */ 
    public function array_value_recursive($key, array $arr){
        $val = array();
        array_walk_recursive($arr, function($v, $k) use($key, &$val){
            if($k == $key) array_push($val, $v);
        });
        return count($val) > 1 ? $val : array_pop($val);
    }


    public function getClientIp($list_ip = null)
    {
        $ipaddress = '';
        // CloudFlare support
        if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
            // Validate IP address (IPv4/IPv6)
            if (filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
                $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
                return $ipaddress;
            }
        }
        if (array_key_exists('X-Forwarded-For', $_SERVER)) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $_SERVER['X-Forwarded-For'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipaddress = $ips[0];
            } else {
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } else {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }


        if (isset($list_ip) && $list_ip != null) {
            if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
                return  $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
                return $_SERVER["HTTP_CF_CONNECTING_IP"];
            } else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
                return $_SERVER["REMOTE_ADDR"];
            }
        } else {
            return $ipaddress;
        }
    }




    /**
     * Procesowanie odpowiedzi od dotpay
     */
    public function plgVmOnPaymentNotification() {
        $jinput = JFactory::getApplication()->input;

        $DP_post_array = $jinput->getArray($_POST);

        $paymentMethod = $this->getVmPluginMethod($jinput->get->get('pm', 0));

        if(!$this->isPluginValidated($paymentMethod)){
            exit('plugin error');
        }


        $dotpay_office = false;
        $dp_debug_allow = false;
        $show_time_in_urlc = "";
        $proxy_desc ='';

        if( (int)$this->getDPConf('dotpay_nonproxy') == 1) {
            $clientIp = $_SERVER['REMOTE_ADDR'];
            $proxy_desc = 'FALSE';
        }else{
            $clientIp = $this->getClientIp();
            $proxy_desc = 'TRUE';
        }




// diagnostic only for customer service dotpay :


        if( ($clientIp == self::DP_SUPPORT_IP) && (strtoupper($_SERVER['REQUEST_METHOD']) == 'GET')) 
        {
                $dotpay_office = true;
        }else{
                $dotpay_office = false;
        }

        $get_dp_debug = JFactory::getApplication()->input->getString('dp_debug');
        

        if( strtoupper($_SERVER['REQUEST_METHOD']) == 'GET' && isset($get_dp_debug) ){
            $string_to_hash = 'h:'.$this->geShoptHost().',id:'.$paymentMethod->dotpay_id.',d:'.date('YmdHi').',p:'.$paymentMethod->dotpay_pin;
            
            if(trim($get_dp_debug) == 'time'){
                $show_time_in_urlc = ", Time: ".date('YmdHi');
            }
            $dp_debug_hash = hash('sha256', $string_to_hash);
            if(trim($get_dp_debug) == $dp_debug_hash){
                $dp_debug_allow = true;
            }else{
                $dp_debug_allow = false;
            }

        }else{
            $dp_debug_allow = false;
        }



        if($dotpay_office == true || $dp_debug_allow == true) {



            exit("Virtuemart Dotpay payment module debug:<br><br>
			       -------------------------------".
                "<br> Time: ".date('YmdHi').   
                "<br> * Virtuemart ver: " .vmVersion::$RELEASE. " rev. ".vmVersion::$REVISION. " [".vmVersion::$CODENAME ."]". ", release date: ". vmVersion::$RELDATE .
                "<br><br> * Joomla ver: ". JVERSION . ' - '.JVM_VERSION. 
                "<br> * PHP ver: ". PHP_VERSION .

                "<br>  ____________________________________________________________________________________ <br> ".

                "<br> * Dotpay module ver: ".self::DOTPAY_MODULE_VERSION.", release date: ".self::DP_RELDATE.
				"<br>&nbsp;&nbsp;  - ID: ". $paymentMethod->dotpay_id.
                "<br>&nbsp;&nbsp;  - Test mode: ".(int)$this->getDPConf('fake_real').
                "<br />Server does not use a proxy: ".(int)$this->getDPConf('dotpay_nonproxy').
                "<br /> REMOTE ADDRESS: ".$_SERVER['REMOTE_ADDR'].
                "<br>  - Hostname: ".$this->geShoptHost().
                
                "<br>&nbsp;&nbsp;  - Automatyczne przekierowanie: ".$this->getDPConf('autoredirect').
                "<br>&nbsp;&nbsp;  - Opłata dodatkowa wyboru płatności (stała): ".$this->getDPConf('cost_per_transaction').
                "<br>&nbsp;&nbsp;  - Opłata dodatkowa zależna od wartości zamówienia (procent od zamówienia): ".$this->getDPConf('cost_percent_total')
            );
        }

   //  ---- . 


        if (!$this->isAllowedIp($clientIp, self::DOTPAY_IP_WHITE_LIST))  
        {
                die("Virtuemart - ERROR (REMOTE ADDRESS: ".$this->getClientIp(true)."/".$_SERVER["REMOTE_ADDR"].", PROXY:".$proxy_desc.$show_time_in_urlc.")");
        }



        if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
            exit("Virtuemart - ERROR (METHOD <> POST)");
        }


        $check_signature = $this->isSingnatureValidated($DP_post_array, $paymentMethod); 

         if($check_signature != true)
        {
           exit('Virtuemart - signature mismatch !');
        }
 
        $control1 = explode('|', (string)$DP_post_array['control']);     

        $paymentModel = $this->getPaymentModel($control1[0]);


        if(!$this->isCurrencyMatch($DP_post_array['operation_original_currency'], $paymentModel)){
            exit('Virtuemart - currency mismatch');
        }

        if(!$this->isPriceMatch($DP_post_array['operation_original_amount'], $paymentModel)){
            exit('Virtuemart - price mismatch');
        }

        $order_id = $paymentModel->virtuemart_order_id;


        // historia zamowienia:

        $q = 'SELECT `comments` FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`="' .$order_id . '" AND `order_status_code` = "C" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
        $comments = $db->loadAssocList();	
       
        $orderComment1 = [];

                foreach ((array)$comments as $comment1) {
                        $body1 = $comment1['comments'];
                        preg_match_all("/M\d{4,6}\-\d{4,6}/", $body1, $matches);
                        $body2 = array_unique($matches[0]);
                        $orderComment1[] = $body2;
               
                    }       
    
     $dotpay_transaction_number_array = $this->array_value_recursive('0', $orderComment1);             
   
        if($paymentModel->order_status != "C" && $paymentModel->order_status != 'X'){


            switch($DP_post_array['operation_status']){
                case 'new':
                    $this->newStatus($order_id, $paymentMethod->status_pending, vmText::_('PLG_MESSAGE_STATUS_NEW').' ('.$DP_post_array['operation_number'].' - new).', $paymentMethod->feedback);

                    break;
                case 'completed':
                        $this->newStatus($order_id, $paymentMethod->status_success, vmText::_('PLG_MESSAGE_STATUS_OK').' ('.$DP_post_array['operation_number'].' - completed).', $paymentMethod->feedback);

                    break;
                case 'rejected':
                        $this->newStatus($order_id, $paymentMethod->status_canceled, vmText::_('PLG_MESSAGE_STATUS_FAIL').' ('.$DP_post_array['operation_number'].' - rejected).', $paymentMethod->feedback);
                    break;
            }

            exit('OK');
        }
        if($paymentModel->order_status == "C" && $DP_post_array['operation_status'] == 'completed')
        {   
            // jesli w komentarzach nie ma takiego numeru transakcji - moze oznaczac ze to kolejna platnosc za to samo zamowienie
            if (!in_array($DP_post_array['operation_number'], $dotpay_transaction_number_array)) {

                $this->newStatus($order_id, $paymentMethod->status_success, vmText::_('PLG_MESSAGE_STATUS_DUBEL').' ('.$DP_post_array['operation_number'].' - completed).', $paymentMethod->feedback);

            }else{
                $this->newStatus($order_id, $paymentMethod->status_success, vmText::_('PLG_MESSAGE_STATUS_OK_AGAIN').' ('.$DP_post_array['operation_number'].' - completed).', $paymentMethod->feedback);
            }

            exit('OK');
        }

        if($paymentModel->order_status == "C" && $DP_post_array['operation_status'] == 'rejected')
        {   
                $this->newStatus($order_id, $paymentMethod->status_success, vmText::_('PLG_MESSAGE_STATUS_FAIL_AFTER_COMPLETED').' ('.$DP_post_array['operation_number'].' - rejected).', $paymentMethod->feedback);

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
        if (preg_match('/%$/', (string)$method->cost_percent_total)) {
            $cost_percent_total = (string)substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = (string)$method->cost_percent_total;
        }
        return ((string)$method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total *
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
        return $paymentModel->payment_currency == $post;
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
        return $paymentModel->payment_order_total == $post;
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
 
 
    private function isSingnatureValidated($post, $paymentMethod,$debug=false)
    {
          $string = $paymentMethod->dotpay_pin .
                    (isset($post['id']) ? $post['id'] : null).
                    (isset($post['operation_number']) ? $post['operation_number'] : null).
                    (isset($post['operation_type']) ? $post['operation_type'] : null).
                    (isset($post['operation_status']) ? $post['operation_status'] : null).
                    (isset($post['operation_amount']) ? $post['operation_amount'] : null).
                    (isset($post['operation_currency']) ? $post['operation_currency'] : null).
                    (isset($post['operation_withdrawal_amount']) ? $post['operation_withdrawal_amount'] : null).
                    (isset($post['operation_commission_amount']) ? $post['operation_commission_amount'] : null).
                    (isset($post['is_completed']) ? $post['is_completed'] : null).
                    (isset($post['operation_original_amount']) ? $post['operation_original_amount'] : null).
                    (isset($post['operation_original_currency']) ? $post['operation_original_currency'] : null).
                    (isset($post['operation_datetime']) ? $post['operation_datetime'] : null).
                    (isset($post['operation_related_number']) ? $post['operation_related_number'] : null).
                    (isset($post['control']) ? $post['control'] : null).
                    (isset($post['description']) ? $post['description'] : null).
                    (isset($post['email']) ? $post['email'] : null).
                    (isset($post['p_info']) ? $post['p_info'] : null).
                    (isset($post['p_email']) ? $post['p_email'] : null).
                    (isset($post['credit_card_issuer_identification_number']) ? $post['credit_card_issuer_identification_number'] : null).
                    (isset($post['credit_card_masked_number']) ? $post['credit_card_masked_number'] : null).
                    (isset($post['credit_card_expiration_year']) ? $post['credit_card_expiration_year'] : null).
                    (isset($post['credit_card_expiration_month']) ? $post['credit_card_expiration_month'] : null).
                    (isset($post['credit_card_brand_codename']) ? $post['credit_card_brand_codename'] : null).
                    (isset($post['credit_card_brand_code']) ? $post['credit_card_brand_code'] : null).
                    (isset($post['credit_card_unique_identifier']) ? $post['credit_card_unique_identifier'] : null).
                    (isset($post['credit_card_id']) ? $post['credit_card_id'] : null).
                    (isset($post['channel']) ? $post['channel'] : null).
                    (isset($post['channel_country']) ? $post['channel_country'] : null).
                    (isset($post['geoip_country']) ? $post['geoip_country'] : null).
                    (isset($post['payer_bank_account_name']) ? $post['payer_bank_account_name'] : null).
                    (isset($post['payer_bank_account']) ? $post['payer_bank_account'] : null).
                    (isset($post['payer_transfer_title']) ? $post['payer_transfer_title'] : null).
                    (isset($post['blik_voucher_pin']) ? $post['blik_voucher_pin'] : null).
                    (isset($post['blik_voucher_amount']) ? $post['blik_voucher_amount'] : null).
                    (isset($post['blik_voucher_amount_used']) ? $post['blik_voucher_amount_used'] : null);

                //! Warning: is only for the debug!    
                if($debug == true) {

                    return ($string.'<br>signature: '.$post['signature'].'<br>calculate: '.hash('sha256', $string));

                } else{

                    if((string)trim($post['signature']) == (string)hash('sha256', trim($string))){
                        return true;
                    }else{
                        return false;
                    }
                }


    }


    /**
         * Returns if the given ip is on the given whitelist.
         *
         * @param string $ip        The ip to check.
         * @param array  $whitelist The ip whitelist. An array of strings.
         *
         * @return bool
     */
    public function isAllowedIp($ip, array $whitelist)
    {
        $ip = (string)$ip;
        if (in_array($ip, $whitelist, true)) {
            return true;
        }

        return false;
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
        return JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm='.$orderDetails->virtuemart_paymentmethod_id.'&oid='.$orderDetails->order_number;
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
    	 * checks and crops the size of a string
    	 * the $special parameter means an estimate of how many urlencode characters can be used in a given field
    	 * e.q. 'ż' (1 char) -> '%C5%BC' (6 chars)
    	 * replacing removing double or more special characters that appear side by side by space from: firstname, lastname, city, street, p_info...
    	 */
    	public function encoded_substrParams($string, $from, $to, $special=0)
    		{
    			$string2 = preg_replace('/(\s{2,}|\.{2,}|@{2,}|\-{2,}|\/{3,} | \'{2,}|\"{2,}|_{2,})/', ' ', $string);
    			$s = html_entity_decode($string2, ENT_QUOTES, 'UTF-8');
    			$sub = mb_substr($s, $from, $to,'UTF-8');
    			$sum = strlen(urlencode($sub));
    			if($sum  > $to)
    				{
    					$newsize = $to - $special;
    					$sub = mb_substr($s, $from, $newsize,'UTF-8');
    				}
    			return trim($sub);
    		}


                /**
     * Return customer firstname
     * @return string
     */
    public function getFirstnameDP($firstName)
    {
        //allowed only: letters, digits, spaces, symbols _-.,'
        $firstName = preg_replace('/[^\w _-]/u', '', $firstName);
        $firstName1 = html_entity_decode($firstName, ENT_QUOTES, 'UTF-8');


        $NewPersonName1 = preg_replace('/[^\p{L}0-9\s\-_]/u',' ',$firstName1);
        return $this->encoded_substrParams($NewPersonName1,0,49,24);
    }

    /**
     * Return customer lastname
     * @return string
     */
    public function getLastnameDP($lastName)
    {
        //allowed only: letters, digits, spaces, symbols _-.,'
        $lastName = preg_replace('/[^\w _-]/u', '', $lastName);
        $lastName1 = html_entity_decode($lastName, ENT_QUOTES, 'UTF-8');

        $NewPersonName2 = preg_replace('/[^\p{L}0-9\s\-_]/u',' ',$lastName1);
        return $this->encoded_substrParams($NewPersonName2,0,49,24);
    }


    /**
     * Return customer phone
     * @return string
     */
    public function getPhoneDP($phone)
    {
        $phone = str_replace(' ', '', $phone);
        $phone = str_replace('+', '', $phone);

        $NewPhone1 = preg_replace('/[^\+\s0-9\-_]/','',$phone);
        $NewPhone2 = trim($this->encoded_substrParams($NewPhone1,0,19,6));
          if((bool)preg_match('/^[\d\w\s\-]{0,20}$/', $NewPhone2) ) {
                return $NewPhone2;
          }else {
                return null;
          }
    }


    /**
     * Return customer city
     * @return string
     */
    public function getCityDP($city)
    {

        //allowed only: letters, digits, spaces, symbols _-.,'
        $city = preg_replace('/[^.\w \'_-]/u', '', $city);
        $city1 = html_entity_decode($city, ENT_QUOTES, 'UTF-8');

        return $this->encoded_substrParams($city1,0,49,24);

    }


    /**
     * Return customer street (address_1)
     * @return string
     */
    public function getStreetDP($street)
    {

        //allowed only: letters, digits, spaces, symbols _-.,'
        $street = preg_replace('/[^.\w \'_-]/u', '', $street);
        $street1 = html_entity_decode($street, ENT_QUOTES, 'UTF-8');

        return $this->encoded_substrParams($street1,0,99,50);

    }

    /**
     * Return customer street_n1 (address_2)
     * @return string
     */
    public function getStreet2DP($street_n1)
    {

        //allowed only: letters, digits, spaces, symbols _-.,'
        $building_number = preg_replace('/[^\p{L}0-9\s\-_\/]/u',' ',$street_n1);
        $building_number1 = html_entity_decode($building_number, ENT_QUOTES, 'UTF-8');

        return $this->encoded_substrParams($building_number1,0,29,24);

    }


    /**
     * Return customer postcode
     * @return string
     */
    public function getPostcodeDP($postcode,$lang='pl')
    {

        if (empty($postcode)) {
            return $postcode;
        }
        if (preg_match('/^\d{2}\-\d{3}$/', $postcode) == 0 && strtolower($lang) == 'pl') {
            $postcode = str_replace('-', '', $postcode);
            $postcode = substr($postcode, 0, 2) . '-' . substr($postcode, 2, 3);
        }

        $NewPostcode1 = preg_replace('/[^\d\w\s\-]/','',$postcode);
        return $this->encoded_substrParams($NewPostcode1,0,19,6);

    }

    /**
     * Return customer country
     * @return string
     */
    public function getCountryDP($country)
    {

        if (preg_match('/^[a-zA-Z]{2,3}$/', trim($country)) == 0) {
            $country_check = null;
         }else{
            $country_check = trim($country);
         }
         $country_check1 = html_entity_decode($country_check, ENT_QUOTES, 'UTF-8');
        return strtoupper($country_check1);
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
		<form action="'. $this->getDotpayUrl($paymentMethod) .'" method="POST" class="platnosc_dotpay" name="platnosc_dotpay" id="platnosc_dotpay">';
        $html .= $this->getHtmlInputs($orderData);

        $html .= $this->getHtmlFormEnd();
        return $html;
    }

   
    /**
     * Returns correct SERVER NAME or HOSTNAME
     * @return string
     */
    private function geShoptHost()
    {
        $possibleHostSources = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
        $sourceTransformations = array(
            "HTTP_X_FORWARDED_HOST" => function($value) {
                $elements = explode(',', $value);
                return trim(end($elements));
            }
        );
        $host = '';
        foreach ($possibleHostSources as $source)
        {
            if (!empty($host)) break;
            if (empty($_SERVER[$source])) continue;
            $host = $_SERVER[$source];
            if (array_key_exists($source, $sourceTransformations))
            {
                $host = $sourceTransformations[$source]($host);
            }
        }
        // Remove port number from host
        $host = preg_replace('/:\d+$/', '', $host);
            if((bool) preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,10}$/', trim($host))){
                $server_name = trim($host);
            } else {
                $server_name = "HOSTNAME";
            }

     return $server_name;   

    }



    private function getInputsForm($orderData)
    {
        if (null !== $this->getPhoneDP($orderData['phone_2']) ) {
            $phone = (string) $this->getPhoneDP($orderData['phone_2']);
        } else {
            $phone = (string) $this->getPhoneDP($orderData['phone_1']);
        }

        $data = array(
            'id'            => (string) $orderData['dotpay_id'],
            'amount'        => (string) $orderData['amount'],
            'currency'      => (string)$orderData['currency'],
            'control'       => (string) $orderData['dotpay_control'].'|domain:'.$this->geShoptHost().'|VirtueMart:v'.vmVersion::$RELEASE.'|Dotpay module v:'.self::DOTPAY_MODULE_VERSION,
            'description'   => (string)$orderData['description'],
            'lang'          => (string) $orderData['lang'],
            'type'          => '0',
            'url'           => (string) $orderData['url'],
            'urlc'          => (string) $orderData['urlc'],
            'firstname'     => (string) $this->getFirstnameDP($orderData['first_name']),
            'lastname'      => (string) $this->getLastnameDP($orderData['last_name']),
            'email'         => (string) $orderData['email'],    
            'api_version'   => 'next',
            'ignore_last_payment_channel' => '1'

        );

        if( null != trim($phone))
        {
            $data["phone"] = (string) $phone;
        }
        if( null != trim($this->getCountryDP($orderData['country'])))
        {
            $data["country"] = (string)$this->getCountryDP($orderData['country']);
        }
        if( null != trim($this->getPostcodeDP($orderData['postcode'],$orderData['lang'])))
        {
            $data["postcode"] = (string) $this->getPostcodeDP($orderData['postcode'],$orderData['lang']);
        }

        if( null != trim($this->getStreet2DP($orderData['address_2'])))
        {
            $data["street_n1"] = (string)$this->getStreet2DP($orderData['address_2']);
        }

        if( null != trim($this->getStreetDP($orderData['address_1'])))
        {
            $data["street"] = (string) $this->getStreetDP($orderData['address_1']);
        }

        if( null != trim($this->getCityDP($orderData['city'])))
        {
            $data["city"] = (string) $this->getCityDP($orderData['city']);
        }


        return $data;


    }


    /**
     * Zwraca konfigurację do konta Dotpay zapisana do bazy
     *
     * @return string
     */
    private function getDPConf($what){

		$query = "SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE  payment_element = 'dotpay'";

		$db = JFactory::getDBO();
		$db->setQuery($query);
		$params = $db->loadResult();

        if($what == 'dotpay_pin'){
            preg_match('/\|dotpay_pin="(\w+)"\|/', $params, $get_pin);

            if (isset($get_pin[1]) &&  ((strlen(trim($get_pin[1])) >= 16) && (strlen(trim($get_pin[1])) <= 32) )){
                return trim($get_pin[1]);
            }else{
                return false;
            }
        }
        else if($what == 'fake_real') {
            preg_match('/\|fake_real="(\d+)"\|/', $params, $get_param1);
            if (isset($get_param1[1])){
                return trim($get_param1[1]);
            }else{
                return false;
            }
        }
        else if($what == 'dotpay_nonproxy') {
            preg_match('/\|dotpay_nonproxy="(\d+)"\|/', $params, $get_param1);
            if (isset($get_param1[1])){
                return trim($get_param1[1]);
            }else{
                return false;
            }
        }
        else if($what == 'cost_per_transaction') {
            preg_match('/\|cost_per_transaction="([\d\.]+)"\|/', $params, $get_param1);
            if (isset($get_param1[1])){
                return trim($get_param1[1]);
            }else{
                return false;
            }
        }
        else if($what == 'cost_percent_total') {
            preg_match('/\|cost_percent_total="([\d\.]+)"\|/', $params, $get_param2);
            if (isset($get_param2[1])){
                return trim($get_param2[1]);
            }else{
                return false;
            }
        }
        else if($what == 'autoredirect') {
            preg_match('/\|autoredirect="(\d+)"\|/', $params, $get_param2);
            if (isset($get_param2[1])){
                return trim($get_param2[1]);
            }else{
                return false;
            }
        }

        else if($what == 'feedback') {
            preg_match('/\|feedback="(\d+)"\|/', $params, $get_param2);
            if (isset($get_param2[1])){
                return trim($get_param2[1]);
            }else{
                return false;
            }
        }else{
            return false;
        }

        return false;
    }

    /**
     * Generate CHK for seller and payment data
     * @param type $DotpayPin Dotpay seller PIN
     * @param array $orderData parameters of payment
     * @return string
     */
    
    
    ## function: counts the checksum from the defined array of all parameters

    public static function generateCHK($ParametersArray, $DotpayPin)
    {

        if(isset($ParametersArray['chk']))
        {
            unset($ParametersArray['chk']);
        }

            //sorting the parameter list
            ksort($ParametersArray);
            
            // Display the semicolon separated list
            $paramList = implode(';', array_keys($ParametersArray));
            
            //adding the parameter 'paramList' with sorted list of parameters to the array
            $ParametersArray['paramsList'] = $paramList;
            
            //re-sorting the parameter list
            ksort($ParametersArray);
            
            //json encoding  
            $json = json_encode($ParametersArray, JSON_UNESCAPED_SLASHES);

 
        return hash_hmac('sha256', $json, $DotpayPin, false);
   
       
    }




    /**
     * Na podstawie przygotowanego arraya beda renderowane inputy do formularza
     *
     * @param $orderData
     * @return string
     */
 
    private function getHtmlInputs($orderData)
    {
        $data = $this->getInputsForm($orderData);
        $pin =  trim($this->getDPConf('dotpay_pin'));

        $chk =  $this->generateCHK($data, $pin);

        $html = '';
        foreach($data as $key => $value){
            $html .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
        }
        if(null !== $pin){
            $html .= '<input type="hidden" name="chk" value="'.$chk.'" />';
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

        
        $html = "<br /><b>".vmText::_('PLG_DOTPAY_REDIRECT_IMG_CLICK')."</b> <br />";
        if((int)$this->getDPConf('autoredirect') != 1) {
            $html .= "<br /><br /> ".vmText::_('PLG_DOTPAY_REDIRECT_IMG_CLICK_DESCR')."<br /><br />";
        }else{
            $html .= "<br /><br /> ".vmText::_('PLG_DOTPAY_REDIRECT_IMG_WAIT')."<br /><br />";
        }

        $html .='<input type="submit" value="" style="border: 0; background: url(\''.$src.'\') no-repeat; width: 200px; height: 100px;padding-top:10px" /> <br /><br /><br />';
        $html .='</form>';
        $html .='</div>';

        if((int)$this->getDPConf('autoredirect') == 1) {
            $html .= "<br /><br /> ".vmText::_('PLG_DOTPAY_REDIRECT_IMG_WAIT')."<br /><br />";
            $html .= '<script type="text/javascript">';
            $html .=    'setTimeout(function(){document.getElementsByClassName("platnosc_dotpay")[0].submit();}, 10);';
            $html .= '</script>';
        }

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

