<?php
/**
 * EGroupWare - Plugin to import events from a CSV file
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;

/**
 * class import_csv for calendar
 */
class calendar_import_csv extends importexport_basic_import_csv  {

	/**
	 * actions wich could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'insert' );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array('exists');

	/**
	* For figuring out if an entry has changed
	*/
	protected $tracking;

	/**
	 * List of import warnings
	 */
	protected $warnings = array();

	/**
	 * Set up import
	 * @param importexport_definition $definition
	 * @param importexport_import_csv|null $import_csv
	 */
	protected function init(importexport_definition $definition, importexport_import_csv $import_csv=NULL)
	{
		// fetch the addressbook bo
		$this->bo = new calendar_boupdate();

		// Get the tracker for changes
		$this->tracking = new calendar_tracking();

		// Used for participants
		$this->status_map = array_flip(array_map('lang', $this->bo->verbose_status));
		$this->role_map = array_flip($this->bo->roles);

		$this->lookups = array(
			'priority'   => array(
				0 => lang('None'),
				1 => lang('Low'),
				2 => lang('Normal'),
				3 => lang('High')
			),
			'recurrence' => $this->bo->recur_types
		);
	}

	/**
	 * imports a single entry according to given definition object.
	 * Handles the conditions and the actions taken.
	 *
	 * @param calendar_egw_record record The egw_record object being imported
	 * @param importexport_iface_import_record import_csv Import object contains current state
	 *
	 * @return boolean success
	 */
	public function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{
		if(!is_a($record, calendar_egw_record::class))
		{
			throw new TypeError();
		}
		// set eventOwner
		$options =& $this->definition->plugin_options;

		// Check options & set target calendar
		// Make sure Owner from import dialog is not array
		if(is_array($options['owner']))
		{
			$options['owner'] = array_pop($options['owner']);
		}
		if($options['owner'] == '')
		{
			$options['owner_from_csv'] = true;
		}
		$options['owner'] = $options['owner'] ?? $this->user;

		// Set owner, unless it's supposed to come from CSV file
		if($options['owner_from_csv'])
		{
			if(!is_numeric($record->owner))
			{
				$this->errors[$import_csv->get_current_position()] = lang(
					'Invalid owner ID: %1.  Might be a bad field translation.  Used %2 instead.',
					$record->owner,
					$options['owner']
				);
				$record->owner = $options['owner'];
			}
		}
		else
		{
			$record->owner = $options['owner'];
		}

		// Handle errors in length or start/end date
		if(is_numeric($record->start) && is_numeric($record->end) && $record->start > $record->end)
		{
			$record->end = $record->start + $GLOBALS['egw_info']['user']['preferences']['calendar']['defaultlength'] * 60;
			$this->warnings[$import_csv->get_current_position()] = lang('error: starttime has to be before the endtime !!!');
		}

		// Parse particpants
		if ($record->participants && !is_array($record->participants)) {
			$warning = '';
			$record->participants = $this->parse_participants($record, $warning);
			if($warning)
			{
				$this->warnings[$import_csv->get_current_position()] = $warning;
			}
		}

		if($record->recurrence)
		{
			$start = new Api\DateTime($record->start);
			try
			{
				$rrule = calendar_rrule::from_string($record->recurrence, $start);
				$record->recur_type = $rrule->type;
				$record->recur_interval = $rrule->interval;
				$record->recur_enddate = $rrule->enddate;
				$record->recur_data = $rrule->weekdays;
				$record->recur_exception = $rrule->exceptions;
			}
			catch (Exception $e)
			{
				// Try old way from export using just recur_type / interval
				list($record->recur_type, $record->recur_interval) = explode('/',$record->recurrence,2);
				$record->recur_interval = trim($record->recur_interval);
				$record->recur_type = array_search(strtolower(trim($record->recur_type)), array_map('strtolower',$this->lookups['recurrence']));
			}
			unset($record->recurrence);
		}
		$record->tzid = calendar_timezones::id2tz($record->tz_id);

		if ( $options['conditions'] ) {
			foreach ( $options['conditions'] as $condition ) {
				$records = array();
				switch ( $condition['type'] ) {
					// exists
					case 'exists' :
						// Check for that record
						$result = $this->exists($record, $condition, $records);

						if ( is_array( $records ) && count( $records ) >= 1) {
							// apply action to all records matching this exists condition
							$action = $condition['true'];
							foreach ( (array)$records as $event ) {
								$record->id = $event['id'];
								if ( $this->definition->plugin_options['update_cats'] == 'add' ) {
									if ( !is_array( $record->category ) ) $record->category = explode( ',', $record->category );
									$record->category = implode( ',', array_unique( array_merge( $record->category, $event['category'] ) ) );
								}
								$success = $this->action(  $action['action'], $record, $import_csv->get_current_position() );
							}
						} else {
							$action = $condition['false'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						}
						break;

					// not supported action
					default :
						die('condition / action not supported!!!');
						break;
				}
				if ($action['last']) break;
			}
		} else {
			// unconditional insert
			$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
		}

		return $success;
	}

	/**
	 * Parse participants field into calendar resources
	 *
	 * @param string $participants
	 *
	 * @return array
	 */
	protected function parse_participants($record, &$warnings)
	{
		// Importing participants in human friendly format:
		// Name (quantity)? (status) Role[, Name (quantity)? (status) Role]+
		$statuses = implode('|', array_keys($this->status_map));
		//echo ('/((?<name>.+?)(?: \((?<quantity>[\d]+)\))? \((?<status>'.$statuses.')\)(?: (?<role>[^ ,]+))?)(?:, )?/');
		preg_match_all('/((?<name>.+?)(?: \((?<quantity>[\d]+)\))? \((?<status>'.$statuses.')\)(?: (?<role>[^ ,]+))?)(?:, )?/i',$record->participants,$participants);
		$p_participants = array();
		$missing = array();

		list($lines, $p, $names, $quantity, $status, $role) = $participants;
		foreach($names as $key => $name) {
			//echo (__METHOD__ ." Name: $name Quantity: {$quantity[$key]} Status: {$status[$key]} Role: {$role[$key]}");

			// Search for direct account name, then user in accounts first
			$search = "\"$name\"";
			$id = importexport_helper_functions::account_name2id($name);

			// If not found, or not an exact match to a user (account_name2id is pretty generous)
			if(!$id || $names[$key] !== $this->bo->participant_name($id)) {
				$contacts = ExecMethod2('addressbook.addressbook_bo.search', $search,array('contact_id','account_id'),'org_name,n_family,n_given,cat_id,contact_email','','%',false,'OR',array(0,1));
				if($contacts) $id = $contacts[0]['account_id'] ? $contacts[0]['account_id'] : 'c'.$contacts[0]['contact_id'];
			}
			if(!$id)
			{
				// Use calendar's registered resources to find participant
				foreach($this->bo->resources as $resource)
				{
					// Can't search for email
					if($resource['app'] == 'email') continue;
					// Special resource search, since it does special stuff in link_query
					if($resource['app'] == 'resources')
					{
						if(!$this->resource_so)
						{
							$this->resource_so = new resources_so();
						}
						$result = $this->resource_so->search($search,'res_id');
						if($result && count($result) >= 1) {
							$id = $resource['type'].$result[0]['res_id'];
							break;
						}
					}
					else
					{
						// Search app via link query
						$link_options = array();
						$result = Link::query($resource['app'], $search, $link_options);

						if($result)
						{
							$id = $resource['type'] . key($result);
							break;
						}
					}
				}
			}
			if($id) {
				$p_participants[$id] = calendar_so::combine_status(
					$this->status_map[lang($status[$key])] ? $this->status_map[lang($status[$key])] : $status[$key][0],
					$quantity[$key] ? $quantity[$key] : 1,
					$this->role_map[lang($role[$key])] ? $this->role_map[lang($role[$key])] : $role[$key]
				);
			}
			else
			{
				$missing[] = $name;
			}
			if(count($missing) > 0)
			{
				$warnings = $record->title . ' ' . lang('participants') . ': ' .
					lang('Contact not found!') . '<br />'.implode(", ",$missing);
			}
		}
		return $p_participants;
	}

	/**
	 * Search for matching records, based on the the given condition
	 *
	 * @param record
	 * @param condition array = array('string' => field name)
	 * @param matches - On return, will be filled with matching records
	 *
	 * @return boolean
	 */
	protected function exists(importexport_iface_egw_record &$record, Array &$condition, &$records = array())
	{
		if($record->__get($condition['string']) && $condition['string'] == 'id') {
			$event = $this->bo->read($record->__get($condition['string']));
			$records = array($event);
		}

		if ( is_array( $records ) && count( $records ) >= 1) {
			return true;
		}
		return false;
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data record data for the action
	 * @return bool success or not
	 */
	protected function action ( $_action, importexport_iface_egw_record &$record, $record_num = 0 )
	{
		$_data = $record->get_record_array();
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bo->read($_data['id']);

				// Don't change a user account into a record
				if(!$this->definition->plugin_options['change_owner']) {
					// Don't change owner of an existing record
					unset($_data['owner']);
				}

				// Merge to deal with fields not in import record
				if($old)
				{
					$_data = array_merge($old, $_data);
					$changed = $this->tracking->changed_fields($_data, $old);
					if(count($changed) == 0) {
						return true;
					}
				}
				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Backend doesn't like inserting with ID specified, can overwrite existing
					unset($_data['id']);
				}
				// Make sure participants are set
				if(!$_data['participants']) {
					$user = $_data['owner'] ? $_data['owner'] : $this->user;
					$_data['participants'] = array(
						$user => 'U'
					);
				}
				if ( $this->dry_run ) {
					//print_r($_data);
					// User is interested in conflict checks, do so for dry run
					// Otherwise, conflicts are just ignored and imported anyway
					if($this->definition->plugin_options['skip_conflicts'] && !$_data['non_blocking'])
					{
						$conflicts = $this->bo->conflicts($_data);
						if($conflicts)
						{
							$this->conflict_warning($record_num, $conflicts);
							$this->results['skipped']++;
							return false;
						}
					}
					$this->results[$_action]++;
					return true;
				} else {
					$messages = null;
					$result = $this->bo->update( $_data,
						!$this->definition->plugin_options['skip_conflicts'],
						true, $this->is_admin, true, $messages,
						$this->definition->plugin_options['no_notification']
					);
					if(!$result)
					{
						$this->errors[$record_num] = lang('Unable to save') . "\n" .
							implode("\n",$messages);
					}
					else if (is_array($result))
					{
						$this->conflict_warning($record_num, $result);
						$this->results['skipped']++;
						return false;
					}
					else
					{
						$this->results[$_action]++;
						// This does nothing (yet?) but update the identifier
						$record->save($result);
					}
					return $result;
				}
			default:
				throw new Api\Exception('Unsupported action');

		}
	}

	/**
	 * Add a warning message about conflicting events
	 *
	 * @param int $record_num Current record index
	 * @param Array $conflicts List of found conflicting events
	 */
	protected function conflict_warning($record_num, &$conflicts)
	{
		$this->warnings[$record_num] = lang('Conflicts') . ':';
		foreach($conflicts as $conflict)
		{
			$this->warnings[$record_num] .= "<br />\n" . Api\DateTime::to($conflict['start']) . "\t" . $conflict['title'];
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports events into your Calendar from a CSV File. CSV means 'Comma Separated Values'. However in the options Tab you can also choose other seperators.");
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
	 * Alter a row for preview to show multiple participants instead of Array
	 *
	 * @param egw_record $row_entry
	 */
	protected function row_preview(importexport_iface_egw_record &$row_entry)
	{
		$row_entry->participants = implode('<br />', $this->bo->participants(array('participants' => $row_entry->participants), true));
	}


	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @return array (
	 *        name        => string,
	 *        content        => array,
	 *        sel_options => array,
	 *        preserv        => array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition = null)
	{
		$owner = $definition->plugin_options['owner'] ?? $GLOBALS['egw_info']['user']['account_id'];
		// Make sure Owner from import dialog is not array
		if(is_array($owner))
		{
			$owner = array_pop($owner);
		}
		$options = array(
			'name'        => 'calendar.import_csv',
			'content'     => array(
				'owner' => $owner ? [$owner] : null
			),
			'sel_options' => array(
				'cal_owner' => [
					[
						'value' => $GLOBALS['egw_info']['user']['account_id'],
						'label' => calendar_owner_etemplate_widget::get_owner_label($GLOBALS['egw_info']['user']['account_id'])
					]
				]
			)
		);
		return $options;
	}
}