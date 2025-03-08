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

use EGroupware\Api;
use EGroupware\Api\Framework;

/**
 * class addressbook_egw_record
 * compatibility layer for iface_egw_record needed for importexport
 *
 * Note that last_event and next_event are not automatically loaded by
 * addressbook_bo->read(), so if you need them use:
 * addressbook_bo->read_calendar();
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
		$this->bocontacts = new Api\Contacts();
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
	 * doesn't allow objects to define its own casts :-(
	 * once PHP can deal with object casts we will change to them!
	 *
	 * @return array complete record as associative array
	 */
	public function get_record_array() {
		return $this->contact;
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
		return $this->jpegphoto ? Framework::link('/index.php',$ui->photo_src($this->identifier,$this->jpegphoto)):$icon;
	}
	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {
		// Not yet implemented
		$this->identifier = $_dst_identifier;
	}

	/**
	 * copies current record to record identified by $_dst_identifier
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
	 * deletes current record from backend
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
}
