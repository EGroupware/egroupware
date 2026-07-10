import {Et2DatagridColumn} from "./Et2Datagrid.types";

export type Et2NextmatchResolvedColumn = Et2DatagridColumn & {
	customFields?: string[];
};

/**
 * Column changes replayed from saved preferences must update runtime column
 * state without writing preferences again.
 */
export function shouldPersistDatagridColumnPreferenceEvent(event : Event) : boolean
{
	return (event as CustomEvent)?.detail?.source !== "preferences";
}

/**
 * Apply legacy Nextmatch column visibility/order, widths, and customfield child
 * visibility to current Datagrid columns.
 */
export function applyLegacyNextmatchColumnPreferences(
	columns : Et2DatagridColumn[],
	storedVisibility : any,
	storedSizes : any
) : Et2NextmatchResolvedColumn[]
{
	const isLegacyVisibilityCsv = typeof storedVisibility === "string" &&
		!storedVisibility.trim().startsWith("[") &&
		!storedVisibility.trim().startsWith("{");
	const visibleKeys = isLegacyVisibilityCsv
	                    ? storedVisibility.split(",").map((value) => String(value).trim()).filter(Boolean)
	                    : [];
	const mappedVisibleKeys = mapLegacyVisibleKeysToCurrentColumns(visibleKeys, columns);
	const visibleCustomfields = legacyVisibleCustomfieldNames(visibleKeys);

	let nextColumns : Et2NextmatchResolvedColumn[] = [...(columns || [])];
	if(mappedVisibleKeys.length)
	{
		nextColumns = applyLegacySelectionOrder(nextColumns, mappedVisibleKeys) as Et2NextmatchResolvedColumn[];
	}

	const widthMap = normalizeLegacyColumnWidthMap(storedSizes);
	nextColumns = nextColumns.map((column) =>
	{
		const key = String(column.key);
		if(typeof widthMap[key] === "undefined")
		{
			return column;
		}
		return {
			...column,
			width: String(widthMap[key])
		};
	});
	applyLegacyCustomfieldVisibility(nextColumns, visibleCustomfields);
	return nextColumns;
}

/**
 * Convert resolved columns into Datagrid's structured preference shape.
 */
export function datagridColumnPreferenceValue(columns : Et2NextmatchResolvedColumn[]) : Array<{
	key : string;
	width?: string;
	hidden : boolean;
	customFields?: string[];
}>
{
	return (columns || []).map((column) => ({
		key: String(column.key),
		width: typeof column.width === "string" ? column.width : undefined,
		hidden: !!column.hidden,
		customFields: column.customFields?.length ? column.customFields : undefined
	}));
}

/**
 * Apply selected-order visibility semantics used by legacy Nextmatch CSV preferences.
 */
export function applyLegacySelectionOrder(columns : Et2DatagridColumn[], selectedKeysInOrder : string[]) : Et2DatagridColumn[]
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

/**
 * Map legacy Nextmatch visibility keys onto current datagrid column keys.
 *
 * Older preferences can contain expanded/duplicated composite keys or historic
 * custom-field markers (eg `#text`) that no longer match current column keys.
 */
export function mapLegacyVisibleKeysToCurrentColumns(legacyKeys : string[], columns : Et2DatagridColumn[]) : string[]
{
	const currentKeys = (columns || []).map((column) => String(column.key || "")).filter(Boolean);
	if(!legacyKeys.length || !currentKeys.length)
	{
		return [];
	}
	const currentKeySet = new Set(currentKeys);
	const used = new Set<string>();
	const mapped : string[] = [];
	const currentNormalized = new Map<string, string[]>();
	for(const key of currentKeys)
	{
		currentNormalized.set(key, normalizeLegacyColumnKeyTokens(key));
	}

	for(const legacyKeyRaw of legacyKeys)
	{
		const legacyKey = String(legacyKeyRaw || "").trim();
		if(!legacyKey)
		{
			continue;
		}
		// Exact key match first.
		if(currentKeySet.has(legacyKey) && !used.has(legacyKey))
		{
			mapped.push(legacyKey);
			used.add(legacyKey);
			continue;
		}
		// Historic custom-field placeholders map to `customfields` when available.
		if(legacyKey.startsWith("#") && currentKeySet.has("customfields") && !used.has("customfields"))
		{
			mapped.push("customfields");
			used.add("customfields");
			continue;
		}

		const legacyTokens = normalizeLegacyColumnKeyTokens(legacyKey);
		if(!legacyTokens.length)
		{
			continue;
		}
		let bestKey : string | null = null;
		let bestScore = 0;
		for(const currentKey of currentKeys)
		{
			if(used.has(currentKey))
			{
				continue;
			}
			const currentTokens = currentNormalized.get(currentKey) || [];
			const score = legacyColumnKeySimilarityScore(legacyTokens, currentTokens);
			if(score > bestScore)
			{
				bestScore = score;
				bestKey = currentKey;
			}
		}
		// Threshold tuned to prefer clearly-related composites only.
		if(bestKey && bestScore >= 0.6)
		{
			mapped.push(bestKey);
			used.add(bestKey);
		}
	}
	return mapped;
}

/**
 * Extract selected customfield names from legacy visibility CSV entries.
 */
export function legacyVisibleCustomfieldNames(legacyKeys : string[]) : string[]
{
	const names : string[] = [];
	const seen = new Set<string>();
	for(const key of legacyKeys || [])
	{
		const value = String(key || "").trim();
		if(!value.startsWith("#") || value.length <= 1)
		{
			continue;
		}
		const name = value.slice(1);
		if(seen.has(name))
		{
			continue;
		}
		names.push(name);
		seen.add(name);
	}
	return names;
}

/**
 * Apply selected legacy customfield entries to the customfields column header.
 */
export function applyLegacyCustomfieldVisibility(
	columns : Et2NextmatchResolvedColumn[],
	visibleCustomfields : string[]
) : boolean
{
	if(!visibleCustomfields.length)
	{
		return false;
	}
	const visibility = visibleCustomfields.reduce((result, fieldName) =>
	{
		result[fieldName] = true;
		return result;
	}, {} as Record<string, boolean>);
	for(const column of columns || [])
	{
		if(String(column.key) !== "customfields")
		{
			continue;
		}
		column.customFields = Object.keys(visibility).filter((name) => visibility[name] === true);
		const header = column.header as any;
		if(!header)
		{
			return true;
		}
		if(typeof header.setCustomfieldVisibility === "function")
		{
			header.setCustomfieldVisibility(visibility);
		}
		else
		{
			header.fields = {...visibility};
			header.requestUpdate?.("fields");
		}
		return true;
	}
	return false;
}

/**
 * Normalize legacy stored column-size preference into a key/value map.
 */
export function normalizeLegacyColumnWidthMap(storedSizes : any) : Record<string, any>
{
	if(typeof storedSizes === "string")
	{
		try
		{
			return JSON.parse(storedSizes);
		}
		catch(e)
		{
			return {};
		}
	}
	if(storedSizes && typeof storedSizes === "object")
	{
		return storedSizes;
	}
	return {};
}

/**
 * Tokenize and normalize legacy/current column keys for fuzzy matching.
 */
export function normalizeLegacyColumnKeyTokens(key : string) : string[]
{
	const tokens = String(key || "")
		.toLowerCase()
		.replace(/^#+/, "")
		.split(/[^a-z0-9]+/)
		.filter(Boolean);
	// Dedupe repeated runs and global repeats while preserving order.
	const normalized : string[] = [];
	for(const token of tokens)
	{
		if(normalized[normalized.length - 1] === token)
		{
			continue;
		}
		if(normalized.includes(token))
		{
			continue;
		}
		normalized.push(token);
	}
	return normalized;
}

/**
 * Similarity score for legacy/current key token lists.
 */
export function legacyColumnKeySimilarityScore(legacyTokens : string[], currentTokens : string[]) : number
{
	if(!legacyTokens.length || !currentTokens.length)
	{
		return 0;
	}
	const currentSet = new Set(currentTokens);
	let overlap = 0;
	for(const token of legacyTokens)
	{
		if(currentSet.has(token))
		{
			overlap++;
		}
	}
	const overlapRatio = overlap / Math.max(legacyTokens.length, currentTokens.length);
	const legacyContainsCurrent = currentTokens.every((token) => legacyTokens.includes(token)) ? 0.35 : 0;
	const currentContainsLegacy = legacyTokens.every((token) => currentTokens.includes(token)) ? 0.25 : 0;
	return overlapRatio + legacyContainsCurrent + currentContainsLegacy;
}
