<?php
/**
 * EGroupware API: JSON - Deprecated legacy xajax wrapper functions
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/**
 * Deprecated legacy xajax wrapper functions for the new egw_json interface
 *
 * @deprecated use Api\Json\Response methods
 */
class xajaxResponse
{
	public function __call($name, $args)
	{
		if (substr($name, 0, 3) == 'add')
		{
			$name = substr($name, 3);
			$name[0] = strtolower($name[0]);
		}
		return call_user_func_array(array(egw_json_response::get(), $name), $args);
	}

	public function addScriptCall()
	{
		$args = func_get_args();
		$func = array_shift($args);

		return call_user_func(array(egw_json_response::get(), 'apply'), $func, $args);
	}

	public function getXML()
	{
		return '';
	}
}
