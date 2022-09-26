/**
 * EGroupware: Pixelegg template: hiding/showing header
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Wolfgang Ott <wolfgang.ott@pixelegg.de>
 * @package pixelegg
 */

egw_ready.then(function()
{
	/**
	 * replace [+] on mouseover with quick-add selectbox
	 *
	 * Must run after DOM is ready!
	 */
	jQuery('#quick_add').on({
		click: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			var select = document.getElementById('quick_add_selectbox');
			ev.stopImmediatePropagation();
			if (select.dropdown.open)
			{
				select.dropdown.hide();
			}
			else
			{
				select.dropdown.show();
			}

		}
	});
});
