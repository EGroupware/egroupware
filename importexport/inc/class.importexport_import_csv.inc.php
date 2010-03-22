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
	protected function do_conversions( ) {
		if ( $record = importexport_helper_functions::conversion( $this->record, $this->conversion )) {
			$this->record = $record;
			return;
		}
		throw new Exception('Error: Could not applay conversions to record');
	} // end of member function do_conversions
	
} // end of import_csv
?>
