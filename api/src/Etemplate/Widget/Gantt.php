<?php
/**
 * EGroupware - eTemplate serverside gantt widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2014 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use egw_framework;

egw_framework::includeCSS('/phpgwapi/js/dhtmlxGantt/codebase/dhtmlxgantt.css');

/**
 * eTemplate Gantt chart widget
 *
 * The Gantt widget accepts children, and uses them as simple filters
 */
class Gantt extends Box
{
	// No legacy options
	protected $legacy_options = array();


	/**
	 * Validate input
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		unset($expand);	// not used, but required by function signature

		$value = self::get_array($content, $cname);
		$validated[$cname] = array(
			'action' => $value['action'],
			'selected' => $value['selected']
		);
	}

}
