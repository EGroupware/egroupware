import {assert, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from "sinon";
import {Et2VfsUpload, VfsFileInfo} from "../Et2VfsUpload";
import {Et2FileItem} from "../../Et2File/Et2FileItem";
import {Et2Dialog} from "../../Et2Dialog/Et2Dialog";

window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};
describe('Et2VfsUpload', async() =>
{
	let element : Et2VfsUpload;

	beforeEach(async() =>
	{
		element = /** @type {Et2VfsUpload} */ (await fixture(html`
            <et2-vfs-upload></et2-vfs-upload>`));
	});

	// Make sure it works
	it('is defined', async() =>
	{
		const el = await fixture<Et2VfsUpload>(html`
            <et2-vfs-upload></et2-vfs-upload>`);
		assert.instanceOf(el, Et2VfsUpload);

		// Item is also required for some tests, so it needs to work too
		const item = await fixture<Et2FileItem>(html`
            <et2-file-item></et2-file-item>`);
		assert.instanceOf(item, Et2FileItem);
	});

	it('should set default uploadTarget', () =>
	{
		assert.equal(element.uploadTarget, "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_upload");
	});

	it('should update path property and set multiple based on trailing slash', () =>
	{
		element.path = '/some/path/';
		assert.equal(element.path, '/some/path/');
		assert.isTrue(element.multiple);

		element.path = '/some/file.txt';
		assert.equal(element.path, '/some/file.txt');
		assert.isFalse(element.multiple);
	});

	it('should include path in upload query', () =>
	{
		element.path = '/upload/path';
		assert.equal(element.resumableOptions.query().path, '/upload/path');
	});

	it('should handle file removal with confirmation', async() =>
	{
		const fileInfo : VfsFileInfo = <VfsFileInfo>{path: '/test/file.txt'};
		element.value = {test: fileInfo};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;
		await element.updateComplete;

		const confirmStub = sinon.stub(element, 'confirmDelete').resolves([Et2Dialog.YES_BUTTON, undefined]);

		const removeStub = sinon.stub(element, 'handleFileRemove').callThrough();
		await element.handleFileRemove(fileInfo);

		assert(mockEgw.request.calledOnce, 'Request should be sent');
		assert(removeStub.calledWith(fileInfo), 'File should be removed');
		assert.isFalse(Object.values(element.value).includes(fileInfo), "File should not be part of value");
	});

	it('should not remove file if delete confirmation is cancelled', async() =>
	{
		const fileInfo : VfsFileInfo = <VfsFileInfo>{path: '/test/file.txt'};
		element.value = {test: fileInfo};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		const confirmStub = sinon.stub().resolves([false, undefined]);
		sinon.stub(element, 'confirmDelete').callsFake(confirmStub);

		const removeStub = sinon.stub(element, 'handleFileRemove').callThrough();

		await element.handleFileRemove(fileInfo);

		assert(mockEgw.request.notCalled, 'Request should not be sent');
		assert(removeStub.calledWith(fileInfo), 'File removal method should still be invoked');
		assert.isTrue(Object.values(element.value).includes(fileInfo), "File should still be part of value");
	});


	it('should display a message if ajax_remove returns a message', async() =>
	{
		const fileInfo = {path: '/test/file.txt'};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 1, msg: 'Error deleting file'}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		const confirmStub = sinon.stub().resolves([Et2Dialog.YES_BUTTON, undefined]);
		sinon.stub(element, 'confirmDelete').callsFake(confirmStub);

		await element.handleFileRemove(fileInfo);

		assert(mockEgw.request.calledOnce, 'Request should be sent');
		assert(mockEgw.message.calledOnce, 'message() should be called once');
		assert(mockEgw.message.calledOnceWith('Error deleting file', 'error'), 'Error message should be displayed');
	});
});

describe('Et2VfsUpload existing file checks', async() =>
{
	let element : Et2VfsUpload;
	let addSpy;
	let completeSpy;

	beforeEach(async() =>
	{
		element = /** @type {Et2VfsUpload} */ (await fixture(html`
            <et2-vfs-upload></et2-vfs-upload>`));
		addSpy = sinon.spy();
		element.addEventListener("et2-add", addSpy);
		element.addEventListener("change", completeSpy);
	});

	it('Should check for existing file', async() =>
	{
		// Ask is the default, but set it anyway
		element.conflict = "ask";

		const fileInfo : File = <File>{
			name: 'file.txt',
			type: 'text/plain',
			size: 1
		};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0, exists: true}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		const confirmStub = sinon.stub(element, 'confirmConflict').resolves(true);

		element.addFile(fileInfo);
		await element.updateComplete;
		await mockEgw.request.returnValues[0];

		assert(mockEgw.request.calledOnce, 'Request to see if file exists should be sent');
		assert(confirmStub.calledOnce, 'User should be asked about overwriting');
		await oneEvent(element, "change");
		assert(addSpy.calledOnce, 'File upload should proceed');
	});

	it('Should not check if conflict is "overwrite"', async() =>
	{

		element.conflict = "overwrite";

		const fileInfo : File = <File>{
			name: 'file.txt',
			type: 'text/plain',
			size: 1
		};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0, exists: true}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		const confirmStub = sinon.stub(element, 'confirmConflict').resolves(true);

		element.addFile(fileInfo);
		await element.updateComplete;
		await mockEgw.request.returnValues[0];

		assert(mockEgw.request.notCalled, 'Request to see if file exists should not be sent');
		assert(confirmStub.notCalled, 'User should not be asked about overwriting');
		await oneEvent(element, "change");
		assert(addSpy.calledOnce, 'File upload should proceed');
	});

	it('Should not ask if conflict is "rename"', async() =>
	{
		element.conflict = "rename";

		const fileInfo : File = <File>{
			name: 'file.txt',
			type: 'text/plain',
			size: 1
		};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0, exists: true}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		const confirmStub = sinon.stub(element, 'confirmConflict').resolves(true);

		element.addFile(fileInfo);
		await element.updateComplete;
		await mockEgw.request.returnValues[0];

		assert(mockEgw.request.calledOnce, 'Request to see if file exists should be sent');
		assert(confirmStub.notCalled, 'User should not be asked about overwriting');

		await oneEvent(element, "change");
		assert(addSpy.calledOnce, 'File upload should proceed');
	});

	it('Should not upload if they cancel conflict dialog', async() =>
	{
		// Ask is the default, but set it anyway
		element.conflict = "ask";
		const fileInfo : File = <File>{
			name: 'file.txt',
			type: 'text/plain',
			size: 1
		};
		const mockEgw = {
			...window.egw,
			lang: sinon.stub().returnsArg(0),
			request: sinon.stub().resolves({errs: 0, exists: true}),
			message: sinon.stub()
		};
		element.egw = () => mockEgw;

		// Stub the parent's async resumableFileAdded method
		let parentFileAdded = sinon.spy(Object.getPrototypeOf(element), "resumableFileAdded");
		const confirmStub = sinon.stub(element, 'confirmConflict').resolves(false);

		element.addFile(fileInfo);
		await element.updateComplete;
		await mockEgw.request.returnValues[0];

		assert(mockEgw.request.calledOnce, 'Request to see if file exists should be sent');
		assert(confirmStub.calledOnce, 'User should be asked about overwriting');
		assert(addSpy.notCalled, 'File upload should not proceed');
	})
});
