/**
 * Test file for Etemplate webComponent base widget Et2Colorpicker
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2Colorpicker} from "../Et2Colorpicker";
import * as sinon from 'sinon';


describe("Colorpicker widget", () =>
{
	// Reference to component under test
	let element : Et2Colorpicker;
	let egw_stub;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Stub egw
		// @ts-ignore
		egw_stub = sinon.stub(Et2Colorpicker.prototype, "egw").returns({
			lang: i => i,
			tooltipUnbind: () => {}
		});

		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Colorpicker>(html`
            <et2-colorpicker></et2-colorpicker>
		`);
	});

	afterEach(() =>
	{
		egw_stub.restore();
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Colorpicker);
	});

	it('clearing value', () =>
	{
		// set a value
		element.set_value("11111");
		// trigger the clear button
		element.shadowRoot.querySelector(".input__clear").dispatchEvent(new MouseEvent('click'));

		assert.equal(element.getValue(), "");
	});

});