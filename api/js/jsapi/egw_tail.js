/**
 * EGroupware clientside egw tail
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage jsapi
 * @link https://www.egroupware.org
 * @author Hadi Nategh (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_json.js';

jQuery(function()
{
	"use strict";

	var log_tail_start=0;
	var filename = jQuery('pre[id^="log"]');
	if (typeof filename !='undefined' && filename.length > 0)
	{
		filename = filename.attr('data-filename');
	}
	function button_log(buttonId)
	{
		if (buttonId != "clear_log")
		{
			egw.json("api.EGroupware\\Api\\Json\\Tail.ajax_delete",[filename,buttonId=="empty_log"])
				.sendRequest(true);
		}
		jQuery("#log").text("");
	}
	function refresh_log()
	{
		egw.json("api.EGroupware\\Api\\Json\\Tail.ajax_chunk",[filename,log_tail_start], function(_data)
		{
			if (_data.length) {
				log_tail_start = _data.next;
				var log = jQuery("#log").append(_data.content.replace(/</g,"&lt;"));
				log.animate({ scrollTop: log.prop("scrollHeight") - log.height() }, 500);
			}
			if (_data.size === false)
			{
				jQuery("#download_log").hide();
			}
			else
			{
				jQuery("#download_log").show().attr("title", egw(window).lang('Size')+_data.size);
			}
			// Hide purge/empty buttons if file is not writable
			if (_data.writable === false)
			{
				jQuery("#purge_log").hide();
				jQuery("#empty_log").hide();
			}
			else
			{
				jQuery("#purge_log").show();
				jQuery("#empty_log").show();
			}
			window.setTimeout(refresh_log,_data.length?200:2000);
		}).sendRequest(true);
	}
	function resize_log()
	{
		jQuery("#log").width(egw_getWindowInnerWidth()-egw_getWindowInnerWidth()*0.02).height(egw_getWindowInnerHeight()*0.92);
	}

	document.querySelector('et2-button[id^="clear_log"]').onclick = function ()
	{
		button_log(this.id);
	};
	document.querySelector('et2-button[id^="purge_log"]').onclick = function ()
	{
		button_log(this.id);
	};
	document.querySelector('et2-button[id^="empty_log"]').onclick = function ()
	{
		button_log(this.id);
	};
	document.querySelector('et2-button[id^="download_log"]').onclick = function ()
	{	// Download button triggers download by opening download URL
		egw(window).open_link('/index.php?menuaction=api.EGroupware\\Api\\Json\\Tail.download&filename=' + encodeURIComponent(filename));
	};
	jQuery(document).ready(function()
	{
		if (typeof filename !='undefined' && filename.length > 0)
		{
			resize_log();
			refresh_log();
		}
	});
	jQuery(window).resize(resize_log);
});
