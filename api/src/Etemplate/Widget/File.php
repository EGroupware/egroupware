<?php
/**
 * EGroupware - eTemplate serverside file upload widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011-18 Nathan Gray
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate file upload widget
 *
 * Uses AJAX to send file(s) to server, and stores for submit
 *
 * There is an attribute "callback" for a server-side callback in acm notification with the following signature:
 *
 * 	/**
 *	 * Callback for file or vfs-upload widgets
 *	 *
 *	 * @param array $file
 *	 * @param string $widget_id
 *	 * @param Api\Etemplate\Request $request eT2 request eg. to access attribute $content
 *	 * @param Api\Json\Response $response
 *	 *|
 *  function upload_callback(array $file, $widget_id, Api\Etemplate\Request $request, Api\Json\Response $response)
 */
class File extends Etemplate\Widget
{
	/**
	 * Constructor
	 *
	 * @param string $xml
	 */
	public function __construct($xml='')
	{
		$this->bool_attr_default = ($this->bool_attr_default ?? []) + array(
			'multiple' => false,
		);

		if($xml) parent::__construct($xml);

		// set fallback-id client-side uses
		if (empty($this->id)) $this->id = 'file_widget';

		// Legacy multiple - id ends in []
		if(substr($this->id,-2) == '[]')
		{
			self::setElementAttribute($this->id, 'multiple', true);
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
	public static function ajax_upload()
	{
		$response = Api\Json\Response::get();
		$request_id = str_replace(' ', '+', rawurldecode($_REQUEST['request_id']));
		$widget_id = $_REQUEST['widget_id'];
		if(!self::$request = Etemplate\Request::read($request_id))
		{
			$response->error("Could not read session");
			return;
		}

		try {
			if (!($template = Template::instance(self::$request->template['name'], self::$request->template['template_set'],
				self::$request->template['version'], self::$request->template['load_via'])))
			{
				// Can't use callback
				error_log("Could not get template for file upload, callback skipped");
			}
		}
		catch (\Error $e) {
			// retry 3 times, in case the problem (Call to undefined method EGroupware\Api\Etemplate\Widget\Vfs::set_attrs()) is caused by something internal in PHP 8.0
			if (!isset($_REQUEST['retry']) || $_REQUEST['retry'] < 3)
			{
				$url = Api\Header\Http::schema().'://'.Api\Header\Http::host().$_SERVER['REQUEST_URI'];
				if (strpos($url, '&retry=') === false)
				{
					$url .= '&retry=1';
				}
				else
				{
					$url = preg_replace('/&retry=\d+/', '&retry='.($_REQUEST['retry']+1), $url);
				}
				header('Location: '.$url);
				http_response_code(307);
				exit;
			}
			throw new \Error('Error instantiating template '.json_encode(self::$request->template).', $_REQUEST='.json_encode($_REQUEST).': '.$e->getMessage(), $e->getCode(), $e);
		}

		$file_data = array();

		// There should only be one file, as they're sent one at a time
		foreach ($_FILES as $field => &$files)
		{
			$widget = $template->getElementById($widget_id ? $widget_id : $field);
			$matches = null;
			// vfs-upload widget used id "app:$cont[id]:path", with $cont[id] replaces by actual id
			if (!$widget && preg_match('/^([^:]+):(\d+):(.*)$/', $widget_id, $matches))
			{
				$widget = $template->getElementById($matches[1].':$cont[id]:'.$matches[3]);
			}
			if($widget && $widget->attrs['mime'])
			{
				$mime = $widget->attrs['mime'];
			}

			// Check for legacy [] in id to indicate multiple - it changes format
			if(is_array($files['name']))
			{
				$file_list = array();
				foreach($files as $f_field => $values)
				{
					foreach($values as $key => $f_value) {
						$file_list[$key][$f_field] = $f_value;
					}
				}
				foreach($file_list as $file)
				{
					static::process_uploaded_file($field, $file, $mime, $file_data);
				}
			}
			else
			{
				// Just one file
				static::process_uploaded_file($field, $files, $mime, $file_data);
			}
			// Check for a callback, call it if there is one
			if ($widget)
			{
				$callback = $widget->attrs['callback'];
				if(!$callback) $callback = $template->getElementAttribute($field, 'callback');
				if($callback)
				{
					ExecMethod2($callback, $_FILES[$field], $widget_id, self::$request, $response);
				}
			}
		}

		// Set up response
		$response->data($file_data);
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
			// Don't trust what the browser tells us for mime
			if(function_exists('mime_content_type'))
			{
				$file['type'] = $type = Api\MimeMagic::analyze_file($file['tmp_name']);
			}

			// Mime check (can only work for the first chunk, further ones will always fail!)
			if ($mime && (int)$_POST['resumableChunkNumber'] === 1)
			{
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
						$_POST['resumableTotalSize']);
			}
			if( $new_file) {
				$file['tmp_name'] = $new_file;

				// Data to send back to client
				$temp_name = basename($file['tmp_name']);
				$file_data[$temp_name] = array(
					// Use egw_vfs to avoid UTF8 / non-ascii issues
					'name' => Api\Vfs::basename($file['name']),
					'type' => $file['type']
				);
			}
		}
		return true;
	}

	/**
	 *
	 * Check if all the parts exist, and
	 * gather all the parts of the file together
	 *
	 * From Resumable samples - http://resumablejs.com/
	 * @param string $temp_dir - the temporary directory holding all the parts of the file
	 * @param string $fileName - the original file name
	 * @param string $totalSize - original file size (in bytes)
	 */
	private static function createFileFromChunks($temp_dir, $fileName, $totalSize) {

		// count all the parts of this file
		$total_files = $sum_size = 0;
		foreach(scandir($temp_dir) as $file) {
			if (stripos($file, $fileName) !== false) {
				$total_files++;
				$sum_size += filesize($temp_dir.'/'.$file);
			}
		}

		// check that all the parts are present
		// the size of the last part is between chunkSize and 2*$chunkSize
		if ($sum_size >= $totalSize)
		{
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
				error_log(__METHOD__ . ' cannot create the destination file "'.$new_file.'"');
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
	private static function rrmdir($dir)
	{
		if (is_dir($dir))
		{
			foreach (scandir($dir) as $object)
			{
				if ($object != "." && $object != "..")
				{
					if (filetype($dir . "/" . $object) == "dir")
					{
						self::rrmdir($dir . "/" . $object);
					}
					else
					{
						unlink($dir . "/" . $object);
					}
				}
			}
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
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			if(!is_array($value)) $value = array();

			// Incoming values indexed by temp name
			if($value[0]) $value = $value[0];

			foreach($value as $tmp => $file)
			{
				if(!$file || !is_array($file)) continue;
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

	/**
	 * Set default chunk_size attribute to (max_upload_size-1M)/2
	 *
	 * Last chunk can be 2*chunk_size, therefore we can only set max_upload_size/2
	 * minus "some" for other transferred fields.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		$upload_max_filesize = ini_get('upload_max_filesize');
		$unit = strtolower(substr($upload_max_filesize, -1));
		$upload_max_filesize = (float)$upload_max_filesize;
		if (!is_numeric($unit)) $upload_max_filesize *= $unit === 'm' ? 1024*1024 : 1024;
		if ($upload_max_filesize > 1024*1024)
		{
			self::setElementAttribute($form_name, 'chunk_size', ($upload_max_filesize-1024*1024)/2);
		}
	}
}