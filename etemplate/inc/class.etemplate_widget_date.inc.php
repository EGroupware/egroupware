<?php
/**
 * EGroupware - eTemplate serverside date widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate date widget
 * Deals with date and time.  Overridden to handle date-houronly as a transform
 */
class etemplate_widget_date extends etemplate_widget_transformer
{
	protected static $transformation = array(
		'type' => array('date-houronly' => 'select-hour')
	);
}
new jscalendar();
etemplate_widget::registerWidget('etemplate_widget_date', array('date-houronly'));
