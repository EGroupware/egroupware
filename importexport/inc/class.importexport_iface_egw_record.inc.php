<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class importexport_iface_egw_record
 * This a the abstract interface of an egw record.
 * A record is e.g. a single address or or single event.
 * The idea behind is that we can have metaoperation over differnt apps by
 * having a common interface.
 * A record is identified by a identifier. As we are a Webapp and want to
 * deal with the objects in the browser, identifier should be a string!
 *
 * @todo lots! of discussion with other developers
 * @todo move to api once developers accepted it!
 * @todo functions for capabilities of object
 * @todo caching. e.g. only read name of object
 */
interface importexport_iface_egw_record
{

	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' );
	
	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name);
	
	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data);
	
	/**
	 * converts this object to array.
	 * @abstract We need such a function cause PHP5
	 * dosn't allow objects do define it's own casts :-(
	 * once PHP can deal with object casts we will change to them!
	 *
	 * @return array complete record as associative array
	 */
	public function get_record_array();
	
	/**
	 * gets title of record
	 *
	 *@return string tiltle
	 */
	public function get_title();
	
	/**
	 * sets complete record from associative array
	 *
	 * @return void
	 */
	public function set_record(array $_record);
	
	/**
	 * gets identifier of this record
	 *
	 * @return string identifier of this record
	 */
	public function get_identifier();

	/**
	 * Gets the URL icon representitive of the record
	 * This could be as general as the application icon, or as specific as a contact photo
	 *
	 * @return string Full URL of an icon, or appname/icon_name
	 */
	public function get_icon();
	
	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier );
	
	/**
	 * copys current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier );
	
	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier );
	
	/**
	 * delets current record from backend
	 * @return void
	 *
	 */
	public function delete ();
	
	/**
	 * destructor
	 *
	 */
	public function __destruct();

} // end of iface_egw_record
?>
