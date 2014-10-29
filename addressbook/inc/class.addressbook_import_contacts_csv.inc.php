<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id: $
 */


/**
 * class import_csv for addressbook
 */
class addressbook_import_contacts_csv extends importexport_basic_import_csv  {

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists', 'equal' );

	/**
	 * @var bocontacts
	 */
	private $bocontacts;

	/**
	* For figuring out if a contact has changed
	*/
	protected $tracking;


	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function init(importexport_definition &$_definition ) {

		// fetch the addressbook bo
		$this->bocontacts = new addressbook_bo();

		// Get the tracker for changes
		$this->tracking = new addressbook_tracking($this->bocontacts);

		$this->lookups = array(
			'tid' => array('n'=>'contact')
		);
		foreach($this->bocontacts->content_types as $tid => $data)
		{
			$this->lookups['tid'][$tid] = $data['name'];
		}

	
		// set contact owner
		$contact_owner = isset( $_definition->plugin_options['contact_owner'] ) ?
			$_definition->plugin_options['contact_owner'] : $this->user;

		// Import into importer's personal addressbook
		if($contact_owner == 'personal')
		{
			$contact_owner = $this->user;
		}
		$this->user = $contact_owner;
	}

	/**
	 * Import a single record
	 *
	 * You don't need to worry about mappings or translations, they've been done already.
	 * You do need to handle the conditions and the actions taken.
	 *
	 * Updates the count of actions taken
	 *
	 * @return boolean success
	 */
	protected function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{

		// Set owner, unless it's supposed to come from CSV file
		if($this->definition->plugin_options['owner_from_csv'] && $record->owner) {
			if(!is_numeric($record->owner)) {
				// Automatically handle text owner without explicit translation
				$new_owner = importexport_helper_functions::account_name2id($record->owner);
				if($new_owner == '') {
					$this->errors[$import_csv->get_current_position()] = lang(
						'Unable to convert "%1" to account ID.  Using plugin setting (%2) for owner.',
						$record->owner,
						common::grab_owner_name($this->user)
					);
					$record->owner = $this->user;
				} else {
					$record->owner = $new_owner;
				}
			}
		} else {
			$record->owner = $this->user;
		}

		// Check that owner (addressbook) is allowed
		if(!array_key_exists($record->owner, $this->bocontacts->get_addressbooks()))
		{
			$this->errors[$import_csv->get_current_position()] = lang("Unable to import into %1, using %2",
				common::grab_owner_name($record->owner),
				common::grab_owner_name($this->user)
			);
			$record->owner = $this->user;
		}

		// Do not allow owner == 0 (accounts) without an account_id
		// It causes the contact to be filed as an account, and can't delete
		if(!$record->owner && !$record->account_id)
		{
			$record->owner = $GLOBALS['egw_info']['user']['account_id'];
		}

		// Do not import into non-existing type, warn and change
		if(!$record->tid || !$this->lookups['tid'][$record->tid])
		{
			$this->warnings[$import_csv->get_current_position()] = lang('Unknown type %1, imported as %2',$record->tid,lang($this->lookups['tid']['n']));
			$record->tid = 'n';
		}

		// Also handle categories in their own field
		$record_array = $record->get_record_array();
		$more_categories = array();
		foreach($this->definition->plugin_options['field_mapping'] as $number => $field_name) {
			if(!array_key_exists($field_name, $record_array) ||
				substr($field_name,0,3) != 'cat' || !$record->$field_name || $field_name == 'cat_id') continue;
			list($cat, $cat_id) = explode('-', $field_name);
			if(is_numeric($record->$field_name) && $record->$field_name != 1) {
				// Column has a single category ID
				$more_categories[] = $record->$field_name;
			} elseif($record->$field_name == '1' ||
				(!is_numeric($record->$field_name) && strtolower($record->$field_name) == strtolower(lang('Yes')))) {
				// Each category got its own column.  '1' is the database value, lang('yes') is the human value
				$more_categories[] = $cat_id;
			} else {
				// Text categories
				$more_categories = array_merge($more_categories, importexport_helper_functions::cat_name2id(is_array($record->$field_name) ? $record->$field_name : explode(',',$record->$field_name), $cat_id));
			}
		}
		if(count($more_categories) > 0) $record->cat_id = array_merge(is_array($record->cat_id) ? $record->cat_id : explode(',',$record->cat_id), $more_categories);

		// Private set but missing causes hidden entries
		if(array_key_exists('private', $record_array) && (!isset($record_array['private']) || $record_array['private'] == '')) unset($record->private);

		// Format birthday as backend requires - converter should give timestamp
		if($record->bday && is_numeric($record->bday))
		{
			$time = new egw_time($record->bday);
			$record->bday = $time->format('Y-m-d');
		}

		if ( $this->definition->plugin_options['conditions'] ) {
			foreach ( $this->definition->plugin_options['conditions'] as $condition ) {
				$contacts = array();
				switch ( $condition['type'] ) {
					// exists
					case 'exists' :
						if($record_array[$condition['string']]) {
							$searchcondition = array( $condition['string'] => $record_array[$condition['string']]);
							// if we use account_id for the condition, we need to set the owner for filtering, as this
							// enables addressbook_so to decide what backend is to be used
							if ($condition['string']=='account_id') $searchcondition['owner']=0;
							$contacts = $this->bocontacts->search(
								//array( $condition['string'] => $record[$condition['string']],),
								'',
								$this->definition->plugin_options['update_cats'] == 'add' ? false : true,
								'', '', '', false, 'AND', false,
								$searchcondition
							);
						}
						if ( is_array( $contacts ) && count( array_keys( $contacts ) ) >= 1 ) {
							// apply action to all contacts matching this exists condition
							$action = $condition['true'];
							foreach ( (array)$contacts as $contact ) {
								$record->id = $contact['id'];
								if ( $this->definition->plugin_options['update_cats'] == 'add' ) {
									if ( !is_array( $contact['cat_id'] ) ) $contact['cat_id'] = explode( ',', $contact['cat_id'] );
									if ( !is_array( $record_array['cat_id'] ) ) $record->cat_id = explode( ',', $record->cat_id );
									$record->cat_id = implode( ',', array_unique( array_merge( $record->cat_id, $contact['cat_id'] ) ) );
								}
								$success = $this->action(  $action['action'], $record, $import_csv->get_current_position() );
							}
						} else {
							$action = $condition['false'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						}
						break;
					case 'equal':
						// Match on field
						$result = $this->equal($record, $condition, $matches);
						if($result)
						{
							// Apply true action to any matching records found
							$action = $condition['true'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						}
						else
						{
							// Apply false action if no matching records found
							$action = $condition['false'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						}
						break;

					// not supported action
					default :
						die('condition / action not supported!!!');
						break;
				}
				if ($action['stop']) break;
			}
		} else {
			// unconditional insert
			$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
		}
		return $success;
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param importexport_iface_egw_record $record contact data for the action
	 * @return bool success or not
	 */
	protected function action ( $_action, importexport_iface_egw_record &$record, $record_num = 0 ) {
		$_data = $record->get_record_array();
		switch ($_action) {
			case 'none' :
				return true;
			case 'delete':
				if($_data['id'])
				{
					if ( $this->dry_run ) {
						//print_r($_data);
						$this->results[$_action]++;
						return true;
					}
					$result = $this->bocontacts->delete($_data);
					if($result && $result === true)
					{
						$this->results[$_action]++;
					}
					else
					{
						// Failure of some kind - unknown cause
						$this->errors[$record_num] = lang('unable to delete');
					}
				}
				break;
			case 'update' :
				// Only update if there are changes
				$old = $this->bocontacts->read($_data['id']);
				// if we get countrycodes as countryname, try to translate them -> the rest should be handled by bo classes.
				foreach(array('adr_one_', 'adr_two_') as $c_prefix) {
					if (strlen(trim($_data[$c_prefix.'countryname']))==2) $_data[$c_prefix.'countryname'] = $GLOBALS['egw']->country->get_full_name(trim($_data[$c_prefix.'countryname']),$translated=true);
				}
				// Don't change a user account into a contact
				if($old['owner'] == 0) {
					unset($_data['owner']);
				} elseif(!$this->definition->plugin_options['change_owner']) {
					// Don't change addressbook of an existing contact
					unset($_data['owner']);
				}

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0) {
					return true;
				} else {
					//error_log(__METHOD__.__LINE__.array2string($changed).' Old:'.$old['adr_one_countryname'].' ('.$old['adr_one_countrycode'].') New:'.$_data['adr_one_countryname'].' ('.$_data['adr_one_countryname'].')');
				}
				
				// Make sure n_fn gets updated
				unset($_data['n_fn']);

				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Addressbook backend doesn't like inserting with ID specified, it screws up the owner & etag
					unset($_data['id']);
				}
				if(!isset($_data['org_name'])) {
					// org_name is a trigger to update n_fileas
					$_data['org_name'] = '';
				}

				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					return true;
				} else {
					$result = $this->bocontacts->save( $_data, $this->is_admin);
					if(!$result) {
						$this->errors[$record_num] = $this->bocontacts->error;
					} else {
						$this->results[$_action]++;
						// This does nothing (yet?) but update the identifier
						$record->save($result);
					}
					return $result;
				}
			default:
				throw new egw_exception('Unsupported action: '. $_action);
			
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Addressbook CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports contacts into your Addressbook from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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
	public function get_options_etpl() {
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
        * Maximum of one warning message per record, but you can append if you need to
        *
        * @return Array (
        *       record_# => warning message
        *       )
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
        *       )
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
} // end of iface_export_plugin
?>
