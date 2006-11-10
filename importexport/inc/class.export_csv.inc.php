<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

require_once('class.iface_export_record.inc.php');
require_once('class.import_export_helper_functions.inc.php');
require_once('class.iface_egw_record.inc.php');

/**
 * class export_csv
 * This an record exporter.
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end export_entry
 * stores it into the stream
 */
class export_csv implements iface_export_record
{

	/** Aggregations: */

	/** Compositions: */

	/**
	 * array with field mapping in form egw_field_name => exported_field_name
	 * @var array
	 */
	protected  $mapping = array();

	/**
	 * array with conversions to be done in form: egw_field_name => conversion_string
	 * @var array
	 */
	protected  $conversion = array();
	
	/**
	 * array holding the current record
	 * @access protected
	 */
	protected $record = array();
	
	/**
	 * holds (charset) translation object
	 * @var object
	 */
	protected $translation;
	
	/**
	 * charset of csv file
	 * @var string
	 */
	protected $csv_charset;
	
	/**
	 * holds number of exported records
	 * @var unknown_type
	 */
	protected $num_of_records = 0;
	
	/**
	 * stream resource of csv file
	 * @var resource
	 */
	protected  $handle;
	
	/**
	 * csv specific options
	 *
	 * @var array
	 */
	protected $csv_options = array(
		'delimiter' => ';',
		'enclosure' => '"',
	);
	
	/**
	 * constructor
	 *
	 * @param object _handle resource where records are exported to.
	 * @param string _charset charset the records are exported to.
	 * @param array _options options for specific backends
	 * @return bool
	 * @access public
	 */
	public function __construct( $_handle,  $_charset, array $_options=array() ) {
		$this->translation &= $GLOBALS['egw']->translation;
		$this->handle = $_handle;
		$this->csv_charset = $_charset;
		if (!empty($_options)) {
			$this->csv_options = array_merge($this->csv_options,$_options);
		}
	}
	
	/**
	 * sets field mapping
	 *
	 * @param array $_mapping egw_field_name => csv_field_name
	 */
	public function set_mapping( array $_mapping) {
		if ($this->num_of_records > 0) {
			throw new Exception('Error: Field mapping can\'t be set during ongoing export!');
		}
		foreach ($_mapping as $egw_filed => $csv_field) {
			$this->mapping[$egw_filed] = $this->translation->convert($csv_field, $this->csv_charset);
		}
	}
	
	/**
	 * Sets conversion.
	 * See import_export_helper_functions::conversion.
	 *
	 * @param array $_conversion
	 */
	public function set_conversion( array $_conversion) {
		$this->conversion = $_conversion;
	}
	
	/**
	 * exports a record into resource of handle
	 *
	 * @param iface_egw_record record
	 * @return bool
	 * @access public
	 */
	public function export_record( iface_egw_record $_record ) {
		$record_data = $_record->get_record_array();
		
		if (empty($this->mapping)) {
			$this->mapping = array_combine(array_keys($record_data),array_keys($record_data));
		}
		
		if ($this->num_of_records == 0 && $this->csv_options['begin_with_fieldnames'] && !empty($this->mapping)) {
			fputcsv($this->handle,array_values($this->mapping),$this->csv_options['delimiter'],$this->csv_options['enclosure']);
		}
		
		// do conversions
		if ($this->conversion[$egw_field]) {
			$record_data[$egw_field] = import_export_helper_functions::conversion($record_data,$this->conversion);
		}
		
		// do fieldmapping
		foreach ($this->mapping as $egw_field => $csv_field) {
			$this->record[$csv_field] = $record_data[$egw_field];
		}
		
		$this->fputcsv($this->handle,$this->record,$this->csv_options['delimiter'],$this->csv_options['enclosure']);
		$this->num_of_records++;
		$this->record = array();
	}

	/**
	 * Retruns total number of exported records.
	 *
	 * @return int
	 * @access public
	 */
	public function get_num_of_records() {
		return $this->num_of_records;
	}

	/**
	 * destructor
	 *
	 * @return 
	 * @access public
	 */
	public function __destruct() {
		
	}

	/**
	 * The php build in fputcsv function is buggy, so we need an own one :-(
	 *
	 * @param resource $filePointer
	 * @param array $dataArray
	 * @param char $delimiter
	 * @param char $enclosure
	 */
	protected function fputcsv($filePointer, $dataArray, $delimiter, $enclosure){
		$string = "";
		$writeDelimiter = false;
		foreach($dataArray as $dataElement) {
			if($writeDelimiter) $string .= $delimiter;
			$string .= $enclosure . $dataElement . $enclosure;
			$writeDelimiter = true;
		} 
		$string .= "\n";
		
		fwrite($filePointer, $string);
			
	}
} // end  export_csv_record
?>
