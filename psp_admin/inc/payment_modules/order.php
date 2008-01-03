<?php
   /*
   $Id: order.php,v 1.7 2003/06/20 16:23:08 hpdl Exp $

   osCommerce, Open Source E-Commerce Solutions
   http://www.oscommerce.com

   Copyright (c) 2003 osCommerce

   Released under the GNU General Public License
   */

   class order {
	  var $info, $totals, $products, $customer, $delivery;

	  function order($order_id) {
		 $this->info = array();
		 $this->totals = array();
		 $this->products = array();
		 $this->customer = array();
		 $this->delivery = array();

		 $this->insert_cybro_values();
	  }

	  function insert_cybro_values()
	  {
		 $bo = CreateObject('psp_admin.bo_oscadminapi');
		 #_debug_array($bo->socreditspoint->sessiondata);
		 $ses = $bo->so_oscadminapi->sessiondata;
		 #_debug_array($bo->socreditspoint->config->config_data[cost_one_credit]);
		 #die();
		 $this->customer = array
		 (
			'name' => $ses[step2][n_givven].''.$ses[step2][n_middle].''.$ses[step2][n_family],
			'company' => '',
			'street_address' => $ses[step2][adr_one_street],
			'suburb' => '',
			'city' => $ses[step2][adr_one_locality],
			'postcode' => $ses[step2][adr_one_postalcode],
			'state' => '',
			'country' => array(
			   'id'=>150 , 
			   'Name'=>'Netherlands',
			   'countries_iso_code_2'=>'NL',
			   'countries_iso_code_3'=>'NLD',
			   'address_format_id'=>3
			),
			'format_id' => '',//$order['customers_address_format_id'],
			'telephone' => $ses[step2][tel_work],
			'email_address' => $ses[step2][email],
			'zone_id'=>18

		 );

		 $this->delivery = $this->billing = $this->customer;

		 $this->info = array
		 (
			'order_status'=>1,
			'currency' => 'EURO',//$order['currency'],
			'currency_value' =>'1',// $order['currency_value'],
			'payment_method' => $ses[stap3]['payment'],//$order['payment_method'],
			'cc_type' => '',//$order['cc_type'],
			'cc_owner' => $ses[stap3][cc_owner],//$order['cc_owner'],
			'cc_number' => $ses[stap3][cc_number],//$order['cc_number'],
			'cc_expires' => $ses[stap3][cc_expires_month].'/'.$ses[stap3][cc_expires_year],//$order['cc_expires'],
			'date_purchased' => date('d-m-Y'),//$order['date_purchased'],
			'orders_status' => '1',//$order['orders_status'],
			'last_modified' => '',//$order['last_modified'],
			'tax'=> 0,
			'subtotal'=>(intval($ses[step2][amount])*floatval($bo->so_pspadmin->config->config_data[cost_one_credit])),
			'total'=>((intval($ses[step2][amount])*floatval($bo->so_pspadmin->config->config_data[cost_one_credit]))*1.19)

		 );
		 $index = 0;
		 $this->products[0] = array
		 (
			'qty' => $ses[step2][amount],
			'name' => lang('Credits'),
			'model' => '',
			'tax' => '19',
			'price' => $bo->so_pspadmin->config->config_data[cost_one_credit],
			'final_price' => (intval($ses[step2][amount])*floatval($bo->so_pspadmin->config->config_data[cost_one_credit]))
		 );

	  }
	  function query($order_id) {
		 $order_query = tep_db_query("select customers_name, customers_company, customers_street_address, customers_suburb, customers_city, customers_postcode, customers_state, customers_country, customers_telephone, customers_email_address, customers_address_format_id, delivery_name, delivery_company, delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country, delivery_address_format_id, billing_name, billing_company, billing_street_address, billing_suburb, billing_city, billing_postcode, billing_state, billing_country, billing_address_format_id, payment_method, cc_type, cc_owner, cc_number, cc_expires, currency, currency_value, date_purchased, orders_status, last_modified from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
		 $order = tep_db_fetch_array($order_query);

		 $totals_query = tep_db_query("select title, text from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' order by sort_order");
		 while ($totals = tep_db_fetch_array($totals_query)) 
		 {
			$this->totals[] = array('title' => $totals['title'],
			'text' => $totals['text']);
		 }

		 $this->info = array('currency' => $order['currency'],
		 'currency_value' => $order['currency_value'],
		 'payment_method' => $order['payment_method'],
		 'cc_type' => $order['cc_type'],
		 'cc_owner' => $order['cc_owner'],
		 'cc_number' => $order['cc_number'],
		 'cc_expires' => $order['cc_expires'],
		 'date_purchased' => $order['date_purchased'],
		 'orders_status' => $order['orders_status'],
		 'last_modified' => $order['last_modified']);

		 $this->customer = array('name' => $order['customers_name'],
		 'company' => $order['customers_company'],
		 'street_address' => $order['customers_street_address'],
		 'suburb' => $order['customers_suburb'],
		 'city' => $order['customers_city'],
		 'postcode' => $order['customers_postcode'],
		 'state' => $order['customers_state'],
		 'country' => $order['customers_country'],
		 'format_id' => $order['customers_address_format_id'],
		 'telephone' => $order['customers_telephone'],
		 'email_address' => $order['customers_email_address']);

		 $this->delivery = array('name' => $order['delivery_name'],
		 'company' => $order['delivery_company'],
		 'street_address' => $order['delivery_street_address'],
		 'suburb' => $order['delivery_suburb'],
		 'city' => $order['delivery_city'],
		 'postcode' => $order['delivery_postcode'],
		 'state' => $order['delivery_state'],
		 'country' => $order['delivery_country'],
		 'format_id' => $order['delivery_address_format_id']);

		 $this->billing = array('name' => $order['billing_name'],
		 'company' => $order['billing_company'],
		 'street_address' => $order['billing_street_address'],
		 'suburb' => $order['billing_suburb'],
		 'city' => $order['billing_city'],
		 'postcode' => $order['billing_postcode'],
		 'state' => $order['billing_state'],
		 'country' => $order['billing_country'],
		 'format_id' => $order['billing_address_format_id']);

		 $index = 0;
		 $orders_products_query = tep_db_query("select orders_products_id, products_name, products_model, products_price, products_tax, products_quantity, final_price from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
		 while ($orders_products = tep_db_fetch_array($orders_products_query)) {
			$this->products[$index] = array(
			   'qty' => $orders_products['products_quantity'],
			   'name' => $orders_products['products_name'],
			   'model' => $orders_products['products_model'],
			   'tax' => $orders_products['products_tax'],
			   'price' => $orders_products['products_price'],
			   'final_price' => $orders_products['final_price']);

			   $subindex = 0;
			   $attributes_query = tep_db_query("select products_options, products_options_values, options_values_price, price_prefix from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "' and orders_products_id = '" . (int)$orders_products['orders_products_id'] . "'");
			   if (tep_db_num_rows($attributes_query)) {
				  while ($attributes = tep_db_fetch_array($attributes_query)) {
					 $this->products[$index]['attributes'][$subindex] = array(
						'option' => $attributes['products_options'],
						'value' => $attributes['products_options_values'],
						'prefix' => $attributes['price_prefix'],
						'price' => $attributes['options_values_price']);

						$subindex++;
					 }
				  }
				  $index++;
			   }
			}
		 }
	  ?>
