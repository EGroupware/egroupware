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

	public function __construct($xml='') {
		if($xml) parent::__construct($xml);
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
		// Find real path
		if($path[0] != '/')
		{
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
			etemplate_old::set_validation_error($name,lang('Error create parent directory %1!',egw_vfs::decodePath($dir)));
error_log(lang('Error create parent directory %1!',egw_vfs::decodePath($dir)));
			return false;
		}
		if (!copy($file['tmp_name'],egw_vfs::PREFIX.$path))
		{
			etemplate_old::set_validation_error($name,lang('Error copying uploaded file to vfs!'));
error_log(lang('Error copying uploaded file to vfs!'));
			return false;
		}
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
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = $value_in = self::get_array($content, $form_name);
		$valid =& self::get_array($validated, $form_name, true);

		if(!is_array($value)) $value = array();
		foreach($value as $tmp => $file)
		{
			if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
			{
				$path = $GLOBALS['egw_info']['server']['temp_dir'].'/'.$tmp;
			}
			else
			{
				$path = $tmp.'+';
			}
			$stat = stat($path);
			$valid[] = array(
				'name'	=> $file['name'],
				'type'	=> $file['type'],
				'tmp_name'	=> $path,
				'error'	=> UPLOAD_ERR_OK, // Always OK if we get this far
				'size'	=> $stat['size'],
				'ip'	=> $_SERVER['REMOTE_ADDR'], // Assume it's the same as for when it was uploaded...
			);
		}

		if($valid && !$this->attrs['multiple']) $valid = $valid[0];
	}
}
etemplate_widget::registerWidget('etemplate_widget_vfs', array('vfs-upload'));
