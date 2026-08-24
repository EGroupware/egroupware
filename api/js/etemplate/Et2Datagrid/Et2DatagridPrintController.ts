import type {Et2DatagridDataProvider, Et2DatagridRow} from "./Et2Datagrid.types";

export interface Et2DatagridPrintHost extends HTMLElement
{
	fixedRowHeight : boolean;
	dataProvider? : Et2DatagridDataProvider;
	isConnected : boolean;
	requestUpdate() : void;
	updateComplete : Promise<boolean>;
	_syncRowsMinHeight() : void;
	_scheduleVirtualizerLayoutSync() : void;
	_sparseVirtualizerLayoutActive : boolean;
	_waitForRowUpgradesToFinish(maxWaitMs? : number) : Promise<void>;
}

/**
 * Renders already-fetched rows in full (unvirtualized) for print output, and
 * restores normal virtualized rendering afterwards.
 *
 * Kept separate from the host's normal row storage (`_rowsByIndex`) so paging
 * and virtualization are untouched by a print job.
 */
export class Et2DatagridPrintController
{
	private host : Et2DatagridPrintHost;

	/** Rows temporarily rendered in full for print output. */
	private _rows : Et2DatagridRow[] | null = null;
	/** Fixed-row-height state to restore after print rows return to virtualization. */
	private _fixedRowHeight : boolean | null = null;

	constructor(host : Et2DatagridPrintHost)
	{
		this.host = host;
	}

	get rows() : Et2DatagridRow[] | null
	{
		return this._rows;
	}

	/**
	 * Render already-fetched rows without virtualization for print output.
	 * The caller owns fetching and must call clearPrintRows() after printing.
	 */
	async setPrintRows(rowIds : string[]) : Promise<void>
	{
		if(this._fixedRowHeight === null)
		{
			this._fixedRowHeight = this.host.fixedRowHeight;
		}
		this.host.fixedRowHeight = false;
		this._rows = (rowIds || []).map((rowId) => ({
			id: this.host.dataProvider?.normalizeRowId?.(rowId, true) || String(rowId)
		}));
		this.host.classList.add("print");
		this.host.requestUpdate();
		await this.host.updateComplete;
		this.host._syncRowsMinHeight();
		for(let frame = 0; frame < 3; frame++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			if((this.host.shadowRoot?.querySelectorAll("#rows > tr[data-row-id]").length || 0) >= rowIds.length) break;
		}
		const updates = Array.from(this.host.shadowRoot?.querySelectorAll("*") || [])
			.map((element : any) => element.updateComplete)
			.filter((updateComplete) => updateComplete && typeof updateComplete.then === "function");
		await Promise.allSettled(updates);
		// Must happen before _waitForPrintImages(): until a row's child widgets are
		// upgraded, an avatar's `image` attribute is still the literal unresolved
		// `$row_cont[photo]` placeholder, not a real URL - waiting for "images" to
		// load before that resolves would just be watching the wrong src.
		await this.host._waitForRowUpgradesToFinish();
		await this._waitForPrintImages();
		await this._waitForPrintRowsToSettle();
		this.syncPrintFlowHeight();
	}

	/**
	 * Wait for every `<img>` inside the print rows (including ones nested in other
	 * components' shadow roots, eg. avatar photos) to finish loading.
	 *
	 * Print renders rows far outside the real screen viewport (that's the whole
	 * point - the user asked for more rows than fit on screen), but native
	 * lazy-loading (eg. the addressbook row template's `<et2-lavatar loading="lazy">`)
	 * never starts a fetch for an image the browser doesn't consider near-visible.
	 * Left alone, those images simply never load - printing without a photo isn't
	 * a settling problem, it's images that were never going to load on their own.
	 * Force eager loading before waiting on them.
	 *
	 * The wait is bounded, scaled to how many images are actually pending, so a
	 * large print job gets a proportionally longer allowance instead of timing out
	 * on images that just haven't gotten their turn yet behind the browser's
	 * per-host connection limit - but one genuinely broken/404 image still can't
	 * hang printing forever.
	 */
	private async _waitForPrintImages(maxWaitMs? : number) : Promise<void>
	{
		const root = this.host.shadowRoot?.querySelector(".dg-body #rows");
		if(!root)
		{
			return;
		}
		const collectImages = (node : ParentNode) : HTMLImageElement[] =>
		{
			const images = Array.from(node.querySelectorAll("img"));
			for(const element of Array.from(node.querySelectorAll("*")))
			{
				if(element.shadowRoot)
				{
					images.push(...collectImages(element.shadowRoot));
				}
			}
			return images;
		};
		const allImages = collectImages(root);
		for(const img of allImages)
		{
			if(img.loading === "lazy")
			{
				img.loading = "eager";
			}
		}

		const pending = allImages.filter((img) => !img.complete);
		if(!pending.length)
		{
			return;
		}
		// The browser's per-host connection limit (typically ~6) means a large batch
		// of images queues rather than loading in parallel, so the wait must scale
		// with how many are pending, not just cap at a flat ceiling - a job of 1000
		// rows is expected to take meaningfully longer than one of 200, and giving up
		// early just means some rows print with a blank photo instead of taking the
		// extra time to get it right. The 30-minute ceiling exists only to bound a
		// truly pathological case (eg. a permanently stalled request), not as a limit
		// any real print job should come close to hitting.
		const bound = maxWaitMs ?? Math.min(1800000, Math.max(5000, pending.length * 750));
		const waits = pending.map((img) => new Promise<void>((resolve) =>
		{
			img.addEventListener("load", () => resolve(), {once: true});
			img.addEventListener("error", () => resolve(), {once: true});
		}));
		await Promise.race([
			Promise.all(waits),
			new Promise<void>((resolve) => window.setTimeout(resolve, bound))
		]);
	}

	/**
	 * Wait until the print rows' rendered extent stops changing.
	 *
	 * Row content (eg. customfield or category labels resolving async) can keep
	 * growing for a while after every component's own `updateComplete` has already
	 * resolved, since that only covers Lit's reactive update cycle, not whatever
	 * caused a later, separate update to be scheduled. Printing before that settles
	 * makes Chromium paginate against a still-changing layout, which is what produced
	 * both inconsistent page counts and a truncated tail row between otherwise
	 * identical print attempts. A fixed delay here previously guessed at "long enough",
	 * which is exactly as fragile as it sounds - poll for real stability instead.
	 */
	private async _waitForPrintRowsToSettle(maxWaitMs = 5000, requiredStableChecks = 3, intervalMs = 150) : Promise<void>
	{
		const tbody = this.host.shadowRoot?.querySelector<HTMLElement>(".dg-body #rows");
		if(!tbody)
		{
			return;
		}
		const start = performance.now();
		let lastHeight = -1;
		let stableCount = 0;
		while(performance.now() - start < maxWaitMs)
		{
			await new Promise<void>((resolve) => window.setTimeout(resolve, intervalMs));
			const height = tbody.scrollHeight;
			if(height === lastHeight)
			{
				stableCount++;
				if(stableCount >= requiredStableChecks)
				{
					return;
				}
			}
			else
			{
				stableCount = 0;
				lastHeight = height;
			}
		}
	}

	/** Reserve the full rendered row extent for print fragmentation. */
	syncPrintFlowHeight() : void
	{
		if(!this._rows)
		{
			return;
		}
		const tbody = this.host.shadowRoot?.querySelector<HTMLTableSectionElement>(".dg-body #rows");
		const height = tbody?.scrollHeight || 0;
		if(!height)
		{
			return;
		}
		// The print body and table use natural flow.  Pinning either to the
		// current row extent clips rows whose descendants settle afterwards.
		tbody.style.height = `${height}px`;
	}

	/** Restore normal virtualized row rendering after printing. */
	clearPrintRows() : void
	{
		if(!this._rows)
		{
			return;
		}
		this._rows = null;
		this.host.fixedRowHeight = this._fixedRowHeight ?? this.host.fixedRowHeight;
		this._fixedRowHeight = null;
		const printContainers = this.host.shadowRoot?.querySelectorAll<HTMLElement>(".dg-body, .dg-body table, .dg-body #rows");
		for(const element of Array.from(printContainers || []))
		{
			element.style.height = "";
		}

		this.host.classList.remove("print");
		// The virtualize() directive is being re-attached to the tbody fresh (it wasn't
		// present while _rows rendered statically), so this is the same "new
		// virtualizer, host has no bounds yet" bootstrap case firstUpdated()/
		// refreshRowHeightFromCss() guard against - reuse that instead of sizing the
		// virtualizer immediately, which risks the zero-viewport feedback loop.
		this.host._sparseVirtualizerLayoutActive = false;
		this.host.requestUpdate();
		void this.host.updateComplete.then(() =>
		{
			if(this.host.isConnected && this._rows === null)
			{
				this.host._syncRowsMinHeight();
				this.host._scheduleVirtualizerLayoutSync();
			}
		});
	}
}
