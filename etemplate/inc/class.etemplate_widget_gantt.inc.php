<?php
/**
 * EGroupware - eTemplate serverside gantt widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2014 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate Gantt chart widget
 *
 * The Gantt widget accepts children, and uses them as simple filters
 */
class etemplate_widget_gantt extends etemplate_widget_box
{
	// No legacy options
	protected $legacy_options = array();
}
// register class for layout widgets, which can have an own namespace
//etemplate_widget::registerWidget('etemplate_widget_box', array('box', 'hbox', 'vbox', 'groupbox'));