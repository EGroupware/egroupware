import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {Et2RowProvider} from "../Et2RowProvider";
import {et2_warnLegacyEventHandler, et2_warnOnce} from "../../Et2Widget/Et2Widget";
import "../../Et2Description/Et2Description";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	app_name: () => "calendar"
};

window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

describe("Et2Datagrid template handlers", () =>
{
	it("provides generic warning deduplication", () =>
	{
		const warnings : any[][] = [];
		const widget = {egw: () => ({debug: (...args : any[]) => warnings.push(args)})};

		assert.isTrue(et2_warnOnce(widget, "template-handler-test:generic-warning", "A generic warning"));
		assert.isFalse(et2_warnOnce(widget, "template-handler-test:generic-warning", "A generic warning"));
		assert.lengthOf(warnings, 1);
	});

	it("warns once for a legacy handler source", () =>
	{
		const warnings : any[][] = [];
		const widget = {egw: () => ({debug: (...args : any[]) => warnings.push(args)})};
		const source = "widget.openUniqueTemplateHandler(); return false;";

		et2_warnLegacyEventHandler(widget, source);
		et2_warnLegacyEventHandler(widget, source);
		et2_warnLegacyEventHandler(widget, "app.calendar.open");

		assert.lengthOf(warnings, 1);
		assert.equal(warnings[0][0], "warn");
	});

	it("keeps regular widget handlers off inline DOM attributes", () =>
	{
		const widget = document.createElement("et2-description") as any;
		widget.setAttribute("onclick", "widget.open(); return false;");
		widget.transformAttributes({onclick: "widget.open(); return false;"});

		assert.isFalse(widget.hasAttribute("onclick"));
		assert.isFunction(widget.onclick);
	});

	it("defers row-scoped widget handlers without retaining inline source", () =>
	{
		const widget = document.createElement("et2-description") as any;
		widget.setAttribute("onclick", "widget.open('$row_cont[id]');");
		widget.setArrayMgr("content", {
			getPerspectiveData: () => ({row: null}),
			getEntry: () => null
		});
		widget.transformAttributes({onclick: "widget.open('$row_cont[id]');"});

		assert.equal(widget.deferredProperties.onclick, "widget.open('$row_cont[id]');");
		assert.isFalse(widget.hasAttribute("onclick"));
	});

	it("delegates row handlers without retaining inline event attributes", async() =>
	{
		const grid = new Et2Datagrid();
		const provider = new Et2RowProvider(grid);
		const row = document.createElement("row");
		row.innerHTML = '<et2-description onclick="widget.open(); return false;"></et2-description>';
		const prepared = await (provider as any)._prepareRowTemplate(row, []);
		const widget = prepared.template.content.querySelector("et2-description") as HTMLElement;
		const id = widget.getAttribute("data-et2nm-id")!;

		assert.isFalse(widget.hasAttribute("onclick"));
		assert.equal(prepared.handlerMap[id].onclick, "widget.open(); return false;");

		let calls = 0;
		(widget as any).open = () =>
		{
			calls++;
			return false;
		};
		grid.templateData = {
			columns: [],
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateHandlerMap: prepared.handlerMap,
			loaderTemplate: null
		};
		document.body.appendChild(grid);
		grid.appendChild(widget);
		const secondWidget = document.createElement("et2-description") as any;
		secondWidget.setAttribute("data-et2nm-id", id);
		secondWidget.open = () => calls++;
		grid.appendChild(secondWidget);
		await grid.updateComplete;

		const event = new MouseEvent("click", {bubbles: true, composed: true, cancelable: true});
		widget.dispatchEvent(event);
		secondWidget.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true, cancelable: true}));

		assert.equal(calls, 2);
		assert.isTrue(event.defaultPrevented);
		assert.equal((grid as any)._templateHandlerListeners.size, 1);
		assert.equal((grid as any)._templateHandlerCache.size, 1);
		grid.remove();
	});

	it("bubbles delegated handlers through nested row widgets", async() =>
	{
		const grid = new Et2Datagrid();
		const box = document.createElement("div") as any;
		const child = document.createElement("div") as any;
		box.setAttribute("data-et2nm-id", "et2nm-box");
		box.setAttribute("data-row-id", "test-row");
		child.setAttribute("data-et2nm-id", "et2nm-child");
		box.appendChild(child);
		const calls : string[] = [];
		box.run = () => calls.push("box");
		child.run = () => calls.push("child");
		grid.templateData = {
			columns: [],
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			rowTemplateHandlerMap: {
				"et2nm-box": {onclick: "widget.run();"},
				"et2nm-child": {onclick: "widget.run();"}
			},
			loaderTemplate: null
		};
		document.body.appendChild(grid);
		grid.appendChild(box);
		await grid.updateComplete;

		child.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));

		assert.deepEqual(calls, ["child", "box"]);
		grid.remove();
	});

	it("calls direct app references with the EgwApp instance as this", async() =>
	{
		const grid = new Et2Datagrid();
		const widget = document.createElement("et2-description") as any;
		widget.setAttribute("data-et2nm-id", "et2nm-1");
		const calendarApp = {
			calledWithThis: null as any,
			open(event : Event, target : HTMLElement)
			{
				this.calledWithThis = this;
				assert.instanceOf(event, Event);
				assert.equal(target, widget);
			}
		};
		widget.getInstanceManager = () => ({app_obj: {calendar: calendarApp}});
		widget.egw = () => ({...egw, window});
		grid.templateData = {
			columns: [],
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			rowTemplateHandlerMap: {"et2nm-1": {onclick: "app.calendar.open"}},
			loaderTemplate: null
		};
		document.body.appendChild(grid);
		grid.appendChild(widget);
		await grid.updateComplete;

		widget.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));

		assert.equal(calendarApp.calledWithThis, calendarApp);
		grid.remove();
	});
});
