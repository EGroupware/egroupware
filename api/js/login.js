/**
 * EGroupware login page javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package login
 * @subpackage api
 * @link https://www.egroupware.org
 */

/* if login page is not in top window, set top windows location to it */
if (top !== window) top.location = window.location;

// check if the browser supports our required JS version and try to warn user
try {
	Function ("() => {};");	// ES6 check
	Function("window?.location;");	// ES2020 check
	document.querySelector('thead:has(*)');
	// Function("window<<<test");	// Test which should fail
}
catch (exception) {
	alert('Your browser is not up-to-date:\n* JavaScript ES2020 compatible\n* support for CSS :has() pseudo class\n\nYou need at least:\n* Chrome(ium)/Edge 105\n* Firefox 121\n* Safari 15.4');
}

const login_on_ready = () => {
	egw_ready.then(async function()
	{
		if (document.readyState === 'loading')
		{
			await new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve, {once: true}));
		}
		document.querySelectorAll('.close').forEach(close => close.addEventListener('click', () =>
		{
			setTimeout(() =>
			{
				document.querySelectorAll('.egw_message_wrapper').forEach(wrapper =>
				{
					// slide up and hide, like jQuery's slideUp("slow") did
					wrapper.style.overflow = 'hidden';
					wrapper.style.transition = 'height .6s, margin .6s, padding .6s, opacity .6s';
					wrapper.style.height = wrapper.offsetHeight + 'px';
					wrapper.offsetHeight;	// force reflow, so the transition starts at the current height
					Object.assign(wrapper.style, {height: '0', marginTop: '0', marginBottom: '0', paddingTop: '0', paddingBottom: '0', opacity: '0'});
					setTimeout(() => wrapper.style.display = 'none', 600);
				});
			}, 100);
		}));
		function do_social(_data)
		{
			const social = document.createElement('div');
			social.id = "socialMedia";
			social.className = "socialMedia";
			document.getElementById('socialBox')?.append(social);

			const language = document.querySelector('meta[name="language"]')?.getAttribute('content');
			for(const data of _data)
			{
				const link = document.createElement('a');
				link.href = (data.lang ? data.lang[language] : null) || data.url;
				link.target = '_blank';
				const img = document.createElement('img');
				img.src = data.svg;
				img.alt = data.name;
				img.title = data.name;
				link.append(img);
				social.append(link);
			}
		}

		/**
		 * Append a hidden input to a form
		 */
		const addHidden = (form, name, value) =>
		{
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.append(input);
		};

		do_social([
			{
				"name": "EGroupware",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_egroupware.svg",
				"url": "https://www.egroupware.org/en",
				"lang": { "de": "https://www.egroupware.org/de/" }
			},
			{
				"name": "Contact",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_contact.svg",
				"url": "https://www.egroupware.org/en/contact.html",
				"lang": { "de": "https://www.egroupware.org/de/kontakt.html" }
			},
			{
				"name": "LinkedIn",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_linkedin.svg",
				"url": "https://www.linkedin.com/company/egroupware-gmbh"
			},
			{
				"name": "Bluesky",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_bluesky.svg",
				"url": "https://bsky.app/profile/egroupware.org"
			},
			{
				"name": "Mastodon",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_mastodon.svg",
				"url": "https://fosstodon.org/@EGroupware"
			},
			{
				"name": "Forum",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_discourse.svg",
				"url": "https://help.egroupware.org"
			},
			{
				"name": "Github",
				"svg": egw_webserverUrl+"/api/templates/default/images/login_github.svg",
				"url": "https://github.com/EGroupware/egroupware"
			}
		]);

		// automatic submit of SAML IdP selection
		document.querySelectorAll('select[name="auth=saml"]').forEach(select => select.addEventListener('change', function() {
			if (this.value) {
				this.form.method = 'get';
				addHidden(this.form, 'auth', 'saml');
				addHidden(this.form, 'idp', this.value);
				this.form.submit();
			}
		}));
		// or optional SAML login with a button for a single IdP
		document.querySelectorAll('input[type="submit"][name^="auth="]').forEach(button => button.addEventListener('click', function() {
			this.form.method = 'get';
			addHidden(this.form, 'auth', this.name.split('=')[1]);
		}));
		// prefer [Login] button below over maybe existing SAML login button above
		document.querySelectorAll('input').forEach(input => input.addEventListener('keypress', function(e)
		{
			if (e.key === 'Enter')
			{
				this.form.submit();
				e.preventDefault();
			}
		}));
		//cleanup darkmode session value
		egw.setSessionItem('api', 'darkmode','');

		document.querySelectorAll("#login_footer .tooltip").forEach(tooltip => tooltip.addEventListener('click', function(e)
		{
			if (e.target == this) window.open(this.getElementsByTagName('a')[0].href, 'blank');
		}));
	});
	// register service worker
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.register('./service-worker.js', {scope:egw_webserverUrl+'/'})
			.then(function(registration) {
				console.log('Registration successful, scope is:', registration.scope);
			})
			.catch(function(error) {
				console.log('Service worker registration failed, error:', error);
			});
	}
};

//cleanup darkmode session value
// Force light mode on login
document.documentElement.classList.add('sl-theme-light');
document.documentElement.classList.remove('sl-theme-dark');
document.documentElement.setAttribute('data-darkmode', '0');
// run login_on_ready, once egw_ready is available, currently it is already available, as login.js is included in the body
if (typeof egw_ready !== "undefined")
{
	login_on_ready();
}
else
{
	const wait4egw_ready = window.setInterval(() => {
		if (typeof egw_ready === "undefined") return;
		window.clearInterval(wait4egw_ready);
		login_on_ready();
	}, 100);
}