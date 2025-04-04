import {assert, fixture, html} from "@open-wc/testing";
import "@shoelace-style/shoelace/dist/components/format-bytes/format-bytes.js";
import {Et2FileItem} from "../Et2FileItem";

// @ts-ignore
window.egw = {
	ajaxUrl: () => "",
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};
describe("Et2FileItem", () =>
{
	// Make sure it works
	it('is defined', async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item></et2-file-item>`);
		assert.instanceOf(el, Et2FileItem);
	});

	// Test case: Component renders with default values
	it("should render with default properties", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item></et2-file-item>`);

		assert.ok(el.shadowRoot);
		assert.strictEqual(el.variant, "default");
		assert.strictEqual(el.loading, false);
		assert.strictEqual(el.closable, false);
		assert.strictEqual(el.hidden, false);
	});

	// Test case: Test different variants of the file item
	it("should apply correct class for 'primary' variant", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item variant="primary"></et2-file-item>`);
		const classList = el.shadowRoot?.querySelector(".file-item")?.classList;
		assert.ok(classList?.contains("file-item--primary"));
	});

	// Test case: Check progress bar visibility when loading
	it("should show progress bar when loading is true", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .loading=${true}></et2-file-item>`);
		const progressBar = el.shadowRoot?.querySelector(".file-item__progress-bar");
		assert.ok(progressBar);
	});

	// Test case: Check if progress value is properly set
	it("should set progress value correctly when loading", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .loading=${true} .progress=${50}></et2-file-item>`);

		const progressBar = el.shadowRoot?.querySelector("sl-progress-bar");
		assert.strictEqual(progressBar?.getAttribute("value"), "50");
	});

	// Test case: Check if closable functionality works
	it("should hide the item when close button is clicked", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .closable=${true}></et2-file-item>`);

		const closeButton = el.shadowRoot?.querySelector(".file-item__close-button");
		closeButton?.dispatchEvent(new MouseEvent("click"));
		assert.strictEqual(el.hidden, true);
	});

	// Test case: Check slot content rendering (for example, image)
	it("should render the image slot content", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .image="path/to/image.png">
                <span slot="image">Custom Image Slot</span>
            </et2-file-item>
		`);

		const imageSlot = el.shadowRoot?.querySelector("slot[name='image']");
		assert.ok(imageSlot);
	});

	// Test case: Check size display when size is set
	it("should display file size when size is provided", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .size=${1000}></et2-file-item>`);

		const sizeElement = el.shadowRoot?.querySelector(".file-item__label__size");
		assert.ok(sizeElement);
		assert.ok(sizeElement?.shadowRoot?.textContent?.includes("1 kB"));
	});

	// Test case: Check if the component applies "hidden" class & attribute when hidden property is true
	it("should be hidden when hidden is true", async() =>
	{
		const el = await fixture<Et2FileItem>(html`
            <et2-file-item .hidden=${true}></et2-file-item>`);

		const classList = el.shadowRoot?.querySelector(".file-item")?.classList;
		assert.ok(classList?.contains("file-item--hidden"));
		assert.ok(el.hasAttribute("hidden"));
	});
});
