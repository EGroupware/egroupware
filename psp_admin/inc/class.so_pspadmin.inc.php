<?php
/**
*
*
*  
**/

class so_pspadmin
{

   var $conf_table;
   var $db;
   
   function so_pspadmin()
   {
	  define(PAYMOD_DIR, EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/payment');
	  define(CONF_TABLE, 'egw_oscadmin_osc_conf');
	  $this->db = $GLOBALS['egw']->db;
   }

   function default_settings()
   {
	  // this needs to be cleaned/stripped whatever
	  // make the modules work without these settings
	  return array(
			'FILENAME_CHECKOUT_PROCESS' => array(
			   'configuration_title' => 'FILENAME_CHECKOUT_PROCESS',			   
			   'configuration_value' => 'index.php?menuaction=confirmcheckoutfunction',
			   'configuration_description' => 'checkout process',			   
			   'use_function' => 'NULL',
			   'set_function' => 'NULL'
			),
			'STORE_NAME' => array(
			   'configuration_title' => 'Store Name',			   
			   'configuration_value'=>'INSTALL',
			   'configuration_description'=>'The name of your store',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'STORE_OWNER' => array(
			   'configuration_title'=>'Store Owner',
			   'configuration_value'=>'Fill in',
			   'configuration_description'=>'The name of my store owner',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'EMAIL_FROM' => array(
			   'configuration_title'=>'E-Mail From',			   
			   'configuration_value'=>'jataggo hosting <info@jataggo.com>',
			   'configuration_description'=>'The e-mail address used in (sent) e-mails',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'STORE_COUNTRY' => array(
			   'configuration_title'=>'Country',			   
			   'configuration_value'=>'223',
			   'configuration_description'=>'The country my store is located in <br><b>Note: Please remember to update the store zone.</b>',
			   'use_function'=>'tep_get_country_name',
			   'set_function'=>'tep_cfg_pull_down_country_list('
			),
			'STORE_ZONE' => array(
			   'configuration_title'=>'Zone',			   
			   'configuration_value'=>'18',
			   'configuration_description'=>'The zone my store is located in',
			   'use_function'=>'tep_cfg_get_zone_name',
			   'set_function'=>'tep_cfg_pull_down_zone_list('
			),
			'STORE_NAME_ADDRESS' => array(
			   'configuration_title'=>'Store Address and Phone',
			   'configuration_value'=>'Jataggo.com',			   
			   'configuration_description'=>'This is the Store Name, Address and Phone used on printable documents and displayed online',
			   'use_function'=>'NULL',
			   'set_function'=>'tep_cfg_textarea('
			),
			'DEFAULT_CURRENCY' => array(
			   'configuration_title'=>'Default Currency',			   
			   'configuration_value'=>'EUR',
			   'configuration_description'=>'Default Currency',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DEFAULT_LANGUAGE' => array(
			   'configuration_title'=>'Default Language',			   
			   'configuration_value'=>'NL',
			   'configuration_description'=>'Default Language',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DEFAULT_ORDERS_STATUS_ID' => array(
			   'configuration_title'=>'Default Order Status For New Orders',			   
			   'configuration_value'=>'1',
			   'configuration_description'=>'When a new order is created, this order status will be assigned to it.',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DIR_WS_LANGUAGES' => array(
			   'configuration_title'=>'DIR_WS_LANGUAGES',			   
			   'configuration_value'=>'inc/languages/',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DIR_WS_MODULES' => array(
			   'configuration_title'=>'DIR_WS_MODULES',			   
			   'configuration_value'=>'inc/payment_modules/',
			   'configuration_description'=>'',	 
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'JS_ERROR' => array(
			   'configuration_title'=>'JS_ERROR',			   
			   'configuration_value'=>'there was a javasript error',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'JS_ERROR_NO_PAYMENT_MODULE_SELECTED' => array(
			   'configuration_title'=>'JS_ERROR_NO_PAYMENT_MODULE_SELECTED',
			   'configuration_value'=>'No payment module selected',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'HTTPS_SERVER' => array(
			   'configuration_title'=>'HTTPS_SERVER',
			   'configuration_value'=>'http://xoo.cybro.info/web_ries/egroupware/',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DIR_WS_HTTPS_CATALOG' => array(
			   'configuration_title'=>'',			   
			   'configuration_value'=>'',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'FILENAME_CHECKOUT_PAYMENT' => array(
			   'configuration_title'=>'',			   
			   'configuration_value'=>'?menuaction=paymentError',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			),
			'DIR_WS_CLASSES' => array(
			   'configuration_title'=>'',
			   'configuration_value'=>'inc/payment_modules/',
			   'configuration_description'=>'',
			   'use_function'=>'NULL',
			   'set_function'=>'NULL'
			)
			);
		 }

		 function get_settings()
		 {
			$keys = array_keys($this->default_settings());
			$keysstr = implode('","',$keys);
			$query = 'SELECT configuration_value FROM '.CONF_TABLE.' WHERE configuration_key IN ("'.$keysstr.'");';
			$erres = $this->db->query($query);
			while ($this->db->next_record())
			{
			   $_res[] = $this->db->row();
			}
			foreach($_res as $key=>$record)
			{
			   $_vals[$keys[$key]] = $_res[$key]['configuration_value'];
			}
			return $_vals;
		 }

		 function check_tables()
		 {
			// 1.  everything OK
			// 2.  first entry in db is not MODULE_PAYMENT_INSTALLED: error
			// 3.  rows are missing: settings not ok.

			$query="SELECT configuration_key FROM ".CONF_TABLE." WHERE configuration_id = '1';";
			$this->db->query($query);
			while ($this->db->next_record())
			{
			   $_result =  $this->db->row(); 
			}
			if( $_result['configuration_key'] != 'MODULE_PAYMENT_INSTALLED' )
			{
			   return 'check_tables failed: MODPAYINSTALLED';
			}
			$mand_keys = implode('","',array_keys($this->default_settings()));
			$query = 'SELECT configuration_key FROM '.CONF_TABLE.' WHERE configuration_key IN ("'.$mand_keys.'");';
			$erres = $this->db->query($query);
			while ($this->db->next_record())
			{
			   $_res[] = $this->db->row();
			}
			if ($_res = '' || count(array_keys($this->default_settings())) != count(array_keys($_res)))
			{
			   return 'check_tables failed: empty rows';
			}
			return True;
		 }

		 function oscom_fsmodules($dir=PAYMOD_DIR)
		 {
			// loops through the given directory for possible plugins
			$_list = scandir($dir); 
			if( ($_list == FALSE) || ($_list == '') ) {
			   $list[0] = 'empty or error while processing scandir';
			   return $list;
			}
			// return only the .php entries (without .php)
			foreach($_list as $record)
			{
			   if(strpos($record,'.php')) {
				  $list[] = substr($record, 0, -4);
			   }
			}
			return $list;
		 }


		 function oscom_dbmodules()
		 {
			$query = "SELECT configuration_value FROM ".CONF_TABLE." WHERE configuration_key = 'MODULE_PAYMENT_INSTALLED'; ";
			$geti = $this->db->query($query);
			while ($this->db->next_record())
			{	
			   $row = $this->db->row();
			   if ($row['configuration_value'] != '') 
			   {
				  return explode(';',$row['configuration_value']);
				  //return explode(';',str_replace('.php','',$row['configuration_value']));
			   } else return array();
			}

		 }

		 function oscom_installmod($module)
		 {
			$module .= '.php';
			$_list= $this->oscom_dbmodules();
			if($_list == '') $_list = array($module); // nothing installed yet
			else
			{
			   // no duplicates
			   if(!array_search($module, $_list)) $_list[] = $module;
			}
			$this->update_oscommod($_list);
		 }

		 function oscom_removemod($module)
		 {
			$module .= '.php';
			$_list= $this->oscom_dbmodules();
			if(array_search($module, $_list) || $_list[0] = $module) 
			{
			   unset($_list[array_search($module, $_list)]); // remove
			}
			$this->update_oscommod($_list);
		 }
		 
		 function update_oscommod($arr)
		 {
			if(count($arr) > 1)
			{
			   $new = implode($arr, ';');
			} else $new = $arr[0];
			$query = "UPDATE egw_oscadmin_osc_conf SET configuration_value = '$new' WHERE configuration_key = 'MODULE_PAYMENT_INSTALLED';";
			$this->db->query($query);
		 }

		 function oscmod_values($config_key)
		 {
			//
			$_columns = 'configuration_title, configuration_value, configuration_description';
			$query = 'SELECT ' .$_columns. ' FROM ' .CONF_TABLE. ' WHERE configuration_key = "' .$config_key. '";';

			$this->db->query($query);
            while ($this->db->next_record())
			{	
			   $values_arr = $this->db->row();
			}
			return $values_arr;
		 }

		 /**
		 *  get_plugin_functions 
		 **/
		 function oscmod_functions($_key) 
		 {
			
			$query = 'SELECT use_function, set_function FROM ' .CONF_TABLE. ' WHERE configuration_key = "' .$_key. '";';
			$erres = $this->db->query($query);
			while ($this->db->next_record())
			{	
			   $values_arr = $this->db->row();
			   if($values_arr['use_function'] == "") $values_arr['use_function'] = "NO_FUNC";
			   if($values_arr['set_function'] == "") $values_arr['set_function'] = "NO_FUNC";
			}
			return $values_arr;
		 }

		 function confedit($key, $newvalue)
		 {
			//
			$query = "UPDATE ".CONF_TABLE." SET configuration_value = '$newvalue' WHERE configuration_key = '$key';";
			$this->db->query($query);
		 }
}




