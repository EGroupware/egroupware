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
 * Where the upload widget sends files.
 *
 * The upload widget follows the directory shown in the list, so a file lands in the folder the
 * user is looking at.  A share with a hidden upload folder is the exception: the server points the
 * widget at that folder (not shown to the visitor, the only place they may write) and navigating
 * to another folder must not move it - the visitor would otherwise upload into a folder they
 * cannot write to ("Permission denied").
 *
 * Setup strategy: the method only touches `this.et2` (upload widget and content), so the app object
 * is a bare Object.create(prototype) with stubs - no EgwApp constructor, which would want a real
 * framework, sidebox and etemplate.
 */
describe('filemanagerAPP upload path', () =>
{
	const HIDDEN_PATH = '/Shared dir/Upload/';
	let app : any;
	let upload : {path : string};
	let content : Record<string, any>;
	let filemanagerAppClass : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		filemanagerAppClass = (<any>window).app.classes.filemanager;
	});

	beforeEach(() =>
	{
		content = {};
		upload = {path: '/Shared dir/'};

		app = Object.create(filemanagerAppClass.prototype);
		app.et2 = {
			getWidgetById: (id : string) => id === 'upload' ? upload : null,
			getArrayMgr: () => ({getEntry: (name : string) => content[name]})
		};
		app.nm = {applyFilters: () => {}};
	});

	function navigate(path : string) : void
	{
		app.handlePathChange(new Event('change'), {getValue: () => path});
	}

	describe('navigating', () =>
	{
		/**
		 * Contract: the normal case - the upload target follows the folder being shown, with the
		 * trailing slash Et2VfsUpload needs.
		 */
		it('points the upload at the folder being shown', () =>
		{
			navigate('/Shared dir/Real subdir');

			assert.equal(upload.path, '/Shared dir/Real subdir/');
		});

		/**
		 * Contract: the regression itself - on a share with a hidden upload folder the server-set
		 * target survives navigation, into a subfolder and back.
		 */
		it('keeps a hidden upload share\'s upload folder when navigating', () =>
		{
			content['hidden_upload'] = true;
			upload.path = HIDDEN_PATH;

			navigate('/Shared dir/Real subdir');
			assert.equal(upload.path, HIDDEN_PATH, 'navigating into a subfolder must not retarget the upload');

			navigate('/Shared dir');
			assert.equal(upload.path, HIDDEN_PATH, 'navigating back must not retarget the upload either');
		});

		/**
		 * Contract: a missing upload widget (templates without one) is not an error.
		 */
		it('does nothing when there is no upload widget', () =>
		{
			app.et2.getWidgetById = () => null;

			navigate('/Shared dir/Real subdir');
		});
	});
});
