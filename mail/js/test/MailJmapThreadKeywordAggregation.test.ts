import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's status-icon/label computation (keywordsToRowFlags()) and thread-level
 * keyword folding (aggregateThreadKeywords()) - doc/ai/projects/mail-test-coverage.md's
 * priority-2 entry flags this as notable: it's the exact logic central to the answered/forwarded
 * status-icon fix (2026-09-09, this session), yet had no direct unit test of its own before this -
 * every existing test only exercised it indirectly through row-fetch fixtures.
 *
 * Both are pure(ish) synchronous methods (keywordsToRowFlags() only additionally reads
 * this.app.getCustomLabels()/getRowLabelTags()) - no token/client/network involved.
 */

function createFakeApp(customLabels : Record<string, any> = {}, labelTagsFor : (flags : Record<string, string>) => any[] = () => []) : MailApp
{
	return {
		egw : {lang : (l : string) => l},
		getCustomLabels : () => customLabels,
		getRowLabelTags : labelTagsFor,
	} as unknown as MailApp;
}

function keywordsToRowFlags(jmap : MailJmap, keywords : Record<string, boolean>)
{
	return (jmap as any).keywordsToRowFlags(keywords);
}

describe("MailJmap.keywordsToRowFlags() - status icon priority", () =>
{
	it("an unread, otherwise-plain message gets the unseen icon/class, no other flags", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {});

		assert.deepEqual(result.flags, {});
		assert.deepEqual(result.css, ['mail', 'unseen']);
		assert.equal(result.status_icon, 'mail_unseen');
		assert.isFalse(result.hasFlagged);
		assert.deepEqual(result.labelTags, []);
	});

	it("a read, otherwise-plain message gets no icon and no unseen class", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$seen' : true});

		assert.deepEqual(result.flags, {read : 'read'});
		assert.notInclude(result.css, 'unseen');
		assert.equal(result.status_icon, '');
	});

	it("$forwarded takes priority over $answered for the status icon", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$seen' : true, '$answered' : true, '$forwarded' : true});

		assert.equal(result.status_icon, 'mail_forward');
		assert.equal(result.flags.replied, 'replied');
		assert.equal(result.flags.forwarded, 'forwarded');
		assert.include(result.css, 'replied');
		assert.include(result.css, 'forwarded');
	});

	it("$answered alone gets the reply icon", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$seen' : true, '$answered' : true});

		assert.equal(result.status_icon, 'mail_reply');
	});

	it("an unread message that was ALSO answered/forwarded still shows forward/reply, not unseen", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$answered' : true});

		assert.equal(result.status_icon, 'mail_reply', "answered/forwarded takes priority over the unseen icon");
	});
});

describe("MailJmap.keywordsToRowFlags() - flagged/custom flags", () =>
{
	it("$flagged sets flags.flagged/hasFlagged/css", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$flagged' : true});

		assert.equal(result.flags.flagged, 'flagged');
		assert.include(result.css, 'flagged');
		assert.isTrue(result.hasFlagged);
	});

	it("a custom flag alone (no $flagged) still sets hasFlagged, keyed by its own name not customflagN", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$customflag2' : true});

		assert.isTrue(result.hasFlagged, "any customFlag1-5 implies hasFlagged, same as $flagged");
		const customFlagName = MailJmap['CUSTOM_FLAGS'][1];
		assert.equal(result.flags[customFlagName], customFlagName);
		assert.include(result.css, customFlagName);
	});

	it("no flags at all means hasFlagged is false", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$seen' : true});

		assert.isFalse(result.hasFlagged);
	});
});

describe("MailJmap.keywordsToRowFlags() - MDN keywords", () =>
{
	it("sets mdnsent/mdnnotsent independently", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.equal(keywordsToRowFlags(jmap, {'$mdnsent' : true}).flags.mdnsent, 'mdnsent');
		assert.equal(keywordsToRowFlags(jmap, {'$mdnnotsent' : true}).flags.mdnnotsent, 'mdnnotsent');
		assert.isUndefined(keywordsToRowFlags(jmap, {}).flags.mdnsent);
	});
});

describe("MailJmap.keywordsToRowFlags() - built-in labels (label1-5)", () =>
{
	it("sets each of label1-5 independently by its own name", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = keywordsToRowFlags(jmap, {'$label1' : true, '$label3' : true});

		assert.equal(result.flags.label1, 'label1');
		assert.equal(result.flags.label3, 'label3');
		assert.isUndefined(result.flags.label2);
		assert.include(result.css, 'label1');
		assert.include(result.css, 'label3');
	});
});

describe("MailJmap.keywordsToRowFlags() - custom labels", () =>
{
	it("matches a configured custom label case-insensitively, keyed by its own (not lowercased) id", () =>
	{
		const jmap = new MailJmap(createFakeApp({MyLabel : {name : 'My Label'}}));
		const result = keywordsToRowFlags(jmap, {'$mylabel' : true});

		assert.equal(result.flags.MyLabel, 'MyLabel');
		assert.include(result.css, 'MyLabel');
	});

	it("a custom label the account doesn't have configured is simply ignored", () =>
	{
		const jmap = new MailJmap(createFakeApp({}));
		const result = keywordsToRowFlags(jmap, {'$somelabel' : true});

		assert.deepEqual(result.flags, {});
	});
});

describe("MailJmap.keywordsToRowFlags() - labelTags threshold", () =>
{
	it("shows labelTags only when getRowLabelTags() finds 2 or more", () =>
	{
		const jmapNone = new MailJmap(createFakeApp({}, () => []));
		assert.deepEqual(keywordsToRowFlags(jmapNone, {}).labelTags, []);

		const jmapOne = new MailJmap(createFakeApp({}, () => [{value : 'a', label : 'A'}]));
		assert.deepEqual(keywordsToRowFlags(jmapOne, {}).labelTags, [],
			"a single matching label must NOT be shown as a tag - it's already visible as the row's own icon");

		const jmapTwo = new MailJmap(createFakeApp({}, () => [{value : 'a', label : 'A'}, {value : 'b', label : 'B'}]));
		assert.deepEqual(keywordsToRowFlags(jmapTwo, {}).labelTags,
			[{value : 'a', label : 'A'}, {value : 'b', label : 'B'}]);
	});
});

describe("MailJmap.aggregateThreadKeywords()", () =>
{
	function aggregate(jmap : MailJmap, members : { keywords? : Record<string, boolean> }[])
	{
		return (jmap as any).aggregateThreadKeywords(members);
	}

	it("$seen is AND-folded - only true when EVERY member is seen", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.isTrue(aggregate(jmap, [{keywords : {'$seen' : true}}, {keywords : {'$seen' : true}}])['$seen']);
		assert.isFalse(aggregate(jmap, [{keywords : {'$seen' : true}}, {keywords : {'$seen' : false}}])['$seen']);
		assert.isFalse(aggregate(jmap, [{keywords : {'$seen' : true}}, {keywords : {}}])['$seen'],
			"a member with no $seen keyword at all counts as unseen");
	});

	it("every other keyword is OR-folded - true if ANY member has it", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = aggregate(jmap, [
			{keywords : {'$seen' : true, '$flagged' : true}},
			{keywords : {'$seen' : true}},
		]);

		assert.isTrue(result['$flagged'], "one member being flagged is enough for the whole thread to show flagged");
	});

	it("$answered/$forwarded fold the same OR way as any other non-$seen keyword", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = aggregate(jmap, [
			{keywords : {'$seen' : true}},
			{keywords : {'$seen' : true, '$forwarded' : true}},
		]);

		assert.isTrue(result['$forwarded']);
		assert.isUndefined(result['$answered']);
	});

	it("a member with no keywords map at all is treated as having none, without throwing", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = aggregate(jmap, [{}, {keywords : {'$flagged' : true}}]);

		assert.isFalse(result['$seen']);
		assert.isTrue(result['$flagged']);
	});

	it("an empty thread (no members) treats $seen as vacuously true and has no other keys", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = aggregate(jmap, []);

		assert.deepEqual(result, {'$seen' : true});
	});

	it("feeding the aggregate straight into keywordsToRowFlags() shows forwarded when only ONE of several members was forwarded", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const aggregated = aggregate(jmap, [
			{keywords : {'$seen' : true}},
			{keywords : {'$seen' : true, '$forwarded' : true}},
			{keywords : {'$seen' : true}},
		]);
		const result = keywordsToRowFlags(jmap, aggregated);

		assert.equal(result.status_icon, 'mail_forward');
		assert.notInclude(result.css, 'unseen', "the thread as a whole IS fully read - $seen AND-folded true");
	});
});
