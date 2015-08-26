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

	public $public_functions = array(
		'download_zip' => true
	);

	protected $legacy_options = 'only_app';

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
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name, true);

		if($value && !is_array($value) && !$this->attrs['only_app'])
		{
			// Try to explode
			if(count(explode(':',$value)) < 2) {
				throw new egw_exception_wrong_parameter("Wrong value sent to $this, needs to be an array. ".array2string($value));
			}
			list($app, $id) = explode(':', $value,2);
			$value = array('app' => $app, 'id' => $id);
		}
		elseif (!$value)
		{
			return;
		}


		// ToDo: implement on client-side
		if (!$attrs['help']) self::setElementAttribute($form_name, 'help', 'view this linked entry in its application');

		if($attrs['type'] == 'link-list') {
			$app = $value['to_app'];
			$id  = $value['to_id'];
			$links = egw_link::get_links($app,$id,'','link_lastmod DESC',true, $value['show_deleted']);
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
		if(!$options['num_rows']) $options['num_rows'] = 1000;
		$links = egw_link::query($app, $pattern, $options);
		$links = array_combine(array_map(create_function('$k', 'return (string)" ".$k;'), array_keys($links)), $links);
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
			if(count($ids))
			{
				$response[$app] = egw_link::titles($app, $ids);
			}
			else
			{
				error_log(__METHOD__."(".array2string($app_ids).") got invalid title request: app=$app, ids=" . array2string($ids));
			}
		}
		egw_json_response::get()->data($response);
	}

	/**
	 * Create links
	 */
	public static function ajax_link($app, $id, Array $links) {
		// Files need to know full path in tmp directory
		foreach($links as $key => $link) {
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
				$links[$key]['id'] = $link;
			}
		}
		$result = egw_link::link($app, $id, $links);

		$response = egw_json_response::get();
		$response->data(is_array($id) ? $id : $result !== false);
	}

	public function ajax_link_list($value) {

		$app = $value['to_app'];
		$id  = $value['to_id'];

		$links = egw_link::get_links($app,$id,$value['only_app'],'link_lastmod DESC',true, $value['show_deleted']);
		foreach($links as &$link)
		{
			$link['title'] = egw_link::title($link['app'],$link['id'],$link);
			if ($link['app'] == egw_link::VFS_APPNAME)
			{
				$link['target'] = '_blank';
				$link['label'] = 'Delete';
				$link['help'] = lang('Delete this file');
				$link['title'] = egw_vfs::decodePath($link['title']);
				$link['icon'] = egw_link::vfs_path($link['app2'],$link['id2'],$link['id'],true);
				$link['download_url'] = egw_vfs::download_url($link['icon']);
				// Make links to directories load in filemanager
				if($link['type'] == 'httpd/unix-directory')
				{
					$link['target'] = 'filemanager';
				}
			}
			else
			{
				$link['icon'] = egw_link::get_registry($link['app'], 'icon');
				$link['label'] = 'Unlink';
				$link['help'] = lang('Remove this link (not the entry itself)');
			}
		}

		$response = egw_json_response::get();
		// Strip keys, unneeded and cause index problems on the client side
		$response->data(array_values($links));
	}

	/**
	 * Allow changing of comment after link is created
	 */
	public static function ajax_link_comment($link_id, $comment)
	{
		$result = false;
		if((int)$link_id > 0)
		{
			solink::update_remark((int)$link_id, $comment);
			$result = true;
		}
		else
		{
			$link = egw_link::get_link((int)$link_id);
			if($link && $link['app'] == egw_link::VFS_APPNAME)
			{
				$file = egw_link::list_attached($link['app2'],$link['id2']);
				$file = $file[(int)$link_id];
				$path = egw_link::vfs_path($link['app2'],$link['id2'],$file['id']);
				$result = egw_vfs::proppatch($path, array(array('name' => 'comment', 'val' => $comment)));
			}
		}
		$response = egw_json_response::get();
		$response->data($result !== false);
	}

	/**
	 * Symlink an existing file in filemanager
	 */
	public static function link_existing($app_id, $files)
	{
		list($app, $id) = explode(':', $app_id);

		if(!is_array($files)) $files = array($files);
		foreach($files as $target) {
			error_log(array2string($target));
			egw_link::link_file($app, $id, $target);
		}
	}

	public function ajax_delete($value) {
		$response = egw_json_response::get();
		$response->data(egw_link::unlink($value));
	}

	/**
	 * Download the files linked to the given entry as one ZIP.
	 *
	 * Because of the $public_functions entry, this is callable as a menuaction.
	 * This lets us just open the URL with app & id parametrs and get a ZIP.  If
	 * the entry has no linked files, the ZIP will still be returned, but it will
	 * be empty.
	 */
	public function download_zip()
	{
		$app = $_GET['app'];
		$id = $_GET['id'];
		if(egw_link::file_access($app, $id))
		{
			$app_path = egw_link::vfs_path($app,$id,'',true);

			// Pass the files linked, not the entry path
			$files = egw_vfs::find($app_path);
			if($files[0] == $app_path)
			{
				array_shift($files);
			}
			egw_vfs::download_zip($files, egw_link::title($app, $id));
			common::egw_exit();
		}
	}

	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in =& self::get_array($content, $form_name);

			// Link widgets can share IDs, make sure to preserve values from others
			$already = self::get_array($validated,$form_name);
			if($already != null)
			{
				$value = array_merge($value,$already);
			}
			// Automatically do link if user selected entry but didn't click 'Link' button
			$link = self::get_array($content, self::form_name($cname, $this->id . '_link_entry'));
			if($this->type =='link-to' && is_array($link) && $link['app'] && $link['id'] )
			{
				// Do we have enough information to link automatically?
				if(is_array($value) && $value['to_id'])
				{
					$result = egw_link::link($value['to_app'], $value['to_id'], $link['app'], $link['id']);
				}
				else
				{
					// Not enough information, leave it to the application
					if(!is_array($value['to_id'])) $value['to_id'] = array();
					$value['to_id'][] = $link;
				}
			}

			// Look for files - normally handled by ajax
			$files = self::get_array($content, self::form_name($cname, $this->id . '_file'));
			if(is_array($files) && !(is_array($value) && $value['to_id']))
			{
				$value = array();
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$path = $GLOBALS['egw_info']['server']['temp_dir'] . '/';
				}
				else
				{
					$path = '';
				}
				foreach($files as $name => $attrs)
				{
					if(!is_array($value['to_id'])) $value['to_id'] = array();
					$value['to_id'][] = array(
						'app'	=> egw_link::VFS_APPNAME,
						'id'	=> array(
							'name'	=> $attrs['name'],
							'type'	=> $attrs['type'],
							'tmp_name'	=> $path.$name
						)
					);
				}
			}
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $value;
			//error_log($this);
			//error_log("   " . array2string($valid));
		}
	}
}
