import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's search/filter/sort-to-JMAP translation (buildFilter()/
 * buildTokenizedFilter()/flaggedFilter()/buildSort()) - doc/ai/projects/mail-test-coverage.md's
 * priority-2 entry. Client-side counterpart of Api\Mail::createIMAPFilter()/buildTokenizedSearch()
 * (the shim's own equivalent, `filterToQuery()`, already has JmapTest.php coverage) - this client
 * side had none at all before this. All four methods are pure/synchronous (buildFilter() only
 * additionally reads this.app.getCustomLabels() for a custom-label status filter) - no token/
 * client/network involved.
 */

function createFakeApp(customLabels : Record<string, any> = {}) : MailApp
{
	return {egw : {lang : (l : string) => l}, getCustomLabels : () => customLabels} as unknown as MailApp;
}

function buildFilter(jmap : MailJmap, query : any, mailboxId = 'mbx-inbox')
{
	return (jmap as any).buildFilter(query, mailboxId);
}

function buildSort(jmap : MailJmap, query : any)
{
	return (jmap as any).buildSort(query);
}

describe("MailJmap.buildSort()", () =>
{
	it("defaults to sentAt, descending, when nothing is specified", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual(buildSort(jmap, {}), [{property : 'sentAt', isAscending : false}]);
	});

	it("maps every known order column to its JMAP property", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const cases : [string, string][] = [
			['subject', 'subject'], ['size', 'size'], ['fromaddress', 'from'], ['address', 'from'],
			['toaddress', 'to'], ['modified', 'receivedAt'], ['date', 'sentAt'],
		];
		for (const [order, property] of cases)
		{
			assert.equal(buildSort(jmap, {order})[0].property, property, `order=${order}`);
		}
	});

	it("falls back to sentAt for an unrecognized order column", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.equal(buildSort(jmap, {order : 'something-unknown'})[0].property, 'sentAt');
	});

	it("is case-insensitive for both order and sort", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.equal(buildSort(jmap, {order : 'SUBJECT'})[0].property, 'subject');
		assert.isTrue(buildSort(jmap, {sort : 'asc'})[0].isAscending);
		assert.isFalse(buildSort(jmap, {sort : 'DESC'})[0].isAscending);
	});

	it("ASC/DESC controls isAscending, DESC is the default", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.isTrue(buildSort(jmap, {sort : 'ASC'})[0].isAscending);
		assert.isFalse(buildSort(jmap, {})[0].isAscending);
	});
});

describe("MailJmap.flaggedFilter() - via buildFilter()'s filter='flagged'", () =>
{
	it("is an OR of $flagged and every custom flag keyword", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {filter : 'flagged'});

		// {inMailbox} AND flaggedFilter() - exactly 2 conditions
		assert.equal(filter.operator, 'AND');
		const flaggedCondition = filter.conditions[1];
		assert.equal(flaggedCondition.operator, 'OR');
		assert.deepEqual(flaggedCondition.conditions[0], {hasKeyword : '$flagged'});
		assert.equal(flaggedCondition.conditions.length, 6, "1 $flagged + 5 custom flags");
	});
});

describe("MailJmap.buildFilter() - base/status filter", () =>
{
	it("always includes inMailbox, alone when nothing else applies", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter = buildFilter(jmap, {});

		assert.deepEqual(filter, {inMailbox : 'mbx-inbox'}, "a single condition is never wrapped in an AND");
	});

	it("'unseen'/'answered'/'seen' map to their own keyword conditions", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual((buildFilter(jmap, {filter : 'unseen'}) as any).conditions[1], {notKeyword : '$seen'});
		assert.deepEqual((buildFilter(jmap, {filter : 'answered'}) as any).conditions[1], {hasKeyword : '$answered'});
		assert.deepEqual((buildFilter(jmap, {filter : 'seen'}) as any).conditions[1], {hasKeyword : '$seen'});
	});

	it("'label3'/'keyword3' both map to hasKeyword $label3", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual((buildFilter(jmap, {filter : 'label3'}) as any).conditions[1], {hasKeyword : '$label3'});
		assert.deepEqual((buildFilter(jmap, {filter : 'keyword3'}) as any).conditions[1], {hasKeyword : '$label3'});
	});

	it("an unrecognized filter value that matches a configured custom label resolves to its keyword", () =>
	{
		const jmap = new MailJmap(createFakeApp({MyLabel : {name : 'My Label'}}));
		const filter : any = buildFilter(jmap, {filter : 'mylabel'});

		assert.deepEqual(filter.conditions[1], {hasKeyword : '$mylabel'});
	});

	it("a filter value matching nothing at all (not a status keyword, not a custom label) adds no extra condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual(buildFilter(jmap, {filter : 'not-a-real-filter'}), {inMailbox : 'mbx-inbox'});
	});

	it("status filter is case-insensitive", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual((buildFilter(jmap, {filter : 'UNSEEN'}) as any).conditions[1], {notKeyword : '$seen'});
	});
});

describe("MailJmap.buildFilter() - date range", () =>
{
	it("startdate becomes an 'after' condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {startdate : '2026-01-15'});

		assert.property(filter.conditions[1], 'after');
	});

	it("enddate becomes a 'before' condition one day LATER than the given date (inclusive-of-that-day semantics)", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {enddate : '2026-01-15'});
		const before = new Date(filter.conditions[1].before);

		assert.equal(before.getUTCDate(), 16, "JMAP's 'before' is exclusive, our enddate is inclusive - needs the +1 day adjustment");
	});
});

describe("MailJmap.buildFilter() - flagFilter (app-header toolbar filter, ANDed separately from the status filter)", () =>
{
	it("'flagged' adds the same OR-of-flagged-keywords condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {flagFilter : 'flagged'});

		assert.equal(filter.conditions[1].operator, 'OR');
		assert.deepEqual(filter.conditions[1].conditions[0], {hasKeyword : '$flagged'});
	});

	it("a custom flag id (case-insensitive) adds its own hasKeyword condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {flagFilter : 'customFlag2'});

		assert.property(filter.conditions[1], 'hasKeyword');
		assert.match(filter.conditions[1].hasKeyword, /^\$customflag/);
	});

	it("status filter and flagFilter can both be active at once, ANDed as two independent conditions", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {filter : 'unseen', flagFilter : 'flagged'});

		assert.equal(filter.operator, 'AND');
		assert.equal(filter.conditions.length, 3, "inMailbox + status filter + flagFilter, all separate");
		assert.deepEqual(filter.conditions[1], {notKeyword : '$seen'});
		assert.equal(filter.conditions[2].operator, 'OR');
	});
});

describe("MailJmap.buildFilter() - larger/smaller size search", () =>
{
	it("'larger' parses the size string into a minSize condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {search : '5MB', cat_id : 'larger'});

		assert.equal(filter.conditions[1].minSize, 5 * 1024 * 1000);
	});

	it("'smaller' parses the size string into a maxSize condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {search : '10K', cat_id : 'smaller'});

		assert.equal(filter.conditions[1].maxSize, 10 * 1024);
	});
});

describe("MailJmap.buildFilter() - text search dispatch by cat_id", () =>
{
	it("default/'quick' searches subject+from+to (not cc)", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {search : 'hello', cat_id : 'quick'});
		const termFilter = filter.conditions[1];

		assert.equal(termFilter.operator, 'OR');
		assert.sameDeepMembers(termFilter.conditions, [{subject : 'hello'}, {from : 'hello'}, {to : 'hello'}]);
	});

	it("'quickwithcc' additionally includes cc", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {search : 'hello', cat_id : 'quickwithcc'});

		assert.sameDeepMembers(filter.conditions[1].conditions,
			[{subject : 'hello'}, {from : 'hello'}, {to : 'hello'}, {cc : 'hello'}]);
	});

	it("a single-field cat_id (e.g. 'subject') searches only that field, unwrapped (no OR)", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter : any = buildFilter(jmap, {search : 'hello', cat_id : 'subject'});

		assert.deepEqual(filter.conditions[1], {subject : 'hello'});
	});

	it("'body'/'text' cat_id map to their own single-field search", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual((buildFilter(jmap, {search : 'x', cat_id : 'body'}) as any).conditions[1], {body : 'x'});
		assert.deepEqual((buildFilter(jmap, {search : 'x', cat_id : 'text'}) as any).conditions[1], {text : 'x'});
	});

	it("an empty search string adds no text condition at all", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual(buildFilter(jmap, {search : '', cat_id : 'subject'}), {inMailbox : 'mbx-inbox'});
	});
});

describe("MailJmap.buildTokenizedFilter() - via buildFilter()'s default text search", () =>
{
	function tokenizedFilter(jmap : MailJmap, search : string)
	{
		return ((buildFilter(jmap, {search, cat_id : 'subject'}) as any)).conditions[1];
	}

	it("a quoted phrase is kept as a single token, not split on its internal spaces", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual(tokenizedFilter(jmap, '"hello world"'), {subject : 'hello world'});
	});

	it("multiple bare tokens are OR'd together by default", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter = tokenizedFilter(jmap, 'foo bar');

		assert.equal(filter.operator, 'OR');
		assert.deepEqual(filter.conditions, [{subject : 'foo'}, {subject : 'bar'}]);
	});

	it("the literal 'and' keyword between two tokens ANDs them instead", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter = tokenizedFilter(jmap, 'foo and bar');

		assert.equal(filter.operator, 'AND');
	});

	it("a '-' prefix negates a token (wrapped in NOT) and forces AND with its neighbour", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter = tokenizedFilter(jmap, 'foo -bar');

		assert.equal(filter.operator, 'AND');
		assert.deepEqual(filter.conditions[1], {operator : 'NOT', conditions : [{subject : 'bar'}]});
	});

	it("a '+' prefix keeps the term positive but still forces AND with its neighbour", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const filter = tokenizedFilter(jmap, 'foo +bar');

		assert.equal(filter.operator, 'AND');
		assert.deepEqual(filter.conditions[1], {subject : 'bar'});
	});

	it("a whitespace-only search string produces no text condition", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.deepEqual(buildFilter(jmap, {search : '   ', cat_id : 'subject'}), {inMailbox : 'mbx-inbox'});
	});
});
