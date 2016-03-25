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

/**
 * eTemplate tag list widget
 *
 * The naming convention is <appname>_<subtype>_etemplate_widget
 */
class calendar_owner_etemplate_widget extends etemplate_widget_taglist
{

	/**
	 *  Make sure all the needed select options are there
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{

		egw_framework::validate_file('.','et2_widget_owner','calendar');
		egw_framework::includeCSS('calendar');
		
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
			$accounts_type = accounts::link_query('',$account_options);
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
					'app' => lang('home-accounts')
				);
			},
			array_keys($accounts), $accounts
		);


		// Add external owners that a select account widget will not find
		foreach($value as &$owner)
		{
			// Make sure it's a string for comparison
			$owner = ''.$owner;
			if(!is_numeric($owner))
			{
				$resource = $bo->resources[substr($owner, 0,1)];
				$label = egw_link::title($resource['app'], substr($owner,1));
				$linked_owners[$resource['app']][substr($owner,1)] = $label;
			}
			else if (!in_array($owner, array_keys($accounts)))
			{
				$label = egw_link::title('home-accounts',$owner);
				$resource = array('app'=> 'home-accounts');
			}
			else
			{
				continue;
			}
			$sel_options[] = array('value' => $owner, 'label' => $label, 'app' => lang($resource['app']));
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
			$valid = $value;
		}
	}
	/**
	 * Handle ajax searches for owner across all supported resources
	 *
	 * @return Array List of matching results
	 */
	public static function ajax_owner()
	{
		$bo = new calendar_bo();

		$query = $_REQUEST['query'];
		// Arbitrarily limited to 50 / resource
		$options = array('start' => 0, 'num_rows' => 50);
		$results = array();

		$resources = array_merge(array('' => $bo->resources['']),$bo->resources);
		foreach($resources as $type => $data)
		{
			$mapped = array();
			$_results = array();

			// Handle accounts seperately
			if($type == '')
			{
				$list = array('accounts', 'owngroups');
				foreach($list as $a_type)
				{
					$account_options = $options + array('account_type' => $a_type);
					$_results += accounts::link_query($query,$account_options);
				}
				$_results = array_intersect_key($_results, $GLOBALS['egw']->acl->get_grants('calendar'));
			}
			else if ($data['app'] && egw_link::get_registry($data['app'], 'query'))
			{
				$_results = egw_link::query($data['app'], $query,$options);
			}
			if(!$_results) continue;
			$_results = array_unique($_results);

			foreach($_results as $id => $title)
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
		egw_json_request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}
}