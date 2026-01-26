import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import type {ReactiveControllerHost} from "lit";
import {AiAssistantController} from "../AiAssistantController";

describe("AiAssistantController", () =>
{
	let host : ReactiveControllerHost & {
		egw : sinon.SinonStub;
		requestUpdate : sinon.SinonSpy;
	};

	let requestStub : sinon.SinonStub;

	beforeEach(() =>
	{
		requestStub = sinon.stub();

		host = {
			addController: sinon.spy(),
			requestUpdate: sinon.spy(),
			egw: sinon.stub().returns({
				request: requestStub
			})
		} as any;

		// Reset static state between tests
		AiAssistantController.API_OK = null;
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	it("registers itself with the host", async() =>
	{
		requestStub.resolves({success: true, result: ""});

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		assert.isTrue(
			(host.addController as sinon.SinonSpy).calledWith(controller),
			"Controller should register with host"
		);
	});

	it("runs a request and sets success state", async() =>
	{
		requestStub.resolves({
			success: true,
			result: "hello",
			error: ""
		});

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		await controller.run("prompt", "context");

		assert.equal(controller.status, "success");
		assert.equal(controller.result, "hello");
		assert.equal(controller.error, "");
		assert.isTrue(host.requestUpdate.called);
	});

	it("sets error state when API returns success=false", async() =>
	{
		requestStub.resolves({
			success: false,
			result: "",
			error: "Nope"
		});

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		await controller.run("prompt", "context");

		assert.equal(controller.status, "error");
		assert.equal(controller.error, "Nope");
	});

	it("handles thrown errors", async() =>
	{
		requestStub.rejects(new Error("API not configured"));

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		await controller.run("prompt", "context");

		assert.equal(controller.status, "error");
		assert.equal(controller.error, "API not configured");
	});

	it("ignores abort errors", async() =>
	{
		requestStub.rejects({name: "AbortError"});

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		await controller.run("prompt", "context");

		assert.notEqual(controller.status, "error");
	});

	it("aborts in-flight requests before starting a new one", async() =>
	{
		const abortSpy = sinon.spy();

		const firstRequest : any = new Promise(() => {});
		firstRequest.abort = abortSpy;

		requestStub
			.onFirstCall().returns(firstRequest)
			.onSecondCall().resolves({success: true, result: ""});

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";

		controller.run("first", "");
		await controller.run("second", "");

		assert.isTrue(abortSpy.calledOnce, "Previous request should be aborted");
	});

	it("AiAssistantController sets unavailable when endpoint is missing", async() =>
	{
		requestStub.resolves({
			success: false,
			result: "",
			error: "Missing API"
		});

		const controller = new AiAssistantController(host);
		await controller.run("test", "");

		assert.equal(AiAssistantController.API_OK, false);
		assert.equal(controller.status, "unavailable");
	});

	it("aborts request on hostDisconnected", async() =>
	{
		const abortSpy = sinon.spy();
		const req : any = new Promise(() => {});
		req.abort = abortSpy;

		requestStub.returns(req);

		const controller = new AiAssistantController(host);
		controller.endpoint = "test";
		controller.run("prompt", "");

		controller.hostDisconnected();

		assert.isTrue(abortSpy.calledOnce);
	});
});
