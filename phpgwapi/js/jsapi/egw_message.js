/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_core;
*/

/**
 * Methods to display a success or error message and the app-header
 *
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('message', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	_app;	// not used, but required by function signature
	var message_timer;
	var jQuery = _wnd.jQuery;
	var framework = _wnd.framework;
	var is_popup = !framework || _wnd.opener;
	var error_reg_exp;

	// install handler to remove message on click
	jQuery('body').on('click', 'div#egw_message', function(e) {
		jQuery('div#egw_message').remove();
	});

	return {
		/**
		 * Display an error or regular message
		 *
		 * @param {string} _msg message to show or empty to remove previous message
		 * @param {string} _type 'error', 'warning' or 'success' (default)
		 */
		message: function(_msg, _type)
		{
			if (_msg && typeof _type == 'undefined')
			{
				if (typeof error_reg_exp == 'undefined') error_reg_exp = new RegExp('(error|'+egw.lang('error')+')', 'i');

				_type = _msg.match(error_reg_exp) ? 'error' : 'success';
			}

			// if we are NOT in a popup and have a framwork --> let it deal with it
			if (!is_popup && typeof framework.setMessage != 'undefined')
			{
				// currently not using framework, but top windows message
				//framework.setMessage.call(framework, _msg, _type);
				if (_wnd !== _wnd.top)
				{
					egw(_wnd.top).message(_msg, _type);
					return;
				}
			}
			// handle message display for non-framework templates, eg. idots or jerryr
			if (message_timer)
			{
				_wnd.clearTimeout(message_timer);
				message_timer = null;
			}
			var parent = jQuery('div#divAppboxHeader');
			// popup has no app-header (idots) or it is hidden by onlyPrint class (jdots) --> use body
			if (!parent.length || parent.hasClass('onlyPrint'))
			{
				parent = jQuery('body');
			}
			jQuery('div#egw_message').remove();

			if (_msg)	// empty _msg just removes pervious message
			{
				parent.prepend(jQuery(_wnd.document.createElement('div'))
					.attr('id','egw_message')
					.text(_msg)
					.addClass(_type+'_message')
					.css('position', 'absolute'));

				if (_type != 'error')	// clear message again after some time, if no error
				{
					message_timer = _wnd.setTimeout(function() {
						jQuery('div#egw_message').remove();
					}, 5000);
				}
			}
		},

		/**
		 * Update app-header and website-title
		 *
		 * @param {string} _header
		 * @param {string} _app Application name, if not for the current app
		 */
		app_header: function(_header,_app)
		{
			if (!is_popup)	// not for popups
			{
				var app = _app || egw_getAppName();
				var title = _wnd.document.title.replace(/[.*]$/, '['+_header+']');

				framework.setWebsiteTitle.call(window.framework, app, title, _header);
				return;
			}
			jQuery('div#divAppboxHeader').text(_header);

			_wnd.document.title = _wnd.document.title.replace(/[.*]$/, '['+_header+']');
		}
	};
});
