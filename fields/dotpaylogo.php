<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.0.1: dotpaylogo.php 2015-08-24
 * @author Dotpay SA  < tech@dotpay.pl >
 * @copyright (C) 2015 - Dotpay SA
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/


defined('JPATH_BASE') or die();

class JFormFieldDotpayLogo extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'DotpayLogo';

	function getInput() {
		
                $logo = '<style> .control-group:has(> label.params_dotpay_urlc_info-lbl) { width: auto} .ui-tooltip {width:60%} .ui-tooltip-content {background-color: #ffffca} .ui-widget-content {background: #ffffca} .control-field{ padding: 10px;} </style><a href="http://www.dotpay.pl" target="_blank"><img src="/media/images/stories/virtuemart/payment/dp_logo_alpha_175_50.png" /></a>  
                         <script type="text/javascript">jQuery(document).ready(function() {jQuery("#params_dotpay_urlc_info-lbl").parents().css("width", "auto")});</script>';
		return $logo;


	}
}