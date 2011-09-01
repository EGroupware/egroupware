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

	public function __construct($xml='') {
		if($xml) parent::__construct($xml);
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
		$request_id = str_replace(' ', '+', rawurldecode($_REQUEST['request_id']));
		$widget_id = $_REQUEST['widget_id'];
		if(!self::$request = etemplate_request::read($request_id)) {
			$response->error("Could not read session");
			return;
		}
		foreach ($_FILES as $field => $file) {
			if ($file['error'] == UPLOAD_ERR_OK) {
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$new_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'egw_');
				}
				else
				{
					$new_file = $value['file']['tmp_name'].'+';
				}
				// Files come from ajax Base64 encoded

				$handle = fopen($new_file, 'w');
				list($prefix, $data) = explode(',', file_get_contents($file['tmp_name']));
				$file['tmp_name'] = $new_file;
				fwrite($handle, base64_decode($data));
				fclose($handle);

				// Store info for future submit
				$data = egw_session::appsession($request_id.'_files');
				$form_name = self::form_name($cname, $field);
				$data[$form_name][] = $file;
				egw_session::appsession($request_id.'_files','',$data);
			}
		}
	}

	/**
	 * Validate input
	 * Merge any already uploaded files into the content array
	 *
	 * @param string $cname current namespace
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id);
		$value = $value_in = self::get_array($content, $form_name);
		$valid =& self::get_array($validated, $form_name, true);

		$files = egw_session::appsession(self::$request->id().'_files');
		$valid = $files[$form_name];
	}
}
etemplate_widget::registerWidget('etemplate_widget_file', array('file'));
