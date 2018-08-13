/**
 * EGroupware login page javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @version $Id$
 */

/* if login page is not in top window, set top windows location to it */
if (top !== window) top.location = window.location;

egw_LAB.wait(function()
{
	jQuery(document).ready(function()
	{
		// lock the device orientation in portrait view
		if (screen.orientation && typeof screen.orientation.lock == 'function') screen.orientation.lock('portrait');
		jQuery('.closeBtn').click(function (){
			setTimeout(function(){jQuery('.loginMessageBox').slideUp("slow")},100);
		});
		function do_social(_data)
		{
			var social = jQuery(document.createElement('div'))
				.attr({
					id: "socialMedia",
					class: "socialMedia"
				})
				 .appendTo(jQuery('#socialBox'));

			for(var i=0; i < _data.length; ++i)
			{
				var data = _data[i];
				var url = (data.lang ? data.lang[jQuery('meta[name="language"]').attr('content')] : null) || data.url;
				jQuery(document.createElement('a')).attr({
					href: url,
					target: '_blank'
				})
				.appendTo(social)
				.append(jQuery(document.createElement('img'))
					.attr('src', data.svg));
			}
		}

		do_social([
			{ "svg": egw_webserverUrl+"/api/templates/default/images/login_contact.svg", "url": "https://www.egroupware.org/en/contact.html", "lang": { "de": "https://www.egroupware.org/de/kontakt.html" }},
			{ "svg": egw_webserverUrl+"/api/templates/default/images/login_facebook.svg", "url": "https://www.facebook.com/egroupware" },
			{ "svg": egw_webserverUrl+"/api/templates/default/images/login_twitter.svg", "url": "https://twitter.com/egroupware" }
		]);

		// IE or Edge do not respect text-indent :( use padding left instead.
		if (navigator && navigator.userAgent.match(/Trident|msie|edge/ig))
		{
			jQuery('select[name="remember_me"]').css({"padding-left": "60%"});
		}
	});
});
