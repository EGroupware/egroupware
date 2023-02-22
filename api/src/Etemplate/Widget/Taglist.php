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
	 * @param string|\XMLReader $xml string with xml or XMLReader positioned on the element to construct
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
	public static function ajax_search($search_text=null, array $search_options = [])
	{
		$app = $_REQUEST['app'];
		$type = $_REQUEST['type'];
		$query = $search_text ?? $_REQUEST['query'];
		$options = $search_options;
		$results = [];
		if (empty($query))
		{
			// do NOT search without a query
		}
		elseif($type === "account")
		{
			$options['account_type'] = $_REQUEST['account_type'];
			$options['tag_list'] = true;
			$results = Api\Accounts::link_query($query, $options);
		}
		else
		{
			foreach(Api\Link::query($app, $query, $options) as $id => $name)
			{
				$results[] = ['value' => $id, 'label' => $name];
			}
		}
		usort($results, static function ($a, $b) use ($query)
		{
			similar_text($query, $a["label"], $percent_a);
			similar_text($query, $b["label"], $percent_b);
			return $percent_a === $percent_b ? 0 : ($percent_a > $percent_b ? -1 : 1);
		});

		// If we have a total, include it too so client knows if results were limited
		if(array_key_exists('total', $options))
		{
			$results['total'] = intval($options['total']);
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
	public static function ajax_email($search=null, array $options=null)
	{
		$_REQUEST['query'] = $_REQUEST['query'] ?: $search;
		// If no mail app access, use link system -> addressbook
		if(empty($GLOBALS['egw_info']['apps']['mail']))
		{
			$_REQUEST['app'] = 'addressbook-email';
			return self::ajax_search();
		}

		// TODO: this should go to a BO, not a UI object
		$_REQUEST['include_lists'] = $options['includeLists'] ?? false;
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
				if($this->type == 'taglist-account' && !$this->attrs['allowFreeEntries'])
				{
					// If in allowed options, skip account check to support app-specific options
					if(count($allowed) > 0 && in_array($val, $allowed)) continue;
					// validate accounts independent of options know to server
					$account_type = $this->attrs['accountType'] ?? $this->attrs['account_type'] ?? 'accounts';
					$type = $GLOBALS['egw']->accounts->exists($val);
					//error_log(__METHOD__."($cname,...) form_name=$form_name, widget_type=$widget_type, account_type=$account_type, type=$type");
					if (!$type || $type == 1 && in_array($account_type, array('groups', 'owngroups', 'memberships')) ||
						$type == 2 && $account_type == 'users' ||
						in_array($account_type, array('owngroups', 'memberships')) &&
							!in_array($val, $GLOBALS['egw']->accounts->memberships(
								$GLOBALS['egw_info']['user']['account_id'], true
							)
							)
					)
					{
						self::set_validation_error($form_name, lang("'%1' is NOT allowed ('%2')!", $val,
																	!$type ? 'not found' : ($type == 1 ? 'user' : 'group')
						),                         ''
						);
						$value = '';
						break;
					}
					continue;
				}
				if(count($allowed) && !$this->attrs['allowFreeEntries'] && empty($this->attrs['searchUrl']) && !array_key_exists($val, $allowed))
				{
					self::set_validation_error($form_name, lang("'%1' is NOT allowed ('%2')!", $val, implode("','", array_keys($allowed))), '');
					unset($value[$key]);
				}
				if(str_contains($this->type, 'email') && ($this->attrs['includeLists'] ?? $this->attrs['include_lists']) && is_numeric($val))
				{
					$lists = $GLOBALS['egw']->contacts->get_lists(Api\Acl::READ);
					if(!array_key_exists($val, $lists))
					{
						self::set_validation_error($form_name, lang("'%1' is NOT allowed ('%2')!", $val, implode("','", array_keys($lists))), '');
					}
				}
				else
				{
					if($val !== '' && str_contains($this->type, 'email') && !preg_match(Url::EMAIL_PREG, $val) &&
						!($this->attrs['domainOptional'] && preg_match(Taglist::EMAIL_PREG_NO_DOMAIN, $val)) &&
						// Allow merge placeholders.  Might be a better way to do this though.
						!preg_match('/{{.+}}|\$\$.+\$\$/', $val)
					)
					{
						self::set_validation_error($form_name, lang("'%1' has an invalid format", $val), '');
					}
				}
			}
			if ($ok && $value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			if(array_key_exists('multiple', $this->attrs) && $this->attrs['multiple'] == false && is_array($value))
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

Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\Taglist', array(
	'taglist', 'et2-select-email', 'et2-select-thumbnail'));