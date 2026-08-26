import {
	Et2DatagridDataProvider,
	Et2DatagridPageResult,
	Et2DatagridRefreshResult,
	Et2DatagridRow,
	Et2DatagridUpdateType
} from "../Et2Datagrid/Et2Datagrid.types";
import type {ReactiveController} from "lit";
import {Et2Nextmatch} from "./Et2Nextmatch";
import {IegwData} from "../../jsapi/egw_global";

/**
 * Nextmatch server adapter for Et2Datagrid.
 * It wraps dataFetch + dataRegisterUID in a generic page provider API.
 */
export class Et2NextmatchDataProvider implements Et2DatagridDataProvider, ReactiveController
{
	private host : Et2Nextmatch;
	/**
	 * No-op UID listeners keeping a row's central egw-cache entry alive (immune to its 5min
	 * idle eviction) for as long as the row belongs to this query - covers both preloaded rows
	 * (storeRows()) and rows added/updated via a single-row refresh (_refreshSingleRow()).
	 */
	private _initialRowRegistrations : Map<string, Function> = new Map();
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
		// Optional chaining because some tests construct this against a bare mock
		// object rather than a real (LitElement-backed) Et2Nextmatch.
		host.addController?.(this);
	}

	/**
	 * No-op - fetching/caching is entirely on-demand, triggered by page/refresh
	 * requests rather than host connection.
	 */
	hostConnected() : void
	{
	}

	hostDisconnected() : void
	{
		this.clearInitialRowRegistrations();
	}

	/** Release the UID listeners used to retain this query's rows (preloaded or refreshed-in). */
	clearInitialRowRegistrations() : void
	{
		const egw = this.host.egw();
		for(const [uid, callback] of this._initialRowRegistrations)
		{
			egw.dataUnregisterUID?.(uid, callback, this.host);
		}
		this._initialRowRegistrations.clear();
	}

	/**
	 * Set while an explicit hard reload (Et2Nextmatch.refresh() with no row ids) is in
	 * flight, so every page fetched during that reload tells the server it knows nothing
	 * - see fetchPage()'s use of this flag below.
	 */
	private _forceFreshKnownUids = false;

	/**
	 * Toggle "pretend the client has no cached rows" for page fetches.
	 *
	 * fetchPage() normally passes `null` as dataFetch()'s knownUids, and egw_data.ts's
	 * dataFetch() falls back to egw.dataKnownUIDs(prefix) - which scans the shared,
	 * otherwise never-cleared cache and finds this query's previously-loaded rows still
	 * sitting in it. The server then legitimately omits their data as "unchanged since
	 * last known" - correct for a normal scroll/page fetch, but wrong for an explicit
	 * reload where the UI just cleared its own rows and has nothing to fall back on.
	 * Callers must turn this back off once the reload's fetch(es) have settled.
	 */
	setForceFreshKnownUids(force : boolean) : void
	{
		this._forceFreshKnownUids = force;
	}

	/**
	 * Process additional data Nextmatch sent such as new SelectOptions or flags.
	 *
	 * Also used by Et2Nextmatch for the same scalars when they arrive mixed into the
	 * initial `rows` attribute instead of a refresh response.
	 */
	processAdditionalData(additionalData)
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
			getRowData(rowId : string) : any
			{
				return provider.getRowData(rowId);
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
		// Use dataStorePrefix setting or fall back to the nextmatch's own app
		const configured = (this.host as any)?.settings?.dataStorePrefix;
		if(configured)
		{
			return String(configured);
		}
		return String(this.host.getInstanceManager?.()?.app || this.host.egw?.()?.app_name?.() || "");
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
	 * Resolve the configured application row-id field, defaulting consistently to `id`.
	 */
	private _rowIdField() : string
	{
		return String((this.host as any)?.settings?.row_id || "id").trim() || "id";
	}

	/**
	 * Resolve the canonical datagrid row id.
	 *
	 * Internally the datagrid, datastore and actions all use prefixed datastore
	 * UIDs. If the configured row id is missing from row data, fall back to the
	 * resolved datastore UID so one bad row cannot collapse a page into duplicate
	 * empty ids.
	 */
	private _rowIdFromData(rowData : Record<string, any> | null | undefined, fallbackUid : string) : string
	{
		const rowIdField = this._rowIdField();
		if(rowData && Object.prototype.hasOwnProperty.call(rowData, rowIdField))
		{
			const rowId = rowData[rowIdField];
			if(rowId !== undefined && rowId !== null && String(rowId) !== "")
			{
				return this.normalizeRowId(rowId, true);
			}
		}
		return this.normalizeRowId(fallbackUid, true);
	}

	/**
	 * Resolve the canonical datagrid/action row id for already-available row data.
	 */
	rowIdForData(rowData : Record<string, any> | null | undefined, fallbackIndex : string | number = "") : string
	{
		return this._rowIdFromData(rowData, String(fallbackIndex));
	}

	/**
	 * Store already-available row data in egw's UID cache using the same row-id
	 * normalization as fetched rows.  This preserves the Nextmatch
	 * contract that visible rows are discoverable through egw.dataKnownUIDs().
	 */
	storeRows(rows : any[], skipCallback : boolean = false) : void
	{
		const egw = this.host.egw();
		if(typeof egw?.dataStoreUID !== "function")
		{
			return;
		}
		const {execId, widgetId} = this._requestContext();
		(rows || []).forEach((row, index) =>
		{
			if(!row || typeof row !== "object")
			{
				return;
			}
			const uid = this._rowIdFromData(row, String(index));
			if(uid)
			{
				egw.dataStoreUID(uid, row, skipCallback);
				// Initial rows are supplied directly rather than through fetchPage(),
				// so they otherwise have no UID registration. The global store expires
				// unregistered entries after five minutes while the virtualizer still
				// retains their ids. A no-op registration gives them the same lifetime
				// as fetched rows without duplicating row data in another cache.
				if(!this._initialRowRegistrations.has(uid))
				{
					const callback = () => {};
					this._initialRowRegistrations.set(uid, callback);
					egw.dataRegisterUID?.(uid, callback, this.host, execId, widgetId);
				}
			}
		});
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
		const rowData = this.getRowData(rowId);
		if(!rowData)
		{
			return null;
		}
		return {
			id: this._rowIdFromData(rowData, rowId)
		};
	}

	/**
	 * Resolve canonical row data from egw's UID cache.
	 */
	getRowData(rowId : string) : any
	{
		const normalizedId = this.normalizeRowId(rowId, true);
		const cached = this.host.egw().dataGetUIDdata?.(normalizedId) as IegwData | undefined;
		return cached?.data ?? null;
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
				const fetchPromise = this.host.egw().dataFetch(
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
							this.processAdditionalData(response.rows);
						}

						// Row payload is already stored in the central egw cache by dataFetch().
						const refreshedRow = this._cachedRow(normalizedId);
						const rowExists = typeof response?.total === "number" ? response.total >= 1 : !!refreshedRow;
						if(rowExists && refreshedRow && !this._initialRowRegistrations.has(normalizedId))
						{
							// Unlike a normal page fetch (fetchPage(), which registers a keep-alive
							// listener per row via egw.dataRegisterUID() - see storeRows()), this path
							// only ever calls egw.dataStoreUID() once, indirectly, inside dataFetch()'s
							// response parsing. Without a registered listener, the central egw cache's
							// periodic cleanup sweep (api/js/jsapi/egw_data.ts, 5min idle/no-listener)
							// evicts the row after 5 minutes - harmless while it's still displayed and
							// gets refreshed again, but a row added/updated via a push held back while
							// this grid wasn't visible (see Et2Datagrid's virtualizer, which renders
							// nothing while hidden) can easily sit that long before ever being rendered,
							// so it would render with no data (bare avatar, blank subject/date) the
							// first time it finally does. Give it the same keep-alive registration as
							// any other row so its data survives until actually rendered.
							const keepAlive = () => {};
							this._initialRowRegistrations.set(normalizedId, keepAlive);
							this.host.egw().dataRegisterUID?.(normalizedId, keepAlive, this.host, execId, widgetId);
						}
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
				// dataFetch() rejects if the underlying request failed - without this, a failed
				// request never calls the success callback above, leaving this promise hanging
				// forever: the .finally() below never runs, so this row stays stuck in
				// _inFlightRefreshes and never refreshes again.
				fetchPromise?.catch(reject);
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
	fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult> & { abort? : () => void }
	{
		return this._fetchPageWithRange(start, pageSize);
	}

	/**
	 * Fetch a page of rows with optional Nextmatch range fields such as `parent_id`.
	 */
	private _fetchPageWithRange(
		start : number,
		pageSize : number,
		rangeOverrides : Record<string, any> = {}
	) : Promise<Et2DatagridPageResult> & { abort? : () => void }
	{
		const {execId, widgetId, filters} = this._requestContext();
		const context = {prefix: this.getDataStorePrefix()};
		const request = {
			start,
			num_rows: pageSize,
			...rangeOverrides
		};

		// Not async/await here: an async function always adopts a returned thenable's
		// *resolution* into a brand-new Promise object, discarding any extra property
		// (like the .abort attached below) from what was actually returned. Both this
		// method and fetchPage() must return the exact same Promise object all the way
		// out for a caller (Et2Datagrid._fetchPage()) to be able to abort the request.
		let fetchPromise : any;
		const resultPromise : any = new Promise((resolve, reject) =>
		{
			try
			{
				fetchPromise = this.host.egw().dataFetch(
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
						this.processAdditionalData(resp.rows || {});

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
									const rowId = this._rowIdFromData(rowData, String(resolvedUid || uid));
									this.host.egw().dataStoreUID?.(rowId, rowData, true);
									rowsByIndex[index] = {
										id: rowId
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
					this._forceFreshKnownUids ? [] : null
				);
				// dataFetch() rejects if the underlying request failed (network error, no
				// response, ...) - without this, a failed request never calls the success
				// callback above, leaving this promise - and the datagrid's in-flight tracking
				// for this page - hanging forever, so a later refresh/retry sees the range as
				// still "in flight" and skips it.
				fetchPromise?.catch(reject);
			}
			catch(e)
			{
				reject(e);
			}
		});
		if(typeof fetchPromise?.abort === "function")
		{
			resultPromise.abort = () => fetchPromise.abort();
		}
		return resultPromise;
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
