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

window.addEventListener('load', function ()
{
	document.body.addEventListener('click', function (event)
	{
		if (event.target.tagName === 'A' && event.target.href)
		{
			// active mailto: links with mail compose
			if (event.target.href.substr(0, 7) == 'mailto:')
			{
				top.egw.open(null, 'mail', 'add', {send_to: btoa(event.target.href.substr(7).replace('%40', '@'))});
				event.preventDefault();
				return false;
			}
			// open links with own origin and "index.php?" as popup (not e.g. share.php or *dav.php)
			else if ((event.target.href[0] === '/' || event.target.href.match(new RegExp('^' + location.protocol + '//' + location.host + '/'))) &&
				event.target.href.match(/\/index.php\?/))
			{
				top.egw.openPopup(event.target.href.replace(/([?&])no_popup=[^&]*/, '$1'), 800, 600, '_blank');
				event.preventDefault();
				return false;
			}
			else
			{ // add target=_blank to all other links, gives CSP error and would open in preview
				event.target.target = '_blank';
			}
		}
	});
});