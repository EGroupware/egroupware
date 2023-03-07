<?php
/**
 * EGroupware - eTemplate serverside of owner list widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2016 Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;

/**
 * eTemplate tag list widget
 *
 * The naming convention is <appname>_<subtype>_etemplate_widget
 */
class calendar_owner_etemplate_widget extends Etemplate\Widget\Taglist
{

	/**
	 *  Make sure all the needed select options are there
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		Framework::includeJS('/calendar/js/app.min.js');
		Framework::includeCSS('calendar', 'calendar');

		$bo = new calendar_bo();

		$form_name = self::form_name($cname, $this->id, $expand);

		$value =& self::get_array(self::$request->content, $form_name);

		if(!is_array(self::$request->sel_options[$form_name]))
		{
			self::$request->sel_options[$form_name] = array();
		}
		$sel_options =& self::$request->sel_options[$form_name];

		if($value && !is_array($value))
		{
			// set value with an empty string only if sel options are not
			// loaded, for example: setting calendar owner via URL when
			// calendar app is not yet loaded.
			$value = !empty($sel_options) ? array(): explode(',', $value);
		}

		// Add external owners that a select account widget will not find
		foreach((array)$value as $owner)
		{
			$label = self::get_owner_label($owner);
			$info = array();
			if(!is_numeric($owner))
			{
				$resource = $bo->resources[substr($owner, 0,1)];
				if($resource['info'] && !($info = $bo->resource_info($owner)))
				{
					continue;	// ignore that resource, we would get a PHP Fatal: Unsupported operand types
				}
			}
			else
			{
				$resource = array('app'=> 'api-accounts');
			}
			if ($resource && is_numeric ($owner) && (int)$owner < 0)
			{
				// Add in group memberships as strings
				$info['resources'] = array_map(function($a) { return ''.$a;},$GLOBALS['egw']->accounts->members($owner, true));
			}
			$option = static::format_owner($owner, $label, $info);
			$sel_option_index = $this->get_index($sel_options, 'value', $owner);
			if($sel_option_index === false)
			{
				$sel_options[] = $option;
			}
			else
			{
				$sel_options[$sel_option_index] = array_merge($sel_options[$sel_option_index], $option);
			}
		}
	}

	/**
	 * Get the index of an array (sel_options) containing the given value
	 *
	 * @param Array $array
	 * @param string $key key we're checking to match value
	 * @param string $value Value we're looking for
	 * @return boolean|int Returns index
	 */
	private function get_index(&$array, $key, $value)
	{
		foreach($array as $_key => $_value)
		{
			if($_value[$key] === $value) return $_key;
		}
		return false;
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

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in =& self::get_array($content, $form_name);
			if(!is_array($value))
			{
				$value = Array($value);
			}

			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}
	/**
	 * Handle ajax searches for owner across all supported resources
	 *
	 * @return Array List of matching results
	 */
	public static function ajax_owner($id = null)
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		// Handle a request for a single ID
		if($id && !is_array($id))
		{
			$label = self::get_owner_label($id);
			Api\Json\Response::get()->data($label);
			return $label;
		}
		else
		{
			if($id && is_array($id))
			{
				$labels = array();
				foreach($id as $index => $_id)
				{
					$labels[$_id] = self::format_owner($_id, self::get_owner_label($_id));
				}
				Api\Json\Response::get()->data($labels);
				return $labels;
			}
		}
	}

	public static function ajax_search($search_text=null, array $search_options = [])
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		$bo = new calendar_bo();

		// Arbitrarily limited to 50 / resource
		$options = array('start'  => 0, 'num_rows' => 50,
						 // Filter accounts out of addressbook
						 'filter' => array('account_id' => null)) +
			$search_options;
		$results = array();
		$is_admin = !empty($GLOBALS['egw_info']['user']['apps']['admin']);
		$total = 0;

		// Contacts matching accounts the user does not have permission for cause
		// confusion as user selects the contact and there's nothing there, so
		// we remove those contacts
		$remove_contacts = array();

		$resources = array_merge(array('' => $bo->resources['']), $bo->resources);
		$contacts_obj = new Api\Contacts();
		foreach($resources as $type => $data)
		{
			$mapped = array();
			$_results = array();

			// Handle Api\Accounts separately, if user is allowed to see accounts
			if($type == '' && ($is_admin || !$is_admin && $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] !== 'none'))
			{
				$owngroup_options = $options+array('account_type'=>'owngroups');
				$own_groups = Api\Accounts::link_query('',$owngroup_options);
				$account_options = $options + ['account_type' => 'both', 'tag_list' => true];
				$_results += $remove_contacts = Api\Accounts::link_query($search_text, $account_options);
				$total += $account_options['total'];
				if (!empty($_REQUEST['checkgrants']))
				{
					$grants = (array)$GLOBALS['egw']->acl->get_grants('calendar') + $own_groups;
					$_results = array_intersect_key($_results, $grants);
					// Grants reduces how many results we get.  This only works since we do accounts first.
					$total = count($_results);
				}
			}
			// App provides a custom search function
			else if ($data['app'] && $data['search'])
			{
				$_results = call_user_func_array($data['search'], array($search_text, $options));
				if(array_key_exists('total', $options))
				{
					$total += $options['total'];
				}
			}
			// Use standard link registry
			else if ($data['app'] && Link::get_registry($data['app'], 'query'))
			{
				$_results = Link::query($data['app'], $search_text, $options);
				if(array_key_exists('total', $options))
				{
					$total += $options['total'];
				}
			}

			// There are always special cases
			switch ($type)
			{
				case 'l':
					// Include mailing lists, but not account groups
					$lists = array_filter(
						$contacts_obj->get_lists(Api\Acl::READ),
						function ($element, $index) use ($search_text)
						{
							return $index > 0 && (stripos($element, $search_text) !== false);
						},
						ARRAY_FILTER_USE_BOTH
					);
					foreach($lists as $list_id => $list)
					{
						$_results[(string)$list_id] = array(
							'label' => $list,
							'resources' => $bo->enum_mailing_list($type.$list_id)
						);
					}
					break;
			}
			if(!$_results)
			{
				continue;
			}

			foreach(array_unique($_results, SORT_REGULAR) as $id => $title)
			{
				if($id && $title)
				{
					$mapped[] = static::format_owner($id, $title, $data);
				}
			}
			if(count($mapped))
			{
				$results = array_merge($results, $mapped);
			}
		}
		if($total)
		{
			$results['total'] = $total;
		}

		Api\Json\Response::get()->data($results);
	}

	/**
	 * Given an ID & title, format the result into data the client side wants
	 *
	 * @param $id
	 * @param $title
	 * @param $type
	 */
	protected static function format_owner($id, $title, $data = array())
	{
		if(!$id)
		{
			return "";
		}

		static $contacts_obj = null;
		if(is_null($contacts_obj))
		{
			$contacts_obj = new Api\Contacts();
		}
		if(!$data)
		{
			$bo = new calendar_bo();
			if(!is_numeric($id))
			{
				$data = $bo->resources[substr($id, 0, 1)];
			}
			else
			{
				$data = $bo->resources[''];
			}
		}
		$type = $data['type'];

		$value = array(
			'value' => substr($id, 0, 1) == $type ? $id : $type . $id,
			'label' => $title,
			'app' => $data['app']
		);
		if(is_array($value['label']))
		{
			$value = array_merge($value, $value['label']);
		}
		switch($type)
		{
			case 'r':
				// TODO: fetch resources photo
				break;
			case 'c':
			case '':
				// check if link-search already returned either icon or (l|f)name and only if not, query contact again
				if (!(isset($value['icon']) || isset($value['lname']) && isset($value['fname'])) &&
					($contact = $contacts_obj->read($type === '' ? 'account:'.$id : $id, true)))
				{
					if (Api\Contacts::hasPhoto($contact))
					{
						$value['icon'] = Api\Framework::link('/api/avatar.php', array(
							'contact_id' => $contact['id'],
							'etag' => $contact['etag'] ? $contact['etag'] : 1
						));
					}
					else
					{
						$value['lname'] = $contact['n_family'];
						$value['fname'] = $contact['n_given'];
					}
				}
				if($id < 0)
				{
					$value['resources'] = array_map('strval',$GLOBALS['egw']->accounts->members($id, true));
				}
				break;
			default :
				// do nothing
		}
		return $value;
	}

	/**
	 * Get just the label for a single owner
	 * @param string $id
	 */
	public static function get_owner_label($id)
	{
		static $bo=null;
		if(!$bo) $bo = new calendar_bo();

		$id = ''.$id;
		if(!is_numeric($id))
		{
			$resource = $bo->resources[substr($id, 0,1)];
			$label = Link::title($resource['app'], substr($id,1));

			// Could not get via link, try via resources info
			if($label === false)
			{
				$info = ExecMethod($resource['info'], substr($id,1));
				$label = $info[0]['name'];
			}
		}
		else
		{
			$label = Link::title('api-accounts', $id) ?: Api\Accounts::username($id);
		}
		return $label;
	}
}

Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\calendar_owner_etemplate_widget', array('et2-calendar-owner'));