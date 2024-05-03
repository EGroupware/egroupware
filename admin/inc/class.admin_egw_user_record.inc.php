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
 * class admin_egw_user_record
 * Record class needed for export
 */
class admin_egw_user_record implements importexport_iface_egw_record
{
	private $identifier = '';
	private $user = array();

	// Used in conversions
	static $types = array(
		'date-time' => array('account_lastlogin', 'account_lastpwd_change', 'account_expires'),
		'select-account' => array('account_primary_group', 'account_groups'),
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
		return $this->user[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data) {
		$this->user[$_attribute_name] = $data;
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
		return $this->user;
	}

	/**
	 * gets title of record
	 *
	 *@return string title
	 */
	public function get_title() {
		return $this->identifier ? Api\Accounts::username($this->identifier) :
			Accounts::format_username($this->account_lid, $this->account_firstname, $this->account_lastname, $this->identifier);
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record){
		$this->user = $_record;
		$this->account_groups = $GLOBALS['egw']->accounts->memberships($this->identifier, true);
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
		return 'user';
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
		unset ($this->user);
	}
}
