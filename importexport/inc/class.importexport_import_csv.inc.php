<?php
/**
 * eGroupWare - importexport
 * General Comma Serperated Values (CSV) record importer (abstract class)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class import_csv
 * This a an abstract implementation of interface iface_import_record
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end the get_record method comes out
 * @todo Throw away spechial chars and trim() entries ?
 * @todo Check for XSS like userinput! (see common_functions)
 */
class importexport_import_csv implements importexport_iface_import_record { //, Iterator {

	const csv_max_linelength = 8000;
	
	/**
	 * @var array array with field mapping in form column number => new_field_name
	 */
	public $mapping = array();

	/**
	 * @var array with conversions to be done in form: new_field_name => conversion_string
	 */
	public $conversion = array();

	/**
	 * @var class with extra conversion functions
	 */
	public $conversion_class = null;

	/**
	 * @var array holding the current record
	 */
	protected $record = array();

	/**
	 * @var int current position counter
	 */
	protected $current_position = 0;

	/**
	 * @var int holds total number of records
	 */
	protected $num_of_records = 0;

	/**
	 * Cache for automatic conversion from human friendly
	 */	
	protected static $cf_parse_cache = array();

	/**
	 * @var stream
	 */
	private $resource;

	/**
	 * fieldseperator for csv file
	 * @access private
	 * @var char
	 */
	private $csv_fieldsep;
	
	/**
	 * 
	 * @var string charset of csv file
	 */
	private $csv_charset;
	
	/**
	 * @param string $_resource resource containing data. May be each valid php-stream
	 * @param array $_options options for the resource array with keys: charset and fieldsep
	 */
	public function __construct( $_resource,  array $_options ) {
		$this->resource = $_resource;
		$this->csv_fieldsep = $_options['fieldsep'];
		if($_options['charset'] == 'user') $_options['charset'] = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		$this->csv_charset = $_options['charset'];
		return;
	} // end of member function __construct

	/**
	 * cleanup
	 */
	public function __destruct( ) {
	} // end of member function __destruct

	/**
	 * Returns array with the record found at position and updates the position
	 *
	 * @param mixed _position may be: {current|first|last|next|previous|somenumber}
	 * @return mixed array with data / false if no furtor records
	 */
	public function get_record( $_position = 'next' ) {
		
		if ($this->get_raw_record( $_position ) === false) {
			return false;
		}
		
		// skip empty records
		if( count( array_unique( $this->record ) ) < 2 ) return $this->get_record( $_position );
		
		if ( !empty( $this->conversion ) ) {
			$this->do_conversions();
		}
		
		if ( !empty( $this->mapping ) ) {
			$this->do_fieldmapping();
		}
		
		return $this->record;
	} // end of member function get_record
	
	/**
	 * Skips $_numToSkip of records from current position on
	 *
	 * @param int $_numToSkip
	 */
	public function skip_records( $_numToSkip ) {
		while ( (int)$_numToSkip-- !== 0 ) {
			fgetcsv( $this->resource, self::csv_max_linelength, $this->csv_fieldsep);
		}
	}
	
	/**
	 * updates $this->record
	 *
	 * @param mixed $_position
	 * @return bool
	 */
	private function get_raw_record( $_position = 'next' ) {
		switch ($_position) {
			case 'current' :
				if ($this->current_position == 0) {
					return;
				}
				break;
			case 'first' :
				if (!$this->current_position == 0) {
					$this->current_position = 0;
					rewind($this->resource);
				}
				
			case 'next' :
				$csv_data = fgetcsv( $this->resource, self::csv_max_linelength, $this->csv_fieldsep);
				if (!is_array($csv_data)) {
					return false;
				}
				$this->current_position++;
				$this->record = $GLOBALS['egw']->translation->convert($csv_data, $this->csv_charset);
				break;
				
			case 'previous' :
				if ($this->current_position < 2) {
					throw new Exception('Error: There is no previous record!');
				}
				$final_position = --$this->current_position;
				$this->current_position = 0;
				rewind($this->resource);
				while ($this->current_position !== $final_position) {
					$this->get_raw_record();
				}
				break;
				
			case 'last' :
				while ($this->get_raw_record()) {}
				break;
				
			default: //somenumber
				if (!is_int($_position)) {
					throw new Exception('Error: $position must be one of {current|first|last|next|previous} or an integer value');
				}
				if ($_position == $this->current_position) {
					break;
				}
				elseif ($_position < $this->current_position) {
					$this->current_position = 0;
					rewind($this->resource);
				}
				while ($this->current_position !== $_position) {
					$this->get_raw_record();
				}
				break;				
		}
		return;
	} // end of member function get_raw_record

	/**
	 * Retruns total number of records for the open resource.
	 *
	 * @return int
	 */
	public function get_num_of_records( ) {
		if ($this->num_of_records > 0) {
			return $this->num_of_records;
		}
		$current_position = $this->current_position;
		while ($this->get_raw_record()) {}
		$this->num_of_records = $this->current_position;
		$this->get_record($current_position);
		return $this->num_of_records;
	} // end of member function get_num_of_records

	/**
	 * Returns pointer of current position
	 *
	 * @return int
	 */
	public function get_current_position( ) {
		
		return $this->current_position;
		
	} // end of member function get_current_position


	/**
	 * does fieldmapping according to $this->mapping
	 *
	 * @return 
	 */
	protected function do_fieldmapping( ) {
		$record = $this->record;
		$this->record = array();
		foreach ($this->mapping as $cvs_idx => $new_idx) {
			if( $new_idx == '' ) continue;
			$this->record[$new_idx] = $record[$cvs_idx];
		}
		return true;
	} // end of member function do_fieldmapping

	/**
	 * does conversions according to $this->conversion
	 *
	 * @return bool
	 */
	protected function do_conversions() {
		if ( $record = importexport_helper_functions::conversion( $this->record, $this->conversion, $this->conversion_class )) {
			$this->record = $record;
			return;
		}
		throw new Exception('Error: Could not applay conversions to record');
	} // end of member function do_conversions

	/**
	 * Automatic conversions from human values
	 *
	 * @param $fields Array of field type -> field name mappings
	 * @param $appname Appname for custom field parsing
	 * @param $selects Array of select values to be automatically parsed
	 *
	 */
	public static function convert(Array &$record, Array $fields = array(), $appname = null, Array $selects = array()) {
		// Automatic conversions
		if($appname) {
			if(!self::$cf_parse_cache[$appname]) {
				$c_fields = importexport_export_csv::convert_parse_custom_fields($appname, $selects, $links, $methods);
				self::$cf_parse_cache[$appname] = array($c_fields, $selects, $links, $methods);
			}
			list($c_fields, $c_selects, $links, $methods) = self::$cf_parse_cache[$appname];
			// Not quite a recursive merge, since only one level
			foreach($fields as $type => &$list) {
				if($c_fields[$type]) {
					$list = array_merge($c_fields[$type], $list);;
					unset($c_fields[$type]);
				}
			}
			$fields += $c_fields;
			$selects += $c_selects;
		}
		if($fields) {
			foreach((array)$fields['select'] as $name) {
				if($record[$name] != null && is_array($selects) && $selects[$name]) {
					$key = array_search($record[$name], $selects[$name]);
					if($key !== false) $record[$name] = $key;
				}
			}
			foreach((array)$fields['links'] as $name) {
				if($record[$name]) {
					// TODO
				}
			}
			foreach((array)$fields['select-account'] as $name) {
				// Compare against null to deal with empty arrays
				if ($record[$name]) {
					// Automatically handle text owner without explicit translation
					$new_owner = importexport_helper_functions::account_name2id($record[$name]);
					if($new_owner != '') {
						$record[$name] = $new_owner;
					}
				}
			}
			foreach((array)$fields['select-bool'] as $name) {
				if($record[$name] != null && $record[$name] != '') {
					$record[$name] = ($record[$name] == lang('Yes') || $record[$name] == '1' ? 1 : 0);
				}
			}
			foreach((array)$fields['date-time'] as $name) {
				if ($record[$name] && !is_numeric($record[$name])) {
					$record[$name] = egw_time::user2server($record[$name],'ts'); 
					if(is_array(self::$cf_parse_cache[$appname][0]['date-time']) && 
							in_array($name, self::$cf_parse_cache[$appname][0]['date-time'])) {
						// Custom fields stored in a particular format (from customfields_widget)
						$record[$name] = date('Y-m-d H:i:s', $record[$name]);
					}
				}
			}
			foreach((array)$fields['date'] as $name) {
				if ($record[$name] && !is_numeric($record[$name])) {
					$record[$name] = egw_time::user2server($record[$name],'ts'); 
					if(is_array(self::$cf_parse_cache[$appname][0]['date']) && 
							in_array($name, self::$cf_parse_cache[$appname][0]['date'])) {
						// Custom fields stored in a particular format (from customfields_widget)
						$record[$name] = date('Y-m-d', $record[$name]);
					}
				}
			}

			// Some custom methods for conversion
			foreach((array)$methods as $name => $method) {
				if($record[$name]) $record[$name] = ExecMethod($method, $record[$name]);
			}

			// cat_name2id will use currentapp to create new categories
			$current_app = $GLOBALS['egw_info']['flags']['currentapp'];
			if($appname) {
				$GLOBALS['egw_info']['flags']['currentapp'] = $appname;
			}
			foreach((array)$fields['select-cat'] as $name) {
				if($record[$name]) {
					$cat_id = importexport_helper_functions::cat_name2id($record[$name]);
					// Don't clear it if it wasn't found
					if($cat_id) $record[$name] = $cat_id;
				}
			}
			$GLOBALS['egw_info']['flags']['currentapp'] = $current_app;
		}

		return;
	}
} // end of import_csv
?>
