<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.1: dotpaylogo.php 2015-10-29
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
        $src = JURI::root() . "media/images/stories/virtuemart/payment/"."dp_logo_alpha_175_50.png";
		$register_link_dotpay = '<br /><br /><p><a href="https://ssl.dotpay.pl/s2/login/registration/?affilate_id=virtuemart" target="_blank" class="btn btn-primary " title="Account registration in dotpay.pl">Zarejestruj konto w dotpay.pl</a>';
        return "<a href='http://www.dotpay.pl' target='_blank'><img src=$src /></a> $register_link_dotpay <script type='text/javascript'>jQuery(document).ready(function() {jQuery('#params_dotpay_urlc_info-lbl').parents().css('width', 'auto')});</script>";
	}
}