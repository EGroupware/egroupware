import {Et2DatagridColumn} from "./Et2Datagrid.types";

/**
 * Unit tokens supported by datagrid column width config.
 *
 * Why this exists:
 * Resize math needs explicit unit semantics so conversion stays predictable.
 */
export type Et2DatagridColumnUnit = "px" | "%" | "fr";

/**
 * Coarse width family used by conversion and write-back rules.
 */
export type Et2DatagridColumnWidthKind = "pixel" | "relative";

/**
 * Parsed width descriptor used throughout sizing helpers.
 */
export interface Et2DatagridColumnWidthDescriptor
{
	kind : Et2DatagridColumnWidthKind;
	unit : Et2DatagridColumnUnit;
	value : number | null;
}

/**
 * Persistent state for a single active resize drag.
 *
 * Why this exists:
 * Datagrid only commits widths at drag end, so we cache all context needed for a
 * deterministic final calculation.
 */
export interface Et2DatagridColumnResizeDragState
{
	columnIndex : number;
	columnKey : string;
	startWidthPx : number;
	currentWidthPx : number;
	totalVisibleWidthPx : number;
	fixedWidthPx : number;
	relativeWidthUnits : number;
	minWidthPx : number;
	maxWidthPx : number;
	widthKind : Et2DatagridColumnWidthKind;
	widthUnit : Et2DatagridColumnUnit;
}

/**
 * Column sizing + resize policy engine for `Et2Datagrid`.
 *
 * Isolates width parsing, normalization, conversion, and resize commit behaviour
 * from DOM/render/event concerns in the component class.
 */
export class Et2DatagridColumnManager
{
	/**
	 * Parse one width string into numeric/unit metadata.
	 *
	 * Multiple helpers need one canonical parse result rather than ad-hoc regexes.
	 */
	columnWidthDescriptor(raw? : string) : Et2DatagridColumnWidthDescriptor
	{
		if(!raw)
		{
			return {kind: "pixel", unit: "px", value: null};
		}
		const value = String(raw).trim().toLowerCase();
		if(/^\d+(\.\d+)?%$/.test(value))
		{
			return {kind: "relative", unit: "%", value: parseFloat(value)};
		}
		if(/^\d+(\.\d+)?fr$/.test(value))
		{
			return {kind: "relative", unit: "fr", value: parseFloat(value)};
		}
		if(/^\d+(\.\d+)?px$/.test(value))
		{
			return {kind: "pixel", unit: "px", value: parseFloat(value)};
		}
		if(/^\d+(\.\d+)?$/.test(value))
		{
			return {kind: "pixel", unit: "px", value: parseFloat(value)};
		}
		return {kind: "pixel", unit: "px", value: null};
	}

	/**
	 * Normalize configured width to the grid-track output convention.
	 *
	 * The grid uses `fr` for all relative tracks, regardless of input `%`/`fr`.
	 */
	normalizeColumnWidth(raw? : string) : string
	{
		const parsed = this.columnWidthDescriptor(raw);
		if(parsed.value === null)
		{
			return "auto";
		}
		if(parsed.kind === "relative")
		{
			return `${parsed.value}fr`;
		}
		return `${parsed.value}px`;
	}

	/**
	 * Normalize min/max width constraints to valid CSS lengths.
	 *
	 * Server payloads may omit units; grid CSS expects explicit lengths.
	 */
	normalizeColumnLength(raw? : string) : string
	{
		if(!raw)
		{
			return "";
		}
		const value = String(raw).trim().toLowerCase();
		if(/^\d+(\.\d+)?px$/.test(value))
		{
			return `${parseFloat(value)}px`;
		}
		if(/^\d+(\.\d+)?$/.test(value))
		{
			return `${parseFloat(value)}px`;
		}
		return value;
	}

	/**
	 * Constrain a number between min and max bounds.
	 */
	clamp(value : number, min : number, max : number) : number
	{
		return Math.max(min, Math.min(max, value));
	}

	/**
	 * Convert configured length into pixel space for runtime math.
	 *
	 * Drag interactions and steal distribution operate in physical px units.
	 */
	columnLengthToPx(
		raw : string | undefined,
		totalVisibleWidthPx : number,
		availableRelativeWidthPx : number,
		relativeWidthUnits : number
	) : number | null
	{
		if(!raw)
		{
			return null;
		}
		const parsed = this.columnWidthDescriptor(raw);
		if(parsed.value === null)
		{
			return null;
		}
		if(parsed.kind === "pixel")
		{
			return parsed.value;
		}
		if(parsed.unit === "%")
		{
			return totalVisibleWidthPx * (parsed.value / 100);
		}
		if(relativeWidthUnits > 0 && availableRelativeWidthPx > 0)
		{
			return availableRelativeWidthPx * (parsed.value / relativeWidthUnits);
		}
		return totalVisibleWidthPx * (parsed.value / 100);
	}

	/**
	 * Aggregate fixed and relative width totals for current visible columns.
	 *
	 * Relative <-> px conversions need both fixed px sum and relative unit sum.
	 */
	visibleColumnWidthMetrics(visibleColumns : Et2DatagridColumn[], totalVisibleWidthPx : number) : {
		totalVisibleWidthPx : number;
		fixedWidthPx : number;
		relativeWidthUnits : number;
	}
	{
		let fixedWidthPx = 0;
		let relativeWidthUnits = 0;
		for(const column of visibleColumns)
		{
			const parsed = this.columnWidthDescriptor(column.width);
			if(parsed.value === null)
			{
				continue;
			}
			if(parsed.kind === "pixel")
			{
				fixedWidthPx += parsed.value;
			}
			else
			{
				relativeWidthUnits += parsed.value;
			}
		}
		return {totalVisibleWidthPx, fixedWidthPx, relativeWidthUnits};
	}

	/**
	 * Format numeric width back into a compact config string.
	 *
	 * Keeps persisted width values stable and avoids noisy floating output.
	 */
	formatColumnWidthValue(value : number, unit : Et2DatagridColumnUnit) : string
	{
		if(unit === "px")
		{
			return `${Math.max(1, Math.round(value))}px`;
		}
		const normalized = Number.isFinite(value) ? Math.max(0.001, value) : 0.001;
		const compact = normalized.toFixed(3).replace(/\.?0+$/, "");
		return `${compact}${unit}`;
	}

	/**
	 * Build CSS `grid-template-columns` track list from visible columns.
	 *
	 * Column rendering paths expect one precomputed track definition string.
	 */
	columnWidths(columns : Et2DatagridColumn[]) : string
	{
		return columns.map((column) =>
		{
			const width = this.normalizeColumnWidth(column.width);
			const minWidth = this.normalizeColumnLength(column.minWidth);
			return minWidth ? `minmax(${minWidth}, ${width})` : width;
		}).join(" ");
	}

	/**
	 * Commit a completed drag operation to updated column config.
	 *
	 * This is the central resize policy: honour min/max, preserve unit families,
	 * enforce hard floor, and distribute growth steal across right-side donors.
	 */
	commitResize(
		columns : Et2DatagridColumn[],
		visibleColumns : Et2DatagridColumn[],
		drag : Et2DatagridColumnResizeDragState,
		resizeFloorPx : number
	) : { columns : Et2DatagridColumn[]; resizedColumn : Et2DatagridColumn } | null
	{
		const desiredWidthPx = this.clamp(drag.currentWidthPx, drag.minWidthPx, drag.maxWidthPx);
		const nextColumns = [...columns];
		const column = nextColumns[drag.columnIndex];
		if(!column || String(column.key || "") !== drag.columnKey)
		{
			return null;
		}
		const availableRelativeWidthPx = Math.max(1, drag.totalVisibleWidthPx - drag.fixedWidthPx);
		const toColumnWidthString = (columnDef : Et2DatagridColumn, widthPx : number) : string =>
		{
			const descriptor = this.columnWidthDescriptor(columnDef.width);
			if(descriptor.kind === "pixel")
			{
				return this.formatColumnWidthValue(widthPx, "px");
			}
			if(descriptor.unit === "%")
			{
				const percentValue = (widthPx / Math.max(1, drag.totalVisibleWidthPx)) * 100;
				return this.formatColumnWidthValue(percentValue, "%");
			}
			const relativeValue = drag.relativeWidthUnits > 0
			                      ? (widthPx * drag.relativeWidthUnits) / availableRelativeWidthPx
			                      : (widthPx / Math.max(1, drag.totalVisibleWidthPx)) * 100;
			return this.formatColumnWidthValue(relativeValue, descriptor.unit);
		};

		let finalWidthPx = desiredWidthPx;
		const growthPx = Math.max(0, desiredWidthPx - drag.startWidthPx);
		if(growthPx > 0)
		{
			const resizedVisibleIndex = visibleColumns.findIndex((visibleColumn) => columns.indexOf(visibleColumn) === drag.columnIndex);
			const donors = resizedVisibleIndex >= 0 ? visibleColumns.slice(resizedVisibleIndex + 1) : [];
			if(donors.length)
			{
				const donorInfos : Array<{
					index : number;
					column : Et2DatagridColumn;
					currentWidthPx : number;
					capacityPx : number;
					stolenPx : number;
				}> = [];
				for(const donorColumn of donors)
				{
					const donorIndex = columns.indexOf(donorColumn);
					if(donorIndex < 0)
					{
						continue;
					}
					const donorCurrentWidthPx = this.columnLengthToPx(
						donorColumn.width,
						drag.totalVisibleWidthPx,
						availableRelativeWidthPx,
						drag.relativeWidthUnits
					);
					if(donorCurrentWidthPx === null)
					{
						continue;
					}
					const donorMinWidthPxRaw = this.columnLengthToPx(
						donorColumn.minWidth,
						drag.totalVisibleWidthPx,
						availableRelativeWidthPx,
						drag.relativeWidthUnits
					);
					const donorMinWidthPx = Math.max(1, resizeFloorPx, donorMinWidthPxRaw ?? 1);
					const donorCapacityPx = Math.max(0, donorCurrentWidthPx - donorMinWidthPx);
					if(donorCapacityPx <= 0)
					{
						continue;
					}
					donorInfos.push({
						index: donorIndex,
						column: donorColumn,
						currentWidthPx: donorCurrentWidthPx,
						capacityPx: donorCapacityPx,
						stolenPx: 0
					});
				}
				const totalDonorCapacityPx = donorInfos.reduce((sum, donor) => sum + donor.capacityPx, 0);
				const stealTargetPx = Math.min(growthPx, totalDonorCapacityPx);
				if(stealTargetPx > 0 && totalDonorCapacityPx > 0)
				{
					let assignedPx = 0;
					for(const donor of donorInfos)
					{
						const sharePx = stealTargetPx * (donor.capacityPx / totalDonorCapacityPx);
						donor.stolenPx = Math.min(donor.capacityPx, sharePx);
						assignedPx += donor.stolenPx;
					}
					let remainingPx = Math.max(0, stealTargetPx - assignedPx);
					for(const donor of donorInfos)
					{
						if(remainingPx <= 0)
						{
							break;
						}
						const extraCapacityPx = donor.capacityPx - donor.stolenPx;
						if(extraCapacityPx <= 0)
						{
							continue;
						}
						const extraPx = Math.min(extraCapacityPx, remainingPx);
						donor.stolenPx += extraPx;
						remainingPx -= extraPx;
					}
				}
				for(const donor of donorInfos)
				{
					nextColumns[donor.index] = {
						...nextColumns[donor.index],
						width: toColumnWidthString(donor.column, donor.currentWidthPx - donor.stolenPx)
					};
				}
				const achievedGrowthPx = donorInfos.reduce((sum, donor) => sum + donor.stolenPx, 0);
				finalWidthPx = drag.startWidthPx + achievedGrowthPx;
			}
		}

		column.width = drag.widthKind === "relative"
		               ? toColumnWidthString(column, finalWidthPx)
		               : this.formatColumnWidthValue(finalWidthPx, "px");
		nextColumns[drag.columnIndex] = {...column};
		return {columns: nextColumns, resizedColumn: nextColumns[drag.columnIndex]};
	}
}
