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

		if (!($template = etemplate_widget_template::instance(self::$request->template['name'], self::$request->template['template_set'],
                        self::$request->template['version'], self::$request->template['load_via'])))
                {
			// Can't use callback
			error_log("Could not get template for file upload, callback skipped");
                }

		$file_data = array();
		foreach ($_FILES as $field => &$file) {
			if ($file['error'] == UPLOAD_ERR_OK && trim($file['name']) != '' && $file['size'] > 0) {
				if (is_dir($GLOBALS['egw_info']['server']['temp_dir']) && is_writable($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$new_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'egw_');
				}
				else
				{
					$new_file = $value['file']['tmp_name'].'+';
				}
				if(move_uploaded_file($file['tmp_name'], $new_file)) {
					$file['tmp_name'] = $new_file;

					// Data to send back to client
					$temp_name = basename($file['tmp_name']);
					$file_data[$temp_name] = array(
						'name' => $file['name'],
						'type' => $file['type']
					);
				}
			}
		}
		$response->data($file_data);

		// Check for a callback, call it if there is one
		foreach($_FILES as $field => $file) {
			if($element = $template->getElementById($field))
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
etemplate_widget::registerWidget('etemplate_widget_file', array('file'));
