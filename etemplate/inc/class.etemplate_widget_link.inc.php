<?php
/**
 * EGroupware - eTemplate serverside of linking widgets
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
 * eTemplate link widgets
 * Deals with creation and display of links between entries in various participating egw applications
 */
class etemplate_widget_link extends etemplate_widget
{

	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml = '')
	{
		if($xml) {
			parent::__construct($xml);

			// TODO: probably a better way to do this
			egw_framework::includeCSS('/phpgwapi/js/jquery/jquery-ui/smoothness/jquery-ui-1.8.16.custom.css');
		}
	}

	/* Changes all link widgets to template
	protected static $transformation = array(
		'type' => array(
			'link-list'=>array(
				'value' => array('__callback__'=>'get_links'),
				'type' => 'template',
				'id' => 'etemplate.link_widget.list'
			)
		),
	);
	*/

	/**
	 * Set up what we know on the server side.
	 *
	 * Set the options for the application select.
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id);
		$value =& self::get_array(self::$request->content, $form_name, true);

		if($value && !is_array($value))
		{
			throw new egw_exception_wrong_parameter("Wrong value sent to link widget, needs to be an array. ".array2string($value));
		}
		elseif (!$value)
		{
			return;
		}

		$app = $value['to_app'];
		$id  = $value['to_id'];

		// ToDo: implement on client-side
		if (!$attrs['help']) self::setElementAttribute($form_name, 'help', 'view this linked entry in its application');

		if($attrs['type'] == 'link-list') {
			$links = egw_link::get_links($app,$id,'','link_lastmod DESC',true, $value['show_deleted']);
	_debug_array($links);
			foreach($links as $link) {
				$value[] = $link;
			}
		}
	}

	/**
	 * Find links that match the given parameters
	 */
	public static function ajax_link_search($app, $type, $pattern, $options=array()) {
		$options['type'] = $type ? $type : $options['type'];
error_log("$app, $pattern, $options");
		$links = egw_link::query($app, $pattern, $options);

		$response = egw_json_response::get();
		$response->data($links);
	}

	/**
	 * Return title for a given app/id pair
	 *
	 * @param string $app
	 * @param string|int $id
	 * @return string|boolean string with title, boolean false of permission denied or null if not found
	 */
	public static function ajax_link_title($app,$id)
	{
		$title = egw_link::title($app, $id);
		//error_log(__METHOD__."('$app', '$id') = ".array2string($title));
		egw_json_response::get()->data($title);
	}

	/**
	 * Return titles for all given app and id's
	 *
	 * @param array $app_ids array with app => array(id, ...) pairs
	 * @return array with app => id => title
	 */
	public static function ajax_link_titles(array $app_ids)
	{
		$response = array();
		foreach($app_ids as $app => $ids)
		{
			$response[$app] = egw_link::titles($app, $ids);
		}
		egw_json_response::get()->data($response);
	}

	/**
	 * Create links
	 */
	public static function ajax_link($app, $id, Array $links) {
		// Files need to know full path in tmp directory
		foreach($links as &$link) {
			if($link['app'] == egw_link::VFS_APPNAME) {
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$path = $GLOBALS['egw_info']['server']['temp_dir'] . '/' . $link['id'];
				}
				else
				{
					$path = $link['id'].'+';
				}
				$link['tmp_name'] = $path;
				$link['id'] = $link;
			}
		}
		$result = egw_link::link($app, $id, $links);

		$response = egw_json_response::get();
		$response->data($result !== false);
	}

	public function get_links($value) {

		$app = $value['to_app'];
		$id  = $value['to_id'];

		$links = egw_link::get_links($app,$id,'','link_lastmod DESC',true, $value['show_deleted']);
_debug_array($links);
		return $links;
	}
}
