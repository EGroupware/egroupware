/**
 * EGroupware: Stylite Pixelegg template: hiding/showing header
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Stefan Reinhard <stefan.reinhard@pixelegg.de>
 * @package pixelegg
 * @version $Id: class.pixelegg_framework.inc.php 2741 2013-11-14 13:53:24Z ralfbecker $
 */

function show_pixelegg_header(_toggle, _delay)
{
	$j("#egw_fw_header").slideToggle();
	$j("#egw_fw_topmenu_addons").animate({'margin-right': '20px'},_delay);
	$j("#egw_fw_sidebar").animate({'top':'57px'},_delay);
	$j(_toggle).removeClass("slidedown");
	$j(_toggle).addClass("slideup");
}

function hide_pixelegg_header(_toggle, _delay)
{
	$j("#egw_fw_header").slideToggle();
	$j("#egw_fw_sidebar").animate({'top':'12px'},_delay);
	$j("#egw_fw_topmenu_info_items").show();
	$j("#egw_fw_logout").show();
	$j("#egw_fw_print").show();
	$j("#egw_fw_topmenu_addons").animate({'margin-right': '250px'},_delay);
	$j(_toggle).removeClass("slideup");
	$j(_toggle).addClass("slidedown");
}

egw_LAB.wait(function() {
	$j(document).ready(function() {

		$j('#slidetoggle').click(function(){
			if ($j('#egw_fw_header').css('display') === 'none') {
				show_pixelegg_header(this, 1000);
				egw.set_preference('common', 'pixelegg_header_hidden', '');
			}
			else {
				hide_pixelegg_header(this, 1000);
				egw.set_preference('common', 'pixelegg_header_hidden', 'true');
			}
		});

		// hide header, if pref says it is not shown
		if (egw.preference('pixelegg_header_hidden')) {
			hide_pixelegg_header($j('#slidetoggle'),0);
		}
	});

	// Override jdots height calcluation
	egw_fw.prototype.getIFrameHeight = function()
	{
		$header = $j(this.tabsUi.appHeaderContainer);
		var height = $j(this.sidemenuDiv).height()-this.tabsUi.appHeaderContainer.outerHeight() - this.tabsUi.appHeader.outerHeight();
		return height;
	}
});