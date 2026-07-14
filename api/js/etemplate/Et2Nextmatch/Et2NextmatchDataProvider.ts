import {
	Et2DatagridDataProvider,
	Et2DatagridPageResult,
	Et2DatagridRefreshResult,
	Et2DatagridRow,
	Et2DatagridUpdateType
} from "./Et2Datagrid.types";
import {Et2Nextmatch} from "./Et2Nextmatch";
import {IegwData} from "../../jsapi/egw_global";

/**
 * Nextmatch server adapter for Et2Datagrid.
 * It wraps dataFetch + dataRegisterUID in a generic page provider API.
 */
export class Et2NextmatchDataProvider implements Et2DatagridDataProvider
{
	private host : Et2Nextmatch;
	/** Tracks one in-flight refresh promise per normalized row id so concurrent callers share one server request. */
	private _inFlightRefreshes : Map<string, Promise<Et2DatagridRefreshResult>> = new Map();

	/**
	 * Build the current server request context shared by page and refresh calls.
	 */
	private _requestContext()
	{
		return {
			execId: this.host.getInstanceManager?.()?.etemplate_exec_id || "",
			widgetId: this.host.id || this.host.getAttribute("id") || "",
			filters: this._currentFilters()
		};
	}

	/**
	 * Normalize arbitrary refresh ids once at the provider boundary so fetch/dedupe/cache all
	 * operate on the same datastore uid format.
	 */
	private _normalizeRefreshRowIds(rowIds : string[]) : string[]
	{
		return Array.from(new Set((rowIds || []).map((rowId) => this.normalizeRowId(rowId, true)).filter(Boolean)));
	}

	/**
	 * Collapse per-row refresh responses into one datagrid result.
	 *
	 * Row data wins over removals for the same id because the final server state is "row exists".
	 */
	private _mergeRefreshResults(results : Et2DatagridRefreshResult[]) : Et2DatagridRefreshResult
	{
		const rowsById = new Map<string, Et2DatagridRow>();
		const removedRowIds = new Set<string>();
		for(const result of results)
		{
			for(const row of result.rows)
			{
				rowsById.set(row.id, row);
				removedRowIds.delete(row.id);
			}
			for(const rowId of result.removedRowIds)
			{
				if(!rowsById.has(rowId))
				{
					removedRowIds.add(rowId);
				}
			}
		}

		return {
			rows: Array.from(rowsById.values()),
			removedRowIds: Array.from(removedRowIds)
		};
	}

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
	constructor(host : Et2Nextmatch)
	{
		this.host = host;
	}

	/**
	 * Process additional data Nextmatch sent such as new SelectOptions or flags.
	 *
	 * @private
	 */
	private _processAdditionalData(additionalData)
	{
		for(let i in additionalData)
		{
			if(!i || /^\d+$/.test(i))
			{
				continue;
			}
			// Select options
			if(i == 'sel_options')
			{
				const mgr = this.host.getArrayMgr(i);
				let app_toolbar = this.host.closest('egw-app')?.querySelector('[slot="main-header"]') as any;
				if(app_toolbar && app_toolbar.localName != "et2-template")
				{
					app_toolbar = app_toolbar?.querySelector("et2-template");
				}
				for(const id in additionalData.sel_options)
				{
					mgr.data[id] = additionalData.sel_options[id];
					var select = this.host.getWidgetById(id);
					if(select && select.set_select_options)
					{
						select.set_select_options(additionalData.sel_options[id]);
					}
					// Clear rowProvider internal cache so it uses new values
					/*if(id == 'cat_id')
					{
						this.self._rowProvider.categories = null;
					}*/
					// update array mgr so select widgets in row also get refreshed options
					this.host.getParent().getArrayMgr('sel_options').data[id] = additionalData.sel_options[id];
					// update filterbox, app-toolbar widgets
					[(this.host as any)._filterbox?.getWidgetById?.(id), app_toolbar?.getWidgetById?.(id)].forEach(widget =>
					{
						if(!widget)
						{
							return;
						}
						widget.set_select_options(additionalData.sel_options[id]);
						widget.value = widget.value;	// not sure why this is necessary
					});
				}
			}
			// Sort order
			else if(i === "order" && additionalData[i] !== this.host.activeFilters.order)
			{
				this.host.sortBy(additionalData[i], undefined, false);
			}
			// Filter values
			else
			{
				const mgr = this.host.getArrayMgr('content');
				mgr.data[i] = additionalData[i];

				// It's not enough to just update the data, the widgets need to
				// be updated too, if there are matching widgets.
				const widget = this.host.getWidgetById(i);
				if(widget && widget.set_value)
				{
					widget.set_value(mgr.getEntry(i));
				}
			}
		}
	}

	/**
	 * Signature of active query context used by datagrid request deduplication.
	 */
	getQuerySignature() : string
	{
		return this._stableSerialize(this._currentFilters());
	}

	/**
	 * Create a provider for a nested child grid under one parent row.
	 *
	 * Child providers reuse the same row-id normalization and refresh path as the
	 * root provider, but add `parent_id` to page fetches and query signatures.
	 */
	createChildProvider(parentRowId : string) : Et2DatagridDataProvider
	{
		const provider = this;
		const parentProviderRowId = this.toProviderRowId(String(parentRowId || ""));
		return {
			fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>
			{
				return provider._fetchPageWithRange(start, pageSize, {parent_id: parentProviderRowId});
			},
			getQuerySignature() : string
			{
				return provider._stableSerialize({
					filters: provider._currentFilters(),
					parent_id: parentProviderRowId
				});
			},
			getDataStorePrefix() : string
			{
				return provider.getDataStorePrefix();
			},
			normalizeRowId(rowId : string | number, ensurePrefix : boolean = false) : string
			{
				return provider.normalizeRowId(rowId, ensurePrefix);
			},
			toProviderRowId(dataStoreRowId : string) : string
			{
				return provider.toProviderRowId(dataStoreRowId);
			},
			refresh(rowIds : string[], type : Et2DatagridUpdateType) : Promise<Et2DatagridRefreshResult>
			{
				return provider.refresh(rowIds, type);
			}
		};
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
	 * Normalize arbitrary row identifiers to datastore uid format.
	 */
	normalizeRowId(rowId : string | number, ensurePrefix : boolean = false) : string
	{
		const normalized = String(rowId ?? "");
		if(!ensurePrefix || !normalized)
		{
			return normalized;
		}
		const prefix = `${this.getDataStorePrefix()}::`;
		return normalized.startsWith(prefix) ? normalized : `${prefix}${normalized}`;
	}

	/**
	 * Resolve the datagrid row id from row data using legacy Nextmatch settings,
	 * defaulting to the `id` field.
	 */
	private _rowIdFromData(rowData : Record<string, any> | null | undefined) : string
	{
		const rowIdField = String((this.host as any)?.settings?.row_id || "id").trim() || "id";
		if(rowIdField && rowData && Object.prototype.hasOwnProperty.call(rowData, rowIdField))
		{
			const rowId = rowData[rowIdField];
			if(rowId !== undefined && rowId !== null && String(rowId) !== "")
			{
				return this.normalizeRowId(rowId, true);
			}
		}
		return "";
	}

	/**
	 * Strip the datastore prefix from a row id to recover the bare provider/server id.
	 */
	toProviderRowId(dataStoreRowId : string) : string
	{
		const normalized = String(dataStoreRowId || "");
		const prefix = `${this.getDataStorePrefix()}::`;
		return normalized.startsWith(prefix) ? normalized.slice(prefix.length) : normalized;
	}

	/**
	 * Read one row from the egw UID cache.
	 *
	 * Refresh reads its final row payload back from the central cache after `egw.dataFetch()`
	 * updates it, instead of rebuilding row payloads in the provider.
	 */
	private _cachedRow(rowId : string) : Et2DatagridRow | null
	{
		const cached = this.host.egw().dataGetUIDdata?.(rowId) as IegwData | undefined;
		if(!cached?.data)
		{
			return null;
		}
		return {
			id: this._rowIdFromData(cached.data),
			data: cached.data
		};
	}

	/**
	 * Refresh exactly one row.
	 *
	 * The flow is:
	 * 1. Reuse any in-flight refresh promise for the same row.
	 * 2. Otherwise fetch the row once from the server.
	 * 3. Read the final row data back from the egw cache populated by `egw.dataFetch()`.
	 */
	private async _refreshSingleRow(
		rowId : string,
		type : Et2DatagridUpdateType,
		execId : string,
		widgetId : string,
		filters : Record<string, any>
	) : Promise<Et2DatagridRefreshResult>
	{
		const normalizedId = this.normalizeRowId(rowId, true);
		const existingRefresh = this._inFlightRefreshes.get(normalizedId);
		if(existingRefresh)
		{
			// Concurrent callers for the same row should observe the same result.
			return existingRefresh;
		}

		const bareRowId = this.toProviderRowId(normalizedId);
		const refreshPromise = new Promise<Et2DatagridRefreshResult>((resolve, reject) =>
		{
			try
			{
				this.host.egw().dataFetch(
					execId,
					{refresh: [bareRowId]},
					filters,
					widgetId,
					(response : any) =>
					{
						if(!this.host.getParent())
						{
							// The owning template was torn down while the request was in flight.
							resolve({rows: [], removedRowIds: []});
							return;
						}
						if(response?.rows)
						{
							// Nextmatch may piggyback select options / filter state on refresh responses too.
							this._processAdditionalData(response.rows);
						}

						// Row payload is already stored in the central egw cache by dataFetch().
						const refreshedRow = this._cachedRow(normalizedId);
						const rowExists = typeof response?.total === "number" ? response.total >= 1 : !!refreshedRow;
						resolve(rowExists && refreshedRow ? {
							rows: [refreshedRow],
							removedRowIds: []
						} : {
							rows: [],
							removedRowIds: [normalizedId]
						});
					},
					{type, prefix: this.getDataStorePrefix()},
					[bareRowId]
				);
			}
			catch(e)
			{
				reject(e);
			}
		}).finally(() =>
		{
			// Only suppress duplicates while the request is active; later refreshes should fetch again.
			this._inFlightRefreshes.delete(normalizedId);
		});

		// Store before returning so near-simultaneous callers can join the same promise.
		this._inFlightRefreshes.set(normalizedId, refreshPromise);
		return refreshPromise;
	}

	/**
	 * Fetch one page of rows through Nextmatch APIs and return normalized datagrid rows.
	 * We preserve server order by resolving rows into an indexed array before emitting.
	 */
	async fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>
	{
		return this._fetchPageWithRange(start, pageSize);
	}

	/**
	 * Fetch a page of rows with optional Nextmatch range fields such as `parent_id`.
	 */
	private async _fetchPageWithRange(
		start : number,
		pageSize : number,
		rangeOverrides : Record<string, any> = {}
	) : Promise<Et2DatagridPageResult>
	{
		const {execId, widgetId, filters} = this._requestContext();
		const context = {prefix: this.getDataStorePrefix()};
		const request = {
			start,
			num_rows: pageSize,
			...rangeOverrides
		};

		return await new Promise((resolve, reject) =>
		{
			try
			{
				this.host.egw().dataFetch(
					execId,
					request,
					filters,
					widgetId,
					(resp : any) =>
					{
						if(!resp)
						{
							resolve({rows: [], total: null});
							return;
						}
						// Extra data from nextmatch
						this._processAdditionalData(resp.rows || {});

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
									const rowData = data || {};
									rowsByIndex[index] = {
										id: this._rowIdFromData(rowData),
										data: rowData
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

	/**
	 * Refresh one or more rows and return normalized row updates/removals for the datagrid.
	 *
	 * The provider does not decide where refreshed rows belong in the visible grid; it only resolves
	 * the latest row data and whether a row still exists.
	 */
	async refresh(row_ids : string[], type : Et2DatagridUpdateType) : Promise<Et2DatagridRefreshResult>
	{
		const {execId, widgetId, filters} = this._requestContext();
		const normalizedIds = this._normalizeRefreshRowIds(row_ids);
		if(!normalizedIds.length)
		{
			return {rows: [], removedRowIds: []};
		}

		const results = await Promise.all(
			normalizedIds.map((rowId) => this._refreshSingleRow(rowId, type, execId, widgetId, filters))
		);
		return this._mergeRefreshResults(results);
	}
}
