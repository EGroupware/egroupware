<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

//require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.iface_egw_record.inc.php');

/**
 * class iface_export_plugin
 * This a the abstract interface for an export plugin of importexport
 * 
 * You need to implement this class in 
 * EGW_INCLUDE_ROOT/appname/inc/class.export_<type>.inc.php
 * to attend the importexport framwork with your export.
 * 
 * NOTE: This is an easy interface, cause plugins live in theire own 
 * space. Means that they are respnsible for generationg a defintion AND 
 * working on that definition.
 * So this interface just garanties the interaction with userinterfaces. It
 * has nothing to do with datatypes.
 * 
 * JS:
 * required function in opener:
 * 
 * 
 * // returns array of identifiers
 * // NOTE: identifiers need to b
 * get_selection(); 
 *
 * get_selector();  //returns array 
 */
interface iface_export_plugin {
	
	/**
	 * exports entries according to given definition object.
	 *
	 * @param definition $_definition
	 */
	public static function export($_stream, $_charset, definition $_definition);
	
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
	 * retruns file suffix for exported file (e.g. csv)
	 *
	 * @return string suffix
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
	
	public static function get_options_etpl();
	
	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public static function get_selectors_etpl();

} // end of iface_export_plugin
?>
