/**
 * EGroupware Pixelegg template
 *
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @author Andreas Stoeckel <as@stylite.de>
 * @copyright EGroupware GmbH 2014-2021
 * @description Create Pixelegg framework
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	framework.fw_desktop;
	/pixelegg/js/slider.js;
*/
//import "../../vendor/bower-asset/jquery-ui/jquery-ui.js";
import "../../api/js/jquery/mousewheel/mousewheel.js";
import '../../api/js/framework/fw_desktop.js';
import './slider.js';

(function(window){
	"use strict";

	/**
	 * jdots framework object definition
	 * here we can add framework methods and also override fw_desktop methods if it is neccessary
	 */
	window.fw_pixelegg = fw_desktop.extend(
	{
		/**
		 * Callback to calculate height of browser iframe or div
		 *
		 * @param {object} _iframe dom node of iframe or null for div
		 * @returns number in pixel
		 */
		getIFrameHeight: function(_iframe)
		{
			var height = this._super.apply(this, arguments);

			return height;
		},

		/**
		 * Check to see if the tab header will overflow and want to wrap.
		 * Deal with it by setting some smaller widths on the tabs.
		 */
		checkTabOverflow: function()
		{
			var topmenuWidth = jQuery('#egw_fw_topmenu_info_items').outerWidth();
			var width = 0;
			var counter = 0;
			var marginR = parseInt(jQuery("#egw_fw_main").css('margin-right'));
			jQuery(this.tabsUi.contHeaderDiv).css('padding-right',topmenuWidth - marginR);
			var outer_width = jQuery(this.tabsUi.contHeaderDiv).width();
			var spans = jQuery(this.tabsUi.contHeaderDiv).children('span');
			spans.css('max-width','');
			spans.each(function() {
				// Do not count and add up node if the width is not set (e.g. status app)
				if (this.clientWidth > 0)
				{
					width += jQuery(this).outerWidth(true);
					counter++;
				}
			});
			if(width > outer_width)
			{
				var max_width = Math.floor(outer_width / counter) - (spans.outerWidth(true) - spans.width());
				spans.css('max-width', max_width + 'px');
			}
		},

		/**
		 * Runs after et2 is loaded
		 *
		 */
		et2_loadingFinished: function() {
			this._super.apply(this, arguments);
			framework.firstload_animation('', 100);
		},

		/**
		 * Set a notification message for topmenu info item
		 *
		 * @param {string} _id id of topmenu info item with its prefix
		 * @param {string} _message message that should be displayed
		 * @param {string} _tooltip hint text as tooltip
		 */
		topmenu_info_notify: function(_id, _switch, _message, _tooltip) {
			var $items = jQuery('#egw_fw_topmenu_info_items').children();
			var prefix = "topmenu_info_";

			$items.each(function(i,item){
				if (item.id == prefix+_id || item.id == _id)
				{
					var $notify = jQuery(item).find('.egw_fw_topmenu_info_notify');
					if (_switch)
					{
						if ($notify.length == 0)
						{
							$notify = jQuery(document.createElement('div'))
									.addClass('egw_fw_topmenu_info_notify')
									.prop('title', _tooltip)
									.appendTo(item);
						}
						$notify.prop('title', _tooltip).text(_message);
					}
					else
					{
						$notify.remove();
					}
				}
			});
		}
	});

	/**
	* Initialise framework
	*/
	window.egw_ready.then(function() {
		function egw_setSideboxSize(_size)
		{
			document.getElementById('egw_fw_main').style.marginLeft = _size + 'px';
			document.getElementById('egw_fw_sidebar').style.width = _size + 'px';
		}

		jQuery(document).ready(function() {
			//Stop the loading animation after 5sec if for whatever reason it didn't end
			setTimeout(function(){jQuery('#egw_fw_firstload').remove();}, 5000);
			window.framework = new fw_pixelegg("egw_fw_sidemenu", "egw_fw_tabs",
					window.egw_webserverUrl, egw_setSideboxSize,"egw_fw_splitter", 255, 245);	// should be identical to jdots_framework::(DEFAULT|MIN)_SIDEBAR_WIDTH
			window.callManual = window.framework.callManual;
			jQuery("#egw_fw_topmenu_info_items").on("contextmenu",function() {return false;});
			jQuery('#topmenu_info_darkmode').click(function(){window.framework.toggle_darkmode(this);});
			jQuery('#topmenu_info_user_avatar').click(function(){window.framework.toggle_avatar_menu();});
			jQuery('#topmenu_info_print_title').click(function(){window.framework.print();});
			jQuery('#topmenu_info_logout').click(function(){ window.framework.redirect(this.getAttribute('data-logout-url')); });
			jQuery('form[name^="tz_selection"]').children()
				.on('change', function() { framework.tzSelection(this.value); return false; })
				.on('click', function(e) { e.stopPropagation(); });
			window.egw.link_quick_add('topmenu_info_quick_add');
			window.egw.add_timer('topmenu_info_timer');

			// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
			var href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
			jQuery('#egw_fw_topmenu_items,#egw_fw_topmenu_info_items,#egw_fw_sidemenu,#egw_fw_footer').on('click','a[href^="javascript:"]',function(ev){
				ev.stopPropagation();	// do NOT execute regular event, as it will violate CSP, when handler does NOT return false
				// fix for Chrome 94.0.4606.54 returning all but first single quote "'" in href as "%27" :(
				var matches = this.href.replaceAll(/%27/g, "'").replaceAll(/%22/g, '"').match(href_regexp);
				var args = [];
				if (matches.length > 1 && matches[2] !== undefined)
				{
					try {
						args = JSON.parse('['+matches[2]+']');
					}
					catch(e) {	// deal with '-enclosed strings (JSON allows only ")
						args = JSON.parse('['+matches[2].replace(/','/g, '","').replace(/((^|,)'|'(,|$))/g, '$2"$3')+']');
					}
				}
				args.unshift(matches[1]);
				if (matches[1] !== 'void') et2_call.apply(this, args);
				return false;	// IE11 seems to require this, ev.stopPropagation() does NOT stop link from being executed
			});
		});
	});
})(window);