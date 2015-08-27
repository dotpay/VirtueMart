<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.0.1: getdotpayurlc.php 2015-08-24
 * @author Dotpay SA  < tech@dotpay.pl >
 * @copyright (C) 2015 - Dotpay SA
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/


defined('JPATH_BASE') or die();

class JFormFieldGetDotpayUrlc extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'DotpayLogo';

	function getInput() {
            
                $db = JFactory::getDBO();
                $sql = 'SELECT virtuemart_paymentmethod_id FROM #__virtuemart_paymentmethods WHERE payment_element ="dotpay"';
                $db->setQuery($sql);
                if(!($plg=$db->loadObject())){
                 JError::raiseError(100,'Fatal: Plugin is not installed or your SQL server is NUTS.');
                } else {
                   $plg_id = $plg->virtuemart_paymentmethod_id;
                }
                
                        
                $urlc = JURI::root(). 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=' . $plg_id;
                
                $html = '<a href="'. $urlc .'" target="_blank">' . $urlc .'</a> ';

		return $html;


	}
}