import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import {Et2VfsMode} from "../Et2VfsMode";
import "../Et2VfsMode";

window.egw = {
	app_name: () => "filemanager",
	debug: () => {},
	lang: (label : string) => label,
	preference: () => "en"
} as any;

describe("Et2VfsMode", () =>
{
	describe("formatMode()", () =>
	{
		it("renders the plain permission bits", () =>
		{
			assert.equal(Et2VfsMode.formatMode(0o100644), "-rw-r--r--");
			assert.equal(Et2VfsMode.formatMode(0o100755), "-rwxr-xr-x");
			assert.equal(Et2VfsMode.formatMode(0o40755), "drwxr-xr-x");
		});

		it("picks the file type from the S_IFMT bits, not the first mask that overlaps", () =>
		{
			// Block special (0x6000) contains character special (0x2000), so it used to render as "c"
			assert.equal(Et2VfsMode.formatMode(0o60644), "brw-r--r--");
			assert.equal(Et2VfsMode.formatMode(0o20644), "crw-r--r--");
			assert.equal(Et2VfsMode.formatMode(0o120777), "lrwxrwxrwx");
			assert.equal(Et2VfsMode.formatMode(0o140755), "srwxr-xr-x");
			assert.equal(Et2VfsMode.formatMode(0o10644), "prw-r--r--");
		});

		it("puts set-UID / set-GID / sticky on the execute column of their own triplet", () =>
		{
			assert.equal(Et2VfsMode.formatMode(0o104755), "-rwsr-xr-x", "SUID replaces owner execute");
			assert.equal(Et2VfsMode.formatMode(0o102755), "-rwxr-sr-x", "sGID replaces group execute");
			assert.equal(Et2VfsMode.formatMode(0o41777), "drwxrwxrwt", "sticky replaces world execute");
		});

		it("keeps the string 10 characters long", () =>
		{
			// The sticky bit used to be written past the end of the permission array, growing it to 11
			assert.equal(Et2VfsMode.formatMode(0o41777).length, 10);
			assert.equal(Et2VfsMode.formatMode(0o107777).length, 10);
		});

		it("uppercases s / t when the execute bit it replaces is not set", () =>
		{
			assert.equal(Et2VfsMode.formatMode(0o104655), "-rwSr-xr-x", "SUID without owner execute");
			assert.equal(Et2VfsMode.formatMode(0o102645), "-rw-r-Sr-x", "sGID without group execute");
			assert.equal(Et2VfsMode.formatMode(0o41776), "drwxrwxrwT", "sticky without world execute");
		});

		it("renders nothing for empty or non-numeric values", () =>
		{
			assert.equal(Et2VfsMode.formatMode(undefined), "");
			assert.equal(Et2VfsMode.formatMode(null), "");
			assert.equal(Et2VfsMode.formatMode(""), "");
			assert.equal(Et2VfsMode.formatMode("rw-r--r--"), "");
		});

		it("accepts a numeric string or a row object", () =>
		{
			assert.equal(Et2VfsMode.formatMode("33188"), "-rw-r--r--");
			assert.equal(Et2VfsMode.formatMode(<any>{mode: 0o40755}), "drwxr-xr-x");
		});
	});

	it("renders the permission string, and repeats it as the tooltip", async() =>
	{
		const element = await fixture<Et2VfsMode>(html`
            <et2-vfs-mode></et2-vfs-mode>`);

		element.value = 0o104755;
		await elementUpdated(element);

		assert.equal(element.shadowRoot?.textContent?.trim(), "-rwsr-xr-x");
		assert.equal(element.shadowRoot?.querySelector("span")?.getAttribute("title"), "-rwsr-xr-x");
	});

	it("takes the mode out of a row object assigned as value", async() =>
	{
		const element = await fixture<Et2VfsMode>(html`
            <et2-vfs-mode></et2-vfs-mode>`);

		(<any>element).value = {mode: 0o41777};
		await elementUpdated(element);

		assert.equal(element.shadowRoot?.textContent?.trim(), "drwxrwxrwt");
	});
});
