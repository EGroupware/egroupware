<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:$
 */

/**
 * class iface_import_plugin
 * This a the abstract interface for an import plugin of importexport
 * 
 * You need to implement this class in 
 * EGW_INCLUDE_ROOT/appname/inc/importexport/class.import_<type>.inc.php
 * to attend the importexport framwork with your export.
 * 
 * NOTE: This is an easy interface, cause plugins live in theire own 
 * space. Means that they are responsible for generating a defintion AND 
 * working on that definition.
 * So this interface just garanties the interaction with userinterfaces. It
 * has nothing to do with datatypes.
 */
interface importexport_iface_import_plugin {
	
	/**
	 * imports entries according to given definition object.
	 *
	 * @param stram $_stram
	 * @param definition $_definition
	 * @return int number of successful imports
	 */
	public function import( $_stream, importexport_definition $_definition );
	
	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name();
	
	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description();
	
	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix();
	
	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 * 
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options => array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl();
	
	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public function get_selectors_etpl();

	/**
	* Returns errors that were encountered during importing
	* Maximum of one error message per record, but you can concatenate them if you need to
	*
	* @return Array (
	*	record_# => error message
	*	)
	*/
	public function get_errors();

	/**
	* Returns a list of actions taken, and the number of records for that action.
	* Actions are things like 'insert', 'update', 'delete', and may be different for each plugin.
	*
	* @return Array (
	*	action => record count
	* )
	*/
	public function get_results();

} // end of iface_export_plugin
?>
