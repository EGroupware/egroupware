<?php
/**
 * eGroupWare - Infolog - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * class infolog_egw_record
 *
 * compability layer for iface_egw_record needet for importexport
 */
class infolog_egw_record implements importexport_iface_egw_record
{
	private $identifier = '';
	private $record = array();
	private static $bo;

	// Used in conversions
	static $types = array(
		'select' => array('info_type', 'info_status', 'info_priority', 'pl_id'),
                'select-cat' => array('info_cat'),
                'select-account' => array('info_owner','info_responsible','info_modifier'),
                'date-time' => array('info_startdate', 'info_enddate','info_datecompleted', 'info_datemodified','info_created'),
		'links' => array('info_link_id'),
        );

	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ){
		$this->identifier = $_identifier;
		if(self::$bo == null) self::$bo = new infolog_bo();
		if($_identifier) {
			$rec = self::$bo->read($this->identifier);
			if (is_array($rec)) $this->set_record($rec);
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
	 *@return string tiltle
	 */
	public function get_title() {
		if (empty($this->record)) {
			$this->get_record();
		}
		return self::$bo->link_title($this->record);
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
		$icon = 'infolog/navbar';
		$ui = new infolog_ui();
		if (!($icon = $ui->icons['type'][$this->info_type]))
		{
			$icon = $ui->icons['status'][$this->info_status];
		}
		if (!$icon)
		{
			$icon = 'navbar';
		}
		return 'infolog/'.$icon;
	}

	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {

	}

	/**
	 * copys current record to record identified by $_dst_identifier
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
	}

} // end of egw_addressbook_record
?>
