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
 * @todo whipe out the bool returns and use exeptions instead!!!
 * @todo do we realy need the stepping? otherwise we also could deal with stream resources.
 * The advantage of resources is, that we don't need to struggle whth stream options!
 * @todo just checked out iterators, but not shure if we can use it.
 * they iterate over public functions and not over datastructures :-(
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
	 * array with field mapping in form column number => new_field_name
	 * @access protected
	 */
	protected $mapping = array();

	/**
	 * array with conversions to be done in form: new_field_name => conversion_string
	 * @access protected
	 */
	protected $conversion = array();

	/**
	 * csv resource
	 * @access private
	 */
	private $resource;

	/**
	 * holds the string of the resource
	 * needed e.g. to reopen resource
	 * @var string
	 * @access private
	 */
	private $csv_resourcename = '';
	
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
	 * Opens resource, returns false if something fails
	 *
	 * @param string _resource resource containing data. May be each valid php-stream
	 * @param array _options options for the resource array with keys: charset and fieldsep
	 * @return bool
	 * @access public
	 */
	public function __construct( $_resource,  $_options = array() ) {
		$this->csv_resourcename = $_resource;
		$this->csv_fieldsep = $_options['fieldsep'];
		$this->csv_charset = $_options['charset'];
		
		//return $this->open_resource();
	} // end of member function __construct

	/**
	 * cleanup
	 *
	 * @return 
	 * @access public
	 */
	public function __destruct( ) {
		//$this->close_resource();
	} // end of member function __destruct

	/**
	 * Returns array with the record found at position and updates the position
	 *
	 * @param mixed _position may be: {current|first|last|next|previous|somenumber}
	 * @return array
	 * @access public
	 */
	public function get_record( $_position = 'next' ) {
		$this->get_raw_record( $_position );
		$this->do_fieldmapping();
		$this->do_conversions();
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
					return false;
				}
				break;
			case 'first' :
				if (!$this->current_position == 0) {
					$this->close_resource();
					$this->open_resource();
				}
				
			case 'next' :
				$csv_data = fgetcsv( $this->resource, self::csv_max_linelength, $this->csv_fieldsep);
				if (!is_array($csv_data)) {
					return false;
				}
				$this->current_position++;
				$this->record = $csv_data;
				//$this->record = $GLOBALS['egw']->translation->convert($csv_data, $this->csv_charset);
				break;
				
			case 'previous' :
				if ($this->current_position < 2) {
					return false;
				}
				$final_position = --$this->current_position;
				$this->close_resource();
				$this->open_resource();
				while ($this->current_position !== $final_position) {
					$this->get_raw_record();
				}
				break;
				
			case 'last' :
				while ($this->get_raw_record()) {}
				break;
				
			default: //somenumber
				if (!is_int($_position)) return false;
				if ($_position == $this->current_position) {
					break;
				}
				elseif ($_position < $this->current_position) {
					$this->close_resource();
					$this->open_resource();
				}
				while ($this->current_position !== $_position) {
					$this->get_raw_record();
				}
				break;				
		}
		return true;
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
		foreach ($this->mapping as $cvs_idx => $new_idx) {
			$record = $this->record;
			$this->record = array();
			$this->record[$new_idx] = $record[$cvs_idx];
			return true;
		}
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
			return true;
		}
		else return false;
	} // end of member function do_conversions


	/**
	 * opens the csv resource (php-stream)
	 *
	 * @param string _resource resource containing data. May be each valid php-stream
	 * @param array _options options for the resource array with keys: charset and fieldsep
	 * @return 
	 * @access private
	 */
	private function open_resource() {
		if ( !is_readable ( $this->csv_resourcename )) {
			error_log('error: file '. $this->csv_resourcename .' is not readable by webserver '.__FILE__.__LINE__);
			return false;
		}
		
		$this->resource = fopen ($this->csv_resourcename, 'rb');
		
		if (!is_resource($this->resource)) {
			// some error handling
			return false;
		}
		$this->current_position = 0;
		return true;
	} // end of member function open_resource

	/**
	 * closes the csv resource (php-stream)
	 *
	 * @return bool
	 * @access private
	 */
	private function close_resource() {
		return fclose( $this->resource );
	} // end of member function close_resource

} // end of import_csv
?>
