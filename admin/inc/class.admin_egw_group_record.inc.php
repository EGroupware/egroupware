<?php
/**
 * eGroupWare - Admin - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

/**
 * class admin_egw_group_record
 * Record class needed for export
 */
class admin_egw_group_record implements importexport_iface_egw_record
{

	private $identifier = '';
	private $group = array();

	// Used in conversions
	static $types = array(
		'select-account' => array('account_members'),
		'select' => array('account_status'),
	);



	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ) {
		if(is_array($_identifier)) {
			$this->identifier = $_identifier['account_id'];
		} else {
			$this->identifier = $_identifier;
		}
		$this->set_record($GLOBALS['egw']->accounts->read($this->identifier));
	}

	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name) {
		return $this->group[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data) {
		$this->group[$_attribute_name] = $data;
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
		return $this->group;
	}

	/**
	 * gets title of record
	 *
	 *@return string title
	 */
	public function get_title() {
		return common::grab_owner_name($this->identifier);
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record){
		$this->group = $_record;
		$this->group['account_members'] = $GLOBALS['egw']->accounts->members($this->group['account_id'],true);
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
		unset ($this->group);
	}

} // end of egw_timesheet_record
