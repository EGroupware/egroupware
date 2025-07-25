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
use EGroupware\Api\Json;
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
		parent::beforeSendToClient($cname, $expand);

		$form_name = self::form_name($cname, $this->id, $expand ?: array('cont' => self::$request->content));
		if(!empty($this->attrs['path']))
		{
			$path = str_contains($this->getElementAttribute($form_name, 'path'), '$') || str_contains($this->getElementAttribute($form_name, 'path'), '$') ?
				self::expand_name($this->getElementAttribute($form_name, 'path') ?? $this->attrs['path'], $expand['c'] ?? null, $expand['row'], $expand['c_'] ?? null, $expand['row_'] ?? null, $expand['cont']) :
				$this->attrs['path'];
		}
		else
		{
			$path = $form_name;
		}

		if(in_array($this->type, ["et2-vfs-upload", 'vfs-upload']) && !$this->getElementAttribute($form_name, 'path'))
		{
			self::setElementAttribute($form_name, 'path', $path);
		}
		// ID maps to path - check there for any existing files
		list($app, $id, $relpath) = explode(':', $path, 3)+[null, null, null];
		if($app && $id)
		{
			if(!is_numeric($id))
			{
				$_id = self::expand_name($id, 0, 0, 0, 0, self::$request->content);
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
			$path = Api\Link::vfs_path($app, $id, '', true);
			if(!empty($relpath))
			{
				$path .= '/' . $relpath;
			}

			$value = self::findAttachments($path);
		}
	}

	/**
	 * Find all attachments, can be used to prepare the data for the widget on client-side
	 *
	 * @param string $path eg. "/apps/$app/$id/$relpath"
	 * @return array
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function findAttachments($path)
	{
		list(,,,, $relpath) = explode('/', $path, 5);

		$value = [];
		// Single file, already existing
		if (substr($path,-1) != '/' && Api\Vfs::file_exists($path) && !Api\Vfs::is_dir($path))
		{
			$file = Api\Vfs::stat($path);
			$file['path'] = $path;
			$file['name'] = Api\Vfs::basename($file['path']);
			$file['type'] = Api\Vfs::mime_content_type($file['path']);
			$file['download_url'] = Api\Vfs::download_url($file['path']);
			$value = array($file);
		}
		// Single file, missing extension in path
		else if (substr($path, -1) != '/' && !Api\Vfs::file_exists($path) && $relpath && substr($relpath,-4,1) !== '.')
		{
			$find = Api\Vfs::find(Api\Vfs::dirname($path), array(
				'type' => 'f',
				'maxdepth' => 1,
				'name' => Api\Vfs::basename($path).'.*',
			));
			foreach($find as $file)
			{
				$file_info = Api\Vfs::stat($file);
				$file_info['path'] = $file;
				$file_info['name'] = Api\Vfs::basename($file_info['path']);
				$file_info['type'] = Api\Vfs::mime_content_type($file_info['path']);
				$file_info['download_url'] = Api\Vfs::download_url($file_info['path']);
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
				$file_info['type'] = Api\Vfs::mime_content_type($file_info['path']);
				$file_info['download_url'] = Api\Vfs::download_url($file_info['path']);
				$value[] = $file_info;
			}
		}
		return $value;
	}

	public static function ajax_upload()
	{
		parent::ajax_upload();
	}

	/**
	 * Check to see if the file already exists before we start uploading it.
	 * If it does, it returns a suggested alternate filename.
	 * @param $request_id
	 * @param $path
	 * @return void
	 */
	public static function ajax_conflict_check($request_id, $path, $filename, $mimetype)
	{
		$response = Api\Json\Response::get();
		$request_id = str_replace(' ', '+', rawurldecode($request_id));
		$response_data = array('errs' => 0);
		if(!self::$request = Etemplate\Request::read($request_id))
		{
			$response->error("Could not read session");
			return;
		}
		if($path[0] !== '/')
		{
			$path = self::get_vfs_path($path);
		}
		if(Api\Vfs::is_dir($path) && !Api\Vfs::is_writable($path))
		{
			$response_data['errs']++;
			$response_data['msg'] = 'Permission denied';
		}
		else
		{
			// Path is to a single file
			if(!str_ends_with($path, '/'))
			{
				$response_data['exists'] = Api\Vfs::file_exists($path);

				$extFilename = static::addExtension($path, ['name' => $filename, 'mime' => $mimetype]);
				// Check for anything matching, ignoring extension
				if($filename != $extFilename)
				{
					$response_data['filename'] = $extFilename;
					$existing = Api\Vfs::find(Api\Vfs::dirname($path), array('type' => 'f', 'maxdepth' => 1,
																			 'name' => Api\Vfs::basename($path) . '.*'));
					$response_data['exists'] = count($existing) > 0;
				}
			}
			elseif(Api\Vfs::is_dir($path))
			{
				$response_data['exists'] = Api\Vfs::file_exists($path . $filename);

				if($response_data['exists'] && !$response_data['filename'])
				{
					$response_data['filename'] = Api\Vfs::basename(Api\Vfs::make_unique($path . $filename));
				}
			}
		}

		$response->data($response_data);
	}

	public static function ajax_remove($request_id, $widget_id, $path)
	{
		$response = Api\Json\Response::get();
		$request_id = str_replace(' ', '+', rawurldecode($request_id));
		$response_data = array('errs' => 0);
		if(!self::$request = Etemplate\Request::read($request_id))
		{
			$response->error("Could not read session");
			return;
		}
		try
		{
			if(!Api\Vfs::unlink($path))
			{
				unset($response_data['errs']);
				$e = error_get_last();
				$response_data['msg'] = $e['message'];
			}
		}
		catch (\Exception $e)
		{
			$response_data['msg'] = $e->getMessage();
		}

		// Set up response
		$response->data($response_data);
	}

	/**
	 * Process one uploaded file.  There should only be one per request...
	 *
	 * Overriden from the parent to see if we can safely show the thumbnail immediately
	 */
	protected static function process_uploaded_file($field, Array &$file, $mime, Array &$file_data)
	{
		$done = parent::process_uploaded_file($field, $file, $mime, $file_data);
		if(!$done)
		{
			return;
		}
		$path = self::store_file($_REQUEST['path'] ?: $_REQUEST['widget_id'], $file);
		if($path)
		{
			$file_data[basename($file['tmp_name'])]['name'] = $file['name'];
			$file_data[basename($file['tmp_name'])]['path'] = $path;
			$file_data[basename($file['tmp_name'])]['mime'] = $file['type'];
			$file_data[basename($file['tmp_name'])]['mtime'] = time();
		}
		else
		{
			// Something happened with the VFS
			$file_data[basename($file['tmp_name'])] = self::get_validation_errors($_REQUEST['widget_id']) ?? lang('Server error');
		}
	}

	/**
	 * Upload via dragging images into htmlarea(tinymce)
	 */
	public static function ajax_htmlarea_upload()
	{
		$request_id = urldecode($_REQUEST['request_id']);
		$type = $_REQUEST['type'];
		$widget_id = $_REQUEST['widget_id'];
		$file = $type == 'htmlarea' ? $_FILES['file'] : $_FILES['upload'];
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
		elseif (!isset($file))
		{
			$error = lang('No _FILES[upload] found!');
		}
		else
		{
			$data = self::$request->content[$widget_id];
			$path = self::store_file($path = (!is_array($data) && $data[0] == '/' ? $data :
				self::get_vfs_path($data['to_app'].':'.$data['to_id'])).'/', $file);

			// store temp. vfs-path like links to be able to move it to correct location after entry is stored
			if (is_array($data) && (empty($data['to_id']) || is_array($data['to_id'])))
			{
				Api\Link::link($data['to_app'], $data['to_id'], Api\Link::VFS_APPNAME, array(
					'name' => $file['name'],
					'type' => $file['upload']['type'],
					'tmp_name' => Api\Vfs::PREFIX.$path,
				));
				self::$request->content = array_merge(self::$request->content, array($widget_id => $data));
			}
		}
		// switch regular JSON response handling off
		Json\Request::isJSONRequest(false);

		if ($type == 'htmlarea')
		{
			// try to show error to user by push and instead of the (anyway not working) URL
			if (isset($error))
			{
				$push = new Json\Push();
				$push->message($error, 'error');
			}
			$result = array ('location' => $error ?? Api\Framework::link(Api\Vfs::download_url($path)));
		}
		else
		{
			$result = array(
				"uploaded" => (int)empty($error),
				"fileName" => Api\Html::htmlspecialchars($file['name']),
				"url" => Api\Framework::link(Api\Vfs::download_url($path)),
				"error" => array(
					"message" => $error,
				)
			);
		}

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result);

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
			if (is_array($link) && !empty($link['id']['tmp_name']) && preg_match('|^'.preg_quote(Api\Vfs::PREFIX,'|').'('.preg_quote(self::get_temp_dir($app, ''), '|').'[^/]+)/|', $link['id']['tmp_name'], $matches))
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

		// Find real path, could be "$app:$id:$path"
		if($path[0] !== '/')
		{
			$path = self::get_vfs_path($path);
		}
		$filename = $file['name'];
		if ($path && substr($path,-1) !== '/')
		{
			// check if path already contains a valid extension --> don't add another one
			$file['name'] = static::addExtension($path, $file);
			$path = Api\Vfs::dirname($path) . '/' . $file['name'];
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
		if(filetype($file['tmp_name']) == 'link')
		{
			$target = readlink($file['tmp_name']) ?: $file['path'];
			Api\Vfs::symlink($target, $path);
		}
		else if(!copy($file['tmp_name'], Api\Vfs::PREFIX . $path))
		{
			self::set_validation_error($name,lang('Error copying uploaded file to vfs!'));
			return false;
		}

		// Try to remove temp file
		unlink($file['tmp_name']);

		return $path;
	}

	const VFS_NAME_REGEXP = '#^[^/\\\\]+$#';

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
		if(in_array($this->type, array('vfs-size', 'et2-vfs-uid', 'et2-vfs-gid', 'vfs', 'et2-vfs-mime')) ||
			$this->is_readonly($cname, $form_name = self::form_name($cname, $this->id, $expand)))
		{
			return;
		}
		$value = $value_in = self::get_array($content, $form_name);
		$valid =& self::get_array($validated, $form_name, true);

		switch($this->type)
		{
			case 'vfs-upload':
			case 'et2-vfs-upload';
				if(!is_array($value)) $value = array();
				/* Check & skip files that made it asynchronously, or they */
				list($app, $id, $relpath) = explode(':', $this->attrs['path'], 3);
				if($app && $id)
				{
					foreach($value as $index => $file)
					{
						if(!empty($file['path']) && Api\Vfs::file_exists($file['path']))
						{
							unset($value[$index]);
						}
					}
				}
				if(count($value))
				{
					parent::validate($cname, $expand, $content, $validated);
				}
				break;
			case 'vfs-name':
			case 'et2-vfs-name':
				if (!preg_match(self::VFS_NAME_REGEXP, $value))
				{
					self::set_validation_error($form_name, lang("'%1' must not contain (back)slashes!", $value));
					return;
				}
				break;
		}
		if (!empty($this->id)) $valid = $value;
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

	/**
	 * Sometimes target path has a filename but no extension.  Add the appropriate extension for the file.
	 * @param $path
	 * @param $filename
	 * @return void
	 */
	protected static function addExtension($path, $file)
	{
		$parts = explode('.', $file['name']);
		// check if path already contains a valid extension --> don't add another one
		$path_parts = explode('.', Api\Vfs::basename($path));
		if((!($path_ext = array_pop($path_parts)) || Api\MimeMagic::ext2mime($path_ext) === 'application/octet-stream') &&
			(($extension = array_pop($parts) ?: Api\MimeMagic::mime2ext($file['mime'])) && $extension != $file['name']))
		{
			// add extension to path
			$path .= '.' . $extension;
		}
		return Api\Vfs::basename($path);
	}

	/**
	 * This function behaves like etemplate app function for set/get content of
	 * VFS Select Widget UI
	 *
	 * There are the following ($params) parameters:
	 *
	 * - mode=(open|open-multiple|saveas|select-dir)(required)
	 * - method=app.class.method					(optional callback, gets called with id and selected file(s))
	 * - method_id= array()|string					(optional parameter passed to callback)
	 * - path=string								(optional path in VFS)
	 * - mime=string								(optional mime-type to limit display to given type)
	 * - name=array|string							(optional name value to preset name field)
	 *
	 * @param array $content
	 * @param array $params
	 * @throws Api\Exception\WrongParameter
	 */
	public static function ajax_vfsSelect_content (array $content=null, $params = null)
	{
		$response = Json\Response::get();
		$readonlys = $sel_options = array();

		if (!empty($params['mime']))
		{
			foreach((array)$params['mime'] as $key => $value)
			{
				if (is_numeric($key))
				{
					$sel_options['mime'][$value] = lang('%1 files',strtoupper(Api\MimeMagic::mime2ext($value))).' ('.$value.')';
				}
				else
				{
					$sel_options['mime'][$key] = lang('%1 files',strtoupper($value)).' ('.$key.')';
				}
			}
		}
		//handle VFS full path
		if ($params['path'][0] != '/' && substr($params['path'], 0, 13) == Api\Vfs::PREFIX)
		{
			$params['path'] = substr($params['path'], 13);
		}

		if (!is_array($content))
		{
			$content = array_merge($params, array(
				'name'	=> (string)$params['name'],
				'path'	=> empty($params['path']) ?
				Api\Cache::getSession('filemanger', 'select_path'): $params['path']
			));
			unset($content['mime']);
			if (!in_array($content['mode'],array('open','open-multiple','saveas','select-dir')))
			{
				throw new Api\Exception\WrongParameter("Wrong or unset required mode parameter!");
			}
			if (!empty($params['mime']))
			{
				$content['showmime'] = true;
				$content['mime'] = key($sel_options['mime']);
			}
		}
		elseif(isset($content['action']))
		{
			$action = $content['action'];
			unset($content['action']);
			switch($action)
			{
				case 'home':
					$content['path'] = Api\Vfs::get_home_dir();
					break;
			}
		}
		if (!empty($content['app']) && $content['old_app'] != $content['app'])
		{
			$content['path'] = $content['app'] == 'home'? Api\Vfs::get_home_dir():
				'/apps/'.$content['app'];
		}

		$favorites_flag = substr($content['path'],0,strlen('/apps/favorites')) == '/apps/favorites';
		if (!$favorites_flag && (!$content['path'] || !Api\Vfs::is_dir($content['path'])))
		{
			$content['path'] = Api\Vfs::get_home_dir();
		}

		if ($favorites_flag)
		{
			// Display favorites as if they were folders
			$files = array();
			$favorites = Api\Framework\Favorites::get_favorites('filemanager');
			$n = 0;
			$content['dir'] = array();
			//check for recent paths and add them to the top of favorites list
			if (is_array($params['recentPaths']))
			{
				foreach($params['recentPaths'] as $p)
				{
					$mime = Api\Vfs::mime_content_type($p);
					$content['dir'][$n] = array(
						'name' => $p,
						'path' => $p,
						'mime' => $mime,
						'is_dir' => true
					);
					++$n;
				}
			}

			foreach($favorites as $favorite)
			{
				$path = $favorite['state']['path'];
				// Just directories
				if(!$path) continue;
				if ($path == $content['path']) continue;	// remove directory itself

				$mime = Api\Vfs::mime_content_type($path);
				$content['dir'][$n] = array(
					'name' => $favorite['name'],
					'path' => $path,
					'mime' => $mime,
					'is_dir' => true
				);
				if ($content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$favorite['name'].']'] = true;
				}
				++$n;
			}
		}
		else if (!($files = Api\Vfs::find($content['path'],array(
			'dirsontop' => true,
			'order' => 'name',
			'sort' => 'ASC',
			'maxdepth' => 1,
		))))
		{
			$content['msg'] = lang("Can't open directory %1!",$content['path']);
		}
		else
		{
			$n = 0;
			$content['dir'] = array('mode' => $content['mode']);
			foreach($files as $path)
			{
				if ($path == $content['path']) continue;	// remove directory itself

				$name = Api\Vfs::basename($path);
				$is_dir = Api\Vfs::is_dir($path);
				$mime = Api\Vfs::mime_content_type($path);
				if ($content['mime'] && !$is_dir && $mime != $content['mime'])
				{
					continue;	// does not match mime-filter --> ignore
				}
				$content['dir'][$n] = array(
					'name' => $name,
					'path' => $path,
					'mime' => $mime,
					'is_dir' => $is_dir
				);
				if ($is_dir && $content['mode'] == 'open-multiple')
				{
					$readonlys['selected['.$name.']'] = true;
				}
				++$n;
			}
			if (!$n) $readonlys['selected[]'] = true;	// remove checkbox from empty line
		}
		$readonlys = array_merge($readonlys, array(
			'createdir' => !Api\Vfs::is_writable($content['path']),
			'upload_file' => !Api\Vfs::is_writable($content['path']) ||
			!in_array($content['mode'],array('open', 'open-multiple')),
			'favorites' => !isset($GLOBALS['egw_info']['apps']['stylite'])
		));

		$sel_options = array_merge($sel_options, array(
			'app' => self::get_apps()
		));

		if ($content['method'] === 'download')
		{
			$download_baseUrl = Api\Vfs::download_url($content['path']);
			if ($download_baseUrl[0] == '/') $download_baseUrl = Api\Egw::link($download_baseUrl);
			$content['download_baseUrl'] = $download_baseUrl;
		}

		Api\Cache::setSession('filemanger', 'select_path', $content['path']);
		// Response
		$response->data(array(
			'content'		=> $content,
			'sel_options'	=> $sel_options,
			'readonlys'		=> $readonlys,
			'modifications'	=> array (
				'mode'   => $content['mode'],
				'method' => $content['method'],
				'id'     => $content['id'],
				'label'  => $content['label'],
				'showmime' => $content['showmime'],
				'old_path' => $content['path'],
				'old_app' => $content['app']
			)
		));
	}

	/**
	 * Get a list of files that match the given parameters
	 *
	 * Can also give a new path (like a link, not redirect), and a writable flag
	 *
	 * @param $search
	 * @param $content
	 * @return void
	 * @throws Json\Exception
	 */
	public static function ajax_vfsSelectFiles($search, $content)
	{
		$response = ['results' => []];
		$content['path'] = $content['path'] ?? '~';
		if($content['path'] == '~')
		{
			$content['path'] = $response['path'] = Api\Vfs::get_home_dir();
		}
		if(!Api\Vfs::is_readable($content['path']))
		{
			if($content['path'] && str_contains($content['path'], ':') && $path = static::get_vfs_path($content['path']))
			{
				$content['path'] = $response['path'] = $path;
			}
		}
		$response['writable'] = Api\Vfs::is_writable($content['path']);

		// Filemanager favorites as directories
		if(substr($content['path'], 0, strlen('/apps/favorites')) == '/apps/favorites')
		{
			$files = static::filesFromFavorites($search, $content);
		}
		else
		{
			$files = static::filesFromVfs($search, $content);
			if(is_string($files))
			{
				$response['message'] = $files;
				$files = [];
			}
		}
		$response['total'] = $content['total'] ?? count($files);
		foreach($files as $path)
		{
			if(is_string($path) && $path == $content['path'] || is_array($path) && $path['path'] == $content['path'])
			{
				// remove directory itself
				$response['total']--;
				continue;
			}
			$name = $path['name'] ?? Api\Vfs::basename($path);
			$path = $path['path'] ?? $path;
			$is_dir = $path['isDir'] ?? Api\Vfs::is_dir($path);
			$mime = $path['mime'] ?? Api\Vfs::mime_content_type($path);
			$download = $path['download_url'] ?? Api\Vfs::download_url($path);

			$response['results'][] = array(
				'name'  => $name,
				'path'  => $path,
				'mime'  => $mime,
				'isDir'       => $is_dir,
				'downloadUrl' => $download
			);
		}
		Json\Response::get()->data($response);
	}

	private static function filesFromVfs($search, &$params)
	{
		$vfs_options = array(
			'dirsontop' => true,
			'order'     => 'name',
			'sort'      => 'ASC',
			'maxdepth'  => 1,
		);
		if($search)
		{
			$vfs_options['name_preg'] = '/' . str_replace(array('\\?', '\\*'),
														  array('.{1}', '.*'),
														  preg_quote($search)) . '/i';
		}
		$dirs = [];
		if($params['mime'])
		{
			// Always get dirs
			$vfs_options['type'] = 'd';
			$dirs = Api\Vfs::find($params['path'], $vfs_options);
			$vfs_options['type'] = 'f';
			$vfs_options['mime'] = $params['mime'];
		}
		if($params['num_rows'])
		{
			$vfs_options['limit'] = (int)$params['num_rows'];
		}
		$files = Api\Vfs::find($params['path'], $vfs_options);
		$params['total'] = Api\Vfs::$find_total;
		return array_merge($dirs, $files);
	}

	/**
	 * Get favorites as if they were folders
	 *
	 * @return array
	 */
	private static function filesFromFavorites($search, $params)
	{

		// Display favorites as if they were folders
		$files = array();
		$favorites = Api\Framework\Favorites::get_favorites('filemanager');

		//check for recent paths and add them to the top of favorites list
		if(is_array($params['recentPaths']))
		{
			foreach($params['recentPaths'] as $p)
			{
				$mime = Api\Vfs::mime_content_type($p);
				$files[] = array(
					'name'   => $p,
					'path'   => $p,
					'mime'   => $mime,
					'is_dir' => true
				);
			}
		}

		foreach($favorites as $favorite)
		{
			$path = $favorite['state']['path'];
			if(!$path)
			{
				continue;
			}
			// Search
			if($search && !(str_contains($favorite['name'], $search) || str_contains($path, $search)))
			{
				continue;
			}
			if(!Api\Vfs::is_readable($path))
			{
				continue;
			}

			$mime = Api\Vfs::mime_content_type($path);
			$files[] = array(
				'name'  => $favorite['name'],
				'path'  => $path,
				'mime'  => $mime,
				'isDir' => true
			);
		}
		return $files;
	}

	/**
	 * function to create directory in the given path
	 *
	 * @param type $dir name of the directory
	 * @param type $path path to create directory in it
	 */
	public static function ajax_create_dir ($dir, $path)
	{
		$response = Json\Response::get();
		$msg = '';
		if (!empty($dir) && !empty($path))
		{
			$dst = Api\Vfs::concat($path, $dir);
			if (Api\Vfs::mkdir($dst, null, STREAM_MKDIR_RECURSIVE))
			{
				$msg = lang("Directory successfully created.");
			}
			else
			{
				$msg = lang("Error while creating directory.");
			}
		}
		$response->data($msg);
	}

	/**
	 * Store uploaded temp file into VFS
	 *
	 * @param array $files temp files
	 * @param string $dir vfs path to store the file
	 */
	static function ajax_vfsSelect_storeFile ($files, $dir)
	{
		$response = Json\Response::get();
		$result = array (
			'errs' => 0,
			'msg' => '',
			'files' => 0,
		);
		$script_error = 0;
		foreach($files as $tmp_name => &$data)
		{
			$path = Api\Vfs::concat($dir, Api\Vfs::encodePathComponent($data['name']));

			if(Api\Vfs::deny_script($path))
			{
				if (!isset($script_error))
				{
					$result['msg'] .= ($result['msg'] ? "\n" : '').lang('You are NOT allowed to upload a script!');
				}
				++$script_error;
				++$result['errs'];
				unset($files[$tmp_name]);
			}
			elseif (Api\Vfs::is_dir($path))
			{
				$data['confirm'] = 'is_dir';
			}
			elseif (!$data['confirmed'] && Api\Vfs::stat($path))
			{
				$data['confirm'] = true;
			}
			else
			{
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'] . '/' . basename($tmp_name);
				}
				else
				{
					$tmp_path = ini_get('upload_tmp_dir').'/'.basename($tmp_name);
				}

				if (Api\Vfs::copy_uploaded($tmp_path, $path, null, false))
				{
					++$result['files'];
					$uploaded[] = $data['name'];
				}
				else
				{
					++$result['errs'];
				}
			}
		}
		if ($result['errs'] > $script_error)
		{
			$result['msg'] .= ($result['msg'] ? "\n" : '').lang('Error uploading file!');
		}
		if ($result['files'])
		{
			$result['msg'] .= ($result['msg'] ? "\n" : '').lang('%1 successful uploaded.', implode(', ', $uploaded));
		}
		$result['uploaded'] = $files;
		$result['path'] = $dir;

		$response->data($result);
	}

	/**
	 * Get a list off all apps having an application directory in VFS
	 *
	 * @return array
	 */
	static function get_apps()
	{
		$apps = array();
		$apps += Api\Link::app_list('query');
		// they do NOT support adding files to VFS
		unset($apps['addressbook-email'], $apps['mydms'], $apps['wiki'],
				$apps['api-accounts']);
		return $apps;
	}
}