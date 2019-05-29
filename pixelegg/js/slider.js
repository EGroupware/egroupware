/**
 * EGroupware: Stylite Pixelegg template: hiding/showing header
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Wolfgang Ott <wolfgang.ott@pixelegg.de>
 * @package pixelegg
 * @version $Id: class.pixelegg_framework.inc.php 2741 2013-11-14 13:53:24Z ralfbecker $
 */

egw_LAB.wait(function() {

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
});
