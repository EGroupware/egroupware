<?php
/**
 * EGroupware - eTemplate serverside of tag list widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate tag list widget
 */
class etemplate_widget_taglist extends etemplate_widget
{
	/**
	 * Constructor
	 *
	 * Overrides parent to check for $xml first, prevents errors when instanciated without (via AJAX)
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml = '')
	{
		$this->attrs['allowFreeEntries'] = true;

		if($xml) {
			parent::__construct($xml);
		}
	}
	/**
	 * The default search goes to the link system
	 *
	 * Find entries that match query parameter (from link system) and format them
	 * as the widget expects, a list of {id: ..., label: ...} objects
	 */
	public static function ajax_search() {
		$app = $_REQUEST['app'];
		$type = $_REQUEST['type'];
		$query = $_REQUEST['query'];
		$options = array();
		if ($type == "account")
		{
			$links = accounts::link_query($query, $options);
		}
		else
		{
			$links = egw_link::query($app, $query, $options);
		}
		$results = array();
		foreach($links as $id => $name)
		{
			$results[] = array('id' => $id, 'label' => $name);
		}
		 // switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}

	/**
	 * Search for emails
	 *
	 * Uses the mail application if available, or addressbook
	 */
	public static function ajax_email() {
		// If no mail app access, use link system -> addressbook
		if(!$GLOBALS['egw_info']['apps']['mail'])
		{
			$_REQUEST['app'] = 'addressbook-email';
			return self::ajax_search();
		}

		// TODO: this should go to a BO, not a UI object
		return mail_compose::ajax_searchAddress();
	}

	/**
	 * Validate input
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);
			$allowed = etemplate_widget_menupopup::selOptions($form_name);

			foreach((array) $value as $key => $val)
			{
				if(!$this->attrs['allowFreeEntries'] && !array_key_exists($val,$allowed))
				{
					self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!",$val,implode("','",array_keys($allowed))),'');
					unset($value[$key]);
				}
				if($this->type == 'taglist-email' && !preg_match(etemplate_widget_url::EMAIL_PREG, $val))
				{
						self::set_validation_error($form_name,lang("'%1' has an invalid format",$val),'');
				}
			}
			if ($ok && $value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
		}
	}
}