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

require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.iface_import_plugin.inc.php');
require_once(EGW_INCLUDE_ROOT.'/importexport/inc/class.import_csv.inc.php');


/**
 * class import_csv for addressbook
 */
class import_events_csv implements iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 			// char
		'charset', 				// string
		'event_owner', 			// int account_id or -1 for leave untuched
		'owner_joins_event',	// bool
		'update_cats', 			// string {override|add} overides record
								// with cat(s) from csv OR add the cat from
								// csv file to exeisting cat(s) of record
		'num_header_lines',		// int number of header lines
		'trash_users_records',	// trashes all events of events owner before import
		'field_conversion', 	// array( $csv_col_num => conversion)
		'field_mapping',		// array( $csv_col_num => adb_filed)
		'conditions',			/* => array containing condition arrays:
				'type' => exists, // record['uid'] exists
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
	private static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	private static $conditions = array( 'exists', 'empty', );

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var bocalupdate
	 */
	private $bocalupdate;

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
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function import( $_stream, definition $_definition ) {
		$import_csv = new import_csv( $_stream, array(
			'fieldsep' => $_definition->plugin_options['fieldsep'],
			'charset' => $_definition->plugin_options['charset'],
		));

		$this->definition = $_definition;

		// user, is admin ?
		$this->is_admin = isset( $GLOBALS['egw_info']['user']['apps']['admin'] ) && $GLOBALS['egw_info']['user']['apps']['admin'];
		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// fetch the calendar bo
		$this->bocalupdate = new calendar_boupdate();

		// set FieldMapping.
		$import_csv->mapping = $_definition->plugin_options['field_mapping'];

		// set FieldConversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'];

		//check if file has a header lines
		if ( isset( $_definition->plugin_options['num_header_lines'] ) ) {
			$import_csv->skip_records( $_definition->plugin_options['num_header_lines'] );
		}

		// set eventOwner
		$_definition->plugin_options['events_owner'] = isset( $_definition->plugin_options['events_owner'] ) ?
			$_definition->plugin_options['events_owner'] : $this->user;

		// trash_users_records ?
		if ( $_definition->plugin_options['trash_users_records'] === true ) {
			if ( !$_definition->plugin_options['dry_run'] ) {
				$socal = new calendar_socal();
				$this->bocalupdate->so->deleteaccount( $_definition->plugin_options['events_owner']);
				unset( $socal );
			} else {
				$lid = $GLOBALS['egw']->accounts->id2name( $_definition->plugin_options['events_owner'] );
				echo "Attension: All Events of '$lid' would be deleted!\n";
			}
		}

		while ( $record = $import_csv->get_record() ) {

			// don't import empty events
			if( count( array_unique( $record ) ) < 2 ) continue;

			if ( $_definition->plugin_options['events_owner'] != -1 ) {
				$record['owner'] = $_definition->plugin_options['events_owner'];
			} else unset( $record['owner'] );

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :

							if ( is_array( $event = $this->bocalupdate->read( $record['uid'], null, $this->is_admin ) ) ) {
								// apply action to event matching this exists condition
								$record['id'] = $event['id'];

								if ( $_definition->plugin_options['update_cats'] == 'add' ) {
									if ( !is_array( $event['cat_id'] ) ) $event['cat_id'] = explode( ',', $event['cat_id'] );
									if ( !is_array( $record['cat_id'] ) ) $record['cat_id'] = explode( ',', $record['cat_id'] );
									$record['cat_id'] = implode( ',', array_unique( array_merge( $record['cat_id'], $event['cat_id'] ) ) );
								}

								// check if entry is modiefied
								$event = array_intersect_key( $event, $record );
								$diff = array_diff( $event, $record );
								if( !empty( $diff ) ) $record['modified'] = time();

								$action = $condition['true'];
							} else $action = $condition['false'];

							$this->action( $action['action'], $record );
							break;
						case 'empty' :
							$action = empty( $record[$condition['string']] ) ? $condition['true'] : $condition['false'];
							$this->action( $action['action'], $record );
							break;

						// not supported action
						default :
							throw new Exception('condition not supported!!!');
							break;
					}
					if ($action['last']) break;
				}
			} else {
				// unconditional insert
				$this->action( 'insert', $record );
			}
		}
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data event data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data ) {
		switch ( $_action ) {
			case 'none' :
				return true;

			case 'update' :
			case 'insert' :

				// paticipants handling
				$participants = $_data['participants'] ? split( '[,;]', $_data['participants'] ) : array();
				$_data['participants'] = array();
				if ( $this->definition->plugin_options['owner_joins_event'] && $this->definition->plugin_options['events_owner'] > 0 ) {
					$_data['participants'][$this->definition->plugin_options['events_owner']] = 'A';
				}
				foreach( $participants as $participant ) {
					list( $participant, $status ) = explode( '=', $participant );
					$valid_staties = array('U'=>'U','u'=>'U','A'=>'A','a'=>'A','R'=>'R','r'=>'R','T'=>'T','t'=>'T');
					$status = isset( $valid_staties[$status] ) ? $valid_staties[$status] : 'U';
					if ( $participant && is_numeric($participant ) ) {
						$_data['participants'][$participant] = $status;
					}
				}
				// no valid participants so far --> add the importing user/owner
				if ( empty( $_data['participants'] ) ) {
					$_data['participants'][$this->user] = 'A';
				}

				// are we serious?
				if ( $this->dry_run ) {
					print_r($_data);
				} else {
					return $this->bocalupdate->update( $_data, true, !$_data['modified'], $this->is_admin);
				}

			case 'delete' :
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports events into your Calendar from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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

} // end of iface_export_plugin
?>
