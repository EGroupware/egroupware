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

require_once('class.iface_import_record.inc.php');
require_once('class.import_export_helper_functions.inc.php');

/**
 * class import_csv
 * This a an abstract implementation of interface iface_import_record
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end the get_record method comes out
 */
class import_csv implements iface_import_record { //, Iterator {

	const csv_max_linelength = 8000;
	
	/** Aggregations: */

	/** Compositions: */
	
	/**
	 * @static import_export_helper_functions
	 */
	
	 /*** Attributes: ***/
	
	/**
	 * array with field mapping in form column number => new_field_name
	 * @access public
	 */
	public $mapping = array();

	/**
	 * array with conversions to be done in form: new_field_name => conversion_string
	 * @access public
	 */
	public $conversion = array();

	/**
	 * array holding the current record
	 * @access protected
	 */
	protected $record = array();

	/**
	 * current position counter
	 * @access protected
	 */
	protected $current_position = 0;

	/**
	 * holds total number of records
	 * @access private
	 * @var int
	 */
	protected $num_of_records = 0;
	
	/**
	 * csv resource
	 * @access private
	 */
	private $resource;

	/**
	 * fieldseperator for csv file
	 * @access private
	 * @var char
	 */
	private $csv_fieldsep;
	
	/**
	 * charset of csv file
	 * @var string
	 * @access privat
	 */
	private $csv_charset;
	
	/**
	 * @param string _resource resource containing data. May be each valid php-stream
	 * @param array _options options for the resource array with keys: charset and fieldsep
	 * @access public
	 */
	public function __construct( $_resource,  $_options = array() ) {
		$this->resource = $_resource;
		$this->csv_fieldsep = $_options['fieldsep'];
		$this->csv_charset = $_options['charset'];
		return;
	} // end of member function __construct

	/**
	 * cleanup
	 *
	 * @return 
	 * @access public
	 */
	public function __destruct( ) {
	} // end of member function __destruct

	/**
	 * Returns array with the record found at position and updates the position
	 *
	 * @param mixed _position may be: {current|first|last|next|previous|somenumber}
	 * @return mixed array with data / false if no furtor records
	 * @access public
	 */
	public function get_record( $_position = 'next' ) {
		if ($this->get_raw_record( $_position ) === false) {
			return false;
		}
		
		if ( !empty( $this->mapping ) ) {
			$this->do_fieldmapping();
		}
		
		if ( !empty( $this->conversion ) ) {
			$this->do_conversions();
		}
		
		return $this->record;
	} // end of member function get_record
	
	
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
	 * @access public
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
	 * @access public
	 */
	public function get_current_position( ) {
		
		return $this->current_position;
		
	} // end of member function get_current_position


	/**
	 * does fieldmapping according to $this->mapping
	 *
	 * @return 
	 * @access protected
	 */
	protected function do_fieldmapping( ) {
		$record = $this->record;
		$this->record = array();
		foreach ($this->mapping as $cvs_idx => $new_idx) {
			$this->record[$new_idx] = $record[$cvs_idx];
		}
		return;
	} // end of member function do_fieldmapping

	/**
	 * does conversions according to $this->conversion
	 *
	 * @return bool
	 * @access protected
	 */
	protected function do_conversions( ) {
		if ( $record = import_export_helper_functions::conversion( $this->record, $this->conversion )) {
			$this->record = $record;
			return;
		}
		throw new Exception('Error: Could not applay conversions to record');
	} // end of member function do_conversions
	
} // end of import_csv
?>
