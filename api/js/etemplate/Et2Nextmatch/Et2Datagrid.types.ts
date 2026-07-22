export interface Et2DatagridColumn
{
	key : string;
	title : string;
	header?: Element;
	width? : string;
	minWidth? : string;
	maxWidth? : string;
	/** Column is fixed hidden/unavailable in the column chooser. */
	disabled? : string | boolean;
	/** Column is currently hidden but may be shown by user preference. */
	hidden? : boolean;
}

export type Et2DatagridView = "row" | "tile";

export interface Et2DatagridTileLayout
{
	width? : string;
	height? : string;
	gap? : string;
	padding? : string;
}

export interface Et2DatagridTemplateData
{
	/** Source template id used for persisted column preference keys. */
	rowTemplateId? : string;
	/** Visual layout expected by the row template. Defaults to row. */
	view? : Et2DatagridView;
	/** Optional tile sizing hints used by virtualized tile view. */
	tileLayout? : Et2DatagridTileLayout;
	rowTemplate : HTMLTemplateElement | null;
	rowTemplateXml : Element | null;
	/** Stored row-template attributes keyed by generated widget id. */
	rowTemplateAttrMap : Record<string, Record<string, string>>;
	/** Row-template event handlers keyed by generated widget id. */
	rowTemplateHandlerMap?: Record<string, Record<string, string>>;
	/** Styles extracted from <et2-styles> inside the row template. */
	rowStylesheets? : CSSStyleSheet[];
	/** Optional row-height hint supplied by template parsing. */
	rowHeight?: number;
	loaderTemplate : HTMLTemplateElement | null;
	noResultsTemplate? : HTMLTemplateElement | null;
	columns : Et2DatagridColumn[];
	/** Physical row-template column order before user/preference reordering. */
	sourceColumns? : Et2DatagridColumn[];
}

export interface Et2DatagridRow
{
	id : string;
	data? : any;
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
	getRowData?(rowId : string) : any;
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
	/** True when the source gesture replaced selection instead of extending/toggling it. */
	replaceSelection? : boolean;
}

export type Et2DatagridSelectionMode = "none" | "single" | "multiple";

/**
 * Context passed to generic expanded-row render hooks.
 *
 * Datagrid owns row expansion mechanics and column alignment. Consumers own the
 * expanded content, which can be another datagrid or any other detail UI.
 */
export interface Et2DatagridExpandedRowContext
{
	/** Parent data row whose expanded content is being rendered. */
	row : Et2DatagridRow;
	/** Absolute row index of the parent row in the virtualized dataset. */
	rowIndex : number;
	/** Datagrid instance rendering the parent row. */
	parentGrid : HTMLElement;
	/** Resolved column track sizing from the parent row. */
	columnSizes : string;
	/** Resolved leading metadata column width from the parent row. */
	metaColumnWidth : string;
}

/**
 * Generic expandable-row configuration.
 *
 * Keep this free of Nextmatch-specific hierarchy fields. Nextmatch maps its
 * `parent_id` / `is_parent` contract into these hooks.
 */
export interface Et2DatagridExpansionConfig
{
	/** Return true when a realized data row should render an expander. */
	isExpandable(row : Et2DatagridRow, rowIndex : number) : boolean;
	/** Render expanded content immediately after the parent row. */
	renderExpandedContent(context : Et2DatagridExpandedRowContext) : unknown;
	/** Optional controlled set of expanded row ids. */
	expandedRowIds? : Set<string>;
	/** Called with the next expanded-id set when expansion state changes. */
	onExpandedRowIdsChanged?(expandedRowIds : Set<string>) : void;
	/** Optional empty-state renderer reserved for consumers with custom detail UI. */
	emptyTemplate?(context : Et2DatagridExpandedRowContext) : unknown;
}

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
