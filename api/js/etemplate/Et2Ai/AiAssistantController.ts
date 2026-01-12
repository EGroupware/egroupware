import type {ReactiveController, ReactiveControllerHost} from "lit";

export type AiStatus = "idle" | "loading" | "success" | "error" | "unavailable";

/**
 * Controller to manage asking the AI questions
 */
export class AiAssistantController implements ReactiveController
{
	readonly host : ReactiveControllerHost;
	static API_OK : boolean | null = null;

	status : AiStatus = "idle";
	result : string | Object = "";
	error = "";

	private request? : Promise<any> & { abort? : () => void };

	constructor(host : ReactiveControllerHost)
	{
		this.host = host;
		host.addController(this);
	}

	async run(prompt : string, context : string, endpoint? : string, action : string = "process_prompt")
	{
		// Abort any in-flight request
		if(this.request?.abort)
		{
			this.request.abort();
		}
		if(AiAssistantController.API_OK === false)
		{
			this.status = "unavailable";
			this.host.requestUpdate();
			return;
		}
		this.status = "loading";
		this.result = "";
		this.error = "";
		this.host.requestUpdate();

		try
		{
			// @ts-ignore
			const req = (this.host.egw() ?? egw).request(
				endpoint ?? "aiassistant.EGroupware\\AIAssistant\\Ui.ajax_api",
				[action, prompt, context]
			);

			this.request = req;
			const result = await req;
			this.result = result.result ?? "";
			this.error = result.error ?? "";
			this.status = result.success ? "success" : "error";
		}
		catch(err)
		{
			// egw.request rejects on abort; ignore those
			if(err?.name === "AbortError" || err === "abort")
			{
				return;
			}

			this.error = err?.message ?? String(err);
			this.status = "error";
		}
		finally
		{
			this.host.requestUpdate();
		}
	}

	/**
	 * Check that the API is available by running a test query.
	 * @return {Promise<boolean>}
	 */
	async testAPI(forceCheck = false)
	{
		if(AiAssistantController.API_OK !== null && !forceCheck)
		{
			return AiAssistantController.API_OK;
		}
		await this.run("", "", undefined, "test_api");
		if(this.status !== "success")
		{
			this.status = "unavailable";
			AiAssistantController.API_OK = false;
			console.warn("AI Assistant API is not available" + (this.error ? ": " + this.error : ""));
			return false;
		}
		AiAssistantController.API_OK = true;
		this.status = "idle";
		this.host.requestUpdate();
		return AiAssistantController.API_OK;
	}

	abort()
	{
		this.request?.abort?.();
	}

	hostConnected()
	{
		this.testAPI();
	}

	hostDisconnected()
	{
		this.abort();
	}
}
