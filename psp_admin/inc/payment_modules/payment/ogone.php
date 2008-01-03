<?php
/**
 *  Ogone Payment Module
 *
 *  osCommerce, Open Source E-Commerce Solutions
 *  http://www.oscommerce.com
 *
 *  Copyright (c) 2002 osCommerce
 *
 *  Released under the GNU General Public License
 *
 *  $Id: ogone.php 3099 2007-03-19 22:49:34Z dlorch $
 */

class ogone {
  var $code, $title, $description, $enabled;

  function ogone() {
    $this->code            = 'ogone';
    $this->title           = MODULE_PAYMENT_OGONE_TEXT_TITLE;
    $this->description     = MODULE_PAYMENT_OGONE_TEXT_DESCRIPTION;
    $this->enabled         = MODULE_PAYMENT_OGONE_STATUS;
    $this->form_action_url = 'https://secure.ogone.com/ncol/' . MODULE_PAYMENT_OGONE_MODE . '/orderstandard.asp';
  }

  function javascript_validation() {
    return true;
  }

  function selection() {
    return array('id' => $this->code, 'module' => $this->title);
  }

  function pre_confirmation_check() {
    return false;
  }

  function confirmation() {
    return false;
  }

  /* For a detailled spec on these fields for ogone see https://secure.ogone.com/ncol/test/admin_ogone.asp */
  function process_button() {
    global $customer_id, $order, $currencies;

    $ogone_orderID = $customer_id . date('YmdHis');
    $ogone_amount  = number_format($order->info['total'] * 100 * $order->info['currency_value'], 0, '', '');

    $process_button_string = "\n" .
                             tep_draw_hidden_field('orderID', $ogone_orderID) . "\n" .
                             tep_draw_hidden_field('pspid', MODULE_PAYMENT_OGONE_PSPID) . "\n" .
                             tep_draw_hidden_field('RL', 'ncol-2.0') . "\n" .
                             tep_draw_hidden_field('currency', $order->info['currency']) . "\n" .
                             tep_draw_hidden_field('language', MODULE_PAYMENT_OGONE_LANGUAGE) . "\n" .
                             tep_draw_hidden_field('amount', $ogone_amount) . "\n" .
                             tep_draw_hidden_field('TITLE', STORE_NAME . ': ' . MODULE_PAYMENT_OGONE_TITLE_OGONE) . "\n" .
                             tep_draw_hidden_field('accepturl', tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')) . "\n" .
                             tep_draw_hidden_field('declineurl', tep_href_link(FILENAME_SHOPPING_CART)) . "\n" .
                             tep_draw_hidden_field('exceptionurl', tep_href_link(FILENAME_SHOPPING_CART)) . "\n" .
                             tep_draw_hidden_field('cancelurl', tep_href_link(FILENAME_SHOPPING_CART)) . "\n" .
                             tep_draw_hidden_field('CN', $order->customer['firstname'] . ' ' . $order->customer['lastname']) . "\n" .
                             tep_draw_hidden_field('catalogurl', tep_href_link(FILENAME_DEFAULT)) . "\n" .
                             tep_draw_hidden_field('owneraddress', $order->delivery['street_address']) . "\n" .
                             tep_draw_hidden_field('ownerZIP', $order->delivery['postcode']) . "\n" .
                             tep_draw_hidden_field('COM', MODULE_PAYMENT_OGONE_TITLE_OGONE) . "\n" .
                             tep_draw_hidden_field('email', $order->customer['email_address']) . "\n";

    $sign = $ogone_orderID . $ogone_amount . $order->info['currency'] . MODULE_PAYMENT_OGONE_PSPID . MODULE_PAYMENT_OGONE_SHA_STRING;
    $process_button_string .= tep_draw_hidden_field('SHASign', sha1($sign)) . "\n";
    
    if(MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE == 'Yes') {
      $process_button_string .= tep_draw_hidden_field('TP', MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE_URL) . "\n";
    }

    return $process_button_string;
  }

  function before_process() {
    return false;
  }

  function after_process() {
    return false;
  }

  function output_error() {
    return false;
  }

  function check() {
    if (!isset($this->check)) {
      $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_OGONE_STATUS'");
      $this->check = tep_db_num_rows($check_query);
    }
    return $this->check;
  }

  function install() {
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Allow OGONE Payments', 'MODULE_PAYMENT_OGONE_STATUS', 'True', 'Do you want to accept OGONE payments?', '6', '20', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('OGONE Status Mode', 'MODULE_PAYMENT_OGONE_MODE', 'test', 'Status mode for OGONE payments?', '6', '21', 'tep_cfg_select_option(array(\'test\', \'prod\'), ', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('OGONE Merchant ID', 'MODULE_PAYMENT_OGONE_PSPID', 'TESTSTD', 'Merchant NCOL ID', '6', '22', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('OGONE Client Language', 'MODULE_PAYMENT_OGONE_LANGUAGE', 'en_US', 'Client language', '6', '23', 'tep_cfg_pull_down_ogone_language(', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('OGONE SHA String', 'MODULE_PAYMENT_OGONE_SHA_STRING', '', 'SHA string used for the signature (set at the merchant administration page)', '6', '24', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('OGONE Dynamic Template', 'MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE', 'No', 'Use dynamic template for payment form?', '6', '25', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ',now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('OGONE Dynamic Template URL', 'MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE_URL', ' http://www.ogone.com/ncol/template_standard.htm', 'Change the appearance of the payment form', '6', '25', now())");
  }

  function remove() {
    tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . join($this->keys(), "','") . "')");
  }

  function keys() {
    return array(
      'MODULE_PAYMENT_OGONE_STATUS',
      'MODULE_PAYMENT_OGONE_MODE',
      'MODULE_PAYMENT_OGONE_PSPID',
      'MODULE_PAYMENT_OGONE_LANGUAGE',
      'MODULE_PAYMENT_OGONE_SHA_STRING',
      'MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE',
      'MODULE_PAYMENT_OGONE_DYNAMIC_TEMPLATE_URL'
    );
  }
}

function tep_cfg_pull_down_ogone_language($language_id, $configuration_key = NULL) {
  $name = isset($configuration_key) ? 'configuration[' . $configuration_key . ']' : 'configuration_value';

  /* languages supported by Ogone */
  $languages = array(
    'en_US' => 'English',
    'fr_FR' => 'French',
    'nl_NL' => 'Dutch',
    'it_IT' => 'Italian',
    'de_DE' => 'German',
    'es_ES' => 'Spanish',
    'no_NO' => 'Norvegian'
  );

  $languages_array = array();

  foreach($languages as $id => $text) {
    $languages_array[] = array('id' => $id, 'text' => $text);
  }

  return tep_draw_pull_down_menu($name, $languages_array, $language_id);
}

?>
