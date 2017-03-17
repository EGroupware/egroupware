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

		Framework::includeJS('.','et2_widget_owner','calendar');
		Framework::includeCSS('calendar','calendar');

		$bo = new calendar_bo();

		$form_name = self::form_name($cname, $this->id, $expand);

		$value =& self::get_array(self::$request->content, $form_name);
		if(!is_array($value)) $value = array();
		if (!is_array(self::$request->sel_options[$form_name]))
		{
			self::$request->sel_options[$form_name] = array();
		}
		$sel_options =& self::$request->sel_options[$form_name];

		// Get user accounts, formatted nicely for grouping and matching
		// the ajax call calendar_uiforms->ajax_owner() - users first
		$accounts = array();
		$list = array('accounts', 'owngroups');
		foreach($list as $type)
		{
			$account_options = array('account_type' => $type);
			$accounts_type = Api\Accounts::link_query('',$account_options);
			if($type == 'accounts')
			{
				$accounts_type = array_intersect_key($accounts_type, $GLOBALS['egw']->acl->get_grants('calendar'));
			}
			$accounts += $accounts_type;
		}
		$sel_options += array_map(
			function($account_id, $account_name) {
				return array(
					'value' => ''.$account_id,
					'label' => $account_name,
					'app' => lang('api-accounts')
				);
			},
			array_keys($accounts), $accounts
		);


		// Add external owners that a select account widget will not find
		foreach($value as &$owner)
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
			else if (!in_array($owner, array_keys($accounts)))
			{
				$resource = array('app'=> 'api-accounts');
			}
			else
			{
				continue;
			}
			$sel_options[] = array('value' => $owner, 'label' => $label, 'app' => lang($resource['app'])) + $info;
		}
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
		// Handle a request for a single ID
		if($id)
		{
			$label = self::get_owner_label($id);
			Api\Json\Response::get()->data($label);
			return $label;
		}

		$bo = new calendar_bo();
		$query = $_REQUEST['query'];

		// Arbitrarily limited to 50 / resource
		$options = array('start' => 0, 'num_rows' => 50,
			// Filter accounts out of addressbook
			'filter' => array('account_id' => null)) +
			array_diff_key($_REQUEST, array_flip(array('menuaction','query')));
		$results = array();

		// Contacts matching accounts the user does not have permission for cause
		// confusion as user selects the contact and there's nothing there, so
		// we remove those contacts
		$remove_contacts = array();

		$resources = array_merge(array('' => $bo->resources['']),$bo->resources);
		foreach($resources as $type => $data)
		{
			$mapped = array();
			$_results = array();

			// Handle Api\Accounts seperately
			if($type == '')
			{
				$account_options = $options + array('account_type' => 'both');
				$_results += $remove_contacts = Api\Accounts::link_query($query, $account_options);
				if (!empty($_REQUEST['checkgrants']))
				{
					$grants = $GLOBALS['egw']->acl->get_grants('calendar');
					$_results = array_intersect_key($_results, $grants);
				}
			}
			// App provides a custom search function
			else if ($data['app'] && $data['search'])
			{
				$_results = call_user_func_array($data['search'], array($query, $options));
			}
			// Use standard link registry
			else if ($data['app'] && Link::get_registry($data['app'], 'query'))
			{
				$_results = Link::query($data['app'], $query,$options);
			}

			// There are always special cases
			switch ($type)
			{
				case 'l':
					// Include mailing lists
					$contacts_obj = new Api\Contacts();
					$lists = array_filter(
						$contacts_obj->get_lists(Api\Acl::READ),
						function($element) use($query) {
							return (stripos($element, $query) !== false);
						}
					);
					foreach($lists as $list_id => $list)
					{
						$_results[$list_id] = array(
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
					// Magicsuggest uses id, not value.
					$value = array(
						'id' => $type.$id,
						'value'=> $type.$id,
						'label' => $title,
						'app'	=> lang($data['app'])
					);
					if(is_array($value['label']))
					{
						$value = array_merge($value, $value['label']);
					}
					$mapped[] = $value;
				}
			}
			if(count($mapped))
			{
				$results = array_merge($results, $mapped);
			}
		}

		// switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		exit();
	}

	/**
	 * Get just the label for a single owner
	 * @param string $id
	 */
	public static function get_owner_label($id)
	{
		static $bo;
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
			$label = Link::title('api-accounts',$id);
		}
		return $label;
	}
}