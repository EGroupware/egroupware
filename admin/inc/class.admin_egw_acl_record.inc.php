<?php
/**
 * EGroupware - Admin - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

use EGroupware\Api;

/**
 * class admin_egw_acl_record
 * Record class needed for export
 */
class admin_egw_acl_record implements importexport_iface_egw_record
{
	private $identifier = '';
	private $record = array();

	// Used in conversions
	static $types = array(
		'select' => array('acl_appname'),
		'select-account' => array('acl_account', 'acl_location'),
		'select-bool' => array('acl_run', 'acl1', 'acl2','acl4','acl8','acl16','acl64','acl128','acl256'),
	);

	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ) {
		if(is_array($_identifier)) {
			$this->identifier = $_identifier['id'];
			$this->set_record($_identifier);
		} else {
			$this->identifier = $_identifier;
			//$GLOBALS['egw']->acl->read($this->identifier);
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
	 *@return string title
	 */
	public function get_title() {
		return Api\Accounts::username($this->identifier);
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record) {
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
		return 'access';
	}

	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used, but require by function signature
	}

	/**
	 * copies current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used, but require by function signature
	}

	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used, but require by function signature
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
		unset ($this->record);
	}
}
