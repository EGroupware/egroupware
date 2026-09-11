/**
 * Tests for egw_tooltip.js ("tooltip" module) - MODULE_WND_LOCAL.
 *
 * No test coverage existed before this file. See EgwTooltipHarness for how
 * it's loaded. Timings below mirror the module's own constants: mouseenter
 * schedules a check every time_delta=100ms, accumulating show_delta until
 * it reaches show_delay=200ms (i.e. ~200-300ms after mouseenter, absent any
 * mousemove reset) before the tooltip actually becomes VISIBLE - waits below
 * use generous margins around that to avoid flakiness.
 *
 * Important: prepare() creates the `.egw_tooltip` <div> and appends it to
 * the DOM SYNCHRONOUSLY on mouseenter, with `display: none` - existence in
 * the DOM is not the same thing as being shown. Tests below check
 * `style.display` for "is it actually showing", and only check for the
 * element's absence entirely for the paths that call hide()/removeDiv()
 * (tooltipUnbind, tooltipCancel, tooltipDestroy, pagehide, hovering onto the
 * tooltip itself with hideonhover) - a plain mouseleave-triggered hide only
 * sets display:none, it does NOT remove the element.
 *
 * KNOWN TOOLING GOTCHA (not a module bug): asserting isNull()/isNotNull()
 * directly on a real DOM Element, when the assertion FAILS, hangs the
 * entire browser test session instead of reporting a normal failure -
 * chai's failure-message formatter appears to choke trying to stringify a
 * DOM node's circular ownerDocument/defaultView graph. Every check below
 * coerces to a plain boolean (or reads a string property) before handing
 * anything to `assert`, specifically to avoid ever tripping this.
 */
import {assert} from "@open-wc/testing";
import {createEgwTooltipEnv, EgwTooltipEnv} from "./EgwTooltipHarness";

function wait(env : EgwTooltipEnv, ms : number) : Promise<void>
{
	return new Promise(resolve => env.window.setTimeout(resolve, ms));
}

function fireMouse(env : EgwTooltipEnv, elem : Element, type : string, opts : any = {}) : void
{
	const event = new (env.window as any).MouseEvent(type,
		Object.assign({bubbles: true, cancelable: true, clientX: 0, clientY: 0}, opts));
	elem.dispatchEvent(event);
}

function tooltipExists(env : EgwTooltipEnv) : boolean
{
	return !!env.window.document.querySelector('.egw_tooltip');
}

function tooltipVisible(env : EgwTooltipEnv) : boolean
{
	const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement | null;
	return !!div && div.style.display === 'block';
}

function tooltipText(env : EgwTooltipEnv) : string | null
{
	const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement | null;
	return div ? div.textContent : null;
}

describe('egw_tooltip.js (tooltip)', () =>
{
	let env : EgwTooltipEnv;
	let elem : HTMLElement;

	beforeEach(async() =>
	{
		env = await createEgwTooltipEnv();
		elem = env.window.document.createElement('div');
		env.window.document.body.appendChild(elem);
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw('testapp', env.window);
		assert.isFunction(instance.tooltipBind);
		assert.isFunction(instance.tooltipUnbind);
	});

	it('shows a text tooltip on the DOM roughly 200-300ms after mouseenter', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');

		fireMouse(env, elem, 'mouseenter');
		assert.isFalse(tooltipVisible(env), 'must not be visible immediately');

		await wait(env, 400);
		assert.isTrue(tooltipVisible(env));
		assert.equal(tooltipText(env), 'Hello tooltip');
	});

	it('isHtml=true renders markup instead of escaping it as text', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, '<b>Bold</b>', true);
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);

		assert.isTrue(tooltipVisible(env));
		const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement;
		assert.equal(div.querySelector('b')?.textContent, 'Bold');
	});

	it('isHtml=true with a real Node appends it directly instead of using insertAdjacentHTML', async() =>
	{
		const node = env.window.document.createElement('span');
		node.textContent = 'a real node';
		env.egw('testapp', env.window).tooltipBind(elem, node, true);
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);

		const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement;
		assert.strictEqual(div.querySelector('span'), node);
	});

	it('does nothing on a mobile client (egwIsMobile() truthy)', async() =>
	{
		env.setMobile(true);
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);

		assert.isFalse(tooltipExists(env));
	});

	it('does nothing when bound with empty html', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, '');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);

		assert.isFalse(tooltipExists(env));
	});

	it('a fast, large mouse movement resets the show delay', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter', {clientX: 0, clientY: 0});

		await wait(env, 150);
		// Large jump - resets show_delta back to 0, pushing the eventual
		// display:block out by roughly one more 100ms cycle.
		fireMouse(env, elem, 'mousemove', {clientX: 500, clientY: 500});

		await wait(env, 100);
		assert.isFalse(tooltipVisible(env), 'should still be delayed by the reset');

		await wait(env, 350);
		assert.isTrue(tooltipVisible(env), 'should have shown by now');
	});

	it('mouseleave hides an already-shown tooltip after ~150ms (without removing it from the DOM)', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isTrue(tooltipVisible(env));

		fireMouse(env, elem, 'mouseleave', {relatedTarget: env.window.document.body});
		await wait(env, 50);
		assert.isTrue(tooltipVisible(env), 'not hidden immediately');

		await wait(env, 200);
		assert.isFalse(tooltipVisible(env));
		assert.isTrue(tooltipExists(env), 'a plain mouseleave hide does not remove the element');
	});

	it('mouseleave onto the tooltip itself does not hide it', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement;

		fireMouse(env, elem, 'mouseleave', {relatedTarget: div});
		await wait(env, 250);
		assert.isTrue(tooltipVisible(env));
	});

	it('hovering into the tooltip div itself hides it (removing it) when hideonhover is true (the default)', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement;

		fireMouse(env, div, 'mouseenter');
		assert.isFalse(tooltipExists(env));
	});

	it('hovering into the tooltip div does NOT hide it when hideonhover is false', async() =>
	{
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip', false, {hideonhover: false});
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		const div = env.window.document.querySelector('.egw_tooltip') as HTMLElement;

		fireMouse(env, div, 'mouseenter');
		assert.isTrue(tooltipVisible(env));
	});

	it('calls options.open() when the tooltip is shown', async() =>
	{
		let opened = false;
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip', false, {
			open: () => { opened = true; }
		});
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isTrue(opened);
	});

	it('calls options.close() on mouseleave before hiding', async() =>
	{
		let closed = false;
		env.egw('testapp', env.window).tooltipBind(elem, 'Hello tooltip', false, {
			close: () => { closed = true; }
		});
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);

		fireMouse(env, elem, 'mouseleave', {relatedTarget: env.window.document.body});
		await wait(env, 250);
		assert.isTrue(closed);
	});

	it('tooltipUnbind() removes a currently-shown tooltip and future mouseenter is a no-op', async() =>
	{
		const instance = env.egw('testapp', env.window);
		instance.tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isTrue(tooltipVisible(env));

		instance.tooltipUnbind(elem);
		assert.isFalse(tooltipExists(env));

		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isFalse(tooltipExists(env), 'listeners were removed, so no new tooltip appears');
	});

	it('re-binding the same element replaces the previous tooltip content', async() =>
	{
		const instance = env.egw('testapp', env.window);
		instance.tooltipBind(elem, 'first');
		instance.tooltipBind(elem, 'second');

		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.equal(tooltipText(env), 'second');
	});

	it('tooltipCancel() hides (removes) a currently-shown tooltip', async() =>
	{
		const instance = env.egw('testapp', env.window);
		instance.tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isTrue(tooltipVisible(env));

		instance.tooltipCancel();
		assert.isFalse(tooltipExists(env));
	});

	it('a "pagehide" event on the window unbinds everything and removes the tooltip', async() =>
	{
		const instance = env.egw('testapp', env.window);
		instance.tooltipBind(elem, 'Hello tooltip');
		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isTrue(tooltipVisible(env));

		env.window.dispatchEvent(new (env.window as any).Event('pagehide'));
		assert.isFalse(tooltipExists(env));

		fireMouse(env, elem, 'mouseenter');
		await wait(env, 400);
		assert.isFalse(tooltipExists(env), 'listeners were removed by pagehide cleanup');
	});
});
