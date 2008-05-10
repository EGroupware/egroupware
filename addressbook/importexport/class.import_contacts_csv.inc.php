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
class import_contacts_csv implements iface_import_plugin  {

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

	/**
	 * actions wich could be done to data entries
	 */
	private static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	private static $conditions = array( 'exists', 'greater', 'greater or equal', );

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var bocontacts
	 */
	private $bocontacts;

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

		// fetch the addressbook bo
		$this->bocontacts = new addressbook_bo();

		// set FieldMapping.
		$import_csv->mapping = $_definition->plugin_options['field_mapping'];

		// set FieldConversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'];

		//check if file has a header lines
		if ( isset( $_definition->plugin_options['num_header_lines'] ) ) {
			$import_csv->skip_records($_definition->plugin_options['num_header_lines']);
		}

		// set eventOwner
		$_definition->plugin_options['contact_owner'] = isset( $_definition->plugin_options['contact_owner'] ) ?
			$_definition->plugin_options['contact_owner'] : $this->user;

		while ( $record = $import_csv->get_record() ) {

			// don't import empty contacts
			if( count( array_unique( $record ) ) < 2 ) continue;

			if ( $_definition->plugin_options['contact_owner'] != -1 ) {
				$record['owner'] = $_definition->plugin_options['contact_owner'];
			} else unset( $record['owner'] );

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							$contacts = $this->bocontacts->search(
								array( $condition['string'] => $record[$condition['string']],),
								$_definition->plugin_options['update_cats'] == 'add' ? false : true
							);

							if ( is_array( $contacts ) && count( array_keys( $contacts ) >= 1 ) ) {
								// apply action to all contacts matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$contacts as $contact ) {
									$record['id'] = $contact['id'];
									if ( $_definition->plugin_options['update_cats'] == 'add' ) {
										if ( !is_array( $contact['cat_id'] ) ) $contact['cat_id'] = explode( ',', $contact['cat_id'] );
										if ( !is_array( $record['cat_id'] ) ) $record['cat_id'] = explode( ',', $record['cat_id'] );
										$record['cat_id'] = implode( ',', array_unique( array_merge( $record['cat_id'], $contact['cat_id'] ) ) );
									}
									$this->action(  $action['action'], $record );
								}
							} else {
								$action = $condition['false'];
								$this->action( $action['action'], $record );
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
				$this->action( 'insert', $record );
			}
		}
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data contact data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data ) {
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
			case 'insert' :
				if ( $this->dry_run ) {
					print_r($_data);
				} else {
					return $this->bocontacts->save( $_data );
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
		return lang('Addressbook CSV export');
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

} // end of iface_export_plugin
?>
