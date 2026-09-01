import type {Et2Datagrid, Et2DatagridCustomfieldColumnState} from "./Et2Datagrid";
import type {Et2DatagridRow} from "./Et2Datagrid.types";
import {Et2RowProvider} from "./Et2RowProvider";
import {et2_arrayMgr} from "../et2_core_arrayMgr";

/**
 * Builds row DOM (from either a compiled row template or the plain fallback),
 * applies row-scoped template attributes/customfield state after insertion,
 * and owns the frame-throttled upgrade queue and MutationObserver that drive
 * that post-insertion hydration pass.
 *
 * Typed against the concrete `Et2Datagrid` host (like
 * Et2DatagridColumnResizeController/Et2DatagridSelectionController) rather
 * than a narrow interface - this reaches into row storage, template/array-
 * manager plumbing, and row-focus state that all stay on the host, so a
 * narrow interface would just reproduce most of Et2Datagrid's surface for no
 * real benefit.
 *
 * Row expansion stays entirely on the host (see Et2Datagrid's
 * `_isRowExpandable`/`_isRowExpanded`/`_setRowExpanded`/`_syncRowExpander`) -
 * it's the most fragile, virtualizer-timing-dependent part of the file and is
 * deferred to its own pass rather than folded in here.
 */
export class Et2DatagridRowRenderer
{
	private host : Et2Datagrid;

	private _rowUpgradeQueue : HTMLElement[] = [];
	private _rowUpgradeObserver : MutationObserver | null = null;
	private _rowUpgradeObservedRowsBody : HTMLElement | null = null;
	private _rowUpgradeRangeListener : ((event : Event) => void) | null = null;
	private _rowUpgradeScheduled : boolean = false;
	private _rowUpgradeFrameHandle : number | null = null;
	/**
	 * Rows upgraded per frame, alongside the time budget below.
	 *
	 * Measured hydration is ~0.03ms/row, so this count - not the 8ms budget - is what
	 * actually governs, and it drips a viewport in over several frames (~4 frames for
	 * 30 rows) while those rows sit visibly in `.loading`. Raising it is a real
	 * time-to-settled win but NOT a safe standalone change: the drip is what currently
	 * makes the row-upgrade queue drain, refill, and drain again as the virtualizer
	 * mounts rows, so scheduleRowsUpgradedSettle() runs several times and the last
	 * measurement lands on fully laid-out rows. Draining in one frame yields a single
	 * early settle, and Et2Datagrid._updateMeasuredAverageRowHeight() then locks in a
	 * too-short row height with nothing left to correct it - measured here as a parent
	 * scroll range of 7035px against a 19931px baseline. Make that settle re-measure
	 * until stable first, then raise this.
	 */
	private _rowUpgradeBatchSize : number = 8;
	/** Per-frame time budget (ms) for row widget upgrades to avoid long tasks on the main thread. */
	private _rowUpgradeFrameBudgetMs : number = 8;
	private _rowWidgetsUpgradedFrame : number | null = null;
	private _rowWidgetsUpgradeSettling : boolean = false;

	private _refreshPulseTimersByElement : Map<HTMLElement, number> = new Map();
	private _refreshPulseDurationMs : number = 5000;

	private _customfieldColumnStateByKey : Map<string, Et2DatagridCustomfieldColumnState> = new Map();

	constructor(host : Et2Datagrid)
	{
		this.host = host;
	}

	/** Live reference to the pending-upgrade queue (tests inspect/mutate this directly). */
	get rowUpgradeQueue() : HTMLElement[]
	{
		return this._rowUpgradeQueue;
	}

	/** The rows container currently observed for upgrade/focus-recovery purposes. */
	get rowUpgradeObservedRowsBody() : HTMLElement | null
	{
		return this._rowUpgradeObservedRowsBody;
	}

	/** The live MutationObserver instance (tests disconnect this directly to isolate the rangeChanged listener path). */
	get rowUpgradeObserver() : MutationObserver | null
	{
		return this._rowUpgradeObserver;
	}

	/** Whether upgrade work (queued, scheduled, or settling) is still outstanding. */
	get hasPendingWork() : boolean
	{
		return this._rowUpgradeScheduled || this._rowUpgradeQueue.length > 0 || this._rowWidgetsUpgradeSettling;
	}

	/**
	 * Build one row element from prepared template data or fallback plain cells.
	 */
	buildRowElement(row : Et2DatagridRow, rowIndex : number) : HTMLElement | null
	{
		const template = this.host.templateData?.rowTemplate;
		const templateXml = this.host.templateData?.rowTemplateXml;
		const rowData = this.host._rowDataFor(row);

		// Simple row fallback
		if(!template && !templateXml)
		{
			const tr = document.createElement(this.host._isTileView() ? "div" : "tr");
			tr.setAttribute("part", `${tr.getAttribute("part") || ""} row`.trim());
			tr.innerHTML = this.host.columns
				.filter((column) => !this.host._isColumnHidden(column))
				.map((column) => this.host._isTileView()
				                 ? `<div>${String(this.host._getFieldValue(rowData, column.key) ?? "")}</div>`
				                 : `<td>${String(this.host._getFieldValue(rowData, column.key) ?? "")}</td>`)
				.join("");
			this.ensureMetaCell(tr, row, rowIndex);
			this.markRowElement(tr, row, rowIndex);
			if(!this.host._isTileView())
			{
				this.host._applyColumnLayoutToRowElement(tr);
			}
			return tr;
		}

		let fragment : DocumentFragment | null = null;
		if(template)
		{
			fragment = document.importNode(template.content, true);
		}
		else if(templateXml)
		{
			const templateNode = document.createElement("template");
			templateNode.content.appendChild(templateXml.cloneNode(true));
			fragment = templateNode.content.cloneNode(true) as DocumentFragment;
		}
		if(!fragment)
		{
			return null;
		}

		// Fast, simple replacements
		this.populateCloneWithRow(fragment, rowData);
		const root = (fragment.firstElementChild || null) as HTMLElement | null;
		if(!root)
		{
			return null;
		}
		this.populateRowRootAttributes(root, rowData);
		root.setAttribute("part", `${root.getAttribute("part") || ""} row`.trim());
		this.ensureMetaCell(root, row, rowIndex);
		root.classList.add("loading");
		this.markRowElement(root, row, rowIndex);
		if(!this.host._isTileView())
		{
			this.host._applyColumnLayoutToRowElement(root);
		}
		return root;
	}

	/**
	 * Ensure the leading metadata cell exists and contains the row expander when needed.
	 */
	private ensureMetaCell(rowElement : HTMLElement, row : Et2DatagridRow, rowIndex : number)
	{
		const metaSelector = this.host._isTileView() ? ":scope > [data-dg-meta-cell='1']" : ":scope > td[data-dg-meta-cell='1']";
		let metaCell = rowElement.querySelector(metaSelector) as HTMLTableCellElement | null;
		if(!metaCell)
		{
			metaCell = document.createElement(this.host._isTileView() ? "div" : "td") as HTMLTableCellElement;
			metaCell.setAttribute("data-dg-meta-cell", "1");
			metaCell.setAttribute("part", "row-meta");
			metaCell.setAttribute("aria-hidden", "true");
			rowElement.insertBefore(metaCell, rowElement.firstChild);
		}
		this.host._syncRowExpander(rowElement, metaCell, row, rowIndex);
		this.host.rowCustomizer?.({
			rowElement,
			rowData: this.host._rowDataFor(row),
			rowIndex,
			metaCell
		});
	}

	/**
	 * Stamp row-level accessibility and identity attributes.
	 */
	private markRowElement(rowElement : HTMLElement, row : Et2DatagridRow, rowIndex : number)
	{
		const dataStoreRowId = this.host._dataStoreRowIdFor(row.id ?? rowIndex);
		rowElement.classList.toggle("dg-row-active", row.id == this.host.activeRowId);
		// Set alongside aria-selected below, not just left for the next deferred _syncRowAccessibilityState() pass:
		// otherwise a row rebuilt by a render-version bump (eg. Et2Datagrid.refresh() applying an in-place update)mounts without its highlight for 1 frame, and visibly flashes it back in once that pass catches up.
		rowElement.classList.toggle("dg-row-selected", this.host.allSelected || this.host.selectedRowIds.has(row.id));
		rowElement.setAttribute("role", "row");
		rowElement.setAttribute("data-row-id", dataStoreRowId);
		rowElement.setAttribute("data-row-index", String(rowIndex));
		rowElement.setAttribute("aria-rowindex", String(rowIndex + 1));
		rowElement.setAttribute("aria-selected", this.host.selectedRowIds.has(row.id) ? "true" : "false");
		if(this.host.allSelected && !this.host.selectedRowIds.has(row.id))
		{
			rowElement.setAttribute("aria-selected", "true");
		}
		rowElement.tabIndex = rowIndex === this.host.activeRowIndex ? 0 : -1;
	}

	/**
	 * Clear refresh pulse timers tied to physical row elements.
	 */
	clearRefreshPulseTimers()
	{
		for(const timerId of this._refreshPulseTimersByElement.values())
		{
			window.clearTimeout(timerId);
		}
		this._refreshPulseTimersByElement.clear();
	}

	/**
	 * Pulse only the rows that are currently rendered after a refresh merge completes.
	 *
	 * We intentionally do not persist this state by row id. If a row is off-screen when the
	 * refresh happens, replaying the effect later would not reflect when the change occurred.
	 */
	private pulseRenderedRows(rowIds : string[]) : void
	{
		const normalizedRowIds = Array.from(new Set((rowIds || []).filter(Boolean)));
		if(!normalizedRowIds.length)
		{
			return;
		}
		for(const rowId of normalizedRowIds)
		{
			const renderedRow = this.host._findRenderedRowElement(rowId);
			if(!renderedRow)
			{
				continue;
			}
			const existingTimer = this._refreshPulseTimersByElement.get(renderedRow);
			if(existingTimer)
			{
				window.clearTimeout(existingTimer);
			}
			renderedRow.classList.remove("dg-row--refreshed");
			// Restart the CSS animation when the same visible row refreshes repeatedly.
			void renderedRow.offsetWidth;
			renderedRow.classList.add("dg-row--refreshed");
			this._refreshPulseTimersByElement.set(renderedRow, window.setTimeout(() =>
			{
				renderedRow.classList.remove("dg-row--refreshed");
				this._refreshPulseTimersByElement.delete(renderedRow);
			}, this._refreshPulseDurationMs));
		}
	}

	/**
	 * Defer refreshed-row pulse effects until Lit has rendered the merged rows.
	 */
	scheduleRenderedRowPulse(rowIds : string[])
	{
		const normalizedRowIds = Array.from(new Set((rowIds || []).filter(Boolean)));
		if(!normalizedRowIds.length)
		{
			return;
		}
		void this.host.updateComplete.then(() => this.pulseRenderedRows(normalizedRowIds));
	}

	/**
	 * Queue realized rows for post-render widget binding.
	 *
	 * Row templates are stamped as inert DOM strings for virtualizer throughput.
	 * This method finds newly realized physical rows, avoids duplicate work for
	 * the same row identity, and hands them to the batched upgrade queue where
	 * row-scoped array managers and template attributes are applied.
	 */
	upgradeRenderedRows()
	{
		const rowElements = this.host._isTileView()
		                    ? this.host._renderedDataRowElements(this.host._rowsBody)
		                    : Array.from(this.host._rowsBody?.querySelectorAll("[data-row-id]:not(.dg-row-placeholder)") || []) as HTMLElement[];
		for(const rowElement of rowElements)
		{
			// Skip already-upgraded instances for the same row identity.
			const dataRowId = rowElement.getAttribute("data-row-id") || "";
			const upgradeSignature = this.host._rowUpgradeSignature(dataRowId);
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === upgradeSignature && dataRowId)
			{
				continue;
			}
			const rowIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			if(rowIndex < 0)
			{
				continue;
			}
			// Print rows live in `_printRows`, not `_rowsByIndex` (see Et2Datagrid._renderVirtualRow) -
			// without this fallback, rows rendered only for printing never get queued for
			// upgrade, leaving `$row_cont[...]` template placeholders unresolved forever.
			const row = this.host._printRows?.[rowIndex] || this.host._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			if(rowElement.getAttribute("data-et2dg-upgrade-queued") === "1")
			{
				if(this._rowUpgradeQueue.includes(rowElement))
				{
					continue;
				}
				rowElement.removeAttribute("data-et2dg-upgrade-queued");
			}
			rowElement.setAttribute("data-et2dg-upgrade-queued", "1");
			this._rowUpgradeQueue.push(rowElement);
		}
		if(this._rowUpgradeQueue.length)
		{
			this.scheduleRowUpgradeQueue();
		}
	}

	/**
	 * Cancel queued/in-flight frame work for row upgrades.
	 */
	clearRowUpgradeQueue()
	{
		this._rowUpgradeQueue.length = 0;
		this._rowUpgradeScheduled = false;
		if(this._rowUpgradeFrameHandle !== null)
		{
			cancelAnimationFrame(this._rowUpgradeFrameHandle);
			this._rowUpgradeFrameHandle = null;
		}
	}

	/**
	 * Schedule batched row upgrades on next frame to avoid long main-thread stalls.
	 */
	private scheduleRowUpgradeQueue()
	{
		if(this._rowUpgradeScheduled)
		{
			return;
		}
		this._rowUpgradeScheduled = true;
		this._rowUpgradeFrameHandle = requestAnimationFrame(() =>
		{
			this._rowUpgradeScheduled = false;
			this._rowUpgradeFrameHandle = null;
			this.processRowUpgradeQueue();
		});
	}

	/**
	 * Process a bounded number of row upgrades per frame so scroll/input remain responsive.
	 */
	processRowUpgradeQueue()
	{
		// Keep upgrade work under roughly half a 60fps frame (~16.7ms) so scrolling,
		// input, and paint can still run in the same frame on typical hardware.
		// 8ms is a pragmatic balance between throughput and UI responsiveness.
		const budgetUntil = performance.now() + this._rowUpgradeFrameBudgetMs;
		let processed = 0;
		while(this._rowUpgradeQueue.length && processed < this._rowUpgradeBatchSize && performance.now() < budgetUntil)
		{
			const rowElement = this._rowUpgradeQueue.shift();
			if(!rowElement || !rowElement.isConnected)
			{
				continue;
			}
			rowElement.removeAttribute("data-et2dg-upgrade-queued");
			const dataRowId = rowElement.getAttribute("data-row-id") || "";
			const upgradeSignature = this.host._rowUpgradeSignature(dataRowId);
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === upgradeSignature && dataRowId)
			{
				continue;
			}
			const rowIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			if(rowIndex < 0)
			{
				continue;
			}
			// Same print-rows fallback as upgradeRenderedRows() above - this is where
			// the skip would otherwise silently repeat forever for print-only rows.
			const row = this.host._printRows?.[rowIndex] || this.host._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			rowElement.classList.add("loading");
			if(this.applyRowElementAttributes(rowElement, this.host._rowDataFor(row), rowIndex))
			{
				rowElement.setAttribute("data-et2dg-upgraded-for", upgradeSignature);
			}
			processed++;
		}
		if(this._rowUpgradeQueue.length)
		{
			this.scheduleRowUpgradeQueue();
		}
		else
		{
			this.scheduleRowsUpgradedSettle();
		}
	}

	/**
	 * Wait for upgraded row widgets to paint, update the measured row-height
	 * average, then notify height consumers that row layout is stable enough for
	 * reservation calculations.
	 */
	scheduleRowsUpgradedSettle()
	{
		if(this._rowWidgetsUpgradedFrame !== null)
		{
			return;
		}
		this._rowWidgetsUpgradeSettling = true;
		this._rowWidgetsUpgradedFrame = requestAnimationFrame(() =>
		{
			this._rowWidgetsUpgradedFrame = requestAnimationFrame(() =>
			{
				this._rowWidgetsUpgradedFrame = null;
				this.host._updateMeasuredAverageRowHeight();
				this._rowWidgetsUpgradeSettling = false;
				this.host.dispatchEvent(new CustomEvent("et2-row-widgets-upgraded", {
					bubbles: true,
					composed: true,
					detail: {
						averageRowHeight: this.host._rowHeightPx,
						rowHeightLocked: this.host._rowHeightLocked
					}
				}));
				if(this.host.embeddedVirtualized || this.host._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade)
				{
					this.host._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade = false;
					this.host._scheduleEmbeddedVirtualizedHeightSync();
				}
				else if(this.host.total == this.host.rows.length)
				{
					// Updates are done. If all rows are loaded, ensure height covers
					// upgraded content. Do not do this for partial data sets.
					this.host._scheduleRowsMinHeightSync();
				}
			});
		});
	}

	/**
	 * Replace simple row placeholders in text nodes.
	 */
	private populateCloneWithRow(fragment : DocumentFragment, row : any)
	{
		const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_TEXT, null);
		const texts : Text[] = [];
		let node : Node | null = null;
		while((node = walker.nextNode()) !== null)
		{
			texts.push(node as Text);
		}
		for(const text of texts.filter(t => t.nodeValue.trim()))
		{
			text.nodeValue = Et2RowProvider.resolveSimpleRowPlaceholders(
				text.nodeValue || "",
				row,
				(rowData, key) => this.host._getFieldValue(rowData, key)
			);
		}
	}

	/**
	 * Resolve placeholder expressions on the row root element only.
	 */
	private populateRowRootAttributes(rowRoot : HTMLElement, row : any)
	{
		Et2RowProvider.customizeRowRootAttributes(
			rowRoot,
			row,
			(rowData, key) => this.host._getFieldValue(rowData, key)
		);
	}

	/**
	 * Apply row-scoped template attributes to child widgets after row insertion.
	 * This is deferred to keep scrolling/rendering responsive.
	 */
	applyRowElementAttributes(rowRoot : HTMLElement, rowData : any, rowIndex : number) : boolean
	{
		const attrMap = this.host.templateData?.rowTemplateAttrMap || {};
		const toUpgrade = [
			...(rowRoot.hasAttribute("data-et2nm-id") ? [rowRoot] : []),
			...Array.from(rowRoot.querySelectorAll("[data-et2nm-id]"))
		] as any[];
		if(!toUpgrade.length)
		{
			rowRoot.classList.remove("loading");
			return true;
		}

		const contentMgr = this.host.getArrayMgr("content") || new et2_arrayMgr({});
		const mgrs : any = this.host.getArrayMgrs?.() || {};
		let mgr = contentMgr;
		mgrs.content = contentMgr;
		const rowId = String(this.host._rowsByIndex[rowIndex]?.id ?? this.host._rowIdFor(rowData, rowIndex));
		const usePerspectiveFallback = () =>
		{
			if(mgr !== contentMgr)
			{
				return;
			}
			const mgrRowData = {};
			mgrRowData[rowIndex] = rowData;
			mgr = contentMgr.openPerspective(this.host as any, mgrRowData, rowIndex);
			mgrs.content = mgr;
		};
		try
		{
			// Resolve row values directly first.  The ArrayMgr perspective remains a
			// compatibility fallback for row expressions this resolver cannot handle.
			for(const element of toUpgrade)
			{
				try
				{
					const id = element.getAttribute?.("data-et2nm-id");
					const stored = id ? attrMap[id] : null;
					const handlerSources = id ? this.host.templateData?.rowTemplateHandlerMap?.[id] : null;
					if(handlerSources && Object.values(handlerSources).some((source) => source.includes("$") || source.includes("@")))
					{
						// Virtualized widgets are reused. These handlers are compiled with
						// the current array-manager perspective, so discard their previous
						// row-specific function before applying a new row.
						this.host._rowTemplateHandlerCache.delete(element);
					}
					const isCustomfieldsRow = element.localName === "et2-customfields-list";
					if(isCustomfieldsRow)
					{
						// Customfields use id="$row" for their row object, but any
						// other row-bound attribute follows normal row hydration.
						const customfieldAttributes : Record<string, any> = {};
						for(const [attribute, value] of Object.entries(stored || {}))
						{
							if(attribute === "id")
							{
								continue;
							}
							const resolved = this.host._resolveRowExpression(value, rowData, rowId);
							if(resolved.fallback)
							{
								usePerspectiveFallback();
							}
							const booleanValue = this.host._rowAttributePropertyType(element, attribute) === Boolean
								? this.host._directBooleanRowValue(value, rowData, rowId)
								: undefined;
							customfieldAttributes[attribute] = typeof booleanValue === "undefined" ? resolved.value : booleanValue;
						}
						if(element.setArrayMgrs)
						{
							element.setArrayMgrs(mgrs);
						}
						if(element.setArrayMgr && mgr)
						{
							element.setArrayMgr("content", mgr);
						}
						if(Object.keys(customfieldAttributes).length)
						{
							if(typeof element.transformAttributes === "function")
							{
								element.transformAttributes(customfieldAttributes);
							}
							else
							{
								Object.entries(customfieldAttributes).forEach(([attribute, value]) => element.setAttribute(attribute, String(value ?? "")));
							}
						}
						this.applyCustomfieldRowState(element, rowData);
						continue;
					}
					if(element === rowRoot)
					{
						if(stored && Object.keys(stored).length)
						{
							this.applyRowRootStoredAttributes(rowRoot, stored, rowData);
						}
						continue;
					}
					if(element.setArrayMgrs)
					{
						element.setArrayMgrs(mgrs);
					}
					if(element.setArrayMgr && mgr)
					{
						element.setArrayMgr("content", mgr);
					}
					const attributes : Record<string, any> = {};
					let hasDirectValue = false;
					let directValue : any;
					for(const [attribute, value] of Object.entries(stored || {}))
					{
						const resolved = this.host._resolveRowExpression(value, rowData, rowId);
						if(resolved.fallback)
						{
							usePerspectiveFallback();
						}
						const booleanValue = this.host._rowAttributePropertyType(element, attribute) === Boolean
							? this.host._directBooleanRowValue(value, rowData, rowId)
							: undefined;
						attributes[attribute] = typeof booleanValue === "undefined" ? resolved.value : booleanValue;
						if(attribute === "value" && resolved.rowValue !== undefined)
						{
							hasDirectValue = true;
							directValue = resolved.rowValue;
						}
					}
					// Row-bound ids conventionally mean "the value at this row key".
					// VFS row renderers use $row for the complete row object.
					const idBinding = stored?.id ? this.host._resolveRowExpression(stored.id, rowData, rowId) : null;
					const isRowObjectBinding = stored?.id === "$row" || stored?.id === "${row}";
					if(stored?.value === undefined && (idBinding?.rowValue !== undefined || isRowObjectBinding) &&
						(this.host._rowAttributePropertyType(element, "value") || typeof element.set_value === "function"))
					{
						attributes.value = isRowObjectBinding ? rowData : idBinding!.rowValue;
						hasDirectValue = true;
						directValue = attributes.value;
						delete attributes.id;
						// Restore a plain "<row>[<field>]" id (instead of the row's resolved
						// value, which transformAttributes() would otherwise apply) so
						// id-based lookups that aren't row data - eg. sel_options for an
						// <et2-select>, which live at the un-namespaced column level, not
						// per-row - can still find the widget's field name.  Assigned
						// directly (not via `attributes`) so the property setter always
						// runs, even on a virtualizer-recycled element whose DOM already
						// has an "id" attribute from a previous row.
						if(idBinding?.field)
						{
							element.id = `${rowId}[${idBinding.field}]`;
						}
					}
					else if(!stored?.value && !stored?.id && typeof element.id === "string" && /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(element.id) &&
						Object.prototype.hasOwnProperty.call(rowData || {}, element.id) &&
						(this.host._rowAttributePropertyType(element, "value") || typeof element.set_value === "function"))
					{
						attributes.value = rowData[element.id];
						hasDirectValue = true;
						directValue = attributes.value;
					}
					if(element.setArrayMgrs)
					{
						element.setArrayMgrs(mgrs);
					}
					if(element.setArrayMgr && mgr)
					{
						element.setArrayMgr("content", mgr);
					}
					if(typeof element.transformAttributes === "function")
					{
						if(!Object.keys(attributes).length)
						{
							continue;
						}
						else
						{
							element.transformAttributes(attributes);
							if(hasDirectValue)
							{
								if(typeof element.set_value === "function")
								{
									element.set_value(directValue);
								}
								else
								{
									element.value = directValue;
								}
							}
						}
					}
					else
					{
						Object.entries(attributes).forEach(([attr, value]) =>
						{
							element.setAttribute(attr, mgr.expandName(String(value)));
						});
					}
				}
				catch(e)
				{
					this.host.egw()?.debug?.("error", "Et2Datagrid: failed to apply row element attributes", {
						rowIndex,
						element: element?.tagName || "",
						error: e
					});
				}
			}
		}
		catch(e)
		{
			this.host.egw()?.debug?.("error", "Et2Datagrid: row attribute application failed", {
				rowIndex,
				error: e
			});
			rowRoot.classList.remove("loading");
			return false;
		}
		rowRoot.classList.remove("loading");
		return true;
	}

	/**
	 * Apply deferred row-root attributes through the same resolver used when the
	 * template clone is first built, preserving category class normalization.
	 *
	 * Row-root attributes are not widget attributes. In particular, class values
	 * like `$row_cont[cat_id]` must become `row_category cat_#`, not the raw
	 * category id returned by generic array-manager expansion.
	 */
	private applyRowRootStoredAttributes(rowRoot : HTMLElement, stored : Record<string, string>, rowData : any)
	{
		Object.entries(stored).forEach(([attr, value]) =>
		{
			rowRoot.setAttribute(attr, value);
		});
		this.populateRowRootAttributes(rowRoot, rowData);
	}

	/**
	 * Apply customfields row state directly from row data and the owning header.
	 *
	 * Object properties are not preserved when the row template is cloned, so each
	 * physical row renderer needs its current value assigned. The expensive state
	 * (metadata + selected field names) is cached per customfield column and reused
	 * for every row to avoid header scans or generic array-manager transforms.
	 */
	applyCustomfieldRowState(element : any, rowData : any)
	{
		const columnState = this.customfieldColumnStateForRowElement(element);
		const fallback = !columnState?.customfields
			? this.host.getArrayMgr("modifications")?.getRoot?.()?.getEntry("~custom_fields~", true)
			: null;
		const customfields = columnState?.customfields || fallback?.customfields || element.customfields || {};
		const visibility = columnState?.visibility || fallback?.fields || null;
		if(customfields)
		{
			element.customfields = customfields;
		}
		if(visibility)
		{
			element.fields = visibility;
		}
		// No labels in rows
		element.noLabel = true;
		// Et2CustomfieldsList reads only the visible field keys, so the complete
		// row can be reused without allocating a filtered value object per row.
		element.value = rowData || {};
	}

	/**
	 * Resolve cached customfield state for the renderer's column.
	 *
	 * The fallback to the first cached customfield column supports legacy row
	 * templates where the source cell does not expose a column key.
	 */
	private customfieldColumnStateForRowElement(element : HTMLElement) : Et2DatagridCustomfieldColumnState | null
	{
		if(!this._customfieldColumnStateByKey.size)
		{
			this.rebuildCustomfieldColumnStateCache();
		}
		const cell = element.closest("td,th") as HTMLElement | null;
		const columnKey = cell?.getAttribute("data-col-key") || "";
		if(columnKey && this._customfieldColumnStateByKey.has(columnKey))
		{
			return this._customfieldColumnStateByKey.get(columnKey) || null;
		}
		return this._customfieldColumnStateByKey.values().next().value || null;
	}

	/**
	 * Cache customfield metadata and selected field names from customfield headers.
	 *
	 * Rebuilt only when column/header state changes; row binding reads from this
	 * map instead of recomputing visibility for every row.
	 */
	rebuildCustomfieldColumnStateCache()
	{
		this._customfieldColumnStateByKey.clear();
		for(const column of this.host.columns || [])
		{
			const header = column.header as any;
			if(!header || typeof header.getCustomfieldVisibility !== "function")
			{
				continue;
			}
			const customfields = header.customfields && typeof header.customfields === "object" ? header.customfields : {};
			const visibility = header.getCustomfieldVisibility();
			const visibleFieldNames = visibility && typeof visibility === "object"
				? Object.keys(visibility).filter((name) => visibility[name] === true)
				: Object.keys(customfields);
			this._customfieldColumnStateByKey.set(String(column.key), {
				customfields,
				visibility: visibility && typeof visibility === "object" ? visibility : null,
				visibleFieldNames
			});
		}
	}

	/**
	 * Observe row DOM churn to upgrade widgets and recover row focus after virtualization swaps.
	 */
	initRowUpgradeObserver()
	{
		const rowsBody = this.host._rowsBody;
		if(this._rowUpgradeObservedRowsBody === rowsBody && this._rowUpgradeObserver && this._rowUpgradeRangeListener)
		{
			return;
		}
		this._rowUpgradeObserver?.disconnect();
		if(this._rowUpgradeRangeListener)
		{
			this._rowUpgradeObservedRowsBody?.removeEventListener("rangeChanged", this._rowUpgradeRangeListener);
		}
		this._rowUpgradeObserver = null;
		this._rowUpgradeRangeListener = null;
		this._rowUpgradeObservedRowsBody = null;
		if(!rowsBody)
		{
			return;
		}
		this._rowUpgradeObserver = new MutationObserver(() =>
		{
			this.upgradeRenderedRows();
			this.guardFocusAfterVirtualMutation();
		});
		this._rowUpgradeObserver.observe(rowsBody, {childList: true, subtree: true});
		// The virtualizer emits this after selecting a new realized range. Queue the
		// existing hydration pass after its directive has applied that range; this
		// covers a DOM mutation missed while the virtualizer host is recreated.
		this._rowUpgradeRangeListener = () =>
		{
			queueMicrotask(() =>
			{
				if(this._rowUpgradeObservedRowsBody !== rowsBody)
				{
					return;
				}
				this.upgradeRenderedRows();
				this.guardFocusAfterVirtualMutation();
			});
		};
		rowsBody.addEventListener("rangeChanged", this._rowUpgradeRangeListener);
		this._rowUpgradeObservedRowsBody = rowsBody;
	}

	/**
	 * Virtualizer can remove the currently focused row before the replacement row is mounted.
	 * When that happens, keyboard events stop because focus leaves the grid entirely.
	 * Keep focus anchored to `activeRowIndex` after DOM churn.
	 */
	private guardFocusAfterVirtualMutation()
	{
		if(this.host.activeRowIndex < 0)
		{
			return;
		}
		const shadowActive = this.host.shadowRoot?.activeElement as HTMLElement | null;
		const activeIsRow = !!shadowActive?.matches?.("[data-row-index]");
		if(activeIsRow)
		{
			return;
		}
		// Do not steal focus if the user intentionally moved to another interactive control.
		const active = document.activeElement as HTMLElement | null;
		const activeTag = active?.tagName?.toLowerCase?.() || "";
		if(active && active !== document.body && active !== this.host && activeTag !== "egw-app")
		{
			return;
		}
		this.focusGridFallback();
		this.host._restoreFocusAfterRender = true;
		requestAnimationFrame(() =>
		{
			if(!this.host._restoreFocusAfterRender || this.host.activeRowIndex < 0)
			{
				return;
			}
			this.host._focusRowByIndex(this.host.activeRowIndex, 10, false);
		});
	}

	/**
	 * Keep focus on the grid while virtualizer swaps row DOM so keyboard navigation remains active.
	 */
	private focusGridFallback()
	{
		const table = this.host._gridTable;
		if(!table)
		{
			return;
		}
		try
		{
			table.focus({preventScroll: true});
		}
		catch(e)
		{
			table.focus();
		}
	}

	/** Full teardown on host disconnect: stop observers/timers and drop queued work. */
	dispose() : void
	{
		this._rowUpgradeObserver?.disconnect();
		this._rowUpgradeObserver = null;
		if(this._rowUpgradeRangeListener)
		{
			this._rowUpgradeObservedRowsBody?.removeEventListener("rangeChanged", this._rowUpgradeRangeListener);
		}
		this._rowUpgradeRangeListener = null;
		this._rowUpgradeObservedRowsBody = null;
		this.clearRowUpgradeQueue();
		if(this._rowWidgetsUpgradedFrame !== null)
		{
			cancelAnimationFrame(this._rowWidgetsUpgradedFrame);
			this._rowWidgetsUpgradedFrame = null;
		}
		this._rowWidgetsUpgradeSettling = false;
		this.clearRefreshPulseTimers();
	}
}
