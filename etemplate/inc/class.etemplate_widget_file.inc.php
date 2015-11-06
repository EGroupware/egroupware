<?php
/**
 * EGroupware - eTemplate serverside file upload widget
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
 * eTemplate file upload widget
 * Uses AJAX to send file(s) to server, and stores for submit
 */
class etemplate_widget_file extends etemplate_widget
{
	/**
	 * Constructor
	 *
	 * @param string $xml
	 */
	public function __construct($xml='')
	{
		if($xml) parent::__construct($xml);

		// Legacy multiple - id ends in []
		if(substr($this->id,-2) == '[]')
		{
			$this->setElementAttribute($this->id, 'multiple', true);
		}
	}

	/**
	 * Ajax callback to receive an incoming file
	 *
	 * The incoming file is moved from its temporary location (otherwise server will delete it) and
	 * the file information is stored into the widget's value.  When the form is submitted, the information for all
	 * files uploaded is available in the returned $content array.  Because files are uploaded asynchronously,
	 * submission should be quick.
	 *
	 * @note Currently, no attempt is made to clean up files automatically.
	 */
	public static function ajax_upload() {
		$response = egw_json_response::get();
		$request_id = urldecode($_REQUEST['request_id']);
		$widget_id = $_REQUEST['widget_id'];
		if(!self::$request = etemplate_request::read($request_id)) {
			$response->error("Could not read session");
			return;
		}

		if (!($template = etemplate_widget_template::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			// Can't use callback
			error_log("Could not get template for file upload, callback skipped");
		}

		$file_data = array();

		// There should only be one file, as they're sent one at a time
		foreach ($_FILES as $field => &$files)
		{
			$widget = $template->getElementById($widget_id ? $widget_id : $field);
			if($widget && $widget->attrs['mime']) {
				$mime = $widget->attrs['mime'];
			}

			// Check for legacy [] in id to indicate multiple - it changes format
			if(is_array($files['name'])) {
				$file_list = array();
				foreach($files as $f_field => $values)
				{
					foreach($values as $key => $f_value) {
						$file_list[$key][$f_field] = $f_value;
					}
				}
				foreach($file_list as $file)
				{
					self::process_uploaded_file($field, $file, $mime, $file_data);
				}
			}
			else
			{
				// Just one file
				self::process_uploaded_file($field, $files, $mime, $file_data);
			}
		}

		// Set up response
		$response->data($file_data);

		// Check for a callback, call it if there is one
		foreach($_FILES as $field => $file)
		{
			if(($element = $template->getElementById($field)))
			{
				$callback = $element->attrs['callback'];
				if(!$callback) $callback = $template->getElementAttribute($field, 'callback');
				if($callback)
				{
					ExecMethod($callback, $_FILES[$field]);
				}
			}
		}
	}

	/**
	 * Process one uploaded file.  There should only be one per request...
	 */
	protected static function process_uploaded_file($field, Array &$file, $mime, Array &$file_data)
	{
		unset($field);	// not used

		// Chunks get mangled a little
		if($file['name'] == 'blob')
		{
			$file['name'] = $_POST['resumableFilename'];
			$file['type'] = $_POST['resumableType'];
		}

		if ($file['error'] == UPLOAD_ERR_OK && trim($file['name']) != '' && $file['size'] > 0 && is_uploaded_file($file['tmp_name'])) {
			// Mime check
			if($mime)
			{
				$type = $file['type'];
				$is_preg = $mime[0] == '/';
				if (!$is_preg && strcasecmp($mime,$type) ||
					$is_preg && !preg_match($mime,$type))
				{
					$file_data[$file['name']] = $file['name'].':'.lang('File is of wrong type (%1 != %2)!',$type,$mime);
					//error_log(__METHOD__.__LINE__.array2string($file_data[$file['name']]));
					return false;
				}
			}

			// Resumable / chunked uploads
			// init the destination file (format <filename.ext>.part<#chunk>
			// the file is stored in a temporary directory
			$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'].'/'.str_replace('/','_',$_POST['resumableIdentifier']);
			$dest_file = $temp_dir.'/'.str_replace('/','_',$_POST['resumableFilename']).'.part'.(int)$_POST['resumableChunkNumber'];

			// create the temporary directory
			if (!is_dir($temp_dir))
			{
				mkdir($temp_dir, 0755, true);
			}

			// move the temporary file
			if (!move_uploaded_file($file['tmp_name'], $dest_file))
			{
				$file_data[$file['name']] = 'Error saving (move_uploaded_file) chunk '.(int)$_POST['resumableChunkNumber'].' for file '.$_POST['resumableFilename'];
			}
			else
			{
				// check if all the parts present, and create the final destination file
				$new_file = self::createFileFromChunks($temp_dir, str_replace('/','_',$_POST['resumableFilename']),
						$_POST['resumableChunkSize'], $_POST['resumableTotalSize']);
			}
			if( $new_file) {
				$file['tmp_name'] = $new_file;

				// Data to send back to client
				$temp_name = basename($file['tmp_name']);
				$file_data[$temp_name] = array(
					// Use egw_vfs to avoid UTF8 / non-ascii issues
					'name' => egw_vfs::basename($file['name']),
					'type' => $file['type']
				);
			}
		}
	}

	/**
	 *
	 * Check if all the parts exist, and
	 * gather all the parts of the file together
	 *
	 * From Resumable samples - http://resumablejs.com/
	 * @param string $temp_dir - the temporary directory holding all the parts of the file
	 * @param string $fileName - the original file name
	 * @param string $chunkSize - each chunk size (in bytes)
	 * @param string $totalSize - original file size (in bytes)
	 */
	private static function createFileFromChunks($temp_dir, $fileName, $chunkSize, $totalSize) {

		// count all the parts of this file
		$total_files = 0;
		foreach(scandir($temp_dir) as $file) {
			if (stripos($file, $fileName) !== false) {
				$total_files++;
			}
		}

		// check that all the parts are present
		// the size of the last part is between chunkSize and 2*$chunkSize
		if ($total_files * $chunkSize >=  ($totalSize - $chunkSize + 1)) {
			if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
			{
				$new_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'egw_');
			}
			else
			{
				$new_file = $file['tmp_name'].'+';
			}

			// create the final destination file
			if (($fp = fopen($new_file, 'w')) !== false) {
				for ($i=1; $i<=$total_files; $i++) {
					fwrite($fp, file_get_contents($temp_dir.'/'.$fileName.'.part'.$i));
				}
				fclose($fp);
			} else {
				_log('cannot create the destination file');
				return false;
			}

			// rename the temporary directory (to avoid access from other
			// concurrent chunks uploads) and than delete it
			if (rename($temp_dir, $temp_dir.'_UNUSED')) {
				self::rrmdir($temp_dir.'_UNUSED');
			} else {
				self::rrmdir($temp_dir);
			}

			return $new_file;
		}

		return false;
	}

	/**
	* Delete a directory RECURSIVELY
	* @param string $dir - directory path
	* @link http://php.net/manual/en/function.rmdir.php
	*/
   private static function rrmdir($dir) {
	   if (is_dir($dir)) {
		   $objects = scandir($dir);
		   foreach ($objects as $object) {
			   if ($object != "." && $object != "..") {
				   if (filetype($dir . "/" . $object) == "dir") {
					   rrmdir($dir . "/" . $object);
				   } else {
					   unlink($dir . "/" . $object);
				   }
			   }
		   }
		   reset($objects);
		   rmdir($dir);
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

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			if(!is_array($value)) $value = array();

			// Incoming values indexed by temp name
			if($value[0]) $value = $value[0];

			foreach($value as $tmp => $file)
			{
				if(!$file) continue;
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
}
etemplate_widget::registerWidget('etemplate_widget_file', array('file'));
