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
		mouseover: function(ev){
			// do NOT react on bubbeling events from contained selectbox
			var select = document.getElementById('quick_add_selectbox');
			select.dropdown.open = true;
			ev.stopPropagation();

		},
		mouseout: function(ev){
			var select = document.getElementById('quick_add_selectbox');

			// do NOT react on bubbeling events from contained selectbox
			if (ev.target && ev.relatedTarget && ev.currentTarget.id != "quick_add" && ev.currentTarget.nodeName != 'ET2-SELECT')
			{


			}
			ev.stopPropagation();
		}
	});
});
