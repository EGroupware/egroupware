<?php
/**
 * EGroupware admin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

/**
 * class import_csv for admin (groups)
 */
class admin_import_groups_csv extends importexport_basic_import_csv
{


	/**
	 * actions which could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'create', 'delete');

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists' );


	protected function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{
		if($this->definition->plugin_options['conditions'])
		{
			foreach($this->definition->plugin_options['conditions'] as $condition)
			{
				switch($condition['type'])
				{
					// exists
					case 'exists' :
						$accounts = array();

						// Skip the search if the field is empty
						if($record[$condition['string']] !== '')
						{

							$accounts = $GLOBALS['egw']->accounts->search(array(
																			  'type'       => 'groups',
																			  'query'      => $record[$condition['string']],
																			  'query_type' => $condition['string']
																		  ));
						}
						// Search looks in the given field, but doesn't do an exact match
						foreach((array)$accounts as $key => $account)
						{
							if($account[$condition['string']] != $record[$condition['string']])
							{
								unset($accounts[$key]);
							}
						}
						if(is_array($accounts) && count($accounts) >= 1)
						{
							$account = current($accounts);
							// apply action to all contacts matching this exists condition
							$action = $condition['true'];
							foreach((array)$accounts as $account)
							{
								// Read full account, and copy needed info for accounts->save()
								$account = $GLOBALS['egw']->accounts->read($account['account_id']);
								$record['account_id'] = $account['account_id'];
								$record['account_firstname'] = $account['account_firstname'];
								$record['account_lastname'] = $account['account_lastname'];
								$success = $this->admin_action($action['action'], $record, $import_csv->get_current_position());
							}
						}
						else
						{
							$action = $condition['false'];
							$success = ($this->admin_action($action['action'], $record, $import_csv->get_current_position()));
						}
						break;

					// not supported action
					default :
						die('condition / action not supported!!!');
				}
				if($action['last'])
				{
					break;
				}
			}
		}
		else
		{
			// unconditional insert
			$success = $this->admin_action('insert', $record, $import_csv->get_current_position());
		}
		return $success;
	}

	protected function action($_action, importexport_iface_egw_record &$record, $record_num = 0)
	{
		// Not used, see admin_action()
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data contact data for the action
	 * @return bool success or not
	 */
	private function admin_action($_action, $_data, $record_num = 0 ) {
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
			case 'create' :
				if(count($_data['account_members']) < 1) {
					$this->errors[$record_num] = lang('You must select at least one group member.');
					return false;
				}
				$command = new admin_cmd_edit_group($_action == 'create' ? false : $_data['account_lid'], $_data);
				if($this->dry_run) {
					$this->results[$_action]++;
					return true;
				}
				try {
					$command->run();
				} catch (Exception $e) {
					$this->errors[$record_num] = $e->getMessage();
					return false;
				}
				$this->results[$_action]++;
				return true;
			default:
				throw new Api\Exception('Unsupported action');

		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Group CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Creates / updates user groups from CSV file");
	}

	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options => array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition=null)
	{
		// lets do it!
	}

	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public function get_selectors_etpl() {
		// lets do it!
	}

	/**
	 * Returns warnings that were encountered during importing
	 * Maximum of one warning message per record, but you can concatenate them if you need to
	 *
	 * @return Array (
	 *       record_# => warning message
	 * )
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Returns errors that were encountered during importing
	 * Maximum of one error message per record, but you can append if you need to
	 *
	 * @return Array (
	 *       record_# => error message
	 * )
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Returns a list of actions taken, and the number of records for that action.
	 * Actions are things like 'insert', 'update', 'delete', and may be different for each plugin.
	 *
	 * @return Array (
	 *       action => record count
	 * )
	 */
	public function get_results() {
		return $this->results;
	}
}
