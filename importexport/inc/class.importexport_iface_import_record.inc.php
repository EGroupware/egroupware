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
 * class iface_import_record
 * This a the abstract interface for an record importer.
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end the get_entry method comes out
 */
interface importexport_iface_import_record
{
	/**
	 * Opens resource, returns false if something fails
	 *
	 * @param stream $_stream resource containing data. Differs according to the implementations
	 * @param array $_options options for specific backends
	 * @return bool
	 */
	public function __construct( $_stream, array $_options );

	/**
	 * cleanup
	 *
	 * @return 
	 */
	public function __destruct( );

	/**
	 * Returns array with the record found at position and updates the position
	 *
	 * @param string _position may be: {first|last|next|previous|somenumber}
	 * @return bool
	 */
	public function get_record( $_position = 'next' );

	/**
	 * Retruns total number of records for the open resource.
	 *
	 * @return int
	 */
	public function get_num_of_records( );

	/**
	 * Returns pointer of current position
	 *
	 * @return int
	 */
	public function get_current_position( );





} // end of iface_import_record
?>
