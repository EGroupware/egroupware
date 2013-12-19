/**
 * Calendar - sidebox navigation
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@stylite.de>
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Sidebox navigation for calendar
 *
 * @todo add code from jscalendar->flat(), or better replace it altogether ...
 */
(function()
{
	var script_tag = document.getElementById('calendar-navigation-script');
	var current_view_url;
	if (script_tag)
	{
		current_view_url = script_tag.getAttribute('data-current-view-url');
	}
	function load_cal(url,id,no_reset) {
		var owner='';
		var i = 0;
		selectBox = document.getElementById(id);
		for(i=0; i < selectBox.length; ++i) {
			if (selectBox.options[i].selected) {
				owner += (owner ? ',' : '') + selectBox.options[i].value;
			}
		}
		if (owner) {
			if (typeof no_reset == 'unknown') no_reset = false;
			url += '&owner='+(no_reset?'':'0,')+owner;
			if (url.match('&ajax=true')) url = url.replace('&ajax=true', '')+'&ajax=true';
			egw_link_handler(url, 'calendar');
		}
	}

	/**
	 * Load template specific app.css file in top window too as sidebox needs it
	 *
	 * @returns {Boolean}
	 */
	function load_top_app_css()
	{
		var calendar_window = egw_appWindow('calendar');
		// include template specific app.css
		var link_tags = calendar_window.document.getElementsByTagName('link');
		var cal_app_css = /calendar\/templates\/[^/]+\/app.css/;
		for(var i=0; i < link_tags.length; i++)
		// include template specific app.css
		{
			var href = cal_app_css.exec(link_tags[i].href);
			if (href)
			{
				//alert('loading into top window: '+href);
				egw(calendar_window.top).includeCSS(egw_webserverUrl+'/'+href[0]);
				return true;
			}
		}
		// try loading a little later, due to async loading app.css might not already loaded
		window.setTimeout(load_top_app_css, 100);
		return false;
	}

	/**
	 * Initialisation after DOM *and* jQuery is loaded
	 */
	egw_LAB.wait(function() {
		$j(function(){
			var calendar_window = egw_appWindow('calendar');
			// include template specific app.css
			load_top_app_css();
			// change handlers setting a certain url, eg. view
			$j('#calendar_view').change(function(){
				egw_link_handler(egw_webserverUrl+'/index.php?'+this.value, 'calendar');
			});
			// calendar owner selection change
			$j('#uical_select_owner,#uical_select_resource').change(function(e){
				if (this.value != 'popup')
				{
					load_cal(current_view_url, this.id, this.id != 'uical_select_owner');
					e.preventDefault();
				}
			});
			// diverse change handlers appending a name=value to url
			$j('#calendar_merge,#calendar_filter,#calendar_cat_id').change(function(){
				var val = $j(this).val();
				if ($j.isArray(val)) val = val.join(',');
				var url = current_view_url+(current_view_url.search.length ? '&' : '?')+this.name+'='+val;
				if (url.match('&ajax=true')) url = url.replace('&ajax=true', '')+'&ajax=true';
				egw_link_handler(url, 'calendar');
				if (this.name == 'merge') this.value='';
			});
			// click handler to switch selectbox to multiple
			$j('#calendar_cat_id_multiple').click(function(){
				var selectBox = document.getElementById(this.id.replace('_multiple', ''));
				if (selectBox && !selectBox.multiple)
				{
					selectBox.size=4;
					selectBox.multiple=true;
				}
			});
		});
	});
})();