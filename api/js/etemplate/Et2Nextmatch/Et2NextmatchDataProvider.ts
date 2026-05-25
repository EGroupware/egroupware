import {Et2DatagridDataProvider, Et2DatagridPageResult, Et2DatagridRow} from "./Et2Datagrid.types";

interface Et2NextmatchProviderHost extends HTMLElement
{
	egw : Function;
	getInstanceManager : Function;
	id : string;
	getAttribute : (name : string) => string | null;
}

/**
 * Nextmatch server adapter for Et2Datagrid.
 * It wraps dataFetch + dataRegisterUID in a generic page provider API.
 */
export class Et2NextmatchDataProvider implements Et2DatagridDataProvider
{
	private host : Et2NextmatchProviderHost;

	/**
	 * Deterministically serialize nested values so equivalent filter objects produce the same signature string.
	 */
	private _stableSerialize(value : any) : string
	{
		if(value === null || typeof value !== "object")
		{
			return JSON.stringify(value);
		}
		if(Array.isArray(value))
		{
			return `[${value.map((item) => this._stableSerialize(item)).join(",")}]`;
		}
		const keys = Object.keys(value).sort();
		return `{${keys.map((key) => `${JSON.stringify(key)}:${this._stableSerialize(value[key])}`).join(",")}}`;
	}

	/**
	 * Read active nextmatch filters from host/controller for fetch and dedupe identity.
	 */
	private _currentFilters() : Record<string, any>
	{
		const hostAny = this.host as any;
		const filters = hostAny?.controller?._filters ?? hostAny?._filters ?? {};
		return filters && typeof filters === "object" ? filters : {};
	}

	/**
	 * @param host Nextmatch owner used to access egw data APIs and exec context.
	 */
	constructor(host : Et2NextmatchProviderHost)
	{
		this.host = host;
	}

	/**
	 * Signature of active query context used by datagrid request deduplication.
	 */
	getQuerySignature() : string
	{
		return this._stableSerialize(this._currentFilters());
	}

	getDataStorePrefix() : string
	{
		const app = this.host.getInstanceManager?.()?.app || this.host.egw?.()?.app_name?.();
		if(app)
		{
			return String(app);
		}
		return String(this.host.id || this.host.getAttribute("id") || "row");
	}

	/**
	 * Fetch one page of rows through Nextmatch APIs and return normalized datagrid rows.
	 * We preserve server order by resolving rows into an indexed array before emitting.
	 */
	async fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>
	{
		const execId = this.host.getInstanceManager?.()?.etemplate_exec_id || "";
		const widgetId = this.host.id || this.host.getAttribute("id") || "";
		const context = {prefix: this.getDataStorePrefix()};
		const filters = this._currentFilters();

		return await new Promise((resolve, reject) =>
		{
			try
			{
				this.host.egw().dataFetch(
					execId,
					{start, num_rows: pageSize},
					filters,
					widgetId,
					(resp : any) =>
					{
						if(!resp)
						{
							resolve({rows: [], total: null});
							return;
						}
						const order : string[] = Array.isArray(resp.order) ? resp.order : [];
						if(!order.length)
						{
							resolve({
								rows: [],
								total: typeof resp.total !== "undefined" ? resp.total : null
							});
							return;
						}

							const rowsByIndex : Array<Et2DatagridRow | null> = new Array(order.length).fill(null);
							let pending = order.length;
							order.forEach((uid, index) =>
							{
								// dataRegisterUID can return out-of-order; capture by original position.
								this.host.egw().dataRegisterUID(
								uid,
								(data : any, resolvedUid : string) =>
								{
									rowsByIndex[index] = {
										id: String(resolvedUid || uid),
										data: data || {}
									};
									pending--;
									if(pending <= 0)
									{
										resolve({
											rows: rowsByIndex.filter(Boolean) as Et2DatagridRow[],
											total: typeof resp.total !== "undefined" ? resp.total : null
										});
									}
								},
								this.host,
								execId,
								widgetId
							);
						});
					},
					context,
					null
				);
			}
			catch(e)
			{
				reject(e);
			}
		});
	}
}
