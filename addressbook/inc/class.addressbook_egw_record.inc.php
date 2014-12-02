<?php
/**
 * eGroupWare - Addressbook - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class addressbook_egw_record
 * compability layer for iface_egw_record needet for importexport
 */
class addressbook_egw_record implements importexport_iface_egw_record
{

	private $identifier = '';
	private $contact = array();
	private $bocontacts;

	// Used in conversions
	static $types = array(
		'select-account' => array('owner','creator','modifier'),
		'date-time' => array('modified','created','last_event','next_event'),
		'date' => array('bday'),
		'select-cat' => array('cat_id'),
		'select' => array('tid')
	);


	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ){
		$this->identifier = $_identifier;
		$this->bocontacts = new addressbook_bo();
		if($_identifier) {
			$this->contact = $this->bocontacts->read($this->identifier);
		}
	}

	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name) {
		return $this->contact[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data) {
		$this->contact[$_attribute_name] = $data;
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
		// do not return binary jpeg, it messes up json data
		return array_diff_key($this->contact, array('jpegphoto' => true));
	}

	/**
	 * gets title of record
	 *
	 *@return string tiltle
	 */
	public function get_title() {
		return $this->bocontacts->link_title(empty($this->contact) ? $this->identifier : $this->contact);
	}

	/**
	 * sets complete record from associative array
	 *
	 * @todo add some checks
	 * @return void
	 */
	public function set_record(array $_record){
		$this->contact = $_record;
	}

	/**
	 * gets identifier of this record
	 *
	 * @return string identifier of current record
	 */
	public function get_identifier() {
		return $this->identifier ? $this->identifier : $this->id;
	}

	/**
	 * Gets the URL icon representitive of the record
	 * This could be as general as the application icon, or as specific as a contact photo
	 *
	 * @return string Full URL of an icon, or appname/icon_name
	 */
	public function get_icon() {
		$ui = new addressbook_ui();

		// Type as default
		$label = $icon = null;
		$ui->type_icon($this->owner, $this->private, $this->tid, $icon, $label);

		// Specific photo
		return $this->jpegphoto ? egw_framework::link('/index.php',$ui->photo_src($this->identifier,$this->jpegphoto)):$icon;
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
		unset($_dst_identifier);	// not used, but required by function signature
	}

	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier ) {
		unset($_dst_identifier);	// not used, but required by function signature
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
		unset ($this->bocontacts);
	}

} // end of egw_addressbook_record
