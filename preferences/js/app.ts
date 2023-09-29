/**
 * EGroupware Preferences
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package preferences
 */

import {EgwApp} from '../../api/js/jsapi/egw_app';
import type {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {egw} from "../../api/js/jsapi/egw_global";

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

	addToken(_ev : PointerEvent, _button : Et2Button)
	{
		console.log('app.preferences.addToken', arguments);

		this.openDialog('preferences.EGroupware\\Preferences\\Token.edit');
	}

	editToken(_action, _selection)
	{
		console.log('app.preferences.editToken', arguments);

		this.openDialog('preferences.EGroupware\\Preferences\\Token.edit&token_id='+_selection[0].id.split('::')[1]);
	}
}

// @ts-ignore
app.classes.preferences = PreferencesApp;