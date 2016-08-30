<?php
/**
 * EGroupware - Calendar - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * class calendar_egw_record
 * compability layer for iface_egw_record needet for importexport
 */
class calendar_egw_record implements importexport_iface_egw_record
{

	private $identifier = '';
	private $record = array();
	private static $bo;

	public static $types = array(
		'select-cat'    => array('category'),
		'select-account'=> array('owner','creator', 'modifier'),
		'date-time'     => array('modified', 'created','start','end','recur_date'),
		'date'			=> array('recur_enddate'),
		'select-bool'	=> array('public', 'non_blocking'),
		'select'	=> array('priority'),
	);

	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ){
		$this->identifier = $_identifier;
		if(!is_object($this->bo)) {
			$this->bo = new calendar_bo();
		}
		if($this->identifier) {
			$this->record = $this->bo->read($this->identifier);
		}
	}

	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name) {
		return $this->record[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data) {
		$this->record[$_attribute_name] = $data;
	}

	public function __unset($_attribute_name) {
		unset($this->record[$_attribute_name]);
	}

	/**
	 * converts this object to array.
	 * @abstract We need such a function cause PHP5
	 * dosn't allow objects do define it's own casts :-(
	 * once PHP can deal with object casts we will change to them!
	 *
	 * @return array complete record as associative array
	 */
	public function get_record_array() {
		return $this->record;
	}

	/**
	 * gets title of record
	 *
	 *@return string tiltle
	 */
	public function get_title() {
		if (empty($this->record)) {
			$this->get_record();
		}
		return $this->record['title'];
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record){
		$this->record = $_record;
	}

	/**
	 * gets identifier of this record
	 *
	 * @return string identifier of current record
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * Gets the URL icon representitive of the record
	 * This could be as general as the application icon, or as specific as a contact photo
	 *
	 * @return string Full URL of an icon, or appname/icon_name
	 */
	public function get_icon() {
		return 'calendar/navbar';
	}

	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {
		// Not yet implemeted
		$this->identifier = $_dst_identifier;
	}

	/**
	 * copys current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used
	}

	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used
	}

	/**
	 * delets current record from backend
	 *
	 */
	public function delete () {

	}

	/**
	 * destructor
	 *
	 */
	public function __destruct() {
	}

}