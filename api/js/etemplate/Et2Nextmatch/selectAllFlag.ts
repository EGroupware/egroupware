/**
 * The "all selected" flag to send along with a nextmatch action.
 *
 * False unless "Select all" is ticked. When it is, the server does not act on the ids it is
 * sent: it expands the selection itself, by re-running the query its list last cached. So
 * instead of a plain true this is the number of entries the user's selection holds, which
 * lets the server refuse to act when its own expansion comes out at a different number - see
 * Api\\Etemplate\\Widget\\Nextmatch::checkSelectAllCount(). A positive number is as true as
 * true to every server-side check of the flag.
 *
 * Which number depends on whether the ids are the complete selection:
 * - for an action the server expands itself (EgwAction.serverSelectsAll()) no ids were fetched, the ids
 *   are just the loaded rows, and the number is the list's total
 * - otherwise EgwAction.execute() fetched every matching id with the list's current filters
 *   first, and the number is their count
 * A list that knows neither sends true, which is not checked.
 *
 * A caller holding only the loaded rows for some other reason has to leave the ids out.
 *
 * @param nm nextmatch the action belongs to
 * @param ids ids collected for the action, entries only (no sum or other extra rows)
 * @param action the EgwAction being executed, to tell whether its ids are complete
 */
export function selectAllFlag(nm : any, ids : any[] = [], action : any = null) : boolean | number
{
	if(nm?.getSelection?.().all !== true)
	{
		return false;
	}
	if(ids?.length > 0 && action?.serverSelectsAll?.() !== true)
	{
		return ids.length;
	}
	const total = Number(nm.totalCount);
	return Number.isInteger(total) && total > 0 ? total : true;
}
