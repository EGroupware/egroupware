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
		'fieldsep', 		//char
		'charset', 			//string
		'addressbook', 		//char
		'owner', 			// comma seperated list of int
		'csv_fields',		// array( $csv_col_num => csv_field_name)
		'field_mapping',	// array( $csv_col_num => adb_filed)
		'field_translation', // array( $csv_col_num => translation)
		'has_header_line', 	//bool
		'max',				// int
		'conditions',		/* => array containing condition arrays: 
				'type' => 0, // exists
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
	private static $actions = array( 'none', 'update', 'insert', 'delte', );
	
	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	private static $conditions = array( 'exists', 'greater', 'greater or equal', );
	
	/**
	 * @var bocontacts
	 */
	private $bocontacts;
	
	/**
	 * constructor of import_contacts_csv
	 *
	public function __construct() {
		$this->bocontacts = CreateObject('addressbook.bocontacts');
	}*/
	
	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function import( $_stream, $_charset, definition $_definition ) {
		$import_csv = new import_csv( $_stream, array(
			'fieldsep' => $_definition->plugin_options['fieldsep'],
			'charset' => $_definition->plugin_options['charset'],
		));
		
		// fetch the addressbook bo
		$this->bocontacts = CreateObject('addressbook.bocontacts');
		
		// set FieldMapping. Throw away empty / not assigned entrys
		$import_csv->mapping = array_diff($_definition->plugin_options['field_mapping'],array(''));
		
		// renamed from translation to conversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'] ? 
			$_definition->plugin_options['field_conversion'] : 
			$_definition->plugin_options['field_translation'];
		
		//check if file has a header line
		if ($_definition->plugin_options['has_header_line']) {
			$record = $import_csv->get_record();
		}
		
		// TODO: Throw away spechial chars ?
		// TODO: check conversion:
		// - is not existing cat created?
		// - usermapping?
		
		while ( $record = $import_csv->get_record() ) {

			// don't import empty contacts
			if( count( array_unique( $record ) ) < 2 ) continue;

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							$contacts = $this->bocontacts->search(array(
								$condition['string'] => $record[$condition['string']],
							),true);
							
							if ( is_array( $contacts ) && count( array_keys( $contacts ) >= 1 ) ) {
								// apply action to all contacts matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$contacts as $contact ) {
									$record['id'] = $contact['id'];
									$this->action(  $action['action'], $record );
								}
							} else {
								$action = $condition['true'];
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
				$this->action( 'insert', $values );
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
		print_r($_data);
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
			case 'insert' :
				return $this->bocontacts->save( $_data );
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
