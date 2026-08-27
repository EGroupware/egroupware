<?php
/**
 * EGroupware eTemplate2 - History log server-side
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * eTemplate history log widget displays a list of changes to the current record.
 * The widget is encapsulated, and only needs the record's ID, and a map of
 * fields:widgets for display
 */
class HistoryLog extends Etemplate\Widget
{

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * Historylog need to use $this->id as namespace, otherwise we overwrite
	 * sel_options of regular widgets with more general data for historylog!
	 *
	 * eg. owner in addressbook.edit contains only accounts user has rights to,
	 * while it uses select-account for owner in historylog (containing all users).
	 *
	 * @param string $cname
	*/
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);

		// Historylog is a reusable, self-configuring widget: unlike nextmatch,
		// whose callers each set content[form_name]['get_rows'] themselves,
		// no app sets one for us. Seed the trusted default/override here, server-side, so
		// Nextmatch::ajax_get_rows()'s generic content-vs-client-filters merge picks
		// it up without needing to know about this widget type.
		// $this->attrs['get_rows'] is safe to trust: attrs are populated only from
		// the .xet template XML parsed server-side (see Widget::__construct()),
		// never from request/session data a client could influence.
		$value =& self::get_array(self::$request->content, $form_name, true);
		$value['get_rows'] = $this->attrs['get_rows'] ?? Api\Storage\History::class.'::get_rows';

		if(is_array(self::$request->content[$form_name]['status-widgets']))
		{
			foreach(self::$request->content[$form_name]['status-widgets'] as $key => $type)
			{
				if (is_array($type) && count($type) === 1 && isset($type[0]))
				{
					$type = $type[0];
				}
				if(!is_array($type))
				{
					list($basetype) = explode('-',$type);
					list($tag) = explode(':', $type);	// xml tags must not include undeclared namespaces like: <link-entry:infolog
					$widget = @self::factory($basetype, '<?xml version="1.0"?><'.$tag.' type="'.$type.'"/>', $key);
					$widget->id = $key;
					$widget->attrs['type'] = $type;
					$widget->type = $type;

					if(method_exists($widget, 'beforeSendToClient'))
					{
						// need to use $form_name as $cname see comment in header
						$widget->beforeSendToClient($form_name, array());
					}
				}
				else
				{
					// need to use self::form_name($form_name, $key) as index into sel_options see comment in header
					$options =& self::get_array(self::$request->sel_options, self::form_name($form_name, $key), true);
					if (!is_array($options)) $options = array();
					$options += $type;
				}

			}
		}
	}

	/**
	 * Validate input
	 *
	 * For dates (except duration), it is always a full timestamp in W3C format,
	 * which we then convert to the format the application is expecting.  This can
	 * be either a unix timestamp, just a date, just time, or whatever is
	 * specified in the template.
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array($content, $form_name);
		$valid =& self::get_array($validated, $form_name, true);
		if (true) $valid = $value;
		return true;
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\HistoryLog', 'historylog');