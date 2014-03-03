/**
 * mail - handle mailto and other links in preview
 *
 * @link http://www.egroupware.org
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2014 by Stylite AG <info-AT-stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	phpgwapi.jquery.jquery.base64;
*/

jQuery(function()
{
	jQuery('body').on('click', 'a[href]', function()
	{
		// active mailto: links with mail compose
		if (this.href.substr(0, 7) == 'mailto:')
		{
			egw(window).open(null, 'mail', 'add', {send_to: jQuery.base64Encode(this.href.substr(7).replace('%40', '@'))});

			return false;	// cant do event.stopImediatePropagation() in on!
		}
		else	// add target=_blank to all other links, gives CSP error and would open in preview
		{
			this.target = '_blank';
		}
	});
});