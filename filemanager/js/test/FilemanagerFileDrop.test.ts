import {assert} from "@open-wc/testing";
import "./FilemanagerAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path - see the note in
 * FilemanagerMailAttachments.test.ts: a plain `import ... from "../app"` resolves to a stale
 * gitignored tsc output instead of the live source.
 */
const APP_SOURCE = '/filemanager/js/app.ts';

/**
 * Where a natively dropped file is uploaded to.
 *
 * filedrop() is called from the nextmatch's `et2-filedrop` listener with the UID of the row the
 * file landed on, and picks the upload widget's target folder from it.  It used to take the
 * dropped-on row's own path only for a directory row and fall back to the *current* folder for
 * anything else.  Those two agree for a top-level file row, so the shortcut held until rows could
 * be expanded: a file dropped on a child of an expanded folder was uploaded to the folder the
 * breadcrumb showed instead of the one that actually lists it.
 *
 * Setup strategy: filedrop() only touches the module-scope global `egw`, `this.get_path()`,
 * `this.dirname()` (real, pure) and `this.et2.getWidgetById('upload')`, so the app object is a
 * bare Object.create(prototype) with a stub upload widget - no EgwApp constructor, which would
 * want a real framework, sidebox and etemplate.
 *
 * Pass criteria: the widget's `path` at the moment addFile() is called, since filedrop() restores
 * the previous path again once the upload reports back.
 */
describe('filemanagerAPP.filedrop() upload target', () =>
{
	const CURRENT_FOLDER = '~';
	let app : any;
	let rows : Record<string, any>;
	let uploadPaths : string[];
	let filemanagerAppClass : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		filemanagerAppClass = (<any>window).app.classes.filemanager;
	});

	beforeEach(() =>
	{
		rows = {};
		uploadPaths = [];
		(<any>window).egw = {
			dataGetUIDdata: (uid : string) => rows[uid]
		};

		const upload = {
			path: CURRENT_FOLDER + '/',
			addFile: function() {uploadPaths.push(this.path);},
			addEventListener: () => {}
		};

		app = Object.create(filemanagerAppClass.prototype);
		app.get_path = () => CURRENT_FOLDER;
		app.et2 = {getWidgetById: (id : string) => id === 'upload' ? upload : null};
	});

	function drop(uid : string) : void
	{
		app.filedrop(uid, [new File(['test'], 'dropped.txt', {type: 'text/plain'})]);
	}

	/**
	 * Contract: the regression itself - a child row of an expanded folder uploads into that
	 * folder, not into the folder the list is currently showing.
	 */
	it('uploads onto an expanded folder\'s child into that folder', () =>
	{
		const uid = 'filemanager::~/Test folder/child.odt';
		rows[uid] = {data: {path: '~/Test folder/child.odt', mime: 'application/vnd.oasis.opendocument.text'}};

		drop(uid);

		assert.deepEqual(uploadPaths, ['~/Test folder/'],
			'a file dropped on a nested row belongs in the folder listing it, not the current one');
	});

	/**
	 * Contract: a row whose mime could not be resolved (a broken symlink reports mime false) is
	 * still a file, so it must resolve to its own folder rather than falling back.
	 */
	it('handles a child row with no resolvable mime', () =>
	{
		const uid = 'filemanager::~/Test folder/broken-link.odt';
		rows[uid] = {data: {path: '~/Test folder/broken-link.odt', mime: false}};

		drop(uid);

		assert.deepEqual(uploadPaths, ['~/Test folder/'], 'a missing mime must not send the file to the current folder');
	});

	/**
	 * Contract: a directory row still takes the folder itself, not its parent.
	 */
	it('uploads onto a directory row into that directory', () =>
	{
		const uid = 'filemanager::~/Test folder';
		rows[uid] = {data: {path: '~/Test folder', mime: 'httpd/unix-directory'}};

		drop(uid);

		assert.deepEqual(uploadPaths, ['~/Test folder/'], 'a folder row is the target, not its parent');
	});

	/**
	 * Contract: a top-level file row is unchanged - its folder is the current folder, which is
	 * what the old shortcut returned too.
	 */
	it('uploads onto a top-level file row into the current folder', () =>
	{
		const uid = 'filemanager::~/report.xml';
		rows[uid] = {data: {path: '~/report.xml', mime: 'application/xml'}};

		drop(uid);

		assert.deepEqual(uploadPaths, ['~/'], 'a top-level file row should still resolve to the current folder');
	});

	/**
	 * Contract: a drop that hit no row at all (empty area below the list) still uses the current
	 * folder - the nextmatch passes "" for that.
	 */
	it('uploads a drop outside any row into the current folder', () =>
	{
		drop('');

		assert.deepEqual(uploadPaths, ['~/'], 'a drop on no row should use the current folder');
	});
});
