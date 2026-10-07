import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Coverage for the "Move selected to"/"Copy selected to" quick-submenu (ticket #124271): a target
 * folder used only a handful of times recently used to never be able to outrank an old, high-count
 * habit, since the submenu only ever showed the top 10 by use-count. rememberUsedFolder() now also
 * tracks a 'last used' timestamp per target, and updateFolderQuickAction() appends up to 5 more
 * targets by recency (not already among the top 10 by count) below a menu separator.
 */

function createMailApp(initialUsage : Record<string, any> = {})
{
	const prefs : Record<string, any> = {moveFolderUsage: initialUsage};
	const nm : any = {activeFilters: {selectedFolder: null}, actions: undefined};
	const ftree : any = {getLabel: () => null, getNode: () => null};

	const app = Object.create(MailApp.prototype) as any;
	Object.assign(app, {
		nm_index: 'nm',
		egw: {
			lang: (s : string) => s,
			preference: (key : string) => prefs[key],
			set_preference: (_app : string, key : string, value : any) => void (prefs[key] = value),
		},
		et2: {
			getWidgetById: (id : string) => id === 'nm' ? nm : (id === 'nm[foldertree]' ? ftree : null),
		},
		_unseen_regexp: / \([0-9]+\)$/,
	});
	return {app, prefs, nm};
}

describe("MailApp folder quick-action usage tracking (ticket #124271)", () =>
{
	describe("rememberUsedFolder()", () =>
	{
		it("records a first use as {count: 1, last: <now>}", () =>
		{
			const {app, prefs} = createMailApp();
			const before = Date.now();

			app.rememberUsedFolder('move', '1::INBOX/Sub');

			const entry = prefs.moveFolderUsage['1::INBOX/Sub'];
			assert.equal(entry.count, 1);
			assert.isAtLeast(entry.last, before);
		});

		it("bumps count and refreshes 'last' on a repeat use", () =>
		{
			const {app, prefs} = createMailApp({'1::INBOX/Sub': {count: 2, last: 1000}});

			app.rememberUsedFolder('move', '1::INBOX/Sub');

			const entry = prefs.moveFolderUsage['1::INBOX/Sub'];
			assert.equal(entry.count, 3);
			assert.isAbove(entry.last, 1000);
		});

		it("migrates a pre-#124271 plain-number entry, preserving its count", () =>
		{
			const {app, prefs} = createMailApp({'1::INBOX/Sub': 5});

			app.rememberUsedFolder('move', '1::INBOX/Sub');

			const entry = prefs.moveFolderUsage['1::INBOX/Sub'];
			assert.equal(entry.count, 6);
			assert.isAbove(entry.last, 0);
		});

		it("does nothing for a target with no '::' separator", () =>
		{
			const {app, prefs} = createMailApp();

			app.rememberUsedFolder('move', 'not-a-valid-target');

			assert.deepEqual(prefs.moveFolderUsage, {});
		});

		it("keeps both the use-count and the \"last used\" (#124271) cases alive when pruning past 30 entries", () =>
		{
			// 30 old, high-count entries already fill the kept-by-count window...
			const usage : Record<string, any> = {};
			for (let i = 0; i < 30; i++)
			{
				usage[`1::old${i}`] = {count: 100, last: 1000};
			}
			// ...then a brand new folder gets used once, today - exactly #124271's scenario
			const {app, prefs} = createMailApp(usage);

			app.rememberUsedFolder('move', '1::justStarted');

			assert.property(prefs.moveFolderUsage, '1::justStarted',
				"a low-count but just-used target must survive pruning, or it can never reach the recency slice");
		});
	});

	describe("updateFolderQuickAction()", () =>
	{
		it("shows the top 10 by count, all in group 0, when nothing else qualifies as 'recent'", () =>
		{
			const usage : Record<string, any> = {};
			for (let i = 0; i < 12; i++)
			{
				usage[`1::folder${i}`] = {count: 12 - i, last: 0};
			}
			const {app, nm} = createMailApp(usage);

			app.updateFolderQuickAction('move');

			const children = nm.actions.moveto.children;
			assert.equal(Object.keys(children).length, 10, "only the top 10 by count, nothing else has a 'last'");
			assert.isTrue('move_1::folder0' in children, "highest count must be included");
			assert.isFalse('move_1::folder10' in children, "11th/12th highest count must be excluded");
			Object.values(children).forEach((child : any) => assert.equal(child.group, 0));
		});

		it("appends up to 5 more by recency, in their own group, that aren't already in the top 10", () =>
		{
			const usage : Record<string, any> = {};
			// 10 old habits, high count, long ago
			for (let i = 0; i < 10; i++)
			{
				usage[`1::habit${i}`] = {count: 50, last: 1000};
			}
			// 5 newly-adopted folders, low count, used most recently
			for (let i = 0; i < 5; i++)
			{
				usage[`1::new${i}`] = {count: 1, last: 10000 + i};
			}
			const {app, nm} = createMailApp(usage);

			app.updateFolderQuickAction('move');

			const children = nm.actions.moveto.children;
			assert.equal(Object.keys(children).length, 15);
			for (let i = 0; i < 10; i++)
			{
				assert.equal(children[`move_1::habit${i}`].group, 0);
			}
			for (let i = 0; i < 5; i++)
			{
				assert.equal(children[`move_1::new${i}`].group, 1, "recently-used-but-not-top-10 belongs in the separated group");
			}
		});

		it("only takes the 5 MOST recent of a larger recency pool, and never re-includes a top-10 entry", () =>
		{
			const usage : Record<string, any> = {};
			for (let i = 0; i < 10; i++)
			{
				usage[`1::habit${i}`] = {count: 50, last: 1000};
			}
			for (let i = 0; i < 8; i++)
			{
				usage[`1::new${i}`] = {count: 1, last: 10000 + i};
			}
			const {app, nm} = createMailApp(usage);

			app.updateFolderQuickAction('move');

			const children = nm.actions.moveto.children;
			assert.equal(Object.keys(children).length, 15, "10 top + only the 5 most recent of the 8 candidates");
			assert.isFalse('move_1::new0' in children, "least recent of the 8 candidates must be dropped");
			assert.isTrue('move_1::new7' in children, "most recent of the 8 candidates must be kept");
		});

		it("orders the recent group oldest-first, so the single most-recently-used target is the very last row", () =>
		{
			const usage : Record<string, any> = {};
			for (let i = 0; i < 10; i++)
			{
				usage[`1::habit${i}`] = {count: 50, last: 1000};
			}
			for (let i = 0; i < 5; i++)
			{
				usage[`1::new${i}`] = {count: 1, last: 10000 + i}; // new4 is the most recently used
			}
			const {app, nm} = createMailApp(usage);

			app.updateFolderQuickAction('move');

			const keys = Object.keys(nm.actions.moveto.children);
			assert.deepEqual(keys.slice(10), ['move_1::new0', 'move_1::new1', 'move_1::new2', 'move_1::new3', 'move_1::new4'],
				"oldest of the recent batch right after the top 10, most-recently-used one last");
		});

		it("ignores a pre-#124271 plain-number entry for the recency slice (no 'last' recorded yet)", () =>
		{
			const usage : Record<string, any> = {};
			for (let i = 0; i < 10; i++)
			{
				usage[`1::habit${i}`] = {count: 50, last: 1000};
			}
			usage['1::legacy'] = 1; // old format, no timestamp at all

			const {app, nm} = createMailApp(usage);

			app.updateFolderQuickAction('move');

			assert.isFalse('move_1::legacy' in nm.actions.moveto.children,
				"a target with no recorded 'last' can't be ranked by recency, so it's left out rather than guessed at");
		});

		it("excludes the currently selected folder from both the top and the recent slice", () =>
		{
			const usage = {
				'1::current': {count: 99, last: 99999},
				'1::other': {count: 1, last: 1},
			};
			const {app, nm} = createMailApp(usage);
			nm.activeFilters.selectedFolder = '1::current';

			app.updateFolderQuickAction('move');

			const children = nm.actions.moveto.children;
			assert.isFalse('move_1::current' in children);
			assert.isTrue('move_1::other' in children);
		});

		it("leaves the submenu childless when there is no usage at all", () =>
		{
			const {app, nm} = createMailApp({});

			app.updateFolderQuickAction('move');

			assert.deepEqual(nm.actions.moveto.children, {});
		});
	});
});
