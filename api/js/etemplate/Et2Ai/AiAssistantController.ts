import type {ReactiveController, ReactiveControllerHost} from "lit";

export type AiStatus = "idle" | "loading" | "success" | "error";

/**
 * Controller to manage asking the AI questions
 */
export class AiAssistantController implements ReactiveController
{
	readonly host : ReactiveControllerHost;

	status : AiStatus = "idle";
	result : string | Object = "";
	error = "";

	private request? : Promise<any> & { abort? : () => void };

	constructor(host : ReactiveControllerHost)
	{
		this.host = host;
		host.addController(this);
	}

	async run(prompt : string, context : string, endpoint? : string)
	{
		// Abort any in-flight request
		if(this.request?.abort)
		{
			this.request.abort();
		}

		this.status = "loading";
		this.result = "";
		this.error = "";
		this.host.requestUpdate();

		try
		{
			const req = (this.host.egw() ?? egw).request(
				endpoint ?? "aiassistant.EGroupware\\AIAssistant\\Ui.ajax_api",
				["process_prompt", prompt, context]
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

	abort()
	{
		this.request?.abort?.();
	}

	hostDisconnected()
	{
		this.abort();
	}
}
