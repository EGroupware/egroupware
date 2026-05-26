import {Et2DatagridColumn} from "./Et2Datagrid.types";

/**
 * Shape consumed by the existing `et2-nextmatch-columnselection` widget.
 *
 * The chooser component expects id/visibility metadata in a specific format.
 * Keeping that mapping here avoids duplicating ad-hoc transforms in the grid.
 */
export interface Et2DatagridColumnSelectionItem
{
	id : string;
	title : string;
	caption : string;
	widget : Node | null;
	visibility : boolean;
}

/**
 * Centralized column-state helper for visibility, disabled-state, chooser mapping,
 * and column-order application.  Column visibility/order rules
 * are state policy and can grow independently, so they live in a dedicated helper.
 */
export class Et2DatagridColumnState
{
	/**
	 * Convert a column key into chooser-safe id because the ColumnSelection selection menu
	 * cannot handle spaces in item values.
	 */
	encodeSelectionId(key : string) : string
	{
		return String(key).replaceAll(" ", "___");
	}

	/**
	 * Convert chooser id back to original column key.
	 *
	 * We preserve legacy key values (including spaces) in grid configuration.
	 */
	decodeSelectionId(id : string) : string
	{
		return String(id).replaceAll("___", " ");
	}

	/**
	 * Resolve Etemplate style boolean/expression values.
	 *
	 * Column `disabled` can be a real boolean or expression string, depending on
	 * server-side template generation.
	 */
	resolveColumnBoolean(value : unknown, parseExpression? : (expression : string) => boolean) : boolean
	{
		if(typeof value === "boolean")
		{
			return value;
		}
		if(typeof value === "undefined" || value === null)
		{
			return false;
		}
		const expression = String(value).trim();
		if(expression === "")
		{
			return false;
		}
		if(parseExpression)
		{
			try
			{
				return !!parseExpression(expression);
			}
			catch(e)
			{
			}
		}
		const normalized = expression.toLowerCase();
		return normalized === "true" || normalized === "1";
	}

	/**
	 * Determine whether a column is disabled in chooser interactions.
	 *
	 * Disabled columns are always excluded from ColumnSelection chooser options.
	 */
	isColumnDisabled(column : Et2DatagridColumn | null | undefined, parseExpression? : (expression : string) => boolean) : boolean
	{
		if(!column)
		{
			return false;
		}
		return this.resolveColumnBoolean(column.disabled, parseExpression);
	}

	/**
	 * Determine whether a column should be hidden from rendered grid tracks.
	 *
	 * Hidden and disabled are both non-visible states in datagrid rendering.
	 */
	isColumnHidden(column : Et2DatagridColumn | null | undefined, parseExpression? : (expression : string) => boolean) : boolean
	{
		if(!column)
		{
			return false;
		}
		return !!column.hidden || this.isColumnDisabled(column, parseExpression);
	}

	/**
	 * Return only columns visible to the user in current state.
	 *
	 * Multiple code paths need the same filtering and should stay consistent.
	 */
	visibleColumns(columns : Et2DatagridColumn[], parseExpression? : (expression : string) => boolean) : Et2DatagridColumn[]
	{
		return (columns || []).filter((column) => !this.isColumnHidden(column, parseExpression));
	}

	/**
	 * Build ColumnSelection chooser input list from current grid columns.
	 */
	toSelectionItems(columns : Et2DatagridColumn[], parseExpression? : (expression : string) => boolean) : Et2DatagridColumnSelectionItem[]
	{
		return (columns || [])
			.filter((column) => !this.isColumnDisabled(column, parseExpression))
			.map((column) => ({
				id: this.encodeSelectionId(String(column.key)),
				title: column.title,
				caption: column.title,
				widget: column.header?.cloneNode?.(true) || null,
				visibility: !this.isColumnHidden(column, parseExpression)
			}));
	}

	/**
	 * Apply ColumnSelection chooser-selected order + visibility back to the original column list.
	 *
	 * The chooser returns a subset order, but we need to preserve original entries,
	 * hide unselected columns, and keep selected columns in chosen sequence.
	 */
	applySelectionOrder(columns : Et2DatagridColumn[], selectedKeysInOrder : string[]) : Et2DatagridColumn[]
	{
		const selectedKeys = new Set(selectedKeysInOrder);
		const byKey = new Map((columns || []).map((column) => [String(column.key), column]));
		const selectedOrdered = selectedKeysInOrder
			.map((key) => byKey.get(String(key)))
			.filter(Boolean) as Et2DatagridColumn[];
		let selectedCursor = 0;
		return (columns || []).map((column) =>
		{
			const key = String(column.key);
			if(selectedKeys.has(key) && selectedCursor < selectedOrdered.length)
			{
				const ordered = selectedOrdered[selectedCursor++];
				return {
					...ordered,
					hidden: false
				};
			}
			return {
				...column,
				hidden: true
			};
		});
	}
}
