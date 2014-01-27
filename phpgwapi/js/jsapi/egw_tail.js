/**
 * EGroupware clientside egw tail
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage jsapi
 * @link http://www.egroupware.org
 * @author Hadi Nategh (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id: 
 */


$j(function(){
	var that = this;

	var log_tail_start=0;
	var filename = $j('pre[id^="log"]');
	if (typeof filename !='undefined' && filename.length > 0)
	{
		filename = filename.attr('data-filename');
	}
	function button_log(buttonId)
	{
		if (buttonId != "clear_log")
		{
			var ajax = new egw_json_request("home.egw_tail.ajax_delete",[filename,buttonId=="empty_log"]);
			ajax.sendRequest(true);
		}
		$j("#log").text("");
	}
	function refresh_log()
	{
		var ajax = new egw_json_request("home.egw_tail.ajax_chunk",[filename,log_tail_start]);
		ajax.sendRequest(true,function(_data) {
			if (_data.length) {
				log_tail_start = _data.next;
				var log = $j("#log").append(_data.content.replace(/</g,"&lt;"));
				log.animate({ scrollTop: log.prop("scrollHeight") - log.height() + 20 }, 500);
			}
			if (_data.size === false)
			{
				$j("#download_log").hide();
			}
			else
			{
				$j("#download_log").show().attr("title",this.egw.lang('Size')+_data.size);
			}
			if (_data.writable === false)
			{
				$j("#delete_log").hide();
				$j("#empty_log").hide();
			}
			else
			{
				$j("#delete_log").show();
				$j("#empty_log").show();
			}
			window.setTimeout(refresh_log,_data.length?200:2000);
		});
	}
	function resize_log()
	{
		$j("#log").width(egw_getWindowInnerWidth()-20).height(egw_getWindowInnerHeight()-33);
	}
	jQuery('input[id^="clear_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	jQuery('input[id^="delete_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	jQuery('input[id^="empty_log"]').on('click',function(){
		button_log(this.getAttribute('id'));
	});
	egw_LAB.wait(function() {
		$j(document).ready(function()
		{
			if (typeof filename !='undefined' && filename.length > 0)
			{
				resize_log();
				refresh_log();
			}
		});
		$j(window).resize(resize_log);
	});
});
