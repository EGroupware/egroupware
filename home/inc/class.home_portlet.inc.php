<?php
/**
 * EGroupware - Home - Portlet interface
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @subpackage portlet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

abstract class home_portlet
{
	/**
	 * Attributes that are common to all portlets, but are customized indirectly
	 * through the UI, rather than explictly through the configure popup
	 */
	public static $common_attributes = array(
		'width', 'height', 'row', 'col'
	);

	/**
	 * Constructor sets up the portlet according to the user's saved property values
	 * for this particular portlet.  It is possible to have multiple instances of the
	 * same portlet with different properties.
	 *
	 * The implementing class is allowed to modify the context, if needed, but it is
	 * better to use get_properties().
	 *
	 * @param context Array portlet settings such as size, as well as values for properties
	 * @param boolean $need_reload Flag to indicate that the portlet needs to be reloaded (exec will be called)
	 */
	public abstract function __construct(Array &$context = array(), &$need_reload = false);
	
	/**
	 * Some descriptive information about the portlet, so that users can decide if
	 * they want it or not, and for inclusion in lists, hover text, etc.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @return Array with keys:
	 * - displayName: Used in lists
	 * - title: Put in the portlet header
	 * - description: A short description of what this portlet does or displays
	 */
	public abstract function get_description();

	/**
	 * Generate the display for the portlet
	 *
	 * @param id String unique ID, provided to the portlet so it can make sure content is
	 * 	unique, if needed.
	 * @param etemplate etemplate_new Etemplate to generate content
	 * @param dom_id String ID of the etemplate targe DOM node.  If passed, send -1 to etemplate->exec()
	 */
	public abstract function exec($id = null, etemplate_new &$etemplate = null);

	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @see preferences/inc/class.preferences_settings.inc.php
	 * @return Array of settings.  Each setting should have the following keys:
	 * - name: Internal reference
	 * - type: Widget type for editing
	 * - label: Human name
	 * - help: Description of the setting, and what it does
	 * - default: Default value, for when it's not set yet
	 */
	public function get_properties()
	{
		// Include the common attributes, or they won't get saved
		$properties = array();
		foreach(self::$common_attributes as $prop)
		{
			$properties[$prop] = array('name' => $prop);
		}

		$properties[] = array(
			'name'	=>	'color',
			'type'	=>	'colorpicker',
			'label'	=>	lang('Color'),
		);
		return $properties;
	}

	/**
	 * Return a list of allowable actions for the portlet.
	 *
	 * These actions will be merged with the default portlet actions.  Use the
	 * same id / key to override the default action.
	 */
	public abstract function get_actions();

	/**
	 * If this portlet can accept, display, or otherwise handle multiple
	 * EgroupWare entries.  Used for drag and drop processing.  How the entries
	 * are handled are up to the portlet.
	 */
	public function accept_multiple()
	{
		return false;
	}

	public function __toString()
	{
		return get_called_class() . ' Context:' . array2string($this->context);
	}
}
