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

export interface Et2DatagridDataProvider
{
	fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>;
	getQuerySignature?() : string;
	getDataStorePrefix?() : string;
}

export interface Et2DatagridSelectionDetail
{
	selectedRowIds : string[];
	selectedRows : any[];
	activeRowId : string | null;
	activeRowIndex : number;
}

export type Et2DatagridSelectionMode = "none" | "single" | "multiple";

export interface Et2DatagridRowCustomizeContext
{
	rowElement : HTMLElement;
	rowData : any;
	rowIndex : number;
	metaCell : HTMLTableCellElement;
}

export type Et2DatagridRowCustomizer = (context : Et2DatagridRowCustomizeContext) => void;
