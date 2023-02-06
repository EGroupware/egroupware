<?php
/**
 * EGroupWare iCal import plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

use \EGroupware\Api;

/**
 * import ical for calendar
 */
class calendar_import_ical implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset',		// string
		'owner', 		// int
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

	/**
	 * actions wich could be done to data entries
	 */
	protected static $actions = array();

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array();

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var bo
	 */
	private $bo;

	/**
	* For figuring out if an entry has changed
	*/
	protected $tracking;

	/**
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * @var bool is current user admin?
	 */
	private $is_admin = false;

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

		$this->definition = $_definition;

		// user, is admin ?
		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// fetch the addressbook bo
		$this->bo= new calendar_boupdate();


		// Failures
		$this->errors = array();

		@set_time_limit(0);     // try switching execution time limit off

		$calendar_ical = new calendar_ical;
		$calendar_ical->setSupportedFields('file', '');
		if($this->dry_run)
		{
			// No real dry run for iCal
			echo lang("No preview for iCal");
			return;
		}
		// switch off notifications by default
		$plugin_options = $_definition->plugin_options;
		if(!array_key_exists('no_notification', $_definition->plugin_options))
		{
			$plugin_options['no_notification'] = true;
			$_definition->plugin_options = $plugin_options;
		}

		// Set owner, if not set will be null (current user)
		$owner = $plugin_options['cal_owner'];
		if(is_array($owner))
		{
			$owner = array_pop($owner);
		}

		// Purge
		if($plugin_options['empty_before_import'])
		{
			$remove_past = new Api\DateTime();
			$remove_future = new Api\DateTime();
			$plugin_options = array_merge(array('remove_past' => 100, 'remove_future' => 365), $plugin_options);
			foreach(array('remove_past', 'remove_future') as $date)
			{
				${$date}->add((($date == 'remove_past' ? -1 : 1) * (int)$plugin_options[$date]) . ' days');
			}
			$this->purge_calendar(
					$owner,
					array('from' => $remove_past, 'to' => $remove_future),
					$plugin_options['no_notification']
			);
		}

		$calendar_ical->event_callback = array($this, 'event_callback');

		// User wants conflicting events to not be imported
		if($_definition->plugin_options['skip_conflicts'])
		{
			$calendar_ical->conflict_callback = array($this, 'conflict_warning');
		}
		if (!$calendar_ical->importVCal($_stream, -1,null,false,0,'',$owner,null,null,$_definition->plugin_options['no_notification']))
		{
			$this->errors[] = lang('Error: importing the iCal');
		}
		else
		{
			$this->results['imported'] += $calendar_ical->events_imported;
		}

		return $calendar_ical->events_imported;
	}

	/**
	 * Do some modification on each event
	 */
	public function event_callback(&$event)
	{
		// Check & apply value overrides
		foreach((array)$this->definition->plugin_options['override_values'] as $field => $settings)
		{
			$event[$field] = $settings['value'];
		}
		return true;
	}

	/**
	 * Add a warning message about conflicting events
	 *
	 * @param int $record_num Current record index
	 * @param Array $conflicts List of found conflicting events
	 */
	public function conflict_warning(&$event, &$conflicts)
	{
		$warning = EGroupware\Api\DateTime::to($event['start']) . ' ' . $event['title'] . ' ' . lang('Conflicts') . ':';
		foreach($conflicts as $conflict)
		{
			$warning .= "<br />\n" . EGroupware\Api\DateTime::to($conflict['start']) . "\t" . $conflict['title'];
		}
		$this->warnings[] = $warning;

		// iCal will always count as imported, even if it wasn't
		$this->results['imported'] -= 1;

		$this->results['skipped']++;
	}

	/**
	 * Empty the calendar before importing
	 *
	 * @param string $owner
	 * @param array|string $timespan
	 */
	protected function purge_calendar($owner, $timespan, $skip_notification)
	{
		if(!$owner)
		{
			$owner = $GLOBALS['egw_info']['user']['account_id'];
		}
		if(!is_array($timespan))
		{
			$timespan = importexport_helper_functions::date_rel2abs($timespan);
		}
		if (!$timespan)
		{
			return;
		}

		// Get events in timespan
		$events = $this->bo->search(array(
			'start'     => $timespan['from'],
			'end'       => $timespan['to'],
			'users'     => $owner,
			'num_rows'  => -1
		));

		// Delete
		foreach($events as $event)
		{
			$result = $this->bo->delete($event['id'], $event['recur_date'], true, $skip_notification);
			if($result)
			{
				$this->results['deleted']++;
			}
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar iCal import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports events into your Calendar from an iCal File.");
	}

	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix() {
		return 'ics';
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
		return array(
			'name'        => 'calendar.import_ical',
			'content'     => array(
				'file_type' => 'ical',
				'charset'   => $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'],
				'cal_owner' => [$definition->plugin_options['cal_owner'] ?: $GLOBALS['egw_info']['user']['account_id']]
			),
			'sel_options' => array(
				'charset'   => Api\Translation::get_installed_charsets(),
				'cal_owner' => [
					[
						'value' => $GLOBALS['egw_info']['user']['account_id'],
						'label' => calendar_owner_etemplate_widget::get_owner_label($GLOBALS['egw_info']['user']['account_id'])
					]
				]
			),
			'preserv'     => array()
		);
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
	 * Filter while importing
	 *
	 * The only one currently supported is purge range, nothing on actual fields
	 *
	 * @see importexport_helper_functions::get_filter_fields
	 *
	 * @param array $fields
	 */
	public function get_filter_fields(&$fields) {
		$fields = array(
			'purge' => array(
				'type' => 'date',
				'name' =>'purge',
				'label'=>'Empty target calendar before importing',
				'empty_label' => 'No'
			)
		);
	}
	/**
	 * Get the class name for the egw_record to use
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'calendar_egw_record';
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
}