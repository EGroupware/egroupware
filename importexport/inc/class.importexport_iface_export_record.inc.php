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
 * class iface_export_record
 * This a the abstract interface for an record exporter.
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end export_entry
 * stores it into the stream
 * NOTE: we don't give records the type "egw_reocrd". Thats becuase 
 * PHP5 dosn't allow objects do define it's own casts :-(
 * 
 * NOTE: You are not forced to implement this interface to attend importexport
 * framework. However if you plugin implements this interface it might also be
 * usable for other tasks.
 * 
 */
interface importexport_iface_export_record
{
	/**
	 * constructor
	 *
	 * @param stream $_stream resource where records are exported to.
	 * @param array $_options options for specific backends
	 * @return bool
	 */
	public function __construct( $_stream, array $_options );
	
	/**
	 * exports a record into resource of handle
	 *
	 * @param object of interface egw_record _record
	 * @return bool
	 */
	public function export_record( importexport_iface_egw_record $_record );

	/**
	 * Retruns total number of exported records.
	 *
	 * @return int
	 */
	public function get_num_of_records( );

	/**
	 * destructor
	 *
	 * @return 
	 */
	public function __destruct( );

} // end of iface_export_record
?>
