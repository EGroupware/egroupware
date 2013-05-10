<?php
/**
 * EGroupware - set presonal preferences via ajax
 *
 * @package preferences
 * @copyright Lingewoud B.V.
 * @link http://www.egroupware.org
 * @author Pim Snel <pim-AT-lingewoud-DOT-nl>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Script which is called to store prefs using AJAX
 */
class ajaxpreferences
{
	/**
	 * storeEGWPref
	 *
	 * @param mixed $appname appname
	 * @param mixed $key name of preference
	 * @param mixed $value new value
	 * @access public
	 * @return mixed returns null when no erro, else return error message.
	 */
	function storeEGWPref($appname,$key,$value)
	{
		$response = new xajaxResponse();
		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->add($appname,$key,$value);
		$GLOBALS['egw']->preferences->save_repository(True);
		//$response->addAlert(__METHOD__."('$appname','$key','$value')");
		return $response->getXML();
	}
}
