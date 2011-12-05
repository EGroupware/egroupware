<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class export_csv
 * This an record exporter.
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end export_entry
 * stores it into the stream
 */
class importexport_export_csv implements importexport_iface_export_record
{
	/**
	 * @var array array with field mapping in form egw_field_name => exported_field_name
	 */
	protected  $mapping = array();

	/**
	 * @var array array with conversions to be done in form: egw_field_name => conversion_string
	 */
	protected  $conversion = array();
	
	/**
	 * @var array holding the current record
	 */
	protected $record = array();
	
	/**
	 * @var translation holds (charset) translation object
	 */
	protected $translation;
	
	/**
	 * @var string charset of csv file
	 */
	protected $csv_charset;
	
	/**
	 * @var int holds number of exported records
	 */
	protected $num_of_records = 0;
	
	/**
	 * @var int holds max. number of records allowed to be exported
	 */
	public $export_limit = 0;

	/**
	 * @var stream stream resource of csv file
	 */
	protected  $handle;
	
	/**
	 * @var array csv specific options
	 */
	protected $csv_options = array(
		'delimiter' => ';',
		'enclosure' => '"',
	);

	/**
	 * List of fields for data conversion
	 */
	public static $types = array(
		'select-account' => array('creator', 'modifier'),
		'date-time'	=> array('modified', 'created'),
		'date'		=> array(),
		'select-cat'	=> array('cat_id')
	);
	
	/**
	 * Cache of parsed custom field parameters
	 */
	protected static $cf_parse_cache = array();

	/**
	 * constructor
	 *
	 * @param stram $_stream resource where records are exported to.
	 * @param array _options options for specific backends
	 * @return bool
	 */
	public function __construct( $_stream, array $_options ) {
		if (!is_object($GLOBALS['egw']->translation)) {
			$GLOBALS['egw']->translation = new translation();
		}
		$this->translation = &$GLOBALS['egw']->translation;
		$this->handle = $_stream;
		if($_options['charset'] == 'user') $_options['charset'] = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		$this->csv_charset = $_options['charset'] ? $_options['charset'] : 'utf-8';
		if ( !empty( $_options ) ) {
			$this->csv_options = array_merge( $this->csv_options, $_options );
		}
		//error_log(__METHOD__.__LINE__.array2string($_options['appname']));
		if(!bo_merge::is_export_limit_excepted()) {
			$this->export_limit = bo_merge::getExportLimit($_options['appname']);
			//error_log(__METHOD__.__LINE__.' app:'.$_options['appname'].' limit:'.$this->export_limit);
			if($this->export_limit == 'no') throw new egw_exception_no_permission_admin('Export disabled');
		}
	}
	
	/**
	 * sets field mapping
	 *
	 * @param array $_mapping egw_field_name => csv_field_name
	 */
	public function set_mapping( array &$_mapping) {
		if ($this->num_of_records > 0) {
			throw new Exception('Error: Field mapping can\'t be set during ongoing export!');
		}
		if($_mapping['all_custom_fields']) {
			// Field value is the appname, so we can pull the fields
			$custom = config::get_customfields($_mapping['all_custom_fields']);
			unset($_mapping['all_custom_fields']);
			foreach($custom as $field => $info) {
				$_mapping['#'.$field] = $this->csv_options['begin_with_fieldnames'] == 'label' ? $info['label'] : $field;
			}
		}
		$this->mapping = $_mapping;
	}
	
	/**
	 * Sets conversion.
	 * @see importexport_helper_functions::conversion.
	 *
	 * @param array $_conversion
	 */
	public function set_conversion( array $_conversion) {
		$this->conversion = $_conversion;
	}
	
	/**
	 * exports a record into resource of handle
	 *
	 * @param importexport_iface_egw_record record
	 * @return bool
	 */
	public function export_record( importexport_iface_egw_record $_record ) {
		$this->record = $_record->get_record_array();
		
		// begin with fieldnames ?
		if ($this->num_of_records == 0 && $this->csv_options['begin_with_fieldnames'] ) {
			if($this->csv_options['begin_with_fieldnames'] == 'label') {
				// Load translations for app
				list($appname, $part2) = explode('_', get_class($_record));
				if(!$GLOBALS['egw_info']['apps'][$appname]) $appname .= $part2; // Handle apps with _ in the name
				translation::add_app($appname);
				foreach($this->mapping as $field => &$label) {
					$label = lang($label);
				}
			}
			$mapping = ! empty( $this->mapping ) ? $this->mapping : array_keys ( $this->record );
			$mapping = $this->translation->convert( $mapping, $this->translation->charset(), $this->csv_charset );
			fputcsv( $this->handle ,$mapping ,$this->csv_options['delimiter'], $this->csv_options['enclosure'] );
		}

		// Check for limit
		if($this->export_limit && $this->num_of_records >= $this->export_limit) {
			return;
		}
		
		// do conversions
		if ( !empty( $this->conversion )) {
			$this->record = importexport_helper_functions::conversion( $this->record, $this->conversion );
		}
		
		// do fieldmapping
		if ( !empty( $this->mapping ) ) {
			$record_data = $this->record;
			$this->record = array();
			foreach ($this->mapping as $egw_field => $csv_field) {
				$this->record[$csv_field] = $record_data[$egw_field];
			}
		}
		
		fputcsv( $this->handle, $this->record, $this->csv_options['delimiter'], $this->csv_options['enclosure'] );
		$this->num_of_records++;
	}

	/**
	 * Retruns total number of exported records.
	 *
	 * @return int
	 */
	public function get_num_of_records() {
		return $this->num_of_records;
	}

	/**
	 * Parse custom fields for an app, so a more human friendly value can be exported
	 *
	 * @param appname Name of the app to fetch the custom fields for
	 * @param selects Lookup values for select boxes
	 * @param links Appnames for links to fetch the title
	 * @param methods Method will be called with the record's value
	 * 
	 * @return Array of fields to be added to list of fields needing conversion
	 */
	public static function convert_parse_custom_fields($appname, &$selects = array(), &$links = array(), &$methods = array()) {
		if(!$appname) return;

		$fields = array();
		$custom = config::get_customfields($appname);
		foreach($custom as $name => $c_field) {
			$name = '#' . $name;
			switch($c_field['type']) {
				case 'date':
					$fields['date'][] = $name;
					break;
				case 'date-time':
					$fields['date-time'][] = $name;
					break;
				case 'select-account':
					$fields['select-account'][] = $name;
					break;
				case 'ajax_select':
					if($c_field['values']['get_title']) {
						$methods[$name] = $c_field['values']['get_title'];
						break;
					}
					// Fall through for other settings
				case 'select':
					if (count($c_field['values']) == 1 && isset($c_field['values']['@']))
					{
						$c_field['values'] = ExecMethod('etemplate.customfields_widget._get_options_from_file', $c_field['values']['@']);
					}
					$fields['select'][] = $name;
					$selects[$name] = $c_field['values'];
					break;
				default:
					if(in_array($c_field['type'], array_keys($GLOBALS['egw_info']['apps']))) {
						$fields['links'][] = $name;
						$links[$name] = $c_field['type'];
					}
					break;
			}
		}
		return $fields;
	}

	/**
	 * Convert system info into a format with a little more transferrable meaning
	 *
	 * Uses the static variable $types to convert various datatypes.
	 *
	 * @param record Record to be converted
	 * @parem fields List of field types => field names to be converted
	 * @param appname Current appname if you want to do custom fields too
	 */
	public static function convert(importexport_iface_egw_record &$record, Array $fields = array(), $appname = null, $selects = array()) {
		if($appname) {
			if(!self::$cf_parse_cache[$appname]) {
				$c_fields = self::convert_parse_custom_fields($appname, $selects, $links, $methods);
				self::$cf_parse_cache[$appname] = array($c_fields, $selects, $links, $methods);
			}
			list($c_fields, $c_selects, $links, $methods) = self::$cf_parse_cache[$appname];
			// Not quite a recursive merge, since only one level
			foreach($fields as $type => &$list) {
				if($c_fields[$type]) {
					$list = array_merge($c_fields[$type], $list);
					unset($c_fields[$type]);
				}
			}
			$fields += $c_fields;
			$selects += $c_selects;
		}
		foreach((array)$fields['select'] as $name) {
			if($record->$name != null && is_array($selects) && $selects[$name]) {
				$record->$name = explode(',', $record->$name);
				if(is_array($record->$name)) {
					$names = array();
					foreach($record->$name as $_name) {
						$names[] = lang($selects[$name][$_name]);
					}
					$record->$name = implode(', ', $names);
				} else {
					$record->$name = lang($selects[$name][$record->$name]);
				}
			}
		}
		foreach((array)$fields['links'] as $name) {
			if($record->$name) {
				if(is_numeric($record->$name) && !$links[$name]) {
					$link = egw_link::get_link($record->$name);
					$links[$name] = ($link['link_app1'] == $appname ? $link['link_app2'] : $link['link_app1']);
					$record->$name = ($link['link_app1'] == $appname ? $link['link_id2'] : $link['link_id1']);
				}
				if($links[$name]) {
					$record->$name = egw_link::title($links[$name], $record->$name);
				}
			}
		}
		foreach((array)$fields['select-account'] as $name) {
			// Compare against null to deal with empty arrays
			if ($record->$name !== null) {
				if(is_array($record->$name)) {
					$names = array();
					foreach($record->$name as $_name) {
						$names[] = common::grab_owner_name($_name);
					}
					$record->$name = implode(', ', $names);
				} else {
					$record->$name = common::grab_owner_name($record->$name);
				}
			}
		}
		foreach((array)$fields['select-bool'] as $name) {
			if($record->$name != null) {
				$record->$name = $record->$name ? lang('Yes') : lang('No');
			}
		}
		foreach((array)$fields['date-time'] as $name) {
			//if ($record->$name) $record->$name = date('Y-m-d H:i:s',$record->$name); // Standard date format
			if ($record->$name && !is_numeric($record->$name)) $record->$name = strtotime($record->$name); // Custom fields stored as string
			if ($record->$name && is_numeric($record->$name)) $record->$name = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] . ', '.
				($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '24' ? 'H' : 'h').':i:s',$record->$name); // User date format
		}
		foreach((array)$fields['date'] as $name) {
			//if ($record->$name) $record->$name = date('Y-m-d',$record->$name); // Standard date format
			if ($record->$name && !is_numeric($record->$name)) $record->$name = strtotime($record->$name); // Custom fields stored as string
			if ($record->$name && is_numeric($record->$name)) $record->$name = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'], $record->$name); // User date format
		}

		// Some custom methods for conversion
		foreach((array)$methods as $name => $method) {
			if($record->$name) $record->$name = ExecMethod($method, $record->$name);
		}

		foreach((array)$fields['select-cat'] as $name) {
			if($record->$name) {
				$cats = array();
				$ids = is_array($record->$name) ? $record->$name : explode(',', $record->$name);
				foreach($ids as $n => $cat_id) {
					if ($cat_id) $cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
				}
				$record->$name = implode(', ',$cats);
			}
		}
	}

	/**
	 * destructor
	 *
	 * @return 
	 */
	public function __destruct() {
		
	}

	/**
	 * The php build in fputcsv function is buggy, so we need an own one :-(
	 * Buggy how?
	 *
	 * @param resource $filePointer
	 * @param array $dataArray
	 * @param char $delimiter
	 * @param char $enclosure
	 */
	protected function fputcsv($filePointer, Array $dataArray, $delimiter, $enclosure){
		$string = "";
		$writeDelimiter = false;
		foreach($dataArray as $dataElement) {
			if($writeDelimiter) $string .= $delimiter;
			$string .= $enclosure . str_replace(array("\r\n", '"'), array("\n",'""'), $dataElement) . $enclosure;
			$writeDelimiter = true;
		} 
		$string .= "\n";
		
		// do charset translation
		$string = $this->translation->convert( $string, $this->translation->charset(), $this->csv_charset );
		
		fwrite($filePointer, $string);
			
	}
} // end  export_csv_record
?>
