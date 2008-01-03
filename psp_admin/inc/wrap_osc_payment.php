<?php

   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/database.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/general.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/html_output.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/payment.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/currencies.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/order.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/validations.php');
 //  require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/languages/english.php');

   class wrap_osc_payment extends payment
   {
	  var $conn;
	  var $currency;
	  var $currencies;
	  var $sav2wrapper;
	  var $base_url;
	  var $trans;

	  var $db;
	  var $wrap;

	  function wrap_osc_payment($plug='',$no_order=false)
	  {
/*
		 if($plug!='') 
		 {
			require_once(EGW_INCLUDE_ROOT."/psp_admin/inc/payment_modules/payment/$plug.php");
			eval("\$this->wrap = new $plug();");
		 }
*/
         global $conn;
		 global $currencies;
		 global $currency;
		 global $order;
		 global $HTTP_POST_VARS;
		 global $sav2wrapper;
		 global $base_url;
		 global $trans;
		 $this->trans =& $trans;
		 $this->base_url = $base_url;
		 $this->conn = $conn;
		 tep_db_connect() or die("hmmm...  tep_db_connect error in wrap_osc_payment");
		 $this->read_settings();
		 $currencies = new currencies();
		 $currency = $currencies->get_title('EUR');
		 if($plug !='' and $no_order==false)
		 {
			$order = new order(1);
		 }
		 $this->sav2wrapper = & $sav2wrapper;
		 //$this->tplsav2 = & $sav2wrapper;
		 parent::payment($plug);
		 //$this->wrap = & $this->();
	  }


	  function remove()
	  {
		 $this->wrap->remove();
		 return "removed";
	  }

	  function install()
	  {
		 $this->wrap->install();
		 return "installed";
	  }

	  function keys()
	  {
		 
		 $plugin_keys = $this->wrap->keys();
		 return $plugin_keys;
	  }
/*
	  function selection()
	  {
		 $this->db = clone($GLOBALS['egw']->db);
		 $query = "SELECT configuration_value FROM egw_oscadmin_osc_conf WHERE configuration_key = 'MODULE_PAYMENT_INSTALLED'; ";

		 $geti = $this->db->query($query);
		 while ($this->db->next_record())
		 {
			$row = $this->db->row();
			if ($row != "")
			{
			   $modarray = explode(';',str_replace('.php','',$row['configuration_value']));
			   foreach($modarray as $key=>$mod)
			   {
				  $_ret[$key]['module']= $mod;
				  $_ret[$key]['id'] = $mod;
			   }
			   return $_ret;
			} else 
			return array('');

		 }

	  }
*/	  
	  function getName()
	  {
		 return $GLOBALS[$this->selected_module]->title;
		 }
	  
	  function read_settings()
	  {
		 // set the application parameters
		 $configuration_query = tep_db_query('select configuration_key as cfgKey, configuration_value as cfgValue from ' . TABLE_CONFIGURATION);
		 while ($configuration = tep_db_fetch_array($configuration_query))
		 {
			define($configuration['cfgKey'], $configuration['cfgValue']);
		 }
	  }

	  function convert_order_to_osc()
	  {
		 $GLOBALS['order'] = new order(1);
	  }

	  function get_installedplugs()
	  {
		 die ('get_installedplugs in wrap_osc_payment');
		 return $_result;
	  }

   }
?>
