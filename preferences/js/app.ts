/**
 * EGroupware Preferences
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package preferences
 */

import {EgwApp} from '../../api/js/jsapi/egw_app';

/**
 * JavaScript for Preferences
 *
 * @augments AppJS
 */
export class PreferencesApp extends EgwApp
{
	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		// call parent
		super("preferences");
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param name
	 */
	et2_ready(et2, name)
	{
		// call parent
		super.et2_ready(et2, name);
	}
}

// @ts-ignore
app.classes.preferences = PreferencesApp;
