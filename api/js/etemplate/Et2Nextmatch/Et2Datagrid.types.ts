export interface Et2DatagridColumn
{
	key : string;
	title : string;
	header?: Element;
	width? : string;
	minWidth? : string;
	maxWidth? : string;
	// Not allowed to be shown
	disabled? : string | boolean;
	// Not currently shown, but could be
	hidden? : boolean;
}

export interface Et2DatagridTemplateData
{
	/** Source template id used for persisted column preference keys. */
	rowTemplateId? : string;
	rowTemplate : HTMLTemplateElement | null;
	rowTemplateXml : Element | null;
	rowTemplateAttrMap : Record<string, Record<string, string>>;
	rowHeight?: number;
	loaderTemplate : HTMLTemplateElement | null;
	columns : Et2DatagridColumn[];
}

export interface Et2DatagridRow
{
	id : string;
	data : any;
}

export interface Et2DatagridPageResult
{
	rows : Et2DatagridRow[];
	total? : number | null;
}

export interface Et2DatagridRefreshResult
{
	rows : Et2DatagridRow[];
	removedRowIds : string[];
}

export const Et2DatagridUpdateTypes = {
	UPDATE_IN_PLACE: "update-in-place",
	UPDATE: "update",
	EDIT: "edit",
	DELETE: "delete",
	ADD: "add",
} as const;
export type Et2DatagridUpdateType = typeof Et2DatagridUpdateTypes[keyof typeof Et2DatagridUpdateTypes];
export interface Et2DatagridDataProvider
{
	fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>;
	getQuerySignature?() : string;
	getDataStorePrefix?() : string;
	normalizeRowId(rowId : string | number, ensurePrefix? : boolean) : string;
	toProviderRowId(dataStoreRowId : string) : string;

	refresh(row_ids : string[], type : Et2DatagridUpdateType) : Promise<Et2DatagridRefreshResult>;
}

export interface Et2DatagridSelectionDetail
{
	selectedRowIds : string[];
	allSelected? : boolean;
	selectedRows : any[];
	activeRowId : string | null;
	activeRowIndex : number;
}

export type Et2DatagridSelectionMode = "none" | "single" | "multiple";

/**
 * Context object passed to row customizers for per-row presentation tweaks.
 */
export interface Et2DatagridRowCustomizeContext
{
	/** Row root element rendered by datagrid (usually `<tr>`). */
	rowElement : HTMLElement;
	/** Raw row data object for this row. */
	rowData : any;
	/** Absolute row index in the current virtualized dataset. */
	rowIndex : number;
	/** Leading metadata cell (column 0) for indicators/actions. */
	metaCell : HTMLTableCellElement;
}

/** Hook for consumers to customize each row/meta-cell after row creation. */
export type Et2DatagridRowCustomizer = (context : Et2DatagridRowCustomizeContext) => void;
