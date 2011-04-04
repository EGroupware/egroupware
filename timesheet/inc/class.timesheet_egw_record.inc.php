<?php
/**
 * eGroupWare - Addressbook - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Knut Moeller <k.moeller@metaways.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>, Knut Moeller <k.moeller@metaways.de>
 * @version $Id: class.egw_addressbook_record.inc.php 22827 2006-11-10 15:35:35Z nelius_weiss $
 */

/**
 * class egw_addressbook_record
 * compability layer for iface_egw_record needet for importexport
 */
class timesheet_egw_record implements importexport_iface_egw_record
{

	private $identifier = '';
	private $timesheetentry = array();
	private $botimesheet;

	// Used in conversions
	static $types = array(
		'select-account' => array('ts_owner','ts_modifier'),
		'date-time' => array('ts_start', 'ts_modified'),
		'select-cat' => array('cat_id'),
		'links' => array('pl_id'),
		'select' => array('ts_status'),
	);



	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ){
		$this->identifier = $_identifier;
		$this->botimesheet = new timesheet_bo();
		$this->set_record($this->botimesheet->read($this->identifier));
	}

	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name) {
		return $this->timesheetentry[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data) {
		$this->timesheetentry[$_attribute_name] = $data;
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
		return $this->timesheetentry;
	}

	/**
	 * gets title of record
	 *
	 *@return string title
	 */
	public function get_title() {
// TODO get_record gibts nicht ?
//		if (empty($this->timesheetentry)) {
//			$this->get_record();
//		}
		return $this->timesheetentry['ts_project'] . ' - ' . $this->timesheetentry['ts_title'];
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record){
		$this->timesheetentry = $_record;
		// Check for linked project ID
		if($this->timesheetentry['ts_project']) {
			$links = egw_link::get_links('timesheet', $_record['ts_id'], 'projectmanager');
			foreach($links as $link_id => $app_id) {
				$this->timesheetentry['pm_id'] = $app_id;
				break;
			}
		}
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
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {

	}

	/**
	 * copies current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier ) {

	}

	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier ) {

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
		unset ($this->botimesheet);
	}

} // end of egw_timesheet_record
