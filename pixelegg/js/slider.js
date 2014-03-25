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
	$j("#egw_fw_header").slideToggle();

	$j("#egw_fw_topmenu_info_items").animate({"margin-right": "20px","bottom": "0px","padding-right" : "0", "height": "0px"},_delay);
	$j("#egw_fw_topmenu_info_items").css("position", "relative");
        $j("#egw_fw_topmenu_info_items").css("display", "flex");
        $j("#egw_fw_topmenu_info_items").css("float", "right");

    $j("#egw_fw_sidebar").animate({'top':'52px'},_delay);
        $j("#egw_fw_tabs").animate({'margin-top':'12px'},_delay);
        $j(".egw_fw_ui_tabs_header").css("padding", "1px 1px 0px 1px");
        $j(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% -3000px");
	$j(_toggle).parent().removeClass("slidedown");
	$j(_toggle).parent().addClass("slideup");
}

//closed = Topmenu is gone
function hide_pixelegg_header(_toggle, _delay)
{
	$j("#egw_fw_header").slideToggle();
	$j("#egw_fw_sidebar").animate({'top':'-3px'},_delay);
	$j("#egw_fw_topmenu_info_items").show();
	$j("#egw_fw_logout").show();
	$j("#egw_fw_print").show();
        $j("#egw_fw_tabs").animate({'margin-top':'2px', "top": "0px"},_delay);
	$j("#egw_fw_topmenu_info_items").animate({
			"bottom": "3px",
                        "right": "5px",
			"display": "flex",
			"padding-right" : "20px",
			"text-align": "right",
			"white-space": "nowrap",
			},_delay);
	$j(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% 50%");

	$j("#egw_fw_topmenu_info_items").css("position", "fixed");
	$j("#egw_fw_topmenu_info_items").css("z-index", "1000");
        // Form - Country Switch
        $j("#egw_fw_topmenu_info_items form").css("display", "none");

        $j(".egw_fw_ui_tabs_header").css("height", "34px")
			// Padding to adjust for icons left & right of the tabs
			.css("padding", "1px 75px 0px 20px");


        //Tab
        $j(".egw_fw_ui_tab_header").css("height", "24px");
            // ICON
            //$j(".egw_fw_ui_tab_icon").css("height", "17px");
            $j(".egw_fw_ui_tab_icon").css("display", "inline-block");
            $j(".egw_fw_ui_tab_icon").css("margin-right", "5px");
            // H1
            $j(".egw_fw_ui_tabs_header h1").css("float", "none");
            $j(".egw_fw_ui_tabs_header h1").css("display", "inline");


	$j(_toggle).parent().removeClass("slideup");
	$j(_toggle).parent().addClass("slidedown");
}

/*
 * Replace all SVG images with inline SVG
 */
function replace_svg()
{
	$j('img.svg').each(function()
	{
		var $img = $j(this);
		var imgID = $img.attr('id');
		var imgClass = $img.attr('class');
		var imgURL = $img.attr('src');

		$j.get(imgURL, function(data)
		{
			// Get the SVG tag, ignore the rest
			var $svg = $j(data).find('svg');

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
	$j(document).ready(function() {

		$j('#slidetoggle').click(function(){
			if ($j('#egw_fw_header').css('display') === 'none') {
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
		});

		// hide header, if pref says it is not shown
		if (egw.preference('pixelegg_header_hidden')) {
			hide_pixelegg_header($j('#slidetoggle'),0);
		}

	});

	/**
	 * Callback to calculate height of browser iframe or div
	 *
	 * Override jdots height calcluation
	 *
	 * @param {object} _iframe dom node of iframe or null for div
	 * @returns number in pixel
	 */
	egw_fw.prototype.getIFrameHeight = function(iframe)
	{
		$header = $j(this.tabsUi.appHeaderContainer);
		var content = $j(this.tabsUi.activeTab.contentDiv);
		//var height = $j(this.sidemenuDiv).height()-this.tabsUi.appHeaderContainer.outerHeight() - this.tabsUi.appHeader.outerHeight();
		var height = $j(this.sidemenuDiv).height()
			- $header.outerHeight() - $j(this.tabsUi.contHeaderDiv).outerHeight() - (content.outerHeight(true) - content.height())
			// Not sure where this comes from...
			+ 5;
		return height;
	};

	/**
	 * replace [+] on mouseover with quick-add selectbox
	 *
	 * Must run after DOM is ready!
	 */
	$j('#quick_add').on({
		mouseover: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			if (ev.relatedTarget && ev.relatedTarget.id != 'quick_add_selectbox')
			{
				$j(this).css({
					transition: "0.2s ease-out 0s",
					width: "166px",
					'border-top-left-radius': "20px",
					'background-color': "#0B5FA4"
				});
				$j('select', this).css({
					transition: "0.1s linear 0.2s",
					visibility: "visible"
				});
			}
			ev.stopPropagation();
		},
		mouseout: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			if (ev.relatedTarget && ev.relatedTarget.id != 'quick_add_selectbox')
			{
				$j(this).css({
					transition: "0.6s ease-out 0s",
					width: "16px",
					'border-top-left-radius': "0px",
					'background-color': "transparent"
				});
				$j('select', this).css({
					transition: "0s linear 0s",
					visibility: "hidden"
				});
			}
			ev.stopPropagation();
		}
	});

	replace_svg();
});
