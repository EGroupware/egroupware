<?php
/**
 * EGroupware - eTemplate serverside vfs widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate VFS widget
 * Deals with the Virtual File System
 */
class Vfs extends File
{
	// Legacy option for vfs-upload
	protected $legacy_options = "mime";

	public function __construct($xml='')
	{
		if($xml) parent::__construct($xml);
	}

	/**
	 * If widget is a vfs-file widget, and there are files in the specified directory,
	 * they should be displayed.
	 */
	public function beforeSendToClient($cname, $expand = array())
	{
		if($this->type == 'vfs-upload' || $this->attrs['type'] == 'vfs-upload')
		{
			$form_name = self::form_name($cname, $this->id, $expand ? $expand : array('cont'=>self::$request->content));
			if($this->attrs['path'])
			{
				$path = $this->attrs['path'];
			}
			else
			{
				$path = $this->id;
			}

			$this->setElementAttribute($form_name, 'path', $path);
			// ID maps to path - check there for any existing files
			list($app,$id,$relpath) = explode(':',$path,3);
			if($app && $id)
			{
				if(!is_numeric($id))
				{
					$_id = self::expand_name($id,0,0,0,0,self::$request->content);
					if($_id != $id && $_id)
					{
						$id = $_id;
						$form_name = "$app:$id:$relpath";
					}
					else
					{
						return;
					}
				}
				$value =& self::get_array(self::$request->content, $form_name, true);
				$path = Api\Link::vfs_path($app,$id,'',true);
				if (!empty($relpath)) $path .= '/'.$relpath;

				if (true) $value = array();

				// Single file, already existing
				if (substr($path,-1) != '/' && Api\Vfs::file_exists($path) && !Api\Vfs::is_dir($path))
				{
					$file = Api\Vfs::stat($path);
					$file['path'] = $path;
					$file['name'] = Api\Vfs::basename($file['path']);
					$file['mime'] = Api\Vfs::mime_content_type($file['path']);
					$value = array($file);
				}
				// Single file, missing extension in path
				else if (substr($path, -1) != '/' && !Api\Vfs::file_exists($path) && $relpath && substr($relpath,-4,1) !== '.')
				{
					$find = Api\Vfs::find(substr($path,0, - strlen($relpath)), array(
						'type' => 'f',
						'maxdepth' => 1,
						'name' => $relpath . '*'
					));
					foreach($find as $file)
					{
						$file_info = Api\Vfs::stat($file);
						$file_info['path'] = $file;
						$file_info['name'] = Api\Vfs::basename($file_info['path']);
						$file_info['mime'] = Api\Vfs::mime_content_type($file_info['path']);
						$value[] = $file_info;
					}
				}
				else if (substr($path, -1) == '/' && Api\Vfs::is_dir($path))
				{
					$scan = Api\Vfs::scandir($path);
					foreach($scan as $file)
					{
						$file_info = Api\Vfs::stat("$path$file");
						$file_info['path'] = "$path$file";
						$file_info['name'] = Api\Vfs::basename($file_info['path']);
						$file_info['mime'] = Api\Vfs::mime_content_type($file_info['path']);
						$value[] = $file_info;
					}
				}
			}
		}
	}

	public static function ajax_upload()
	{
		parent::ajax_upload();
	}

	/**
	 * Process one uploaded file.  There should only be one per request...
	 *
	 * Overriden from the parent to see if we can safely show the thumbnail immediately
	 */
	protected static function process_uploaded_file($field, Array &$file, $mime, Array &$file_data)
	{
		parent::process_uploaded_file($field, $file, $mime, $file_data);
		$path = self::store_file($_REQUEST['path'] ? $_REQUEST['path'] : $_REQUEST['widget_id'], $file);
		if($path)
		{
			$file_data[basename($file['tmp_name'])]['name'] = $file['name'];
			$file_data[basename($file['tmp_name'])]['path'] = $path;
			$file_data[basename($file['tmp_name'])]['mime'] = $file['type'];
			$file_data[basename($file['tmp_name'])]['mtime'] = time();
		}
	}

	/**
	 * Upload via dragging images into ckeditor
	 */
	public static function ajax_htmlarea_upload()
	{
		$request_id = urldecode($_REQUEST['request_id']);
		$widget_id = $_REQUEST['widget_id'];
		if(!self::$request = Etemplate\Request::read($request_id))
		{
			$error = lang("Could not read session");
		}
		elseif (!($template = Template::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			// Can't use callback
			$error = lang("Could not get template for file upload, callback skipped");
		}
		elseif (!isset($_FILES['upload']))
		{
			$error = lang('No _FILES[upload] found!');
		}
		else
		{
			$data = self::$request->content[$widget_id];
			$path = self::store_file($path = (!is_array($data) && $data[0] == '/' ? $data :
				self::get_vfs_path($data['to_app'].':'.$data['to_id'])).'/', $_FILES['upload']);

			// store temp. vfs-path like links to be able to move it to correct location after entry is stored
			if (!$data['to_id'] || is_array($data['to_id']))
			{
				Api\Link::link($data['to_app'], $data['to_id'], Api\Link::VFS_APPNAME, array(
					'name' => $_FILES['upload']['name'],
					'type' => $_FILES['upload']['type'],
					'tmp_name' => Api\Vfs::PREFIX.$path,
				));
				self::$request->content = array_merge(self::$request->content, array($widget_id => $data));
			}
		}
		// switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		$file = array(
			"uploaded" => (int)empty($error),
			"fileName" => Api\Html::htmlspecialchars($_FILES['upload']['name']),
			"url" => Api\Framework::link(Api\Vfs::download_url($path)),
			"error" => array(
				"message" => $error,
			)
		);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($file);

		exit;
	}

	/**
	 * Fix source/url of dragged in images in html
	 *
	 * @param string $app
	 * @param int|string $id
	 * @param array $links
	 * @param string& $html
	 * @return boolean true if something was fixed and $html needs to be stored
	 */
	static function fix_html_dragins($app, $id, array $links, &$html)
	{
		$replace = $remove_dir = array();
		foreach($links as $link)
		{
			$matches = null;
			if (is_array($link) && preg_match('|^'.preg_quote(Api\Vfs::PREFIX,'|').'('.preg_quote(self::get_temp_dir($app, ''), '|').'[^/]+)/|', $link['id']['tmp_name'], $matches))
			{
				$replace[substr($link['id']['tmp_name'], strlen(Api\Vfs::PREFIX))] =
					Api\Link::vfs_path($app, $id, Api\Vfs::basename($link['id']['tmp_name']), true);

				if (!in_array($matches[1], $remove_dir)) $remove_dir[] = $matches[1];
			}
		}
		if ($replace)
		{
			$html = strtr($old = $html, $replace);
			// remove all dirs
			foreach($remove_dir as $dir)
			{
				Api\Vfs::remove($dir);
			}
		}
		return isset($old) && $old != $html;
	}

	/**
	 * Generate a temp. directory for htmlarea uploads: /home/$user/.tmp/$app_$postfix
	 *
	 * @param string $app app-name
	 * @param string $postfix =null default random id
	 * @return string vfs path
	 */
	static function get_temp_dir($app, $postfix=null)
	{
		if (!isset($postfix)) $postfix = md5(time().session_id());

		return '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.tmp/'.$app.'_'.$postfix;
	}

	/**
	* Ajax callback to receive an incoming file
	*
	* The incoming file is automatically placed into the appropriate VFS location.
	* If the entry is not yet created, the file information is stored into the widget's value.
	* When the form is submitted, the information for all files uploaded is available in the returned
	* $content array and the application should deal with the file.
	*/
	public static function store_file($path, &$file)
	{
		$name = $_REQUEST['widget_id'];

		// Find real path
		if($path[0] != '/')
		{
			$path = self::get_vfs_path($path);
		}
		$filename = $file['name'];
		if ($path && substr($path,-1) != '/')
		{
			// add extension to path
			$parts = explode('.',$filename);
			if (($extension = array_pop($parts)) && Api\MimeMagic::ext2mime($extension))       // really an extension --> add it to path
			{
				$path .= '.'.$extension;
				$file['name'] = Api\Vfs::basename($path);
			}
		}
		else if ($path)   // multiple upload with dir given (trailing slash)
		{
			$path .= Api\Vfs::encodePathComponent($filename);
		}
		if (!($dir = Api\Vfs::dirname($path)))
		{
			self::set_validation_error($name,lang('Error create parent directory %1!', "dirname('$path') === false"));
			return false;
		}
		if (!Api\Vfs::file_exists($dir) && !Api\Vfs::mkdir($dir,null,STREAM_MKDIR_RECURSIVE))
		{
			self::set_validation_error($name,lang('Error create parent directory %1!',Api\Vfs::decodePath($dir)));
			return false;
		}
		if (!copy($file['tmp_name'],Api\Vfs::PREFIX.$path))
		{
			self::set_validation_error($name,lang('Error copying uploaded file to vfs!'));
			return false;
		}

		// Try to remove temp file
		unlink($file['tmp_name']);

		return $path;
	}

	/**
	 * Validate input
	 * Merge any already uploaded files into the content array
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		// do not validate, as it would overwrite preserved values with null!
		if (in_array($this->type, array('vfs-size', 'vfs-uid', 'vfs-gid', 'vfs', 'vfs-mime')))
		{
			return;
		}
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = $value_in = self::get_array($content, $form_name);
		$valid =& self::get_array($validated, $form_name, true);

		switch($this->type)
		{
			case 'vfs-upload':
				if(!is_array($value)) $value = array();
				/* Check & skip files that made it asyncronously
				list($app,$id,$relpath) = explode(':',$this->id,3);
				//...
				foreach($value as $tmp => $file)
				{
					if(Api\Vfs::file_exists(self::get_vfs_path($id) . $relpath)) {}
				}*/
				parent::validate($cname, $content, $validated);
				break;
		}
		if (true) $valid = $value;
	}

	/**
	 * Change an ID like app:id:relative/path to an actual VFS location
	 */
	public static function get_vfs_path($path)
	{
		list($app,$id,$relpath) = explode(':',$path,3);
		if (empty($id) || $id == 'undefined')
		{
			static $tmppath = array();      // static var, so all vfs-uploads get created in the same temporary dir
			if (!isset($tmppath[$app])) $tmppath[$app] = self::get_temp_dir ($app);
			$path = $tmppath[$app];
		}
		else
		{
			if(!is_numeric($id))
			{
				$_id = self::expand_name($id,0,0,0,0,self::$request->content);
				if($_id != $id && $_id)
				{
					$id = $_id;
				}
				else
				{
					// ID didn't resolve, try again without it as a 'new record'
					return static::get_vfs_path("$app::$relpath");
				}
			}
			$path = Api\Link::vfs_path($app,$id,'',true);
		}
		if (!empty($relpath)) $path .= '/'.$relpath;
		return $path;
	}
}
