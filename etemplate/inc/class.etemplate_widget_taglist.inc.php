<?php
/**
 * EGroupware - eTemplate serverside of tag list widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate tag list widget
 */
class etemplate_widget_taglist extends etemplate_widget
{
	/**
	 * Constructor
	 *
	 * Overrides parent to check for $xml first, prevents errors when instanciated without (via AJAX)
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml = '')
	{
		if($xml) {
			parent::__construct($xml);
		}
	}
	/**
	 * The default search goes to the link system
	 *
	 * Find entries that match query parameter (from link system) and format them
	 * as the widget expects, a list of {id: ..., label: ...} objects
	 */
	public static function ajax_search() {
		$app = $_REQUEST['app'];
		$query = $_REQUEST['query'];
		$options = array();
		$links = egw_link::query($app, $query, $options);
		
		$results = array();
		foreach($links as $id => $name)
		{
			$results[] = array('id'=>$id, 'label' => htmlspecialchars($name));
		}
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}
}