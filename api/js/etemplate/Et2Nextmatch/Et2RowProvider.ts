import {loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2DatagridColumn, Et2DatagridTemplateData} from "./Et2Datagrid.types";

interface Et2RowProviderHost extends HTMLElement
{
	egw? : Function;
	getArrayMgr? : Function;
}

/**
 * Resolves nextmatch row definitions from a template name or from slotted markup.
 * It returns normalized columns and a prepared row template for Et2Datagrid.
 */
export class Et2RowProvider
{
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

			return this._fromTemplateRoot(xml || tpl);
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
					try { tpl.destroy(); } catch(e) {}
					try { tpl.remove(); } catch(e) {}
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
	fromSlots() : Et2DatagridTemplateData | null
	{
		const headerSource = this._getSlotContent("header") || this._getSlotContent("columns");
		const rowSource = this._getSlotContent("row");
		const loaderSource = this._getSlotContent("loader") || this._getSlotContent("row-loader");

		const columns = headerSource ? this._extractColumnsFromHeaderNode(headerSource) : [];
		const rowElement = this._resolveSlotRowElement(rowSource);
		const prepared = rowElement ? this._prepareRowTemplate(rowElement, columns) : null;
		const loaderTemplate = loaderSource ? this._toTemplate(loaderSource) : null;

		if(!columns.length && !prepared)
		{
			return null;
		}

		return {
			columns,
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
	private _fromTemplateRoot(tplRoot : Element) : Et2DatagridTemplateData
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
		const normalizedRowNode = this._normalizeTemplateRowNode(rowNode);
		const prepared = this._prepareRowTemplate(normalizedRowNode, columns);

		return {
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
	 * Apply persisted user width preferences.
	 * Current preference path is kept for compatibility with existing Nextmatch behavior.
	 */
	private _applySavedWidths(columns : Et2DatagridColumn[])
	{
		try
		{
			const sizes = this.host.egw?.()?.preference("nextmatch-addressbook.index.rows-size", "addressbook");
			if(sizes)
			{
				for(const col of columns)
				{
					if(typeof sizes[col.key] !== "undefined")
					{
						col.width = String(sizes[col.key]);
					}
				}
			}
		}
		catch(e)
		{
		}
	}

	/**
	 * Parse column definitions from header
	 */
	private _extractColumnsFromHeaderNode(headerNode : Element) : Et2DatagridColumn[]
	{
		const nodes = headerNode instanceof HTMLTemplateElement ?
			Array.from(headerNode.content.children) :
			(Array.from(headerNode.children).length ? Array.from(headerNode.children) : [headerNode]);
		const columns : Et2DatagridColumn[] = [];
		nodes.forEach((node, index) =>
		{
			if(node.nodeType !== Node.ELEMENT_NODE)
			{
				return;
			}
			const element = node as Element;
			const widget = this.host.createElementFromNode(node);
			const key = this._getColumnKey(element, index);
			const title = this._extractHeaderTitle(element) || (element.textContent || element.getAttribute("title") || key).trim();
			const col : Et2DatagridColumn = {key, title, header: widget};
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
	 * Compile row template DOM into a reusable HTMLTemplateElement with tracked dynamic attributes.
	 */
	private _prepareRowTemplate(rowNode : Element, columns : Et2DatagridColumn[]) : { template : HTMLTemplateElement; xml : Element; attrMap : Record<string, Record<string, string>> } | null
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
		if(typeof window.customElements.get(tag + "_ro") !== "undefined")
		{
			tag += "_ro";
		}
		const element = document.createElement(tag);

		let assignedId : string | null = null;
		if(recordAttributes)
		{
			assignedId = "et2nm-" + idState.next++;
			element.setAttribute("data-et2nm-id", assignedId);
			attrMap[assignedId] = {};
		}

		for(const name of source.getAttributeNames())
		{
			const value = source.getAttribute(name);
			if(value === null)
			{
				continue;
			}
			element.setAttribute(name, value);
			if(recordAttributes && assignedId && value.includes("$"))
			{
				attrMap[assignedId][name] = value;
			}
		}

		return element;
	}

	/**
	 * Normalize legacy <row> templates into proper table row markup.
	 */
	private _normalizeTemplateRowNode(rowNode : Element) : Element
	{
		if(!rowNode || rowNode.tagName.toLowerCase() !== "row")
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
			return (node.getAttribute("label") || node.getAttribute("emptyLabel") || node.getAttribute("title") || "").trim();
		}

		const labels = Array.from(node.querySelectorAll("*"))
			.map((element) => (element.getAttribute("label") || element.getAttribute("emptyLabel") || element.getAttribute("title") || element.textContent||"").trim())
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
	 * Return first node assigned to a named slot on the host.
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
