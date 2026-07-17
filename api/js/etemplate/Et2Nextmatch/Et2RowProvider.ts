import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Template} from "../Et2Template/Et2Template";
import {
	Et2DatagridColumn,
	Et2DatagridTileLayout,
	Et2DatagridView,
	Et2DatagridTemplateData
} from "./Et2Datagrid.types";
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
 * Resolves nextmatch row definitions from a template name or from slotted markup.
 * It returns normalized columns and a prepared row template for Et2Datagrid.
 */
export class Et2RowProvider
{
	private static readonly CATEGORY_CLASS_PLACEHOLDER_FIELDS = ["cat", "cat_id", "category", "info_cat"] as const;

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

			return await this._fromTemplateRoot(xml || tpl);
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
	 * Resolve row/column metadata from named slots on the host element.
	 */
	async fromSlots() : Promise<Et2DatagridTemplateData | null>
	{
		const headerSource = this._getSlotContent("columns");
		const rowSource = this._getSlotContent("row");
		const loaderSource = this._getSlotContent("loader");

		const resolvedHeader = headerSource ? this._resolveSlotHeaderElement(headerSource) : null;
		const columnMeta = resolvedHeader ? this._extractSlotColumnMeta(resolvedHeader) : [];
		const columns = resolvedHeader
		                ? this._extractColumnsFromHeaderNode(resolvedHeader).map((column, index) => ({...columnMeta[index], ...column}))
		                : [];
		const rowElement = this._resolveSlotRowElement(rowSource);
		const view = this._templateView(rowElement);
		const normalizedRowElement = rowElement ? this._normalizeTemplateRowNode(rowElement, view) : null;
		const prepared = normalizedRowElement ? await this._prepareRowTemplate(normalizedRowElement, columns) : null;
		const loaderTemplate = loaderSource ? this._toTemplate(loaderSource) : null;

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
			loaderTemplate
		};
	}

	/**
	 * Parse template XML root to produce datagrid-ready template data.
	 */
	private async _fromTemplateRoot(tplRoot : Element) : Promise<Et2DatagridTemplateData>
	{
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

		const columns : Et2DatagridColumn[] = this._extractColumnsFromHeaderNode(headerNode)
			.map((c, index) => {return {...colMeta[index], ...c}});
		const view = this._templateView(rowNode);
		const normalizedRowNode = this._normalizeTemplateRowNode(rowNode, view);
		const prepared = await this._prepareRowTemplate(normalizedRowNode, columns);

		return {
			view,
			tileLayout: view === "tile" ? this._tileLayoutFromRowNode(rowNode) : undefined,
			columns,
			rowTemplateId: tplRoot.getAttribute("id") || tplRoot.id || normalizedRowNode?.id || undefined,
			rowTemplate: prepared?.template ?? null,
			rowTemplateXml: prepared?.xml ?? null,
			rowTemplateAttrMap: prepared?.attrMap ?? {},
			loaderTemplate: null
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
	 */
	private _extractColumnsFromHeaderNode(headerNode : Element) : Et2DatagridColumn[]
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
	private async _prepareRowTemplate(rowNode : Element, columns : Et2DatagridColumn[]) : Promise<{
		template : HTMLTemplateElement;
		xml : Element;
		attrMap : Record<string, Record<string, string>>
	} | null>
	{
		if(!rowNode)
		{
			return null;
		}

		const xml = rowNode.cloneNode(true) as Element;
		const attrMap : Record<string, Record<string, string>> = {};
		const idState = {next: 1};

		const template = document.createElement("template");
		const fragment = this._createFragmentFromXml(xml, attrMap, idState, true);
		template.content.appendChild(fragment);

		// Keep existing readonly behavior so row widgets render as display-only templates.
		template.content.querySelectorAll("*:not([readonly])").forEach((element : any) =>
		{
			element.readonly = true;
		});

		return {
			template,
			xml,
			attrMap
		};
	}

	/**
	 * Deep-clone XML into DOM while optionally recording dynamic attributes for later transformAttributes().
	 */
	private _createFragmentFromXml(
		node : Element,
		attrMap : Record<string, Record<string, string>>,
		idState : { next : number },
		recordAttributes : boolean = false
	) : DocumentFragment
	{
		const fragment = document.createDocumentFragment();
		const root = this._cloneElement(node, attrMap, idState, recordAttributes);
		fragment.appendChild(root);

		const walk = (source : Element, destination : Element) =>
		{
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

				const childElement = this._cloneElement(child as Element, attrMap, idState, recordAttributes);
				destination.appendChild(childElement);
				walk(child as Element, childElement);
			}
		};

		walk(node, root);
		return fragment;
	}

	/**
	 * Clone one source element, optionally swapping to readonly widget variant and recording placeholders.
	 */
	private _cloneElement(
		source : Element,
		attrMap : Record<string, Record<string, string>>,
		idState : { next : number },
		recordAttributes : boolean
	) : Element
	{
		let tag = source.tagName.toLowerCase();
		const lightweightDescription = tag === "et2-description"
		                               ? this._lightweightDescriptionElement(source)
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
			if(this._hasTemplateChildren(source))
			{
				// Children are cloned by _createFragmentFromXml(). Do not use
				// loadWebComponent() here, as it calls loadFromXML() and would load
				// the source children before the row provider appends its prepared
				// child clones.
				element = document.createElement(tag);
			}
			else
			{
				element = loadWebComponent(tag, source, null);
			}
		}
		else
		{
			element = document.createElement(tag);
		}

		let assignedId : string | null = null;
		if(recordAttributes)
		{
			assignedId = "et2nm-" + idState.next++;
			element.setAttribute("data-et2nm-id", assignedId);
			attrMap[assignedId] = {};
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
			if(recordAttributes && assignedId && normalizedValue.includes("$"))
			{
				attrMap[assignedId][name] = normalizedValue;
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
		if(recordAttributes && assignedId && et2Element.deferredProperties)
		{
			Object.assign(attrMap[assignedId], et2Element.deferredProperties);
		}


		return element;
	}

	private _shouldDeferTemplateAttribute(element : Element, attributeName : string) : boolean
	{
		// Et2Widget's data setter rewrites dataset and removes data-et2nm-id,
		// which makes the row upgrade pass skip this widget entirely.
		return attributeName === "data" && typeof (element as any).transformAttributes === "function";
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
	 */
	private _lightweightDescriptionElement(source : Element) : HTMLElement | null
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
		const textExpression = idIsDynamic ? id : value ?? id;
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
		element.textContent = this._normalizeLegacyRowExpressionShorthand(textExpression);
		return element;
	}

	/**
	 * Normalize legacy row-expression shorthand so Datagrid row context resolves it like classic Nextmatch.
	 *
	 * `$field` becomes `$row_cont[field]`, `${field}` becomes `${row}[field]`.
	 * Already explicit row/content references are preserved.
	 */
	private _normalizeLegacyRowExpressionShorthand(value : string) : string
	{
		if(!value || value.indexOf("$") === -1)
		{
			return value;
		}
		let normalized = value;
		normalized = normalized.replace(
			/\$\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g,
			(_match, token) => token === "row" ? "${row}" : "${row}[" + token + "]"
		);
		normalized = normalized.replace(
			/\$([a-zA-Z_][a-zA-Z0-9_]*)\b/g,
			(match, token) => ["row", "row_cont", "cont", "_cont"].includes(token) ? match : "$row_cont[" + token + "]"
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
