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
		if($this->type == 'vfs-upload')
		{
echo "EXPAND";
_debug_array($expand);
			$form_name = self::form_name($cname, $this->id, $expand ? $expand : array('cont'=>self::$request->content));

echo "this-ID: {$this->id}<br />";
echo "Form name: $form_name<br />";
			// ID maps to path - check there for any existing files
			list($app,$id,$relpath) = explode(':',$this->id,3);
			if($app && $id)
			{
echo "ID: $id<br />";
				if(!is_numeric($id))
				{
					$_id = self::expand_name($id,0,0,0,0,self::$request->content);
					if($_id != $id)
					{
						$id = $_id;
						$form_name = "$app:$id:$relpath";
echo "Form name: $form_name<br />";
					}
				}
				$value =& self::get_array(self::$request->content, $form_name, true);
echo "ID: $id<br />";
				$path = egw_link::vfs_path($app,$id,'',true);
				if (!empty($relpath)) $path .= '/'.$relpath;

				// Single file, already existing
				if (substr($path,-1) != '/' && egw_vfs::file_exists($path) && !egw_vfs::is_dir($path))
				{
					$value = $path;
				}
				else if (substr($path, -1) == '/' && egw_vfs::is_dir($path))
				{
					$value = egw_vfs::scandir($path);
echo 'HERE!';
					foreach($value as &$file)
					{
echo $file.'<br />';
						$file = egw_vfs::stat("$path$file");
_debug_array($file);
					}
				}
			}
echo $this;
_debug_array($value);
		}
	}

	public static function ajax_upload() {
		parent::ajax_upload();
		error_log(array2string($_FILES));
		foreach($_FILES as $field => $file)
		{
			self::store_file($field, $file);
		}
	}

	/**
         * Ajax callback to receive an incoming file
         *
         * The incoming file is automatically placed into the appropriate VFS location.
	 * If the entry is not yet created, the file information is stored into the widget's value.
	 * When the form is submitted, the information for all files uploaded is available in the returned
	 * $content array and the application should deal with the file.
         */
	public static function store_file($path, $file) {
error_log(array2string($file));
		$name = $path;

		// Find real path
		if($path[0] != '/')
		{
			$path = self::get_vfs_path($path, $file['name']);
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
error_log(lang('Error create parent directory %1!',egw_vfs::decodePath($dir)));
			return false;
		}
		if (!copy($file['tmp_name'],egw_vfs::PREFIX.$path))
		{
			self::set_validation_error($name,lang('Error copying uploaded file to vfs!'));
error_log(lang('Error copying uploaded file to vfs!'));
			return false;
		}

		// Try to remove temp file
		unlink($file['tmp_name']);
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
				// Check & skip files that made it asyncronously
				list($app,$id,$relpath) = explode(':',$this->id,3);
		//...
				foreach($value as $tmp => $file)
				{
					if(egw_vfs::file_exists(self::get_vfs_path($id) . $relpath)) {}
				}
				parent::validate($cname, $content, $validated);
				break;
		}
		$valid = $value;
	}

	/**
	 * Change an ID like app:id:relative/path to an actual VFS location
	 */
	public static function get_vfs_path($path) {
		list($app,$id,$relpath) = explode(':',$path,3);
		if (empty($id) || $id == 'undefined')
		{
			static $tmppath = array();      // static var, so all vfs-uploads get created in the same temporary dir
			if (!isset($tmppath[$app])) $tmppath[$app] = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.'.$app.'_'.md5(time().session_id());
			$path = $tmppath[$app];
			unset($cell['onchange']);       // no onchange, if we have to use a temporary dir
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
