/**
 * Shared custom-event names/details used by nextmatch header widgets.
 *
 * They are emitted by header components and consumed by both the legacy
 * nextmatch widget and the new `et2-nextmatch` web component.
 */
export const ET2_NEXTMATCH_SORT_EVENT = "et2-nextmatch-sort";
export const ET2_NEXTMATCH_FILTER_EVENT = "et2-nextmatch-filter";

export type Et2NextmatchSortEventDetail = {
	id : string;
	asc? : boolean;
	update? : boolean;
};

export type Et2NextmatchFilterEventDetail = {
	filters : Record<string, any>;
};
