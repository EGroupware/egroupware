import {assert} from "@open-wc/testing";
import {etemplate2} from "../etemplate2";

/**
 * The stand-in a popup page is sent with (Framework::skeleton()): the first etemplate2 of the page takes it over and
 * removes it when its form is loaded, unless whoever resizes the popup asks for it to stay a little longer.
 */
describe("etemplate2 skeleton hand-over", () =>
{
	let skeleton : HTMLElement;
	beforeEach(() =>
	{
		skeleton = document.createElement('div');
		skeleton.id = 'egw-skeleton';
		document.body.append(skeleton);
	});
	afterEach(() => skeleton.remove());

	describe("claimSkeleton()", () =>
	{
		it("gives the stand-in to the first etemplate2 only", () =>
		{
			assert.strictEqual(etemplate2.claimSkeleton(document), skeleton);
			assert.isNull(etemplate2.claimSkeleton(document), "a second (nested or later) etemplate2 must not get it");
		});

		it("returns null for a page without one", () =>
		{
			skeleton.remove();
			assert.isNull(etemplate2.claimSkeleton(document));
		});
	});

	describe("releaseSkeleton()", () =>
	{
		it("removes the stand-in", () =>
		{
			etemplate2.releaseSkeleton(skeleton);
			assert.isFalse(skeleton.isConnected);
		});

		it("leaves it to whoever cancels the release event, eg. the popup that is resized first", () =>
		{
			skeleton.addEventListener('egw-skeleton-release', ev => ev.preventDefault());

			etemplate2.releaseSkeleton(skeleton);

			assert.isTrue(skeleton.isConnected);
		});

		it("does not mind there being none, or it being gone already", () =>
		{
			assert.doesNotThrow(() => etemplate2.releaseSkeleton(null));
			skeleton.remove();
			assert.doesNotThrow(() => etemplate2.releaseSkeleton(skeleton));
		});
	});
});
