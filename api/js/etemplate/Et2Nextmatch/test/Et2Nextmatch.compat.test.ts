import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";

describe("Et2Nextmatch legacy event-handler compatibility", () =>
{
	/**
	 * Contract: legacy onselect callbacks receive the current selection and the
	 * nextmatch instance after selection processing.
	 * Setup: provide a source datagrid and a fixed current selection.
	 * Pass: the callback receives the legacy two-argument signature exactly.
	 */
	it("invokes onselect with selected IDs and the nextmatch", () =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const datagrid = document.createElement("et2-datagrid");
		Object.defineProperty(nextmatch, "_datagrid", {value: datagrid});
		nextmatch.getSelection = () => ({ids: ["addressbook::42"], all: false});
		let received : unknown[] = [];
		nextmatch.legacyOnselect = (...args : unknown[]) => received = args;

		nextmatch._handleLegacyOnselect({composedPath: () => [datagrid]} as CustomEvent);

		assert.deepEqual(
			received,
			[["addressbook::42"], nextmatch],
			"onselect should receive selected row IDs followed by the nextmatch"
		);
	});

	/**
	 * Contract: an event listener may suppress a legacy onselect callback while
	 * preserving normal selection processing.
	 * Setup: invoke the compatibility bridge with a cancelled selection event.
	 * Pass: the callback is not invoked.
	 */
	it("allows listeners to suppress legacy onselect", () =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const datagrid = document.createElement("et2-datagrid");
		let called = false;
		nextmatch.legacyOnselect = () => called = true;
		const event = {
			defaultPrevented: true,
			composedPath: () => [datagrid]
		} as CustomEvent;

		Object.defineProperty(nextmatch, "_datagrid", {value: datagrid});
		nextmatch._handleLegacyOnselect(event);

		assert.isFalse(called, "cancelled selection events should not invoke legacy onselect");
	});

	/**
	 * Contract: returning false from legacy onfiledrop cancels the default drop
	 * action, just like calling preventDefault() on et2-filedrop.
	 * Setup: install a callback returning false and invoke the compatibility bridge.
	 * Pass: it receives the row UID and files, and the event is cancelled.
	 */
	it("cancels the default file drop when onfiledrop returns false", () =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const files = [new File(["test"], "test.txt")];
		const event = new CustomEvent("et2-filedrop", {
			cancelable: true,
			detail: {rowUid: "addressbook::42", files}
		});
		let received : unknown[] = [];
		nextmatch.onfiledrop = (...args : unknown[]) =>
		{
			received = args;
			return false;
		};

		nextmatch._handleLegacyOnfiledrop(event);

		assert.deepEqual(received, ["addressbook::42", files], "onfiledrop should receive the row UID and files");
		assert.isTrue(event.defaultPrevented, "returning false should cancel the default file-drop action");
	});
});
