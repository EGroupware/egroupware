/**
 * EGroupware: Stylite Pixelegg template: hiding/showing header
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Wolfgang Ott <wolfgang.ott@pixelegg.de>
 * @package pixelegg
 * @version $Id: class.pixelegg_framework.inc.php 2741 2013-11-14 13:53:24Z ralfbecker $
 */

//open
function show_pixelegg_header(_toggle, _delay)
{
	jQuery("#egw_fw_header").slideToggle();
	jQuery("#egw_fw_sidebar").animate({'top':'49px'},_delay);
	jQuery("#jsxc_roster").animate({'top':'89px'});
	jQuery("#egw_fw_tabs").animate({'margin-top':'8px'},_delay);
	jQuery(".egw_fw_ui_tabs_header").css("padding", "1px 1px 0px 1px");
	jQuery(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% -3000px");
	jQuery(_toggle).parent().removeClass("slidedown");
	jQuery(_toggle).parent().addClass("slideup");
	jQuery('#egwpopup, .egwpopup_message.egwpopup_expanded').css({top:'90px', height:'calc(100% - 130px)'});
	jQuery('#egw_fw_basecontainer').addClass('slideIsUp');
}

//closed = Topmenu is gone
function hide_pixelegg_header(_toggle, _delay)
{
	jQuery("#egw_fw_header").slideToggle();
	jQuery("#egw_fw_sidebar").animate({'top':'3px'},_delay);
	//	jQuery("#egw_fw_topmenu_info_items").show();
	jQuery("#egw_fw_logout").show();
	jQuery("#egw_fw_print").show();
	jQuery("#egw_fw_tabs").animate({'margin-top':'8px', "top": "0px"},_delay);
	jQuery("#jsxc_roster").animate({'top':'49px'});
	jQuery(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% 50%");
	jQuery(".egw_fw_ui_tabs_header").css("height", "34px")
		// Padding to adjust for icons left & right of the tabs
		.css("padding", "1px 150px 0px 20px");
	//Tab
	jQuery(".egw_fw_ui_tab_header").css("height", "24px");
	// ICON
	//jQuery(".egw_fw_ui_tab_icon").css("height", "17px");
	jQuery(".egw_fw_ui_tab_icon").css("display", "inline-block");
	jQuery(".egw_fw_ui_tab_icon").css("margin-right", "5px");
	// H1
	jQuery(".egw_fw_ui_tabs_header h1").css("float", "none");
	jQuery(".egw_fw_ui_tabs_header h1").css("display", "inline");
	jQuery(_toggle).parent().removeClass("slideup");
	jQuery(_toggle).parent().addClass("slidedown");
	jQuery('#egwpopup, .egwpopup_message.egwpopup_expanded').css({top:'45px', height:'calc(100% - 85px)'});
	jQuery('#egw_fw_basecontainer').removeClass('slideIsUp');
}

/*
 * Replace all SVG images with inline SVG
 */
function replace_svg()
{
	jQuery('img.svg').each(function()
	{
		var $img = jQuery(this);
		var imgID = $img.attr('id');
		var imgClass = $img.attr('class');
		var imgURL = $img.attr('src');

		jQuery.get(imgURL, function(data)
		{
			// Get the SVG tag, ignore the rest
			var $svg = jQuery(data).find('svg');

			// Add replaced image's ID to the new SVG
			if(typeof imgID !== 'undefined') {
				$svg = $svg.attr('id', imgID);
			}
			// Add replaced image's classes to the new SVG
			if(typeof imgClass !== 'undefined') {
				$svg = $svg.attr('class', imgClass+' replaced-svg');
			}

			// Remove any invalid XML tags as per http://validator.w3.org
			$svg = $svg.removeAttr('xmlns:a');

			// Replace image with new SVG
			$img.replaceWith($svg);

		}, 'xml');
	});
}

egw_LAB.wait(function() {
	jQuery(document).ready(function() {

		jQuery('#slidetoggle').click(function(){
			if (jQuery('#egw_fw_header').css('display') === 'none') {
				show_pixelegg_header(this, 1000);
				// Check for too many tabs, and adjust width if needed
				egw_getFramework().checkTabOverflow();
				egw.set_preference('common', 'pixelegg_header_hidden', '');
			}
			else {
				hide_pixelegg_header(this, 1000);
				// Check for too many tabs, and adjust width if needed
				egw_getFramework().checkTabOverflow();
				egw.set_preference('common', 'pixelegg_header_hidden', 'true');
			}
			window.setTimeout(function() {
				egw_getFramework().notifyTabChange();
			},1000);
		});
		jQuery('#egw_fw_basecontainer').addClass('slideIsUp');
		// hide header, if pref says it is not shown
		if (egw.preference('pixelegg_header_hidden')) {
			setTimeout(function(){hide_pixelegg_header(jQuery('#slidetoggle'),0)},1);
		}

	});

	/**
	 * replace [+] on mouseover with quick-add selectbox
	 *
	 * Must run after DOM is ready!
	 */
	jQuery('#quick_add').on({
		mouseover: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			var $select = jQuery('#quick_add_selectbox');
			if (!$select.children()[0]['value']) $select.children()[0].text = '';
			var $chosen_div = $select.next();
			if ($chosen_div.hasClass('chzn-container'))
			{
				$chosen_div.show();
			}
			else
			{
				$select.chosen({
					disable_search: true,
					display_selected_options: false
				});
				 $chosen_div = $select.next();
			}
			$select.trigger('liszt:open');
			$select.on('liszt:hiding_dropdown', function(e){
				$chosen_div.hide();
			});
			ev.stopPropagation();
		},
		mouseout: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			if (ev.target && ev.relatedTarget && ev.target.id != 'quick_add_selectbox'
					&& !jQuery(ev.relatedTarget).hasClass('chzn-container'))
			{
				 jQuery('#quick_add_selectbox').next().hide();
			}
			ev.stopPropagation();
		}
	});

	replace_svg();
});
