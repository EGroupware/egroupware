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
 * class importexport_iface_export_plugin
 * This a the abstract interface for an export plugin of importexport
 *
 * You need to implement this class in
 * EGW_INCLUDE_ROOT/appname/inc/importexport/class.export_<type>.inc.php
 * to attend the importexport framwork with your export.
 *
 * NOTE: This is an easy interface, cause plugins live in theire own
 * space. Means that they are responsible for generating a defintion AND
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
interface importexport_iface_export_plugin {

	/**
	 * exports entries according to given definition object.
	 *
	 * @param stream $_stream
	 * @param importexport_definition $_definition
	 */
	public function export($_stream, importexport_definition $_definition);

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
	 * returns mime type for exported file
	 *
	 * @return string mimetype
	 */
	public static function get_mimetype();

	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @param $definition Specific definition
	 * 
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options	=> array,
	 * 		readonlys	=> array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition=null);

	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options	=> array,
	 * 		readonlys	=> array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_selectors_etpl();

	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * importexport_iface_egw_record classes are used for a lot of field detection
	 * and automatic conversions.  In most cases they are named <appname>_egw_record,
	 * but projectmanager is an exception to this.
	 *
	 * @return string;
	 */
	public static function get_egw_record_class();
} // end of iface_export_plugin
?>
