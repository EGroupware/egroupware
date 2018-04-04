<?php
/**
 * EGroupware - eTemplate serverside of linking widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate link widgets
 * Deals with creation and display of links between entries in various participating egw applications
 */
class Link extends Etemplate\Widget
{

	public $public_functions = array(
		'download_zip' => true
	);

	protected $legacy_options = 'only_app';

	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
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
			if(count(explode(':',$value)) < 2)
			{
				throw new Api\Exception\WrongParameter("Wrong value sent to $this, needs to be an array. ".array2string($value));
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

		if($attrs['type'] == 'link-list')
		{
			$app = $value['to_app'];
			$id  = $value['to_id'];
			$links = Api\Link::get_links($app,$id,'','link_lastmod DESC',true, $value['show_deleted']);
			foreach($links as $link) {
				$value[] = $link;
			}
		}
	}

	/**
	 * Find links that match the given parameters
	 */
	public static function ajax_link_search($app, $type, $pattern, $options=array())
	{
		$options['type'] = $type ? $type : $options['type'];
		if(!$options['num_rows']) $options['num_rows'] = 1000;

		$links = Api\Link::query($app, $pattern, $options);

		// Add ' ' to key so javascript does not parse it as a number.
		// This preserves the order set by the application.
		$linksc = array_combine(array_map(function($k)
		{
			return (string)" ".$k;
		}, array_keys($links)), $links);

		$response = Api\Json\Response::get();
		$response->data($linksc);
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
		$title = Api\Link::title($app, $id);
		//error_log(__METHOD__."('$app', '$id') = ".array2string($title));
		Api\Json\Response::get()->data($title);
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
				$response[$app] = Api\Link::titles($app, $ids);
			}
			else
			{
				error_log(__METHOD__."(".array2string($app_ids).") got invalid title request: app=$app, ids=" . array2string($ids));
			}
		}
		Api\Json\Response::get()->data($response);
	}

	/**
	 * Create links
	 */
	public static function ajax_link($app, $id, Array $links)
	{
		// Files need to know full path in tmp directory
		foreach($links as $key => $link) {
			if($link['app'] == Api\Link::VFS_APPNAME) {
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
		$result = Api\Link::link($app, $id, $links);

		$response = Api\Json\Response::get();
		$response->data(is_array($id) ? $id : $result !== false);
	}

	public static function ajax_link_list($value)
	{
		$app = $value['to_app'];
		$id  = $value['to_id'];

		$links = Api\Link::get_links($app,$id,$value['only_app'],'link_lastmod DESC',true, $value['show_deleted']);
		foreach($links as &$link)
		{
			$link['title'] = Api\Link::title($link['app'],$link['id'],$link);
			if ($link['app'] == Api\Link::VFS_APPNAME)
			{
				$link['target'] = '_blank';
				$link['label'] = 'Delete';
				$link['help'] = lang('Delete this file');
				$link['title'] = Api\Vfs::decodePath($link['title']);
				$link['icon'] = Api\Link::vfs_path($link['app2'],$link['id2'],$link['id'],true);
				$link['download_url'] = Api\Vfs::download_url($link['icon']);
				// Make links to directories load in filemanager
				if($link['type'] == Api\Vfs::DIR_MIME_TYPE)
				{
					$link['target'] = 'filemanager';
				}
			}
			else
			{
				$link['icon'] = Api\Link::get_registry($link['app'], 'icon');
				$link['label'] = 'Unlink';
				$link['help'] = lang('Remove this link (not the entry itself)');
			}
		}

		$response = Api\Json\Response::get();
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
			Api\Link::update_remark((int)$link_id, $comment);
			$result = true;
		}
		else
		{
			$link = Api\Link::get_link((int)$link_id);
			if($link && $link['app'] == Api\Link::VFS_APPNAME)
			{
				$files = Api\Link::list_attached($link['app2'],$link['id2']);
				$file = $files[(int)$link_id];
				$path = Api\Link::vfs_path($link['app2'],$link['id2'],$file['id']);
				$result = Api\Vfs::proppatch($path, array(array('name' => 'comment', 'val' => $comment)));
			}
		}
		$response = Api\Json\Response::get();
		$response->data($result !== false);
	}

	/**
	 * Symlink/copy/move an existing file in filemanager
	 *
	 * @param string $app_id app's id, e.g. infolog:2
	 * @param array $files an array of paths
	 * @param string $action custom action, default is null which
	 * means link action (actions: copy, move)
	 *
	 * @return nothing
	 */
	public static function ajax_link_existing($app_id, $files, $action = null)
	{
		list($app, $id, $dest_file) = explode(':', $app_id);

		if (empty($app_id) || empty($id))
		{
			return;	// cant do anything
		}
		if($id && $dest_file && trim($dest_file) !== '')
		{
			$id .= "/$dest_file";
		}

		if(!is_array($files)) $files = array($files);

		if ($action == "copy")
		{
			Api\Vfs::copy_files($files, Api\Link::vfs_path($app, $id, '', true));
		}
		elseif ($action == "move")
		{
			Api\Vfs::move_files($files, Api\Link::vfs_path($app, $id, '', true), $errs = array(), $moved = array());
		}
		else
		{
			foreach($files as $target)
			{
				Api\Link::link_file($app, $id, $target);
			}
		}
	}

	public static function ajax_delete($value)
	{
		$response = Api\Json\Response::get();
		$response->data(Api\Link::unlink($value));
	}

	/**
	 * Download the files linked to the given entry as one ZIP.
	 *
	 * Because of the $public_functions entry, this is callable as a menuaction.
	 * This lets us just open the URL with app & id parametrs and get a ZIP.  If
	 * the entry has no linked files, the ZIP will still be returned, but it will
	 * be empty.
	 */
	public static function download_zip()
	{
		$app = $_GET['app'];
		$id = $_GET['id'];
		if(Api\Link::file_access($app, $id))
		{
			$app_path = Api\Link::vfs_path($app,$id,'',true);

			// Pass the files linked, not the entry path
			$files = Api\Vfs::find($app_path);
			if($files[0] == $app_path)
			{
				array_shift($files);
			}
			Api\Vfs::download_zip($files, Api\Link::title($app, $id));
			exit;
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

			// keep values added into request by other ajax-functions, eg. files draged into htmlarea (Vfs)
			if ((!$value || is_array($value) && !$value['to_id']) && is_array($expand['cont'][$this->id]) && !empty($expand['cont'][$this->id]['to_id']))
			{
				if (!is_array($value)) $value = array(
					'to_app' => $expand['cont'][$this->id]['to_app'],
				);
				$value['to_id'] = $expand['cont'][$this->id]['to_id'];
			}

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
					Api\Link::link($value['to_app'], $value['to_id'], $link['app'], $link['id']);
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
						'app'	=> Api\Link::VFS_APPNAME,
						'id'	=> array(
							'name'	=> $attrs['name'],
							'type'	=> $attrs['type'],
							'tmp_name'	=> $path.$name
						)
					);
				}
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
			//error_log($this);
			//error_log("   " . array2string($valid));
		}
	}
}
