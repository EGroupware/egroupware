import ResumableStub, {Resumable} from "./ResumableStub";
import {assert, fixture, oneEvent} from "@open-wc/testing";
import {html} from "lit";
import * as sinon from "sinon";
import {Et2File} from "../Et2File";
import {Et2FileItem} from "../Et2FileItem";


window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};

describe('Et2File Component', async() =>
{
	let element : Et2File;
	let instanceManagerStub;

	before(() =>
	{
		globalThis.Resumable = ResumableStub;
	})


	beforeEach(async() =>
	{
		element = <Et2File>await fixture(html`
            <et2-file></et2-file>`);

		// Stub instance manager
		if(!element.getInstanceManager)
		{
			element.getInstanceManager = () => {}; // Define an empty function
		}
		instanceManagerStub = sinon.stub(element, "getInstanceManager").returns({
			etemplate_exec_id: "mocked-id",
		});
	});


	it('should create a Resumable instance', async() =>
	{
		const resumable = new Resumable();
		assert.instanceOf(resumable, Resumable);
	});

	// Make sure it works
	it('is defined', async() =>
	{
		const el = await fixture<Et2File>(html`
            <et2-file></et2-file>`);
		assert.instanceOf(el, Et2File);

		// Item is also required for some tests, so it needs to work too
		const item = await fixture<Et2FileItem>(html`
            <et2-file-item></et2-file-item>`);
		assert.instanceOf(item, Et2FileItem);
	});

	it('should have default properties', () =>
	{
		assert.strictEqual(element.accept, '');
		assert.strictEqual(element.maxFileSize, undefined);
		assert.strictEqual(element.maxFiles, undefined);
		assert.strictEqual(element.multiple, false);
		assert.strictEqual(element.loading, false);
		assert.strictEqual(element.noFileList, false);
		assert.strictEqual(element.uploadTarget, 'EGroupware\\Api\\Etemplate\\Widget\\File::ajax_upload');
	});

	it('should allow setting properties', async() =>
	{
		element.accept = 'image/png';
		element.multiple = true;
		await element.updateComplete;

		assert.strictEqual(element.accept, 'image/png');
		assert.isTrue(element.multiple);
	});

	it('should open file input when clicking the button', async() =>
	{
		const fileInput = element.shadowRoot.querySelector('#file-input');
		const clickSpy = sinon.spy(fileInput, 'click');

		element.shadowRoot.querySelector('et2-button').click();
		assert.isTrue(clickSpy.calledOnce);
	});

	it('should dispatch a change event when a file is added', async() =>
	{
		const file = new File(['content'], 'test.txt', {type: 'text/plain'});
		const fileList = {
			0: file,
			length: 1,
			item: (index) => fileList[index],
		};

		const listener = oneEvent(element, 'change');
		element.handleFiles(fileList);

		const event = await listener;
		assert.isDefined(event);
		assert.deepStrictEqual(event.detail[0].file, file);
	});

	it('should remove a file and dispatch a change event', async() =>
	{
		const file = new File(['content'], 'test.txt', {type: 'text/plain'});
		element.addFile(file);
		await element.updateComplete;

		const fileInfo = element.files[0];
		assert.strictEqual(element.files.length, 1);

		const listener = oneEvent(element, 'change');
		element.handleFileRemove(fileInfo);

		await listener;
		assert.strictEqual(element.files.length, 0);
	});

	it('should render file items when files are added', async() =>
	{
		const file = new File(['content'], 'test.txt', {type: 'text/plain'});
		element.addFile(file);
		await element.updateComplete;

		const fileItems = element.shadowRoot.querySelectorAll('et2-file-item');
		assert.strictEqual(fileItems.length, 1, 'File item was not rendered');
		assert.strictEqual(fileItems[0].getAttribute('display'), 'large', 'Incorrect file item display mode');
	});
	it('should reject files that exceed maxFileSize', async() =>
	{
		element.maxFileSize = 1024; // 1KB max size
		await element.updateComplete;
		const addSpy = sinon.spy(element.resumable.events, "fileAdded");

		const largeFile = new File(['a'.repeat(2048)], 'bigfile.txt', {type: 'text/plain'});
		element.addFile(largeFile);
		assert.isFalse(addSpy.called, "File should not be added")
		assert.strictEqual(element.resumable.files.length, 0, 'File should not be added');
	});

	it('should reject files with incorrect mime type', async() =>
	{
		element.accept = 'image/png';
		await element.updateComplete;
		const addSpy = sinon.spy(element.resumable.events, "fileAdded");

		const invalidFile = new File(['content'], 'test.txt', {type: 'text/plain'});
		element.addFile(invalidFile);

		assert.isFalse(addSpy.called, "File should not be added")
		assert.strictEqual(element.resumable.files.length, 0, 'File should be rejected due to invalid type');
	});

	it('should update file progress when uploading', async() =>
	{
		const file = new File(['content'], 'test.txt', {type: 'text/plain'});
		const listener = oneEvent(element, 'et2-add');
		const clock = sinon.useFakeTimers();
		element.addFile(file);
		await element.updateComplete;
		const event = await listener;


		const fileInfo = element.files[0];

		const fileItem = <Et2FileItem>element.findFileItem(fileInfo.file);

		// Et2File waits 100 ms before upload starts
		clock.tick(101);
		await fileItem.updateComplete;

		assert.strictEqual(fileItem.progress, 50, 'File progress should be updated');
		clock.restore();
	});

	it('should update when file is done', async() =>
	{
		const file = new File(['content'], 'test.txt', {type: 'text/plain'});
		const listener = oneEvent(element, 'et2-load');
		const clock = sinon.useFakeTimers();
		element.addFile(file);
		await element.updateComplete;
		await oneEvent(element, 'et2-add');


		const fileInfo = element.files[0];
		const fileItem = <Et2FileItem>element.findFileItem(fileInfo.file);

		// Et2File waits 100 ms before upload starts, stub waits 100 before completing
		clock.tick(101);
		await fileItem.updateComplete;

		// Wait for event
		clock.tick(101);
		let event = await listener;
		assert.equal(event.detail.uniqueIdentifier, fileInfo.uniqueIdentifier);
		assert.strictEqual(fileItem.progress, 100, 'File progress should be 100%');
		clock.restore();
	});
});
