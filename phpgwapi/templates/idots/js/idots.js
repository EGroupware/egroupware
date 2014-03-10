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
egw_LAB.wait(function() {
	// add quick-add select box, but only if parent is present (not for login page!)
	var quick_add = document.getElementById('quick_add');
	if (quick_add) egw.link_quick_add(quick_add);

	// Handle sliding menues of extra apps and sidebox
	var egw_script = document.getElementById('egw_script_id');
	var mouseHandler = 'click';
	if (egw_script)
	{
		var data_slide_out = egw_script.getAttribute('data-slide-out');
		mouseHandler = data_slide_out == 'mouseover'?'mouseover':'click';
	}
	var extra_icons_show = 	jQuery('#extra_icons_show');
	var menu1Container = jQuery('#menu1Container')
			.width(180)
			.offset({top:extra_icons_show.offset().top+extra_icons_show.height()});
	var menu2show = jQuery('#menu2show');
	var menu2Container = jQuery('#menu2Container');
	//Click handler for extra apps menu
	extra_icons_show.on(mouseHandler,function (event){
			var extraIcon = event;
			$j('html').on(mouseHandler,function(event) {
				if ($j(event.target).parents('#menu1Container').length==0 && event.target !== extraIcon.target)
				{
					menu1Container.slideUp();
					$j(this).unbind(event);
				}
			});
			menu1Container.slideToggle();
		}
	);
	// Click handler for sidebox menu
	menu2show.on(mouseHandler,function (event){
			var m2showIcon = event;
			var options = {
				direction: "left"
			};
			$j('html').on('click',function(event) {
				if (event.target !== m2showIcon.target)
				{
					menu2Container.toggle('slide',options);
					$j(this).unbind(event);
				}
			});
			menu2Container.toggle("slide",options);
		}
	);

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
			$j('.et2_container').last().height(appbox_height-7);
		});
		$j(window).resize();
		$j(window).load(function(){	// fixes sometimes not called resize, probably due to timing issues
			$j(window).resize();
		});

		// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
		var href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
		jQuery('#topmenu_items,#thesideboxcolumn').on('click','a[href^="javascript:"]',function(ev){
			ev.stopPropagation();	// do NOT execute regular event, as it will violate CSP, when handler does NOT return false
			var matches = this.href.match(href_regexp);
			var args = [];
			if (matches.length > 1 && matches[2] !== undefined)
			{
				try {
					args = JSON.parse('['+matches[2]+']');
				}
				catch(e) {	// deal with '-encloded strings (JSON allows only ")
					args = JSON.parse('['+matches[2].replace(/','/g, '","').replace(/((^|,)'|'(,|$))/g, '$2"$3')+']');
				}
			}
			args.unshift(matches[1]);
			et2_call.apply(this, args);
			return false;	// IE11 seems to require this, ev.stopPropagation() does NOT stop link from being executed
		});

		// make sidebox resizable with jQueryUI resizable
		jQuery('#thesideboxcolumn').resizable({handles: 'e', minWidth: 200, stop: function(event, ui){
			egw.set_preference(egw_appName, 'idotssideboxwidth', ui.size.width);
		}});
	});
});
