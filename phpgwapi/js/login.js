/**
 * EGroupware login page javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @version $Id$
 */

egw_LAB.wait(function()
{
	$j(document).ready(function()
	{
		function do_social(_data)
		{
			var isPixelegg = $j('link[href*="pixelegg.css"]')[0];
			var social = $j(document.createElement('div'))
				.attr({
					id: "socialMedia",
					class: "socialMedia"
				})
				 .appendTo($j( isPixelegg? 'form' : '#socialBox'));

			for(var i=0; i < _data.length; ++i)
			{
				var data = _data[i];
				var url = (data.lang ? data.lang[$j('meta[name="language"]').attr('content')] : null) || data.url;
				$j(document.createElement('a')).attr({
					href: url,
					target: '_blank'
				})
				.appendTo(social)
				.append($j(document.createElement('img'))
					.attr('src', data.svg));
			}
		}

		do_social([
			{ "svg": egw_webserverUrl+"/phpgwapi/templates/default/images/login_contact.svg", "url": "https://www.egroupware.org/en/contact.html", "lang": { "de": "https://www.egroupware.org/de/kontakt.html" }},
			{ "svg": egw_webserverUrl+"/phpgwapi/templates/default/images/login_facebook.svg", "url": "https://www.facebook.com/egroupware" },
			{ "svg": egw_webserverUrl+"/phpgwapi/templates/default/images/login_twitter.svg", "url": "https://twitter.com/egroupware" }
		]);
	});
});
