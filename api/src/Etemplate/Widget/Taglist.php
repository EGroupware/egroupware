<?php
/**
 * EGroupware - eTemplate serverside of tag list widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013-18 Nathan Gray
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

// explicitly import old not yet ported classes
use mail_compose;

/**
 * eTemplate tag list widget
 */
class Taglist extends Etemplate\Widget
{

	/**
	 * Regexp for validating email alias considering domain part be optional
	 * this should be used regarding the domainOptional attribute defined in
	 * taglist-email.
	 */
	const EMAIL_PREG_NO_DOMAIN = "/^(([^,<][^,<]+|\042[^\042]+\042|\'[^\']+\'|)\s?<)?[^\x01-\x20()\xe2\x80\x8b<>@,;:\042\[\]]+(?<![.\s])(@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,})?>?$/iu";

	/**
	 * Constructor
	 *
	 * Overrides parent to check for $xml first, prevents errors when instanciated without (via AJAX)
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml = '')
	{
		$this->bool_attr_default = array_merge($this->bool_attr_default, array(
			'allowFreeEntries' => true,
			'useCommaKey' => true,
			'editModeEnabled' => true,
			// inherited on js-side from Selextbox
			'multiple' => true,
			'selected_first' => true,
			'search' => false,
			'tags' => false,
			'allow_single_deselect' => true,
		));

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
	public static function ajax_search()
	{
		$app = $_REQUEST['app'];
		$type = $_REQUEST['type'];
		$query = $_REQUEST['query'];
		$options = array();
		$links = array();
		if ($type == "account")
		{
			// Only search if a query was provided - don't search for all accounts
			if($query)
			{
				$options['account_type'] = $_REQUEST['account_type'];
				$links = Api\Accounts::link_query($query, $options);
			}
		}
		else
		{
			$links = Api\Link::query($app, $query, $options);
		}
		$results = array();
		foreach($links as $id => $name)
		{
			$results[] = array('id' => $id, 'label' => $name);
		}
		 // switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		exit;
	}

	/**
	 * Search for emails
	 *
	 * Uses the mail application if available, or addressbook
	 */
	public static function ajax_email()
	{
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
			$allowed = Select::selOptions($form_name);

			foreach((array) $value as $key => $val)
			{
				if(count($allowed) && !$this->attrs['allowFreeEntries'] && !array_key_exists($val,$allowed))
				{
					self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!",$val,implode("','",array_keys($allowed))),'');
					unset($value[$key]);
				}
				if($this->type == 'taglist-email' && $this->attrs['include_lists'] && is_numeric($val))
				{
					$lists = $GLOBALS['egw']->contacts->get_lists(Api\Acl::READ);
					if(!array_key_exists($val, $lists))
					{
						self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!",$val,implode("','",array_keys($lists))),'');
					}
				}
				else if($this->type == 'taglist-email' && !preg_match(Url::EMAIL_PREG, $val) &&
						!($this->attrs['domainOptional'] && preg_match (Taglist::EMAIL_PREG_NO_DOMAIN, $val)) &&
					// Allow merge placeholders.  Might be a better way to do this though.
					!preg_match('/{{.+}}|\$\$.+\$\$/',$val)
				)
				{
					self::set_validation_error($form_name,lang("'%1' has an invalid format",$val),'');
				}
			}
			if ($ok && $value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			if(array_key_exists('multiple', $this->attrs) && $this->attrs['multiple'] == false)
			{
				$value = array_shift($value);
			}
			$valid =& self::get_array($validated, $form_name, true);
			// returning null instead of array(), as array() will be overwritten by etemplate_new::complete_array_merge()
			// with preserved old content and therefore user can not empty a taglist
			if (true) $valid = $value ? $value : null;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
		}
	}
}
