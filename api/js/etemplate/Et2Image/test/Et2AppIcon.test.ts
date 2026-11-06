/**
 * Tests for Et2AppIcon (`et2-appicon`).
 *
 * A thin subclass of Et2Image (~80 uses across shipped templates) whose entire job is one
 * `parse_href()` override: turn an application name into that app's navbar icon path, honouring
 * the per-app `icon_app`/`icon` overrides an app can register.  Everything after that is
 * Et2Image's, which is covered separately - so these tests deliberately stop at the path this
 * class produces rather than re-asserting the rendering.
 *
 * Behaviour under test:
 * - an app name resolves to `<app>/navbar`, and to `<icon_app>/<icon>` when the app registers
 *   either override
 * - no src at all falls back to the *current* app, which is what makes a bare `<et2-appicon/>`
 *   work in a navbar
 * - `defaultSrc` is "nonav" and, because Et2Image runs it back through this same override, it
 *   resolves as an app name too rather than as a literal path
 * - `kdots` switches to the kdots navbar icon, turns on SVG inlining and sets the per-app colour
 *   custom property, so the icon can be recoloured by CSS
 *
 * Setup strategy:
 * `egw()` is stubbed globally before the first import - `parse_href()` runs from Et2Image's `src`
 * setter during upgrade, which is too early for a per-instance stub - with an `app()` map that
 * returns per-app overrides for exactly one app, so the override and non-override paths are both
 * exercised against the same stub.
 *
 * Pass criteria:
 * Explicit assertions on `parse_href()`'s return value and on the properties `kdots` sets.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";

// Per-app registrations; only "mail" overrides anything, everything else takes the defaults.
const APP_DATA = {
	mail: {icon_app: "felamimail", icon: "navbar_mail"}
};

const stub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	// Echo the name back inside a data: URI: it keeps the resolved <app>/<icon> visible to
	// assertions (via iconUrl() below) while making sure the <img> Et2Image renders never
	// actually hits the network for a path that does not exist.
	image: (name : string) => "data:image/svg+xml," + encodeURIComponent(name),
	link: (url : string) => url,
	open_link: () => {},
	app_name: () => "addressbook",
	app: (app : string, key : string) => APP_DATA[app]?.[key],
	webserverUrl: "",
	preference: () => null,
	debug: () => {}
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;

import "../Et2AppIcon";
import {Et2AppIcon} from "../Et2AppIcon";
import {Et2Image} from "../Et2Image";

async function appIcon(attributes = "")
{
	const element = <Et2AppIcon>await fixture(`<et2-appicon ${attributes}></et2-appicon>`);
	await element.updateComplete;
	return element;
}

const href = (element : Et2AppIcon, app? : string) => (<any>element).parse_href(app);

/** What the stubbed egw().image() returns for a given resolved "<app>/<icon>" name. */
const iconUrl = (path : string) => "data:image/svg+xml," + encodeURIComponent(path);

describe("Et2AppIcon", () =>
{
	it("upgrades and is an Et2Image", async() =>
	{
		const element = await appIcon('src="addressbook"');

		assert.instanceOf(element, Et2AppIcon, "et2-appicon did not upgrade");
		assert.instanceOf(element, Et2Image, "et2-appicon should inherit Et2Image's rendering");
	});

	it("defaults to the nonav icon", async() =>
	{
		const element = await appIcon();
		assert.equal(element.defaultSrc, "nonav", "an app with no icon falls back to nonav");
	});

	describe("resolving an app name", () =>
	{
		it("maps an app to its navbar icon", async() =>
		{
			const element = await appIcon();
			assert.equal(href(element, "calendar"), iconUrl("calendar/navbar"), "an app name should resolve to its navbar icon");
		});

		it("honours a per-app icon_app and icon override", async() =>
		{
			// mail's icons actually live under felamimail
			const element = await appIcon();
			assert.equal(
				href(element, "mail"),
				iconUrl("felamimail/navbar_mail"),
				"both the icon directory and the icon name must be overridable per app"
			);
		});

		it("falls back to the current app when given nothing", async() =>
		{
			// This is what makes a bare <et2-appicon/> work - it shows the app it is rendered in
			const element = await appIcon();
			assert.equal(
				href(element, undefined),
				iconUrl("addressbook/navbar"),
				"no app name should mean the current app"
			);
		});

		it("runs defaultSrc through the same app resolution", async() =>
		{
			// Et2Image falls back to parse_href(defaultSrc), which is this override - so "nonav"
			// is treated as an app name, not as a literal image name.
			const element = await appIcon();
			assert.equal(
				href(element, element.defaultSrc),
				iconUrl("nonav/navbar"),
				"defaultSrc goes through the app-icon path too"
			);
		});
	});

	describe("kdots", () =>
	{
		it("uses the kdots navbar icon", async() =>
		{
			const element = await appIcon("kdots");
			assert.equal(
				href(element, "calendar"),
				iconUrl("calendar/kdots-navbar"),
				"the kdots framework has its own navbar icons"
			);
		});

		it("still lets an app override the icon name", async() =>
		{
			const element = await appIcon("kdots");
			assert.equal(
				href(element, "mail"),
				iconUrl("felamimail/navbar_mail"),
				"a registered icon name wins over the kdots default"
			);
		});

		it("inlines the SVG and sets the per-app colour so CSS can recolour it", async() =>
		{
			const element = await appIcon("kdots");
			href(element, "calendar");

			assert.equal(
				element.style.color,
				"var(--calendar-color)",
				"the icon takes its app's colour"
			);
			assert.isTrue(element.inline, "recolouring only works on an inlined SVG");
		});

		it("does neither of those without kdots", async() =>
		{
			const element = await appIcon();
			href(element, "calendar");

			assert.notEqual(element.style.color, "var(--calendar-color)", "only kdots recolours the icon");
			assert.isNotTrue(element.inline, "a plain app icon is a normal img");
		});
	});
});
