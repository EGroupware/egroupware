/**
 * mail - handle mailto and other links in preview
 *
 * @link http://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2014 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	/api/js/jquery/jquery.base64.js;
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