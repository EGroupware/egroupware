<?php

/**
*  WRAPPER FOR OSC-PLUGINS
**/


   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/database.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/general.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/html_output.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/payment.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/currencies.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/order.php');
   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/payment_modules/validations.php');
//   require_once(EGW_INCLUDE_ROOT.'/psp_admin/inc/languages/english.php');

   class wrap_osc_plugin extends payment
   {

	  var $db;
	  var $wrap;

	  function wrap_osc_plugin($plug='')
	  {
		 if($plug!='') 
		 {
			require_once(EGW_INCLUDE_ROOT."/psp_admin/inc/payment_modules/payment/$plug.php");
			eval("\$this->wrap = new $plug();");
		 }
		 tep_db_connect() or die("hmmm...  tep_db_connect error in wrap_osc_plugin");
	  }


	  function remove()
	  {
		 $this->wrap->remove(); 
	  }

	  function install()
	  {
		 $this->wrap->install(); 
	  }

	  function keys()
	  {
		 $plugin_keys = $this->wrap->keys();
		 return $plugin_keys;
	  }
   }
