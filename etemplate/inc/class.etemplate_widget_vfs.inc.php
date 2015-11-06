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

/**
 * eTemplate VFS widget
 * Deals with the Virtual File System
 */
class etemplate_widget_vfs extends etemplate_widget_file
{

	// Legacy option for vfs-upload
	protected $legacy_options = "mime";

	public function __construct($xml='') {
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
					if($_id != $id)
					{
						$id = $_id;
						$form_name = "$app:$id:$relpath";
					}
				}
				$value =& self::get_array(self::$request->content, $form_name, true);
				$path = egw_link::vfs_path($app,$id,'',true);
				if (!empty($relpath)) $path .= '/'.$relpath;

				if (true) $value = array();

				// Single file, already existing
				if (substr($path,-1) != '/' && egw_vfs::file_exists($path) && !egw_vfs::is_dir($path))
				{
					$file = egw_vfs::stat($path);
					$file['path'] = $path;
					$file['name'] = egw_vfs::basename($file['path']);
					$file['mime'] = egw_vfs::mime_content_type($file['path']);
					$value = array($file);
				}
				// Single file, missing extension in path
				else if (substr($path, -1) != '/' && !egw_vfs::file_exists($path) && $relpath && substr($relpath,-4,1) !== '.')
				{
					$find = egw_vfs::find(substr($path,0, - strlen($relpath)), array(
						'type' => 'f',
						'maxdepth' => 1,
						'name' => $relpath . '*'
					));
					foreach($find as $file)
					{
						$file_info = egw_vfs::stat($file);
						$file_info['path'] = $file;
						$file_info['name'] = egw_vfs::basename($file_info['path']);
						$file_info['mime'] = egw_vfs::mime_content_type($file_info['path']);
						$value[] = $file_info;
					}
				}
				else if (substr($path, -1) == '/' && egw_vfs::is_dir($path))
				{
					$scan = egw_vfs::scandir($path);
					foreach($scan as $file)
					{
						$file_info = egw_vfs::stat("$path$file");
						$file_info['path'] = "$path$file";
						$file_info['name'] = egw_vfs::basename($file_info['path']);
						$file_info['mime'] = egw_vfs::mime_content_type($file_info['path']);
						$value[] = $file_info;
					}
				}
			}
		}
	}

	public static function ajax_upload()
	{
		parent::ajax_upload();
		foreach($_FILES as $file)
		{
			self::store_file($_REQUEST['path'] ? $_REQUEST['path'] : $_REQUEST['widget_id'], $file);
		}
	}

	/**
	 * Upload via dragging images into ckeditor
	 */
	public static function ajax_htmlarea_upload()
	{
		$request_id = urldecode($_REQUEST['request_id']);
		$widget_id = $_REQUEST['widget_id'];
		if(!self::$request = etemplate_request::read($request_id))
		{
			$error = lang("Could not read session");
		}
		elseif (!($template = etemplate_widget_template::instance(self::$request->template['name'], self::$request->template['template_set'],
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
			$path = self::store_file($path = self::get_vfs_path($data['to_app'].':'.$data['to_id']).'/', $_FILES['upload']);

			// store temp. vfs-path like links to be able to move it to correct location after entry is stored
			if (!$data['to_id'] || is_array($data['to_id']))
			{
				egw_link::link($data['to_app'], $data['to_id'], egw_link::VFS_APPNAME, array(
					'name' => $_FILES['upload']['name'],
					'type' => $_FILES['upload']['type'],
					'tmp_name' => egw_vfs::PREFIX.$path,
				));
				self::$request->content = array_merge(self::$request->content, array($widget_id => $data));
			}
		}
		// switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		$file = array(
			"uploaded" => (int)empty($error),
			"fileName" => html::htmlspecialchars($_FILES['upload']['name']),
			"url" => egw::link(egw_vfs::download_url($path)),
			"error" => array(
				"message" => $error,
			)
		);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($file);

		common::egw_exit();
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
			if (is_array($link) && preg_match('|^'.preg_quote(egw_vfs::PREFIX,'|').'('.preg_quote(self::get_temp_dir($app, ''), '|').'[^/]+)/|', $link['id']['tmp_name'], $matches))
			{
				$replace[substr($link['id']['tmp_name'], strlen(egw_vfs::PREFIX))] =
					egw_link::vfs_path($app, $id, egw_vfs::basename($link['id']['tmp_name']), true);

				if (!in_array($matches[1], $remove_dir)) $remove_dir[] = $matches[1];
			}
		}
		if ($replace)
		{
			$html = strtr($old = $html, $replace);
			// remove all dirs
			foreach($remove_dir as $dir)
			{
				egw_vfs::remove($dir);
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
	public static function store_file($path, $file)
	{
		$name = $_REQUEST['widget_id'];

		// Find real path
		if($path[0] != '/')
		{
			$path = self::get_vfs_path($path);
		}
		$filename = $file['name'];
		if (substr($path,-1) != '/')
		{
			// add extension to path
			$parts = explode('.',$filename);
			if (($extension = array_pop($parts)) && mime_magic::ext2mime($extension))       // really an extension --> add it to path
			{
				$path .= '.'.$extension;
			}
		}
		else    // multiple upload with dir given (trailing slash)
		{
			$path .= egw_vfs::encodePathComponent($filename);
		}
		if (!egw_vfs::file_exists($dir = egw_vfs::dirname($path)) && !egw_vfs::mkdir($dir,null,STREAM_MKDIR_RECURSIVE))
		{
			self::set_validation_error($name,lang('Error create parent directory %1!',egw_vfs::decodePath($dir)));
			return false;
		}
		if (!copy($file['tmp_name'],egw_vfs::PREFIX.$path))
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
					if(egw_vfs::file_exists(self::get_vfs_path($id) . $relpath)) {}
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
			$path = egw_link::vfs_path($app,$id,'',true);
		}
		if (!empty($relpath)) $path .= '/'.$relpath;
		return $path;
	}
}
etemplate_widget::registerWidget('etemplate_widget_vfs', array('vfs-upload'));
