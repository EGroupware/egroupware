import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Template} from "../Et2Template/Et2Template";
import {loadStylesheet} from "../Et2Widget/cssTools";
import {resolveEt2StylesSrc} from "../Et2Styles/Et2Styles";
import {Et2DatagridColumn, Et2DatagridTemplateData, Et2DatagridTileLayout, Et2DatagridView} from "./Et2Datagrid.types";
import "../Et2Customfields/Et2CustomfieldsList";

interface Et2RowProviderHost extends HTMLElement
{
	egw? : Function;
	getArrayMgr? : Function;
}

const DEFAULT_TILE_LAYOUT = {
	// @lit-labs/virtualizer parses grid itemSize, gap and padding as pixel numbers internally.
	// Keep these defaults in px so spacing does not collapse when passed through the grid layout.
	width: "150px",
	height: "120px",
	gap: "4px",
	padding: "4px"
} as const;

/**
 * Mutable state threaded through one row-template preparation pass.
 *
 * Preparation walks the row template once and records everything per-row
 * hydration will need, keyed by a generated `data-et2nm-id` so the recorded
 * information survives the widget being namespaced and recycled later.
 */
interface Et2RowTemplatePrepareContext
{
	/** Row-scoped attribute expressions, by row-upgrade id. */
	attrMap : Record<string, Record<string, string>>;
	/** Row-template event handler sources, by row-upgrade id. */
	handlerMap : Record<string, Record<string, string>>;
	/** Dotted row-data path each widget binds its value to, by row-upgrade id. */
	fieldMap : Record<string, string>;
	/** Counter handing out the generated row-upgrade ids. */
	idState : { next : number };
	/** False when preparing a fragment that is not hydrated per row. */
	recordAttributes : boolean;
}

/**
 * Resolves nextmatch row definitions from a template name or from slotted markup.
 * It returns normalized columns and a prepared row template for Et2Datagrid.
 */
export class Et2RowProvider
{
	private static readonly CATEGORY_CLASS_PLACEHOLDER_FIELDS = ["cat", "cat_id", "category", "info_cat"] as const;

	/** An id that names a row field or a row sub-object, rather than a row expression. */
	private static readonly PLAIN_FIELD_ID = /^[a-zA-Z_][a-zA-Z0-9_]*$/;

	private host : Et2RowProviderHost;
	private _templateLoadToken : number = 0;
	private _activeTemplate : Et2Template | null = null;

	/**
	 * @param host Owning widget used for context (egw, array managers, DOM slot source).
	 */
	constructor(host : Et2RowProviderHost)
	{
		this.host = host;
	}

	static resolveSimpleRowPlaceholders(value : string, row : any, getFieldValue : (row : any, key : string) => any) : string
	{
		if(!value || value.indexOf("$") === -1 && value.indexOf("{") === -1)
		{
			return value;
		}
		let resolved = value;
		resolved = resolved.replace(/\{([^}]+)\}/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		resolved = resolved.replace(/\$row\.([a-zA-Z0-9_.]+)/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		resolved = resolved.replace(/\$\{row\}\[([^\]]+)\]/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		resolved = resolved.replace(/\$\[([^\]]+)\]/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		resolved = resolved.replace(/\$row_cont\[([^\]]+)\]/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		resolved = resolved.replace(/\$([a-zA-Z_][a-zA-Z0-9_]*)\b/g, (_match, token) => String(getFieldValue(row, token) ?? ""));
		return resolved;
	}

	/**
	 * Look up a dotted row-field path, reporting whether the row actually has it.
	 *
	 * Direct id bindings may only supply a value for a field the row really carries,
	 * so an absent field leaves the widget's own value alone.  A plain value lookup
	 * cannot answer that, since a missing and an empty field both read as blank.
	 */
	static resolveRowField(rowData : any, path : string) : { found : boolean, value : any }
	{
		let current = rowData;
		for(const part of path.split("."))
		{
			if(!current || typeof current !== "object" || !Object.prototype.hasOwnProperty.call(current, part))
			{
				return {found: false, value: undefined};
			}
			current = current[part];
		}
		return {found: true, value: current};
	}

	static customizeRowRootAttributes(rowRoot : HTMLElement, row : any, getFieldValue : (row : any, key : string) => any)
	{
		const categoryIds = this._rowCategoryIds(row, getFieldValue);
		for(const name of rowRoot.getAttributeNames())
		{
			const value = rowRoot.getAttribute(name);
			if(value === null)
			{
				continue;
			}
			const resolved = name === "class"
				? this._resolveRowRootClassValue(value, row, getFieldValue, categoryIds)
				: this.resolveSimpleRowPlaceholders(value, row, getFieldValue);
			if(resolved !== value)
			{
				rowRoot.setAttribute(name, resolved);
			}
		}
	}

	private static _resolveRowRootClassValue(
		classValue : string,
		row : any,
		getFieldValue : (row : any, key : string) => any,
		categoryIds : string[]
	) : string
	{
		const classTokens = classValue.split(/\s+/).filter(Boolean);
		if(!classTokens.length)
		{
			return "";
		}
		const normalized = new Set<string>();
		for(const token of classTokens)
		{
			if(this._isCategoryPlaceholder(token))
			{
				const tokenCategoryIds = this._extractCategoryIds(this.resolveSimpleRowPlaceholders(token, row, getFieldValue));
				for(const id of tokenCategoryIds.length ? tokenCategoryIds : categoryIds)
				{
					normalized.add("row_category");
					normalized.add(`cat_${id}`);
				}
				continue;
			}
			const resolved = this.resolveSimpleRowPlaceholders(token, row, getFieldValue).trim();
			if(resolved)
			{
				normalized.add(resolved);
			}
		}
		return Array.from(normalized).join(" ");
	}

	private static _isCategoryPlaceholder(token : string) : boolean
	{
		const field = this._placeholderField(token);
		return !!field && (Et2RowProvider.CATEGORY_CLASS_PLACEHOLDER_FIELDS as readonly string[]).includes(field);
	}

	private static _placeholderField(token : string) : string | null
	{
		return token.match(/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/)?.[1]
			|| token.match(/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/)?.[1]
			|| token.match(/^\$row\.([a-zA-Z_][a-zA-Z0-9_]*)$/)?.[1]
			|| token.match(/^\$\{row\}\[([^\]]+)\]$/)?.[1]
			|| token.match(/^\$row_cont\[([^\]]+)\]$/)?.[1]
			|| null;
	}

	private static _rowCategoryIds(row : any, getFieldValue : (row : any, key : string) => any) : string[]
	{
		const candidates = [
			row?.cat_id,
			row?.info_cat,
			row?.category,
			row?.cat,
			getFieldValue(row, "cat_id"),
			getFieldValue(row, "info_cat"),
			getFieldValue(row, "category"),
			getFieldValue(row, "cat")
		];
		for(const value of candidates)
		{
			const ids = this._extractCategoryIds(value);
			if(ids.length)
			{
				return ids;
			}
		}
		return [];
	}

	private static _extractCategoryIds(raw : any) : string[]
	{
		const value = String(raw ?? "").trim();
		if(!value)
		{
			return [];
		}
		return value
			.split(",")
			.map((part) => part.trim())
			.filter((part) => /^\d+$/.test(part));
	}

	/**
	 * Resolve row/column metadata from a named Et2Template.
	 * Token checks and active-template cancellation prevent stale concurrent loads.
	 */
	async fromTemplate(templateName : string) : Promise<Et2DatagridTemplateData | null>
	{
		if(!templateName)
		{
			return null;
		}

		let tpl : Et2Template | null = null;
		const token = ++this._templateLoadToken;
		// Cancel previous in-flight template to avoid duplicate temporary widgets.
		this._cancelActiveTemplate();
		try
		{
			tpl = <Et2Template><unknown>loadWebComponent("et2-template", {id: templateName}, this.host as any);
			this._activeTemplate = tpl;
			let xml : Element | null = null;
			// We prefer to read it directly ourselves
			if(typeof (tpl as any).findTemplate === "function")
			{
					try
					{
						xml = await (tpl as any).findTemplate();
						// Guard against writes after a newer load has started.
						if(token !== this._templateLoadToken)
						{
							return null;
					}
				}
					catch(e)
					{
						// Fallback to full load for environments where findTemplate is unavailable or fails.
						await tpl.load();
						await tpl.updateComplete;
						xml = tpl as unknown as Element;
					if(token !== this._templateLoadToken)
					{
						return null;
					}
				}
			}
			else
			{
				// Fallback to processing an already loaded template
				await tpl.load();
				await tpl.updateComplete;
				xml = tpl as unknown as Element;
				if(token !== this._templateLoadToken)
				{
					return null;
				}
			}

			if(token !== this._templateLoadToken)
			{
				return null;
			}

			const templateUrl = typeof (tpl as any).getUrl === "function" ? (tpl as any).getUrl() : "";
			return await this._fromTemplateRoot(xml || tpl, templateUrl);
		}
		catch(e)
		{
			try
			{
				this.host.egw?.()?.debug("warn", "Et2RowProvider: could not load template " + templateName, e);
			}
			catch(_e)
			{
			}
		}
		finally
		{
			if(this._activeTemplate === tpl)
			{
				this._activeTemplate = null;
			}
			if(tpl)
			{
				// Wait a tick so teardown does not race pending widget initialization.
				await tpl.updateComplete;
				try
				{
					tpl.destroy();
				}
				catch(e)
				{
				}
				try
				{
					tpl.remove();
				}
				catch(e)
				{
				}
			}
		}

		return null;
	}

	/**
	 * Stop and dispose any previously spawned temporary Et2Template widget.
	 */
	private _cancelActiveTemplate()
	{
		if(!this._activeTemplate)
		{
			return;
		}
		try { this._activeTemplate.destroy(); } catch(e) {}
		try { this._activeTemplate.remove(); } catch(e) {}
		this._activeTemplate = null;
	}

	/**
	 * Dispose of a slotted definition node once its content has been read into a clone/template.
	 * Destroying it clears its own widget-tree children so getWidgetById() on the host no longer
	 * descends into this now-invisible source when resolving an id that also exists live elsewhere.
	 */
	private _releaseSlotSource(node : Element | null)
	{
		if(!node)
		{
			return;
		}
		try { (node as any).destroy?.(); } catch(e) {}
		try { node.remove(); } catch(e) {}
	}

	/**
	 * Resolve row/column metadata from named slots on the host element.
	 */
	async fromSlots() : Promise<Et2DatagridTemplateData | null>
	{
		const headerSource = this._getSlotContent("columns");
		const originalRowSource = this._getSlotContent("row");
		const rowSource = this._cloneTemplateSource(originalRowSource);
		const loaderSource = this._getSlotContent("loader");
		const noResultsSource = this._getSlotContent("noResults");
		const templateUrl = this._templateUrl(originalRowSource);
		const rowStylesheets = rowSource ? await this._extractRowStylesheets(rowSource, templateUrl) : [];

		// The slotted "row" source is only ever read here to build a raw DOM/XML clone
		// (rowSource, prepared.template, prepared.xml) - the live widget it was parsed into
		// (with its own field widgets, e.g. selects) is never displayed and would otherwise
		// sit forever as an invisible Et2Nextmatch child, shadowing real widgets by id in
		// getWidgetById(). "columns" and "noResults" are excluded: their content (or its
		// children) is reused directly as the live header/no-results DOM, not cloned.
		this._releaseSlotSource(originalRowSource);

		const resolvedHeader = headerSource ? this._resolveSlotHeaderElement(headerSource) : null;
		const columnMeta = resolvedHeader ? this._extractSlotColumnMeta(resolvedHeader) : [];
		const columns = resolvedHeader
		                ? this._extractColumnsFromHeaderNode(resolvedHeader, columnMeta.length).map((column, index) => ({...columnMeta[index], ...column}))
		                : [];
		const rowElement = this._resolveSlotRowElement(rowSource);
		const view = this._templateView(rowElement);
		const normalizedRowElement = rowElement ? this._normalizeTemplateRowNode(rowElement, view) : null;
		this._applyDefaultColumnMinWidths(columns, normalizedRowElement);
		const prepared = normalizedRowElement ? await this._prepareRowTemplate(normalizedRowElement, columns, templateUrl) : null;
		const loaderTemplate = loaderSource ? this._toTemplate(loaderSource) : null;
		const noResultsTemplate = noResultsSource ? this._toTemplate(noResultsSource) : null;

		// Same as "row" above, unless _toTemplate() returned the live node itself
		// (an already-inert <template slot="loader">, never upgraded/connected as a widget).
		if(loaderSource && loaderTemplate !== loaderSource)
		{
			this._releaseSlotSource(loaderSource);
		}

		if(!columns.length && !prepared)
		{
			return null;
		}

		return {
			columns,
			view,
			tileLayout: view === "tile" ? this._tileLayoutFromRowNode(rowElement) : undefined,
			rowTemplateId: rowElement?.id || undefined,
			rowTemplate: prepared?.template ?? null,
			rowTemplateXml: prepared?.xml ?? null,
			rowTemplateAttrMap: prepared?.attrMap ?? {},
			rowTemplateHandlerMap: prepared?.handlerMap ?? {},
			rowTemplateFieldMap: prepared?.fieldMap ?? {},
			rowStylesheets: [
				...rowStylesheets,
				...(prepared?.rowStylesheets ?? [])
			],
			loaderTemplate,
			noResultsTemplate
		};
	}

	/**
	 * Parse template XML root to produce datagrid-ready template data.
	 */
	private async _fromTemplateRoot(tplRoot : Element, templateUrl : string = "") : Promise<Et2DatagridTemplateData>
	{
		tplRoot = this._cloneTemplateSource(tplRoot) ?? tplRoot;
		const rowStylesheets = await this._extractRowStylesheets(tplRoot, templateUrl);
		let headerNode : Element | null = tplRoot.querySelector(".th") ?? tplRoot.querySelector("thead");
		let rowNode : Element | null = null;
		if(!headerNode && tplRoot.children.length >= 2)
		{
			headerNode = tplRoot.children[0] as Element;
			rowNode = tplRoot.children[1] as Element;
		}
		rowNode = rowNode ?? headerNode?.nextElementSibling ?? tplRoot;

		// Use the original header node structure without flattening
		// This preserves wrappers like et2-vbox, et2-hbox, etc.
		const columnDefs = headerNode ?
		                   this._extractColumnDefs(tplRoot, headerNode) :
			[];

		// Get metadata from an already parsed grid, the xml for the grid, or nothing
		const colMeta = columnDefs.map((column) => ({
			width: column.getAttribute("width"),
			minWidth: column.getAttribute("minWidth") || column.getAttribute("minwidth") || column.getAttribute("min-width"),
			disabled: column.getAttribute("disabled")
		}));

		const columns : Et2DatagridColumn[] = this._extractColumnsFromHeaderNode(headerNode, colMeta.length)
			.map((c, index) => {return {...colMeta[index], ...c}});
		const view = this._templateView(rowNode);
		const normalizedRowNode = this._normalizeTemplateRowNode(rowNode, view);
		this._applyDefaultColumnMinWidths(columns, normalizedRowNode);
		const prepared = await this._prepareRowTemplate(normalizedRowNode, columns, templateUrl);
		const loaderSource = tplRoot.querySelector('[slot="loader"]');
		const loaderTemplate = loaderSource ? this._toTemplate(loaderSource) : null;
		const noResultsSource = tplRoot.querySelector('[slot="noResults"]');
		const noResultsTemplate = noResultsSource ? this._toTemplate(noResultsSource) : null;

		return {
			view,
			tileLayout: view === "tile" ? this._tileLayoutFromRowNode(rowNode) : undefined,
			columns,
			rowTemplateId: tplRoot.getAttribute("id") || tplRoot.id || normalizedRowNode?.id || undefined,
			rowTemplate: prepared?.template ?? null,
			rowTemplateXml: prepared?.xml ?? null,
			rowTemplateAttrMap: prepared?.attrMap ?? {},
			rowTemplateHandlerMap: prepared?.handlerMap ?? {},
			rowTemplateFieldMap: prepared?.fieldMap ?? {},
			rowStylesheets: [
				...rowStylesheets,
				...(prepared?.rowStylesheets ?? [])
			],
			loaderTemplate,
			noResultsTemplate
		};
	}

	/**
	 * Extract column definition elements from the header node.
	 * This preserves wrapper elements like et2-vbox, et2-hbox, and other containers.
	 */
	private _extractColumnDefs(tplRoot, headerNode : Element) : Element[]
	{
		let columnDefs = [];
		// Reading inside a grid?
		const columnsNode = tplRoot.querySelector("thead:has(*)") ?? tplRoot.querySelector("tr.th:has(*)") ?? tplRoot.querySelector("columns");
		columnDefs = Array.from(columnsNode.children)
			.filter((c:Element) => c.nodeType === Node.ELEMENT_NODE && ["column", "td"].includes(c.tagName.toLowerCase())) as Element[]

		// If we have a .th class or thead element, get children
		if(columnDefs?.length == 0 && (headerNode.classList.contains("th") || headerNode.tagName.toLowerCase() === "thead"))
		{
			// Get all direct children that are actual column headers
			const children = Array.from(headerNode.children).filter((child) => child.nodeType === Node.ELEMENT_NODE);

			// Filter for elements that represent columns
			// This includes elements with specific header-related attributes or tags
			columnDefs = children.filter((child) =>
			{
				const tag = child.tagName.toLowerCase();
				// Include actual header elements and common wrapper elements that define columns
				return tag === "column" ||
					tag === "columns" ||
					tag === "th" ||
					child.hasAttribute("id") && !child.classList.contains("hidden");
			});
		}
		return columnDefs;
	}

	/**
	 * Parse column definitions from header
	 *
	 * @param headerNode Header row/thead element to read column definitions from.
	 * @param minColumnCount If the header row has no child elements (e.g. `<row class="th"></row>`),
	 *        synthesize this many blank placeholder columns instead of returning none - otherwise
	 *        the datagrid's grid-template-columns ends up empty and every data cell collapses to
	 *        zero width, leaving rows present in the DOM but entirely invisible.
	 */
	private _extractColumnsFromHeaderNode(headerNode : Element, minColumnCount : number = 0) : Et2DatagridColumn[]
	{
		const nodes = this._headerColumnSourceNodes(headerNode)
			.filter((node) =>
			{
				const tag = node.tagName.toLowerCase();
				return tag !== "columns" && tag !== "column";
			});
		const columns : Et2DatagridColumn[] = [];
		nodes.forEach((node, index) =>
		{
			if(node.nodeType !== Node.ELEMENT_NODE)
			{
				return;
			}
			const element = node as Element;
			const key = this._getColumnKey(element, index);
			const title = this._extractHeaderTitle(element) || (element.textContent || element.getAttribute("title") || key).trim();
			const col : Et2DatagridColumn = {key, title, header: element};
			const width = element.getAttribute("width") || element.getAttribute("data-width");
			const minWidth = element.getAttribute("minWidth") || element.getAttribute("data-min-width");
			const disabled = element.getAttribute("disabled");
			if(width) col.width = width;
			if(minWidth) col.minWidth = minWidth;
			if(disabled !== null) col.disabled = disabled;
			columns.push(col);
		});
		if(!columns.length && minColumnCount > 0)
		{
			for(let index = 0; index < minColumnCount; index++)
			{
				columns.push({key: "col" + index, title: "", header: document.createElement("et2-description")});
			}
		}
		return columns;
	}

	/**
	 * Read optional slotted <columns><column/></columns> metadata for width/minWidth/disabled.
	 */
	private _extractSlotColumnMeta(headerNode : Element) : Array<{
		width? : string;
		minWidth? : string;
		disabled? : string
	}>
	{
		let columnNodes : Element[] = [];
		const tag = headerNode.tagName.toLowerCase();
		if(tag === "columns")
		{
			columnNodes = Array.from(headerNode.children).filter((child) => child.tagName.toLowerCase() === "column") as Element[];
		}
		else
		{
			columnNodes = Array.from(headerNode.children) as Element[];
		}
		return columnNodes.map((column) => ({
			// Width in parsed etemplates disappears into style, but it's still there when reading the raw template
			width: column.getAttribute("width") || (column as HTMLElement).style.width || undefined,
			minWidth: column.getAttribute("minWidth") || column.getAttribute("minwidth") || column.getAttribute("min-width") || undefined,
			disabled: column.getAttribute("disabled") || undefined
		}));
	}

	/**
	 * Slot header can use any wrapper. Use wrapper contents as effective column source.
	 */
	private _resolveSlotHeaderElement(headerSource : Element) : Element
	{
		if(headerSource instanceof HTMLTemplateElement)
		{
			const first = headerSource.content.firstElementChild as Element | null;
			return first || headerSource;
		}
		return headerSource;
	}

	/**
	 * Resolve header column-source nodes from common wrappers (<tr>/<row>/<thead>) or generic containers.
	 */
	private _headerColumnSourceNodes(headerNode : Element) : Element[]
	{
		if(headerNode instanceof HTMLTemplateElement)
		{
			const children = Array.from(headerNode.content.children) as Element[];
			if(children.length === 1 && ["tr", "row", "thead"].includes(children[0].tagName.toLowerCase()))
			{
				return Array.from(children[0].children) as Element[];
			}
			return children.length ? children : [headerNode];
		}
		const tag = headerNode.tagName.toLowerCase();
		if(["tr", "row", "thead"].includes(tag))
		{
			return Array.from(headerNode.children) as Element[];
		}
		const children = Array.from(headerNode.children) as Element[];
		return children.length ? children : [headerNode];
	}

	/**
	 * Compile row template DOM into a reusable HTMLTemplateElement with tracked dynamic attributes.
	 */
	private async _prepareRowTemplate(rowNode : Element, columns : Et2DatagridColumn[], templateUrl : string = "") : Promise<{
		template : HTMLTemplateElement;
		xml : Element;
		attrMap : Record<string, Record<string, string>>;
		handlerMap : Record<string, Record<string, string>>;
		fieldMap : Record<string, string>;
		rowStylesheets : CSSStyleSheet[];
	} | null>
	{
		if(!rowNode)
		{
			return null;
		}

		const xml = rowNode.cloneNode(true) as Element;
		const rowStylesheets = await this._extractRowStylesheets(xml, templateUrl);
		const attrMap : Record<string, Record<string, string>> = {};
		const handlerMap : Record<string, Record<string, string>> = {};
		const fieldMap : Record<string, string> = {};
		const idState = {next: 1};

		const template = document.createElement("template");
		const fragment = this._createFragmentFromXml(xml, {
			attrMap,
			handlerMap,
			fieldMap,
			idState,
			recordAttributes: true
		});
		template.content.appendChild(fragment);

		// Keep existing readonly behavior so row widgets render as display-only templates.
		template.content.querySelectorAll("*:not([readonly])").forEach((element : any) =>
		{
			element.readonly = true;
		});

		return {
			template,
			xml,
			attrMap,
			handlerMap,
			fieldMap,
			rowStylesheets
		};
	}

	/**
	 * Pull row-template-local styles into constructable stylesheets for the
	 * datagrid shadow root, and remove the style widgets so row clones do not
	 * inject them into the document head.
	 */
	private async _extractRowStylesheets(rowNode : Element, templateUrl : string = "") : Promise<CSSStyleSheet[]>
	{
		const root = rowNode instanceof HTMLTemplateElement ? rowNode.content : rowNode;
		const styleNodes = Array.from(root.querySelectorAll("et2-styles")) as HTMLElement[];
		if(["et2-styles"].includes(rowNode.tagName.toLowerCase()))
		{
			styleNodes.unshift(rowNode as HTMLElement);
		}

		// Start every node's work before awaiting any of it, so several <et2-styles src=...>
		// on one row template fetch concurrently instead of in series. Each node still
		// resolves to its own sheets in source order, and the flatten below preserves that.
		const perNode = styleNodes.map((styleNode) =>
		{
			const inlineCss = styleNode.getAttribute("value") || styleNode.textContent || "";
			const src = styleNode.getAttribute("src") || "";
			styleNode.remove();
			return this._loadStyleNodeSheets(inlineCss, src, templateUrl);
		});
		return (await Promise.all(perNode)).flat();
	}

	/**
	 * Resolve one <et2-styles> node's inline CSS and/or `src` into stylesheets,
	 * keeping inline before src as the node itself declares them.
	 */
	private async _loadStyleNodeSheets(inlineCss : string, src : string, templateUrl : string) : Promise<CSSStyleSheet[]>
	{
		const sheets : CSSStyleSheet[] = [];
		if(inlineCss.trim())
		{
			try
			{
				const sheet = new CSSStyleSheet();
				await sheet.replace(inlineCss);
				sheets.push(sheet);
			}
			catch(e)
			{
				this.host.egw?.()?.debug?.("error", "Et2RowProvider: failed to parse row template styles", {
					error: e
				});
			}
		}
		if(src)
		{
			const sheet = await loadStylesheet(resolveEt2StylesSrc(src, this.host.egw?.(), templateUrl));
			if(sheet)
			{
				sheets.push(sheet);
			}
		}
		return sheets;
	}

	private _cloneTemplateSource(source : Element | null) : Element | null
	{
		if(!source)
		{
			return null;
		}
		if(source instanceof HTMLTemplateElement)
		{
			const template = document.createElement("template");
			template.content.appendChild(source.content.cloneNode(true));
			return template;
		}
		return source.cloneNode(true) as Element;
	}

	private _templateUrl(source? : Element | null) : string
	{
		const domTemplate = source?.closest?.("et2-template") as any;
		if(typeof domTemplate?.getUrl == "function")
		{
			return domTemplate.getUrl();
		}
		const hostTemplate = this.host.closest?.("et2-template") as any;
		if(typeof hostTemplate?.getUrl == "function")
		{
			return hostTemplate.getUrl();
		}
		return "";
	}

	/**
	 * Deep-clone XML into DOM while optionally recording dynamic attributes for later transformAttributes().
	 *
	 * @param namespace Row-data path the node sits under, built up from container ids.
	 */
	private _createFragmentFromXml(
		node : Element,
		context : Et2RowTemplatePrepareContext,
		namespace : string[] = []
	) : DocumentFragment
	{
		const fragment = document.createDocumentFragment();
		const root = this._cloneElement(node, context, namespace);
		fragment.appendChild(root);

		const walk = (source : Element, destination : Element, sourceNamespace : string[]) =>
		{
			const childNamespace = this._childNamespace(source, destination, sourceNamespace);
			for(const child of Array.from(source.childNodes))
			{
				if(child.nodeType === Node.TEXT_NODE)
				{
					destination.appendChild(document.createTextNode(child.nodeValue || ""));
					continue;
				}

				if(child.nodeType !== Node.ELEMENT_NODE)
				{
					continue;
				}

				const childElement = this._cloneElement(child as Element, context, childNamespace);
				destination.appendChild(childElement);
				walk(child as Element, childElement, childNamespace);
			}
		};

		walk(node, root, namespace);
		return fragment;
	}

	/**
	 * Row-data path that applies to the children of one row-template node.
	 *
	 * An id on a namespace-opening widget names a sub-object of the row, not a value
	 * of its own: with row data `{sub: {name: "cheese"}}`, `<et2-vbox id="sub">`
	 * scopes its descendants so an `<et2-description id="name">` inside it binds
	 * `sub.name`.  Ids that are row expressions (`${row}[field]`, `$row_cont[...]`)
	 * address the row directly and open no namespace.
	 */
	private _childNamespace(source : Element, element : Element, namespace : string[]) : string[]
	{
		const id = source.getAttribute?.("id");
		if(!id || !Et2RowProvider.PLAIN_FIELD_ID.test(id) || !this._opensNamespace(element))
		{
			return namespace;
		}
		return [...namespace, id];
	}

	/**
	 * Does this widget's id name a namespace for its children rather than a value?
	 *
	 * Deliberately the same question etemplate asks everywhere else, answered by the
	 * widget class itself: boxes, grids, nextmatch and toolbar scope their children,
	 * while the base widget does not, so a `<et2-select id="status">` carrying option
	 * children still binds a value.  Anything we cannot ask - a plain element, or a
	 * legacy tag with no custom-element registration - names a value.
	 */
	private _opensNamespace(element : Element) : boolean
	{
		const createNamespace = (element as any)?._createNamespace;
		if(typeof createNamespace !== "function")
		{
			return false;
		}
		try
		{
			return createNamespace.call(element) === true;
		}
		catch(e)
		{
			return false;
		}
	}

	/**
	 * Row-data expression addressing one field, for use in template text.
	 *
	 * A field inside a namespace needs the nested `$[a.b]` form; a top-level one
	 * keeps the plain `$field` shorthand.
	 */
	private static _rowFieldExpression(path : string[]) : string
	{
		return path.length > 1 ? "$[" + path.join(".") + "]" : "$" + path[0];
	}

	/**
	 * Clone one source element, optionally swapping to readonly widget variant and recording placeholders.
	 */
	private _cloneElement(
		source : Element,
		context : Et2RowTemplatePrepareContext,
		namespace : string[] = []
	) : Element
	{
		const {attrMap, handlerMap, fieldMap, idState, recordAttributes} = context;
		let tag = source.tagName.toLowerCase();
		const lightweightDescription = tag === "et2-description"
		                               ? this._lightweightDescriptionElement(source, namespace)
		                               : null;
		if(lightweightDescription)
		{
			return lightweightDescription;
		}
		if(typeof window.customElements.get(tag + "_ro") !== "undefined")
		{
			tag += "_ro";
		}
		let element : HTMLElement | typeof Et2Widget;
		if(typeof window.customElements.get(tag) !== "undefined")
		{
			// Children are cloned by _createFragmentFromXml(). Do not use
			// loadWebComponent() here, as it calls loadFromXML() and would load
			// source children before the row provider appends prepared clones.
			// Attribute transformation is handled below when the element is an
			// Et2Widget and implements transformAttributes().
			element = document.createElement(tag);
		}
		else
		{
			element = document.createElement(tag);
		}

		let assignedId : string | null = null;
		const ensureRowUpgradeId = () : string | null =>
		{
			if(!recordAttributes)
			{
				return null;
			}
			if(!assignedId)
			{
				assignedId = "et2nm-" + idState.next++;
				(element as HTMLElement).setAttribute("data-et2nm-id", assignedId);
				attrMap[assignedId] = {};
			}
			return assignedId;
		};

		// Customfield renderers receive their value and shared field state during
		// row hydration even when the source template has no dynamic attributes.
		if(recordAttributes && tag === "et2-customfields-list")
		{
			ensureRowUpgradeId();
		}

		// Resolve row-independent attributes now. Keep row-scoped string
		// attributes for per-row binding, but let Et2Widget see row-scoped
		// boolean/function attributes so it can populate deferredProperties.
		const staticAttrs : Record<string, string> = {};
		for(const name of source.getAttributeNames())
		{
			const value = source.getAttribute(name);
			if(value === null)
			{
				continue;
			}
			const normalizedValue = this._normalizeLegacyRowExpressionShorthand(value);
			if(recordAttributes && this._isTemplateEventHandler(element, name))
			{
				const id = ensureRowUpgradeId();
				if(!id)
				{
					continue;
				}
				handlerMap[id] ??= {};
				handlerMap[id][name] = normalizedValue;
				continue;
			}
			if(recordAttributes && normalizedValue.includes("$"))
			{
				const id = ensureRowUpgradeId();
				if(!id)
				{
					continue;
				}
				attrMap[id][name] = normalizedValue;
				if(name !== "id" && !this._shouldDeferTemplateAttribute(element, name))
				{
					element.setAttribute(name, normalizedValue);
				}
				if(this._shouldTransformForDeferredProperty(element, name))
				{
					staticAttrs[name] = normalizedValue;
				}
			}
			else
			{
				element.setAttribute(name, normalizedValue);
				staticAttrs[name] = normalizedValue;
			}
		}
		const et2Element = element as HTMLElement & {
			deferredProperties? : Record<string, string>;
			transformAttributes? : (attrs : Record<string, string>) => void;
		};
		this._setTemplateArrayManagers(et2Element);
		if(typeof et2Element.transformAttributes === "function" && Object.keys(staticAttrs).length > 0)
		{
			try
			{
				et2Element.transformAttributes(staticAttrs);
			}
			catch(e)
			{
				this.host.egw?.()?.debug?.("error", "Et2RowProvider: failed to transform row template widget", {
					element: element?.tagName || "",
					error: e
				});
			}
		}
		if(recordAttributes && et2Element.deferredProperties)
		{
			const id = ensureRowUpgradeId();
			if(id)
			{
				Object.assign(attrMap[id], et2Element.deferredProperties);
			}
		}

		// A plain identifier id is either a namespace or a direct row binding, never both.
		// A namespace-opening widget (a box, a grid) has already had its id folded into
		// `namespace` by _childNamespace(); everything else binds a value: id="host_name"
		// means this row's host_name, and inside <et2-vbox id="sub"> it means the row's
		// sub.host_name.  Record the path built from the template ids, because the live
		// element gets namespaced by its container (eg. "nm_host_name") and would no
		// longer match the row field it names.
		if(recordAttributes && !this._opensNamespace(element))
		{
			const sourceId = source.getAttribute("id");
			if(sourceId && Et2RowProvider.PLAIN_FIELD_ID.test(sourceId))
			{
				const fieldId = ensureRowUpgradeId();
				if(fieldId)
				{
					fieldMap[fieldId] = [...namespace, sourceId].join(".");
				}
			}
		}

		return element;
	}

	private _shouldDeferTemplateAttribute(element : Element, attributeName : string) : boolean
	{
		// Et2Widget's data setter rewrites dataset and removes data-et2nm-id,
		// which makes the row upgrade pass skip this widget entirely.
		return attributeName === "data" && typeof (element as any).transformAttributes === "function";
	}

	/**
	 * Event handlers are delegated by Et2Datagrid, rather than installed on every
	 * virtualized row widget.  Only declared Function properties qualify, so
	 * ordinary attributes beginning with "on" retain their normal behaviour.
	 */
	private _isTemplateEventHandler(element : Element, attributeName : string) : boolean
	{
		if(!attributeName.startsWith("on"))
		{
			return false;
		}
		const widgetClass : any = window.customElements.get(element.localName);
		const property = widgetClass?.getPropertyOptions?.(attributeName);
		return (typeof property === "object" ? property?.type : property) === Function;
	}

	private _setTemplateArrayManagers(element : any)
	{
		const contentMgr = this.host.getArrayMgr?.("content");
		if(contentMgr && element.setArrayMgr)
		{
			element.setArrayMgr("content", contentMgr);
		}
		const modificationsMgr = this.host.getArrayMgr?.("modifications");
		if(modificationsMgr && element.setArrayMgr)
		{
			element.setArrayMgr("modifications", modificationsMgr);
		}
	}

	private _hasTemplateChildren(source : Element) : boolean
	{
		return Array.from(source.childNodes).some((child) =>
			child.nodeType === Node.ELEMENT_NODE ||
			child.nodeType === Node.TEXT_NODE && !!child.nodeValue?.trim()
		);
	}

	private _shouldTransformForDeferredProperty(element : Element, attributeName : string) : boolean
	{
		const widgetClass : any = window.customElements.get(element.localName);
		if(!widgetClass?.getPropertyOptions)
		{
			return false;
		}
		const propertyName = this._attributeToPropertyName(attributeName);
		const property = widgetClass.getPropertyOptions(propertyName);
		const type = typeof property === "object" ? property.type : property;
		return type === Boolean || type === Function;
	}

	private _attributeToPropertyName(attribute : string) : string
	{
		if(attribute === "select_options" || attribute.indexOf("_") === -1)
		{
			return attribute;
		}
		const parts = attribute.split("_");
		if(attribute === "parent_node")
		{
			parts[1] = "Id";
		}
		const first = parts.shift() || "";
		return first + parts.map((part) => part[0].toUpperCase() + part.substring(1)).join("");
	}

	/**
	 * Replace plain readonly row descriptions with native text.
	 *
	 * Datagrid rows can contain many simple et2-description widgets. If the
	 * description does not need link, tooltip, translation, or event behaviour,
	 * native text avoids creating a Lit element and shadow root for every row.
	 *
	 * @param namespace Row-data path opened by the containers this description sits in.
	 */
	private _lightweightDescriptionElement(source : Element, namespace : string[] = []) : HTMLElement | null
	{
		const allowedAttributes = new Set([
			"id",
			"value",
			"class",
			"align",
			"style",
			"readonly",
			"noLang",
			"nolang",
			"no_lang"
		]);
		for(const name of source.getAttributeNames())
		{
			if(!allowedAttributes.has(name))
			{
				return null;
			}
		}

		const id = source.getAttribute("id");
		const value = source.getAttribute("value");
		const idIsDynamic = !!id && (id.includes("$") || id.includes("{"));
		const plainFieldId = !!id && Et2RowProvider.PLAIN_FIELD_ID.test(id);
		// A plain id in a row description is a row-value binding, scoped by whatever
		// containers it sits in.  Preserve that contract when replacing the widget
		// with native text.
		const textExpression = idIsDynamic
		                       ? id
		                       : value ?? (plainFieldId ? Et2RowProvider._rowFieldExpression([...namespace, id!]) : id);
		if(!textExpression)
		{
			return null;
		}
		const noLang = source.hasAttribute("noLang") || source.hasAttribute("nolang") || source.hasAttribute("no_lang");
		const dynamicText = textExpression.includes("$") || textExpression.includes("{");
		if(!noLang && !dynamicText)
		{
			return null;
		}

		const element = document.createElement("span");
		const className = source.getAttribute("class");
		if(className)
		{
			element.setAttribute("class", className);
		}
		const style = source.getAttribute("style");
		if(style)
		{
			element.setAttribute("style", style);
		}
		const align = source.getAttribute("align");
		if(align)
		{
			element.setAttribute("data-align", align);
		}
		// Mark as an individually addressable widget target, so row context actions
		// (eg. "Copy to OS clipboard") resolve to this field instead of the whole row.
		if(id)
		{
			element.setAttribute("data-et2-id", id);
		}
		element.textContent = this._normalizeLegacyRowExpressionShorthand(textExpression);
		return element;
	}

	/**
	 * Normalize legacy row-expression shorthand so Datagrid row context resolves it like classic Nextmatch.
	 *
	 * Legacy row references become the direct row-data syntax used by the
	 * datagrid. Template source remains unchanged; this is only the prepared
	 * row representation. Content/template references stay untouched.
	 */
	private _normalizeLegacyRowExpressionShorthand(value : string) : string
	{
		if(!value || value.indexOf("$") === -1)
		{
			return value;
		}
		// A single, simple bracket key (no nested path) collapses to the bare
		// `$field` shorthand, matching how `${field}` normalizes below. Only
		// an actual nested path needs the `$[a.b]` form.
		const bracketPathToShorthand = (fields : string) : string =>
		{
			const path = fields.slice(1, -1).split("][").join(".");
			return /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(path) ? "$" + path : "$[" + path + "]";
		};
		let normalized = value;
		normalized = normalized.replace(/\$row_cont((?:\[[^\]]+\])+)/g, (_match, fields) => bracketPathToShorthand(fields));
		normalized = normalized.replace(/\$\{row\}((?:\[[^\]]+\])+)/g, (_match, fields) => bracketPathToShorthand(fields));
		normalized = normalized.replace(/\{\$row\}((?:\[[^\]]+\])+)/g, (_match, fields) => bracketPathToShorthand(fields));
		normalized = normalized.replace(/\$row\.([a-zA-Z0-9_.]+)/g, (_match, field) => bracketPathToShorthand("[" + field + "]"));
		normalized = normalized.replace(
			/\$\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g,
			(_match, token) => token === "row" ? "${row}" : "$" + token
		);
		return normalized;
	}

	/**
	 * Normalize legacy <row> templates into proper table row markup.
	 */
	private _normalizeTemplateRowNode(rowNode : Element, view : Et2DatagridView = "row") : Element
	{
		if(!rowNode)
		{
			return rowNode;
		}

		const tagName = rowNode.tagName.toLowerCase();
		if(view === "tile")
		{
			return this._normalizeTileTemplateRowNode(rowNode);
		}
		if(tagName !== "row" && tagName !== "tr")
		{
			return rowNode.cloneNode(true) as Element;
		}

		const newRow = document.createElement("tr");
		for(let i = 0; i < rowNode.attributes.length; i++)
		{
			newRow.setAttribute(rowNode.attributes[i].name, rowNode.attributes[i].value);
		}

		for(const child of Array.from(rowNode.childNodes))
		{
			if(child.nodeType === Node.TEXT_NODE)
			{
				if(!child.nodeValue || child.nodeValue.trim() === "")
				{
					continue;
				}
				const tdText = document.createElement("td");
				tdText.appendChild(document.createTextNode(child.nodeValue));
				newRow.appendChild(tdText);
				continue;
			}
			if(child.nodeType !== Node.ELEMENT_NODE)
			{
				continue;
			}
			const childElement = child as Element;
			const tag = childElement.tagName.toLowerCase();
			if(tag === "td" || tag === "th")
			{
				newRow.appendChild(childElement.cloneNode(true));
			}
			else
			{
				const td = document.createElement("td");
				td.appendChild(childElement.cloneNode(true));
				newRow.appendChild(td);
			}
		}

		return newRow;
	}

	/**
	 * Give columns without an explicit minWidth a content-aware floor, so a
	 * column can never be squeezed narrower than what its own widgets need.
	 * Row cells align positionally with columns (one <td> per column, same
	 * order as the header), so column[index] pairs with cells[index].
	 */
	private _applyDefaultColumnMinWidths(columns : Et2DatagridColumn[], normalizedRowNode : Element | null) : void
	{
		if(!normalizedRowNode)
		{
			return;
		}
		const cells = Array.from(normalizedRowNode.children) as Element[];
		columns.forEach((column, index) =>
		{
			if(column.minWidth)
			{
				return;
			}
			const cell = cells[index];
			if(!cell)
			{
				return;
			}
			column.minWidth = this._defaultMinWidthForCell(cell);
		});
	}

	/**
	 * Sniff a row cell's widgets for date/date-time content to pick a default
	 * minimum width (date-time needs more room than a bare date). Uses the
	 * largest match so a stacked cell (e.g. an et2-vbox with mixed widgets)
	 * gets the floor its widest widget actually needs.
	 */
	private _defaultMinWidthForCell(cell : Element) : string
	{
		const tags = new Set<string>([cell.tagName.toLowerCase()]);
		cell.querySelectorAll("*").forEach((el) => tags.add(el.tagName.toLowerCase()));
		if(tags.has("et2-date-time"))
		{
			return "9em";
		}
		if(tags.has("et2-date"))
		{
			return "5em";
		}
		return "3em";
	}

	/**
	 * Normalize legacy tile rows into a non-table item root.
	 */
	private _normalizeTileTemplateRowNode(rowNode : Element) : Element
	{
		const tile = document.createElement("div");
		for(let i = 0; i < rowNode.attributes.length; i++)
		{
			tile.setAttribute(rowNode.attributes[i].name, rowNode.attributes[i].value);
		}
		tile.classList.add("tile");

		const appendChild = (child : Node) =>
		{
			if(child.nodeType === Node.TEXT_NODE)
			{
				if(child.nodeValue && child.nodeValue.trim() !== "")
				{
					tile.appendChild(document.createTextNode(child.nodeValue));
				}
				return;
			}
			if(child.nodeType !== Node.ELEMENT_NODE)
			{
				return;
			}
			const childElement = child as Element;
			const tag = childElement.tagName.toLowerCase();
			if(tag === "td" || tag === "th")
			{
				for(const nested of Array.from(childElement.childNodes))
				{
					appendChild(nested);
				}
				return;
			}
			tile.appendChild(childElement.cloneNode(true));
		};

		for(const child of Array.from(rowNode.childNodes))
		{
			appendChild(child);
		}

		return tile;
	}

	/**
	 * Infer the template's intended layout without changing server data.
	 */
	private _templateView(rowNode : Element | null) : Et2DatagridView
	{
		return rowNode?.classList?.contains("tile") ? "tile" : "row";
	}

	/**
	 * Extract fixed tile dimensions from generic tile markup when available.
	 */
	private _tileLayoutFromRowNode(rowNode : Element | null) : Et2DatagridTileLayout
	{
		const tileContent = this._tileContentElement(rowNode);
		const width =
			rowNode?.getAttribute("data-tile-width") ||
			rowNode?.getAttribute("tile-width") ||
			tileContent?.getAttribute("width") ||
			(tileContent as HTMLElement | null)?.style?.width ||
			undefined;
		const height =
			rowNode?.getAttribute("data-tile-height") ||
			rowNode?.getAttribute("tile-height") ||
			tileContent?.getAttribute("height") ||
			(tileContent as HTMLElement | null)?.style?.height ||
			undefined;
		return {
			width: this._normalizeCssLength(width) || DEFAULT_TILE_LAYOUT.width,
			height: this._normalizeCssLength(height) || DEFAULT_TILE_LAYOUT.height,
			gap: DEFAULT_TILE_LAYOUT.gap,
			padding: DEFAULT_TILE_LAYOUT.padding
		};
	}

	private _tileContentElement(rowNode : Element | null) : Element | null
	{
		if(!rowNode)
		{
			return null;
		}
		const explicitlySized = rowNode.querySelector("[data-tile-width],[data-tile-height],[tile-width],[tile-height],[width],[height]");
		if(explicitlySized)
		{
			return explicitlySized;
		}
		for(const child of Array.from(rowNode.children))
		{
			const tag = child.tagName.toLowerCase();
			if(tag !== "td" && tag !== "th")
			{
				return child;
			}
			const nested = Array.from(child.children).find((element) =>
				element.tagName.toLowerCase() !== "td" && element.tagName.toLowerCase() !== "th"
			);
			if(nested)
			{
				return nested;
			}
		}
		return null;
	}

	private _normalizeCssLength(value? : string | null) : string | undefined
	{
		const length = String(value || "").trim();
		if(!length)
		{
			return undefined;
		}
		return /^\d+(\.\d+)?$/.test(length) ? `${length}px` : length;
	}

	/**
	 * Convert slot-provided element to HTMLTemplateElement.
	 */
	private _toTemplate(source : Element) : HTMLTemplateElement
	{
		if(source instanceof HTMLTemplateElement)
		{
			return source;
		}
		const template = document.createElement("template");
		template.content.appendChild(source.cloneNode(true));
		return template;
	}

	/**
	 * Pick the effective row root from a slot source.
	 */
	private _resolveSlotRowElement(rowSource : Element | null) : Element | null
	{
		if(!rowSource)
		{
			return null;
		}
		if(rowSource instanceof HTMLTemplateElement)
		{
			return rowSource.content.firstElementChild as Element | null;
		}
		return rowSource;
	}

	/**
	 * Resolve user-visible header title from known Nextmatch header widgets.
	 */
	private _extractHeaderTitle(node : Element) : string
	{
		const tag = node.tagName.toLowerCase();
		if(tag.includes("nextmatch"))
		{
			return (
				// Node has already been read, maybe put into the DOM
				(node as any).label || (node as any).emptyLabel ||
				// Maybe reading raw template
				node.getAttribute("label") || node.getAttribute("emptyLabel") || node.getAttribute("title") ||
				""
			).trim();
		}

		const labels = Array.from(node.querySelectorAll("*"))
			.map((element) => ((element as any).label || (element as any).emptyLabel || element.getAttribute("label") || element.getAttribute("emptyLabel") || element.getAttribute("title") || element.textContent || "").trim())
			.filter(Boolean);

		return [...new Set(labels)].join(" / ");
	}

	/**
	 * Resolve stable column key from explicit attributes or descendant field names.
	 */
	private _getColumnKey(column : Element, index : number) : string
	{
		const explicit = column.getAttribute("data-key") || column.getAttribute("data-field") || column.getAttribute("name") || column.id;
		if(explicit)
		{
			return explicit;
		}

		const parts = Array.from(column.querySelectorAll("[name],[data-field],[data-key],[id]"))
			.map((element : Element) => element.getAttribute("name") || element.getAttribute("data-field") || element.getAttribute("data-key") || element.id)
			.filter(Boolean);
		if(parts.length)
		{
			return [...new Set(parts)].join("_");
		}

		return "col" + index;
	}

	/**
	 * Return the first node assigned to a named slot on the host.
	 */
	private _getSlotContent(name : string) : Element | null
	{
		const nodes = Array.from(this.host.querySelectorAll(`[slot="${name}"]`));
		if(!nodes.length)
		{
			return null;
		}
		const node = nodes[0];
		return node as Element;
	}
}
