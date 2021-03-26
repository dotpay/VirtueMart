<?php
/**
 * @package Dotpay Payment Plugin module for VirtueMart v3 for Joomla! 3.4
 * @version $1.2: dotpaylogo.php 2021-03-26
 * @author Dotpay sp. z o.o.  < tech@dotpay.pl >
 * @copyright (C) 2021 - Dotpay sp. z o.o.
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
        $src = JURI::root() . "plugins/vmpayment/dotpay/"."dp_logo_alpha_175_50.png";
		$register_link_dotpay = '';
        return "<a href='http://www.dotpay.pl' target='_blank'><img src=$src /></a> $register_link_dotpay 
		<script type='text/javascript'>jQuery(document).ready(function() {jQuery('#params_dotpay_urlc_info-lbl').parents().css({'width': 'auto', 'max-width': '1000px','padding': '5px'}); jQuery('input#params_dotpay_id').attr('pattern', '[0-9]{6}');jQuery('input#params_dotpay_id').attr('maxlength', '6'); 
		jQuery('input#params_dotpay_id').bind('keyup paste keydown', function(e) {
			if (/\D/g.test(this.value)) {
			  // Filter non-digits from input value.
			  this.value = this.value.replace(/\D/g, '');
			}
		  });
		  jQuery('input#params_dotpay_pin').bind('keyup paste keydown', function(e) {
			  jQuery(this).val(function(_, v){
				  return v.replace(/\s+/g, '');
			  });
		  });
		  function checIfLangPL() {
			var doclang = document.documentElement.lang;
			var res = doclang.toLowerCase();
			var n = res.search('pl');
		  if(n >= 0 ) {
			return true;
		  }else{
			return false;
		  }	  
	   }
	//check if is polish language in admin panel
	if(checIfLangPL() === true) {
	var dp_pincheck = 'PIN znajdziesz w panelu Dotpay. Pin składa się przynajmniej z 16 a maksymalnie z 32 znaków alfanumerycznych!';
	var dp_allowed = 'Dozwolone tylko cyfry (6 cyfr)';
	var dp_exampleid = 'np. 123456';
	var dp_costtr ='np. 2.00';
	}else {
	var dp_pincheck = 'You will find the PIN in the Dotpay panel. The PIN consists of at least 16 and a maximum of 32 alphanumeric characters!';
	var dp_allowed = 'Only digits allowed (6 digits)';
	var dp_exampleid = 'e.g. 123456';
	var dp_costtr ='e.g. 2.00';
	}

	jQuery('input#params_dotpay_id').prop('placeholder', dp_exampleid );
	jQuery('input#params_dotpay_id').attr('title', dp_allowed);
	jQuery('input#params_dotpay_pin').attr('minlength', '16');
	jQuery('input#params_dotpay_pin').attr('pattern', '[a-zA-Z0-9]{16,32}');
	jQuery('input#params_dotpay_pin').attr('title', dp_pincheck);

	jQuery('input#params_cost_per_transaction').attr('maxlength', '8');
    jQuery('input#params_cost_per_transaction').attr('pattern', '(^[0-9]{0,5}$)|(^[0-9]{0,5}\.[0-9]{0,2}$)');
    jQuery('input#params_cost_per_transaction').attr('title', dp_costtr);

		jQuery('input#params_cost_per_transaction').keyup(function () {     
			this.value = this.value.replace(/[^0-9\.]/g,'');
		});

	jQuery('input#params_cost_percent_total').attr('maxlength', '8');
    jQuery('input#params_cost_percent_total').attr('pattern', '(^[0-9]{0,5}$)|(^[0-9]{0,5}\.[0-9]{0,2}$)');
    jQuery('input#params_cost_percent_total').attr('title', dp_costtr);

		jQuery('input#params_cost_percent_total').keyup(function () {     
			this.value = this.value.replace(/[^0-9\.]/g,'');
		});

	jQuery('<em> %</em>').insertAfter('input#params_cost_percent_total');


	});</script>";
	}
}
