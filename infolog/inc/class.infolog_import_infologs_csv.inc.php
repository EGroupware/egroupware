<?php
/**
 * EGroupware - InfoLog CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * class import_csv for infolog
 */
class infolog_import_infologs_csv implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset', 			// string
		'contact_owner', 	// int
		'update_cats', 			// string {override|add} overides record
								// with cat(s) from csv OR add the cat from
								// csv file to exeisting cat(s) of record
		'num_header_lines', // int number of header lines
		'field_conversion', // array( $csv_col_num => conversion)
		'field_mapping',	// array( $csv_col_num => adb_filed)
		'conditions',		/* => array containing condition arrays:
				'type' => exists, // exists
				'string' => '#kundennummer',
				'true' => array(
					'action' => update,
					'last' => true,
				),
				'false' => array(
					'action' => insert,
					'last' => true,
				),*/

	);

	public static $special_fields = array(
		'projectmanager'  => 'Link to Projectmanager, use Project-ID, Title or @project_id(id_or_title)',
		'addressbook'     => 'Link to Addressbook, use nlast,nfirst[,org] or contact_id from addressbook',
		'link_1'      => '1. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_2'      => '2. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_3'      => '3. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_custom'	=> 'Link by custom field, use <appname>:<custom_field_name>:|[<field_index>]'
	);

	/**
	 * actions wich could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists' );

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var infolog_bo
	 */
	private $boinfolog;

	/**
	* For figuring out if a record has changed
	*
	* @var infolog_tracking::
	*/
	protected $tracking;

	/**
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * @var int
	 */
	private $user = null;

	/**
	 * List of import warnings
	 */
	protected $warnings = array();

	/**
	 * List of import errors
	 */
	protected $errors = array();

	/**
 	* List of actions, and how many times that action was taken
	 */
	protected $results = array();

	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function import( $_stream, importexport_definition $_definition ) {
		$import_csv = new importexport_import_csv( $_stream, array(
			'fieldsep' => $_definition->plugin_options['fieldsep'],
			'charset' => $_definition->plugin_options['charset'],
		));

		$this->definition = $_definition;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// fetch the infolog bo
		$this->boinfolog = new infolog_bo();

		// Get the tracker for changes
		$this->tracking = new infolog_tracking($this->boinfolog);

		// Need translations for some human stuff (early type detection)
		Api\Translation::add_app('infolog');

		// set FieldMapping.
		$import_csv->mapping = $_definition->plugin_options['field_mapping'];

		// set FieldConversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'];

		// Add extra conversions
		$import_csv->conversion_class = $this;

		//check if file has a header lines
		if ( isset( $_definition->plugin_options['num_header_lines'] ) && $_definition->plugin_options['num_header_lines'] > 0) {
			$import_csv->skip_records($_definition->plugin_options['num_header_lines']);
		} elseif(isset($_definition->plugin_options['has_header_line']) && $_definition->plugin_options['has_header_line']) {
			// First method is preferred
			$import_csv->skip_records(1);
		}

		// set Owner
		$plugin_options = $_definition->plugin_options;
		$plugin_options['record_owner'] = $_definition->plugin_options['record_owner'] ?
			$_definition->plugin_options['record_owner'] : $this->user;
		$plugin_options['record_owner'] = $this->user;
		$_definition->plugin_options = $plugin_options;

		$_lookups = array(
			'info_type'	=>	$this->boinfolog->enums['type'],
			'info_status'	=>	$this->boinfolog->status['task'],
			'info_priority'	=>	$this->boinfolog->enums['priority'],
			'info_confirm'	=>	$this->boinfolog->enums['confirm']
		);

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();
		$this->warnings = array();

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;

			$lookups = $_lookups;

			// Early detection of type, to load appropriate statuses
			foreach(array($lookups['info_type'], array_map('strtolower',array_map('lang',$lookups['info_type']))) as $types)
			{
				if($record['info_type'] && $key = array_search(strtolower($record['info_type']),$types))
				{
					$lookups['info_status'] = $this->boinfolog->status[$key];
					break;
				}
			}

			$result = importexport_import_csv::convert($record, infolog_egw_record::$types, 'infolog', $lookups, $_definition->plugin_options['convert']);
			if($result) $this->warnings[$import_csv->get_current_position()] = $result;

			// Make sure type is valid
			if(!$record['info_type'] || $record['info_type'] && !$this->boinfolog->enums['type'][$record['info_type']])
			{
				// Check for translated type
				$un_trans = Api\Translation::get_message_id($record['info_type'],'infolog');
				if($record['info_type'] && $this->boinfolog->enums['type'][$un_trans])
				{
					$record['info_type'] = $un_trans;
				}
				else
				{
					$this->errors[$import_csv->get_current_position()] .= ($this->errors[$import_csv->get_current_position()] ? "\n":'').
						lang('Unknown type: %1', $record['info_type']);
				}
			}

			// Set default status for type, if not specified
			if(!$record['info_status'] && $record['info_type'])
			{
				$record['info_status'] = $this->boinfolog->status['defaults'][$record['info_type']];
			}

			// Set owner, unless it's supposed to come from CSV file
			if($_definition->plugin_options['owner_from_csv'])
			{
				if(!is_numeric($record['info_owner']))
				{
					$this->errors[$import_csv->get_current_position()] = lang(
						'Invalid owner ID: %1.  Might be a bad field translation.  Used %2 instead.',
						$record['info_owner'],
						$_definition->plugin_options['record_owner']
					);
					$record['info_owner'] = $_definition->plugin_options['record_owner'];
				}
			}
			else
			{
				$record['info_owner'] = $_definition->plugin_options['record_owner'];
			}
			if (!isset($record['info_owner'])) $record['info_owner'] = $GLOBALS['egw_info']['user']['account_id'];

			// Responsible has to be an array
			$record['info_responsible'] = $record['info_responsible'] ? explode(',',$record['info_responsible']) : 0;

			// Special values
			if ($record['addressbook'] && !is_numeric($record['addressbook']))
			{
				list($lastname,$firstname,$org_name) = explode(',',$record['addressbook']);
				$record['addressbook'] = importexport_basic_import_csv::addr_id($lastname,$firstname,$org_name);
			}
			if ($record['projectmanager'] && !is_numeric($record['projectmanager']))
			{
				$record['projectmanager'] = self::project_id($record['projectmanager']);
			}

			if ( $_definition->plugin_options['conditions'] )
			{
				foreach ( $_definition->plugin_options['conditions'] as $condition )
				{
					$results = array();
					switch ( $condition['type'] )
					{
						// exists
						case 'exists' :
							if($record[$condition['string']]) {
								$query['col_filter'] = array( $condition['string'] => $record[$condition['string']],);
								// Needed to query custom fields
								if($condition['string'][0] == '#') $query['custom_fields'] = true;
								$results = $this->boinfolog->search($query);
							}

							if ( is_array( $results ) && count( array_keys( $results )) >= 1) {
								// apply action to all records matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$results as $contact ) {
									$record['info_id'] = $contact['info_id'];
									$record['info_owner'] = $contact['info_owner'];
									if ( $_definition->plugin_options['update_cats'] == 'add' ) {
										if ( !is_array( $contact['cat_id'] ) ) $contact['cat_id'] = explode( ',', $contact['cat_id'] );
										if ( !is_array( $record['cat_id'] ) ) $record['cat_id'] = explode( ',', $record['cat_id'] );
										$record['cat_id'] = implode( ',', array_unique( array_merge( $record['cat_id'], $contact['cat_id'] ) ) );
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
			}
			else
			{
				// unconditional insert
				$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
			}
			if($success) $count++;
			if($this->warnings[$import_csv->get_current_position()]) {
				$this->warnings[$import_csv->get_current_position()] .= "\nRecord:\n" .array2string($record);
			}
			if($this->errors[$import_csv->get_current_position()]) {
				$this->errors[$import_csv->get_current_position()] .= "\nRecord:\n" .array2string($record);
			}
		}
		return $count;
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data contact data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data, $record_num = 0 ) {
		$result = true;
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->boinfolog->read($_data['info_id']);

				if(!$this->definition->plugin_options['change_owner']) {
					// Don't change addressbook of an existing contact
					unset($_data['owner']);
				}

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0 && !$this->definition->plugin_options['update_timestamp']) {
					break;
				}

				// Fall through
			case 'insert' :
				if ( $this->dry_run ) {
					//print_r($_data);

					// Check for link during dry run
					if($_data['link_custom'])
					{
						list($app, $app_id2) = explode(':', $_data['link_custom'],2);
						$app_id = $this->link_by_cf($record_num, $app, $field, $app_id2);
					}

					$this->results[$_action]++;
					break;
				} else {
					$result = $this->boinfolog->write(
						$_data, true, 2,true, 	// 2 = dont touch modification date
						$this->definition->plugin_options['no_notification']
					);
					if(!$result)
					{
						if($result === false)
						{
							$this->errors[$record_num] = lang('Permissions error - %1 could not %2',
								$GLOBALS['egw']->accounts->id2name($_data['info_owner']),
								lang($_action)
							);
						}
						else
						{
							$this->errors[$record_num] = lang('Error: the entry has been updated since you opened it for editing!');
						}
					}
					else
					{
						$this->results[$_action]++;
					}
					break;
				}
			default:
				throw new Api\Exception('Unsupported action');
		}

		// Process some additional fields
		if(!is_numeric($result)) {
			return $result;
		}
		$info_link_id = $_data['info_link_id'];
		foreach(array_keys(self::$special_fields) as $field) {
			if(!$_data[$field]) continue;
			if(strpos($field, 'link') === 0) {
				list($app, $app_id) = explode(':', $_data[$field],2);

				// Linking to another entry based on matching custom fields
				if($field == 'link_custom')
				{
					$app_id = $this->link_by_cf($record_num, $app, $field, $app_id);
				}
			} else {
				$app = $field;
				$app_id = $_data[$field];
			}
			if ($app && $app_id) {
				$id = $_data['info_id'] ? $_data['info_id'] : (int)$result;
				//echo "<p>linking infolog:$id with $app:$app_id</p>\n";
				$link_id = Link::link('infolog',$id,$app,$app_id);
				if ($link_id && !$info_link_id)
				{
					$to_write = array(
						'info_id'      => $id,
						'info_link_id' => $link_id,
					);
					$this->boinfolog->write($to_write,False,false,true,true);	// last true = no notifications, as no real change
					$info_link_id = $link_id;
				}
			}
		}
		return $result;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Infolog CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports entries into the infolog from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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
	// end of iface_export_plugin

	// Extra conversion functions - must be static
	public static function project_id($num_or_title)
	{
		static $boprojects=null;

		if (!$num_or_title) return false;

		if (!is_object($boprojects))
		{
			$boprojects = new projectmanager_bo();
		}
		if (($projects = $boprojects->search(array('pm_number' => $num_or_title))) ||
			($projects = $boprojects->search(array('pm_title'  => $num_or_title))))
		{
			return $projects[0]['pm_id'];
		}
		return false;
	}

	/**
	 * Get the primary key for an entry based on a custom field
	 * Returns key, so regular linking can take over
	 *
	 * This is a copy of what's in importexport_basic_import_csv, and can go
	 * away if this is changed to extend it
	 */
	protected function link_by_cf($record_num, $app, $fieldname, $_value)
	{
		$app_id = false;

		list($custom_field, $value) = explode(':',$_value);
		// Find matching entry
		if($app && $custom_field && $value)
		{
			$cfs = Api\Storage\Customfields::get($app);
			// Error if no custom fields, probably something wrong in definition
			if(!$cfs[$custom_field])
			{
				// Check for users specifing label instead of name
				foreach($cfs as $name => $settings)
				{
					if(strtolower($settings['label']) == strtolower($custom_field))
					{
						$custom_field = $name;
						break;
					}
				}
			}

			// Couldn't find field, give an error - something's wrong
			if(!$cfs[$custom_field] && !$cfs[substr($custom_field,1)]) {
				$this->errors[$record_num] .= lang('No custom field "%1" for %2.',
					$custom_field, lang($app));
				return false;
			}
			if($custom_field[0] != '#') $custom_field = '#' . $custom_field;

			// Search
			if(Link::get_registry($app, 'query'))
			{
				$options = array('filter' => array("$custom_field = " . $GLOBALS['egw']->db->quote($value)));
				$result = Link::query($app, '', $options);

				// Only one allowed
				if(count($result) != 1)
				{
					$this->warnings[$record_num] .= ($this->warnings[$record_num] ? "\n" : '') .
						lang('Unable to link to %3 by custom field "%1": "%4".  %2 matches.',
						$custom_field, count($result), lang($app), $options['filter'][0]
					);
					return false;
				}
				$app_id = key($result);
			}
		}
		return $app_id;
	}
}
