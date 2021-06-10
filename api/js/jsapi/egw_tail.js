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
				log.animate({ scrollTop: log.prop("scrollHeight") - log.height() + 20 }, 500);
			}
			if (_data.size === false)
			{
				jQuery("#download_log").hide();
			}
			else
			{
				jQuery("#download_log").show().attr("title", egw(window).lang('Size')+_data.size);
			}
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
		jQuery("#log").width(egw_getWindowInnerWidth()-20).height(egw_getWindowInnerHeight()-33);
	}
	jQuery('input[id^="clear_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	jQuery('input[id^="purge_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	jQuery('input[id^="empty_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	//egw_LAB.wait(function() {
		jQuery(document).ready(function()
		{
			if (typeof filename !='undefined' && filename.length > 0)
			{
				resize_log();
				refresh_log();
			}
		});
		jQuery(window).resize(resize_log);
	//});
});
