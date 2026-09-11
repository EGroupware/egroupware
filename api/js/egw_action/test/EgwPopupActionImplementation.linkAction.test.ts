import {assert} from "@open-wc/testing";
import {EgwPopupActionImplementation} from "../EgwPopupActionImplementation";

/**
 * Unit coverage for EgwPopupActionImplementation._addLinkAction(): the "Link" contextmenu
 * action auto-injected right below Paste (see _addCopyPaste for the sibling mechanism it
 * mirrors). Exercises the method directly with a minimal fake action manager, rather than
 * building a full Et2Nextmatch/EgwActionObject tree (see Et2Nextmatch.actions.test.ts for that
 * heavier harness) - _addLinkAction only ever touches _links, _selected[0].id/.manager, and
 * window.egw, so a fake covering just those is enough.
 */
describe("EgwPopupActionImplementation - _addLinkAction", () =>
{
	let linkRegistry : Record<string, Record<string, any>>;

	beforeEach(() =>
	{
		linkRegistry = {};
		const egwStub : any = {
			lang: (label : string) => label,
			image: () => "",
			link_get_registry: (app : string, name? : string) : any =>
			{
				const entry = linkRegistry[app];
				if(!entry)
				{
					return false;
				}
				return name ? (entry[name] ?? false) : entry;
			}
		};
		(<any>window).egw = egwStub;
	});

	const makeManager = () =>
	{
		const actions : Record<string, any> = {};
		return {
			getActionById: (id : string) => actions[id] || null,
			addAction: (type : string, id : string, caption : string, icon : string, onExecute : Function, allowOnMultiple : boolean) =>
			{
				const action : any = {id, type, caption, icon, onExecute, allowOnMultiple, group: 0, order: 0};
				actions[id] = action;
				return action;
			},
			_actions: actions
		};
	};

	it("adds the Link action for an app registered with a query hook (eg. infolog)", () =>
	{
		linkRegistry.infolog = {query: true};
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();
		const links : any = {};

		(<any>popup)._addLinkAction(links, [{id: "infolog::123", manager: mgr}]);

		assert.isDefined(links.egw_link);
		assert.isTrue(links.egw_link.enabled);
		assert.isTrue(links.egw_link.visible);
		assert.equal(mgr._actions.egw_link.caption, "Link");
		assert.equal(mgr._actions.egw_link.group, 2.5);
		assert.equal(mgr._actions.egw_link.order, 9.5);
	});

	it("adds the Link action for an app registered with only a title hook (eg. filemanager)", () =>
	{
		linkRegistry.filemanager = {title: true};
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();
		const links : any = {};

		(<any>popup)._addLinkAction(links, [{id: "filemanager::/tmp/x", manager: mgr}]);

		assert.isDefined(links.egw_link);
	});

	it("does not add the Link action for mail, which registers neither query nor title", () =>
	{
		// Mirrors mail_hooks::search_link() - view/add/edit/mime only, no query, no title
		linkRegistry.mail = {view: true, add: true, edit: true, mime: {}};
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();
		const links : any = {};

		(<any>popup)._addLinkAction(links, [{id: "mail::1234", manager: mgr}]);

		assert.isUndefined(links.egw_link);
		assert.isNull(mgr.getActionById("egw_link"));
	});

	it("does not add the Link action for an app missing from the registry entirely", () =>
	{
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();
		const links : any = {};

		(<any>popup)._addLinkAction(links, [{id: "unregisteredapp::1", manager: mgr}]);

		assert.isUndefined(links.egw_link);
	});

	it("does nothing when there is no selection, or the selected id has no app::id shape", () =>
	{
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();

		const links1 : any = {};
		(<any>popup)._addLinkAction(links1, [{id: "", manager: mgr}]);
		assert.isUndefined(links1.egw_link);

		const links2 : any = {};
		(<any>popup)._addLinkAction(links2, [{id: "noSeparatorHere", manager: mgr}]);
		assert.isUndefined(links2.egw_link);
	});

	it("reuses the same underlying action across multiple menu builds instead of recreating it", () =>
	{
		linkRegistry.infolog = {query: true};
		const mgr = makeManager();
		const popup = new EgwPopupActionImplementation();

		const links1 : any = {};
		(<any>popup)._addLinkAction(links1, [{id: "infolog::1", manager: mgr}]);
		const firstAction = mgr._actions.egw_link;

		const links2 : any = {};
		(<any>popup)._addLinkAction(links2, [{id: "infolog::2", manager: mgr}]);

		assert.strictEqual(mgr._actions.egw_link, firstAction, "must not create a second action instance");
		assert.isDefined(links2.egw_link);
	});
});
