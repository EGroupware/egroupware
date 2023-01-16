<?php
/**
 * EGroupware API - Framework extra
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api\Link;
use EGroupware\Api\Json;

/**
 * Framework extra - handling of server-responses either send via data attribute
 */
abstract class Extra
{
	/**
	 * Extra values send as data attributes to script tag of egw.js
	 *
	 * @var array
	 */
	protected static $extra = array();

	/**
	 * Refresh given application $targetapp display of entry $app $id, incl. outputting $msg
	 *
	 * Calling egw_refresh and egw_message on opener in a content security save way
	 *
	 * To provide more information about necessary refresh an automatic 9th parameter is added
	 * containing an object with application-name as attributes containing an array of linked ids
	 * (adding happens in get_extras to give apps time to link new entries!).
	 *
	 * @param string $msg message (already translated) to show, eg. 'Entry deleted'
	 * @param string $app application name
	 * @param string|int $id =null id of entry to refresh
	 * @param string $type =null either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.
	 *	Sorting and filtering are not considered, so if the sort field is changed,
	 *	the row will not be moved.  If the current filtering could include or exclude
	 *	the record, use edit.
	 * - edit: rows changed, but sorting or filtering may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * - null: full reload
	 * @param string $targetapp =null which app's window should be refreshed, default current
	 * @param string $replace =null regular expression to replace in url
	 * @param string $with =null
	 * @param string $msg_type =null 'error', 'warning' or 'success' (default)
	 */
	public static function refresh_opener($msg, $app, $id=null, $type=null, $targetapp=null, $replace=null, $with=null, $msg_type=null)
	{
		// if we have real push available and a regular single-entry refresh of a push supporting app, no need to refresh
		if (!Json\Push::onlyFallback() &&
			!empty($type) && !empty($id) &&	// $type === null --> full reload
			Link::get_registry($app, 'push_data'))
		{
			$app = 'msg-only-push-refresh';
		}
		//error_log(__METHOD__.'('.array2string(func_get_args()).')');
		self::$extra['refresh-opener'] = func_get_args();

		unset($msg, $app, $id, $type, $targetapp, $replace, $with, $msg_type);	// used only via func_get_args();
	}

	/**
	 * Display an error or regular message
	 *
	 * Calls egw_message on client-side in a content security save way
	 *
	 * @param string $msg message to show
	 * @param string $type ='success' 'error', 'warning' or 'success' (default)
	 */
	public static function message($msg, $type='success')
	{
		self::$extra['message'] = func_get_args();

		unset($msg, $type);	// used only via func_get_args();
	}

	/**
	 * Open a popup independent if we run as json or regular request
	 *
	 * @param string $link
	 * @param string $target
	 * @param string $popup
	 */
	public static function popup($link, $target='_blank', $popup='640x480')
	{
		// default params are not returned by func_get_args!
		$args = func_get_args()+array(null, '_blank', '640x480');

		unset($link, $target, $popup);	// used only via func_get_args()

		if (Json\Request::isJSONRequest())
		{
			Json\Response::get()->apply('egw.open_link', $args);
		}
		else
		{
			self::$extra['popup'] = $args;
		}
	}

	/**
	 * Close (popup) window, use to replace egw_framework::onload('window.close()') in a content security save way
	 *
	 * @param string $alert_msg ='' optional message to display as alert, before closing the window
	 */
	public static function window_close($alert_msg='')
	{
		//error_log(__METHOD__."()");
		self::$extra['window-close'] = $alert_msg ? $alert_msg : true;

		// are we in ajax_process_content -> just return extra data, with close instructions
		if (preg_match('/(Etemplate::ajax_process_content|(::|\.)et2_process)$/', $_GET['menuaction']))
		{
			$response = Json\Response::get();
			$response->generic('et2_load', self::get_extra());
		}
		else
		{
			$GLOBALS['egw']->framework->render('', false, false);
		}
		// run egw destructor now explicit, in case a (notification) email is send via Egw::on_shutdown(),
		// as stream-wrappers used by Horde Smtp fail when PHP is already in destruction
		$GLOBALS['egw']->__destruct();
		exit;
	}

	/**
	 * Close (popup) window, use to replace egw_framework::onload('window.close()') in a content security save way
	 */
	public static function window_focus()
	{
		//error_log(__METHOD__."()");
		self::$extra['window-focus'] = true;
	}

	/**
	 * Allow app to store arbitray values in egw script tag
	 *
	 * Attribute name will be "data-$app-$name" and value will be json serialized, if not scalar.
	 *
	 * @param string $app
	 * @param string $name
	 * @param mixed $value
	 */
	public static function set_extra($app, $name, $value)
	{
		self::$extra[$app.'-'.$name] = $value;
	}

	/**
	 * Clear all extra data
	 */
	public static function clear_extra()
	{
		self::$extra = array();
	}

	/**
	 * Allow eg. ajax to query content set via refresh_opener or window_close
	 *
	 * @return array content of egw_framework::$extra
	 */
	public static function get_extra()
	{
		// adding links of refreshed entry, to give others apps more information about necessity to refresh
		if (isset(self::$extra['refresh-opener']) && count(self::$extra['refresh-opener']) <= 8 &&	// do not run twice
			!empty(self::$extra['refresh-opener'][1]) && !empty(self::$extra['refresh-opener'][2]))	// app/id given
		{
			$links = Link::get_links(self::$extra['refresh-opener'][1], self::$extra['refresh-opener'][2]);
			$apps = array();
			foreach($links as $link)
			{
				$apps[$link['app']][] = $link['id'];
			}
			while (count(self::$extra['refresh-opener']) < 8)
			{
				self::$extra['refresh-opener'][] = null;
			}
			self::$extra['refresh-opener'][] = $apps;
		}
		return self::$extra;
	}
}