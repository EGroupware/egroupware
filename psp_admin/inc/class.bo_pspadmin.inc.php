<?php
/**
*
*
**/

class bo_pspadmin
{
   var $so;

   function bo_pspadmin()
   {
	  $this->so = CreateObject('psp_admin.so_pspadmin');
   }

   function get_installed_modules($wrapper)
   {
	  switch($wrapper)
	  {
		 case 'oscommerce':
			$fsplugins = $this->so->oscom_fsmodules();
			$dbplugins = $this->so->oscom_dbmodules();

			foreach($dbplugins as $key=>$plugin)
			{
			   $dbplugins[$key] = substr("$plugin",0,strpos($plugin,".php"));
			   }

			if ($dbplugins=='') $dbplugins = array();
			if ($fsplugins=='') $fsplugins = array();
			foreach($fsplugins as $record) 
			{
			   if(in_array($record, $dbplugins)) 
			   {
				  $_ret[] = array($record,'installed');
			   } else $_ret[] = array($record, 'not_yet');
			}

			foreach($dbplugins as $record)
			{
			   if(!in_array($record, $fsplugins)) 
			   {
				  if($record != '') $_ret[] = array($record, 'broken');
			   }
			}
			break;
		 default:
			$_ret = 'not yet.';
			break;
		 }
	  return $_ret;
   }

   function oscom_install($module)
   {
	  require_once(EGW_INCLUDE_ROOT."/psp_admin/inc/wrap_osc_plugin.php");
	  $osc_wrap = new wrap_osc_plugin($module);
	  $debugmess = $osc_wrap->install();
		 // todo: if and only if it really happened 
		 // insert it into the PAYMODINSTALLED list
	  $this->so->oscom_installmod($module);
	  return $module.' module installed.';
   }

   function oscom_remove($module)
   {
	  require_once(EGW_INCLUDE_ROOT."/psp_admin/inc/wrap_osc_plugin.php");
	  $osc_wrap = new wrap_osc_plugin($module);
	  $debugmess = $osc_wrap->remove();
		 // todo: if and only if it really happened 
		 // remove it from the PAYMODINSTALLED list
	  $this->so->oscom_removemod($module);
	  return $module.' module removed.';
   }

   function oscom_broken($module)
   {
	  return 'error.';
   }

   function config_module($module)
   {
	  // form based on keys, values and types of plugin
	  // 
	  require_once(EGW_INCLUDE_ROOT."/psp_admin/inc/wrap_osc_plugin.php");
	  $osc_wrap = new wrap_osc_plugin($module);
	  // get the keys and their values
	  $plugin_keys = $osc_wrap->keys();

	  foreach($plugin_keys as $record)
	  {
		 $plugin_values[] = $this->so->oscmod_values($record);

		 $plugin_functions[$record] = $this->so->oscmod_functions($record);
	  }
	  // create displaybox 2d-array pluginkey[title, value, description]
	  foreach($plugin_keys as $key=>$record)
	  { 
		 //$display[$record] = array_values($plugin_values[$key]);   
		 $display[$record] = $plugin_values[$key];   
	  }
	  return array($display, $plugin_functions);
   }

}
