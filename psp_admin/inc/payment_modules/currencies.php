<?php
/*
  $Id: currencies.php,v 1.3 2003/06/20 16:23:08 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

////
// Class to handle currencies
// TABLES: currencies
   class currencies {
    var $currencies;

// class constructor
    function currencies() {
      $this->currencies = array();
		 $this->currencies['EUR'] = array('title' => 'EURO',
                                                       'symbol_left' => '&euro;',
                                                       'symbol_right' => 'EURO',
													   'decimal_point' => ',',
                                                       'thousands_point' => '.',
                                                       'decimal_places' => 2,
                                                       'value' => 1);
    }

// class methods
    function format($number, $calculate_currency_value = false, $currency_type = DEFAULT_CURRENCY, $currency_value = '') {
      if ($calculate_currency_value) {
        $rate = ($currency_value) ? $currency_value : $this->currencies[$currency_type]['value'];
        $format_string = $this->currencies[$currency_type]['symbol_left'] . number_format($number * $rate, $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
// if the selected currency is in the european euro-conversion and the default currency is euro,
// the currency will displayed in the national currency and euro currency
        if ( (DEFAULT_CURRENCY == 'EUR') && ($currency_type == 'DEM' || $currency_type == 'BEF' || $currency_type == 'LUF' || $currency_type == 'ESP' || $currency_type == 'FRF' || $currency_type == 'IEP' || $currency_type == 'ITL' || $currency_type == 'NLG' || $currency_type == 'ATS' || $currency_type == 'PTE' || $currency_type == 'FIM' || $currency_type == 'GRD') ) {
          $format_string .= ' <small>[' . $this->format($number, true, 'EUR') . ']</small>';
        }
      } else {
        $format_string = $this->currencies[$currency_type]['symbol_left'] . number_format($number, $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
      }

      return $format_string;
    }
	function is_set($code) {
	   if (isset($this->currencies[$code]) && tep_not_null($this->currencies[$code])) {
		  return true;
	   } else {
		  return false;
	   }
	}

	function get_value($code) {
	   return $this->currencies[$code]['value'];
	}
	function get_title($code)
	{
	   return $this->currencies[$code]['title'];
	   }

	function get_decimal_places($code) {
	   return $this->currencies[$code]['decimal_places'];
	}
	
    function display_price($products_price, $products_tax, $quantity = 1) {
      return $this->format(tep_add_tax($products_price, $products_tax) * $quantity);
    }
  }
?>
