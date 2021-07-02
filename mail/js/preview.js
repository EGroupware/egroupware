/**
 * EGroupware Mail - handle mailto and other links in preview
 *
 * @link http://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2014 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
*/

jQuery(function()
{
	jQuery('body').on('click', 'a[href]', function()
	{
		// active mailto: links with mail compose
		if (this.href.substr(0, 7) == 'mailto:')
		{
			top.egw.open(null, 'mail', 'add', {send_to: btoa(this.href.substr(7).replace('%40', '@'))});

			return false;	// cant do event.stopImediatePropagation() in on!
		}
		// open links with own orgin and "index.php?" as popup (not eg. share.php or *dav.php)
		else if ((this.href[0] === '/' || this.href.match(new RegExp('^'+location.protocol+'//'+location.host+'/'))) &&
			this.href.match(/\/index.php\?/))
		{
			top.egw.openPopup(this.href.replace(/([?&])no_popup=[^&]*/, '$1'), 800, 600, '_blank');
			return false;
		}
		else	// add target=_blank to all other links, gives CSP error and would open in preview
		{
			this.target = '_blank';
		}
	});
});