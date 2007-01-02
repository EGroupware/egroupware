<?php
   /**
   * eGW idots template ajax server
   * 
   * @link http://www.egroupware.org
   * @author Pim Snel <pim@lingewoud.nl> author of the idots template set
   * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
   * @package api
   * @subpackage framework
   * @access public
   * @abstract script which is called by idots template set to store prefs using AJAX
   * @version $Id$
   */

   if(!$_GET['currentapp'])
   {
	  $_GET['currentapp']='preferences';
   }

   $egw_flags = Array(
	  'currentapp'	=>	$_GET['currentapp'],
	  'noheader'	=>	True,
	  'nonavbar'	=>	True,
	  'noappheader'	=>	True,
	  'noappfooter'	=>	True,
	  'nofooter'	=>	True
   );

   $GLOBALS['egw_info']['flags'] = $egw_flags;

   require('../../../header.inc.php');
   require_once(EGW_API_INC.'/xajax.inc.php');
   $xajax = new xajax($GLOBALS['egw_info']['server']['webserver_url']."/phpgwapi/templates/idots/ajaxStorePrefs.php");
   $xajax->registerFunction("storeEGWPref");

   /**
    * storeEGWPref 
    * 
	* @param mixed $repository egroupware preferences repository
    * @param mixed $key key to preference
	* @param mixed $value new value
    * @access public
	* @return mixed returns null when no erro, else return error message.
    */
   function storeEGWPref($repository,$key,$value)
   {
	  $objResponse = new xajaxResponse();
	  $GLOBALS['egw']->preferences->read_repository();
	  $GLOBALS['egw']->preferences->change($repository,$key,$value);
	  $GLOBALS['egw']->preferences->save_repository(True);
	  return $objResponse;
   }

   $xajax->processRequests();
?>
