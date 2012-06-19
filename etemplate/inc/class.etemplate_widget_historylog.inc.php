<?php
/**
 * eGroupWare eTemplate2 - History log server-side
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate history log widget displays a list of changes to the current record.
 * The widget is encapsulated, and only needs the record's ID, and a map of
 * fields:widgets for display
 */

class etemplate_widget_historylog extends etemplate_widget
{

	/**
         * Fill type options in self::$request->sel_options to be used on the client
         *
         * @param string $cname
         */
        public function beforeSendToClient($cname)
        {
		$form_name = self::form_name($cname, $this->id);

		if(is_array(self::$request->content[$form_name]['status-widgets']))
		{
			foreach(self::$request->content[$form_name]['status-widgets'] as $key => $type)
			{
				if(!is_array($type))
				{
					list($basetype) = explode('-',$type);
					$widget = @self::factory($basetype, '<?xml version="1.0"?><'.$type.' type="'.$type.'"/>', $key);
					$widget->id = $key;
					$widget->attrs['type'] = $type;
					$widget->type = $type;

					if(method_exists($widget, 'beforeSendToClient'))
					{
						$widget->beforeSendToClient($cname);
					}
				}
				else
				{
					if (!is_array(self::$request->sel_options[$key])) self::$request->sel_options[$key] = array();
					self::$request->sel_options[$key] += $type;
				}
				
			}
		}
	}
}
?>
