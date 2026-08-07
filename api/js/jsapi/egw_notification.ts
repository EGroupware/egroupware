/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-AT-stylite.de>
 */

/*egw:uses
	egw_core;
*/
import './egw_core';

export interface NotificationModule
{
	/**
	 * @param _title a string to be shown as notification message
	 * @param _options an object of Notification possible options:
	 *		options = {
	 *			dir:  // direction of notification to be shown rtl, ltr or auto
	 *			lang: // a valid BCP 47 language tag
	 *			body: // DOM body
	 *			icon: // parse icon URL, default icon is app icon
	 *			tag: // a string value used for tagging an instance of notification, default is app name
	 *			onclick: // Callback function dispatches on click on notification message
	 *			onshow: // Callback function dispatches when notification is shown
	 *			onclose: // Callback function dispateches on notification close
	 *			onerror: // Callback function dispatches on error, default is a egw.debug log
	 *		    requireInteraction: // boolean value indicating that a notification should remain active until the user clicks or dismisses it
	 *		}
	 *	@return false if Notification is not supported by browser
	 */
	notification(_title : string, _options : {dir?: "ltr"|"rtl"|"auto", lang?: string, body?: string, icon?: string,
		tag?: string, onclick?: Function, onshow?: Function, onclose?: Function, onerror?: Function, requireInteraction?: boolean}) : false|void;

	/**
	 * Check Notification availability by browser
	 *
	 * @returns true if notification is supported and permitted otherwise false
	 */
	checkNotification() : boolean;

	/**
	 * Check if there's any runnig notifications and will close them all
	 */
	killAliveNotifications() : void;
}

declare global
{
	interface IegwWndLocal extends NotificationModule
	{
	}
}

/**
 * Methods to display browser notification
 *
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 *
 * @return {object} defined functions of module
 */
egw.extend('notification', egw.MODULE_WND_LOCAL, function(_app : string, _wnd : Window) : NotificationModule
{
	"use strict";

	// Notification permission, the default value is 'default' which is equivalent to 'denied'
	var permission = 'default';
	// Keeps alive notifications
	var alive_notifications : any[] = [];

	if (typeof Notification != 'undefined')
	{
		permission = Notification.permission;
	}

	return {

		/**
		 *
		 * @param {string} _title a string to be shown as notification message
		 * @param {object} _options an object of Notification possible options:
		 *		options = {
		 *			dir:  // direction of notification to be shown rtl, ltr or auto
		 *			lang: // a valid BCP 47 language tag
		 *			body: // DOM body
		 *			icon: // parse icon URL, default icon is app icon
		 *			tag: // a string value used for tagging an instance of notification, default is app name
		 *			requireInteraction: // boolean value indicating that a notification should remain active until the user clicks or dismisses it
		 *			onclick: // Callback function dispatches on click on notification message
		 *			onshow: // Callback function dispatches when notification is shown
		 *			onclose: // Callback function dispateches on notification close
		 *			onerror: // Callback function dispatches on error, default is a egw.debug log
		 *		}
		 *	@return {boolean} false if Notification is not supported by browser
		 */
		notification: function (this : any, _title : string, _options? : any) : any
		{
			// Check if the notification is supported by  browser
			if (typeof Notification == 'undefined') return false;

			var self = this;
			// Check and ask for permission
			if (Notification && Notification.requestPermission && permission === 'default') (<any>Notification).requestPermission (function(_permission : string){
				permission = _permission;
				if (permission === 'granted') self.notification(_title,_options);
			});

			// All options including callbacks
			var options : any = _options || {};

			// Options used for creating Notification instane
			var inst_options : any = {
				tag: options.tag || egw.app_name(),
				dir: options.dir || 'ltr',
				lang: options.lang || egw.preference('lang', 'common'),
				body: options.body || '',
				icon: options.icon || egw.image('navbar', egw.app_name()),
				requireInteraction: options.requireInteraction || false
			};

			// Create an  instance of Notification object
			var notification = new Notification(_title, inst_options);

			//set timer to close shown notification in 10 s, some browsers do not
			//close it automatically.
			setTimeout(notification.close.bind(notification), 10000);

			// Callback function dispatches on click on notification message
			// (cast: DOM types require Function|null here, but the original
			// falls back to '' - preserved exactly rather than "fixed" to null,
			// which would be an observable behavior change)
			(<any>notification).onclick = options.onclick || '';
			// Callback function dispatches when notification is shown
			(<any>notification).onshow = options.onshow || '';
			// Callback function dispateches on notification close
			(<any>notification).onclose = options.onclose || '';
			// Callback function dispatches on error
			// (this call is single-argument, so the message string ends up in
			// debug()'s _level parameter, which it never matches - a silent
			// no-op in the original .js too; preserved via cast, not "fixed"
			// by adding a level argument)
			(<any>notification).onerror = options.onerror || function (e) {(<any>egw.debug)('Notification failed because of ' + e);};

			// Collect all running notifications in case if want to close them all,
			// for instance on logout action.
			alive_notifications.push(notification);
		},

		/**
		 * Check Notification availability by browser
		 *
		 * @returns {Boolean} true if notification is supported and permitted otherwise false
		 */
		checkNotification: function () : boolean {
			// Check if the notification is supported by  browser
			if (typeof Notification == 'undefined') return false;

			// Ask for permission if there's nothing decided yet
			if (Notification && Notification.requestPermission && permission == 'default') {
				(<any>Notification).requestPermission (function(_permission : string){
					permission = _permission;
				});
			}
			return !!(Notification && Notification.requestPermission && permission == 'granted');
		},

		/**
		 * Check if there's any runnig notifications and will close them all
		 *
		 */
		killAliveNotifications: function () : void
		{
			if (alive_notifications && alive_notifications.length > 0)
			{
				for (var i=0; i<alive_notifications.length;i++)
				{
					if (typeof alive_notifications[i].close == 'function') alive_notifications[i].close();
				}
				alive_notifications = [];
			}
		}
	};
});
