/**
 * EGroupware idots template javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package phpgwapi
 * @subpackage idots
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */


/**
 * idots javascript
 */
(function(){
	// add quick add select box
	egw.link_quick_add('quick_add');

	// instanciate slideout menus via "data-slide-out" of egw.js script tag
	var egw_script = document.getElementById('egw_script_id');
	if (egw_script)
	{
		var data_slide_out = egw_script.getAttribute('data-slide-out');
		if (data_slide_out)
		{
			data_slide_out = JSON.parse(data_slide_out);
			for(var i=0; i < data_slide_out.length; ++i)
			{
				var args=data_slide_out[i];

				new ypSlideOutMenu(args.id, args.dir, args.left, args.top, args.width, args.height, args.pos);
				for(var selector in args.bind)
				{
					var data = args.bind[selector];
					jQuery(selector).on(data.event, {menu: args.id, method: data.method}, function(event){
						window.ypSlideOutMenu[event.data.method].call(window, event.data.menu);
						event.preventDefault();
					});
				}
			}
		}
	}

	/**
	 * Initialisation, when DOM is ready
	 */
	$j(function()
	{
		// Installing resize handler for divAppbox and et2_container, as et2 otherwise can not correctly size nextmatch
		$j(window).resize(function(){
			var appbox_height = $j(window).height()-$j('#topmenu').height()-$j('#divAppIconBar').height()-
				$j('#divStatusBar').height()-$j('#divAppboxHeader').height()-$j('#divPoweredBy').height()-20;
			//console.log('setting height of '+appbox_height);
			$j('#divAppbox').css('min-height', appbox_height+'px');
			$j('.et2_container').height(appbox_height-7);
		});
		$j(window).resize();
		$j(window).load(function(){	// fixes sometimes not called resize, probably due to timing issues
			$j(window).resize();
		});

		// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
		var href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
		jQuery('#topmenu_items,#thesideboxcolumn').on('click','a[href^="javascript:"]',function(){
			var matches = this.href.match(href_regexp);
			if (matches && typeof window[matches[1]] == 'function') {
				var args = [];
				if (matches.length > 1 && matches[2] !== undefined) args = JSON.parse('['+matches[2].replace(/'/g,'"')+']');
				window[matches[1]].apply(window.framework, args);
			}
			else
			{
				alert('Do NOT know how to execute '+this.href);
			}
			// return false to not execute link itself, which would violate CSP
			return false;
		});

		// make sidebox resizable with jQueryUI resizable
		jQuery('#thesideboxcolumn').resizable({handles: 'e', minWidth: 200, stop: function(event, ui){
			egw.set_preference(egw_appName, 'idotssideboxwidth', ui.size.width);
		}});
	});
})();
