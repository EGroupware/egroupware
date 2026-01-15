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
	endpoint : string = "";

	private request? : Promise<any> & { abort? : () => void };

	constructor(host : ReactiveControllerHost, _endpoint? : string)
	{
		this.host = host;
		host.addController(this);
		this.endpoint = _endpoint;
	}

	async run(prompt : string, context : string, endpoint? : string, action : string = "process_prompt")
	{
		// Abort any in-flight request
		if(this.request?.abort)
		{
			this.request.abort();
		}
		if (!(endpoint || this.endpoint))
		{
			AiAssistantController.API_OK = false;
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
				endpoint || this.endpoint,
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

	abort()
	{
		this.request?.abort?.();
	}

	hostConnected()
	{

	}

	hostDisconnected()
	{
		this.abort();
	}
}