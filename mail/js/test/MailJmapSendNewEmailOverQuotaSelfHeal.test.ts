import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket/Birgit, 2026-11-19: Stalwart enforces a hard per-account quota on how many
 * EmailSubmission objects it keeps (RFC 8621 S7.1) - sendNewEmail() never destroyed its own
 * EmailSubmission object after a successful send, so every account using this client-side
 * real-JMAP send path (see sendNewEmail()'s own docblock - `!token.isLocal`) eventually hit
 * EmailSubmission/set's "overQuota" SetError on every subsequent send.
 *
 * Covers the two-part fix: (1) sendNewEmail() now self-cleans its own just-created
 * EmailSubmission object right after success, and (2) on "overQuota" it purges the existing
 * backlog (query+get+destroy, see purgeStaleEmailSubmissions()) and retries the create exactly
 * once before giving up - mirroring Api\Mail\Jmap\EmailSubmission::submit()'s own PHP-side fix
 * for the separate server-side Horde_Mail_Transport send path.
 *
 * Same "sequential requestMany() calls, counted by call order" harness as
 * MailJmapSendNewEmailRemembersIdentity.test.ts's own createFakeClient().
 */

const EMAIL = {to : ["recipient@example.org"], subject : "Hello", body : "Hi there", isHtml : false};
const IDENTITY = {id : '15', email : 'me@example.org'};

function createFakeApp() : MailApp
{
	const egw = {
		user : (_key : string) => 1,
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference : (_key : string, _app? : string) => null,
		config : (_name : string, _app? : string) => null,
		request : async() => ({}),
		message : (_msg : string, _type? : string) => {},
	};
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function resolveComposeContextStub(t : any) : any
{
	return {
		Mailbox : {get : () => ({list : [{id : 'drafts-id', role : 'drafts'}, {id : 'sent-id', role : 'sent'}]})},
		Identity : {get : () => ({list : [{id : 'stalwart-opaque-id', email : IDENTITY.email}]})},
	};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

function createJmap() : MailJmap
{
	const jmap = new MailJmap(createFakeApp());
	(jmap as any).getIdentities = async() => [IDENTITY];
	(jmap as any).rememberLastUsedIdentity = () => {};
	return jmap;
}

describe("MailJmap.sendNewEmail() self-cleans its own EmailSubmission object after a successful send", () =>
{
	it("issues an EmailSubmission/set destroy for the just-created submission id", async() =>
	{
		const destroyCalls : any[] = [];
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1) return [buildFn({...resolveComposeContextStub(null)})];
				if (call === 2) return [buildFn({Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}})];
				if (call === 3) return [buildFn({EmailSubmission : {set : () => ({created : {sub1 : {id : 'sub-id'}}, notCreated : {}})}})];
				if (call === 4)
				{
					// the self-cleanup destroy
					return [buildFn({
						EmailSubmission : {
							set : (args : any) => { destroyCalls.push(args); return {destroyed : args.destroy, notDestroyed : {}}; },
						},
					})];
				}
				return [buildFn({Email : {set : () => ({updated : {'draft-id' : {}}, notUpdated : {}})}})];
			},
		};
		const jmap = createJmap();
		primeToken(jmap, "1:15", client);

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(destroyCalls.length, 1, "must self-clean exactly once");
		assert.deepEqual(destroyCalls[0].destroy, ['sub-id']);
	});

	it("still returns the successful send result even if the self-cleanup destroy call itself fails", async() =>
	{
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1) return [buildFn({...resolveComposeContextStub(null)})];
				if (call === 2) return [buildFn({Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}})];
				if (call === 3) return [buildFn({EmailSubmission : {set : () => ({created : {sub1 : {id : 'sub-id'}}, notCreated : {}})}})];
				if (call === 4) throw new Error('network error');
				return [buildFn({Email : {set : () => ({updated : {'draft-id' : {}}, notUpdated : {}})}})];
			},
		};
		const jmap = createJmap();
		primeToken(jmap, "1:15", client);

		const result = await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(result.emailId, 'draft-id');
	});
});

describe("MailJmap.sendNewEmail()'s overQuota self-heal", () =>
{
	it("purges the stale EmailSubmission backlog and retries the create exactly once on overQuota", async() =>
	{
		const setCalls : any[] = [];
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1) return [buildFn({...resolveComposeContextStub(null)})];
				if (call === 2) return [buildFn({Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}})];
				if (call === 3)
				{
					// first create attempt: rejected as overQuota
					return [buildFn({
						EmailSubmission : {
							set : (args : any) => { setCalls.push(args); return {created : {}, notCreated : {sub1 : {type : 'overQuota'}}}; },
						},
					})];
				}
				if (call === 4)
				{
					// purgeStaleEmailSubmissions(): query+get combined via $ref in ONE requestMany,
					// one stale backlog entry
					return [buildFn({
						EmailSubmission : {
							query : () => ({$ref : (_path : string) => ({})}),
							get : () => ({list : [{id : 'old1', sendAt : '2000-01-01T00:00:00Z'}]}),
						},
					})];
				}
				if (call === 5)
				{
					// purgeStaleEmailSubmissions(): destroy the stale backlog entry
					return [buildFn({EmailSubmission : {set : (args : any) => { setCalls.push(args); return {destroyed : args.destroy}; }}})];
				}
				if (call === 6)
				{
					// purgeStaleEmailSubmissions(): next page's combined query+get - nothing left, loop ends
					return [buildFn({
						EmailSubmission : {
							query : () => ({$ref : (_path : string) => ({})}),
							get : () => ({list : []}),
						},
					})];
				}
				if (call === 7)
				{
					// the retry: succeeds this time
					return [buildFn({
						EmailSubmission : {
							set : (args : any) => { setCalls.push(args); return {created : {sub1 : {id : 'sub-id'}}, notCreated : {}}; },
						},
					})];
				}
				if (call === 8)
				{
					// the retry's own self-cleanup destroy
					return [buildFn({EmailSubmission : {set : (args : any) => { setCalls.push(args); return {destroyed : args.destroy}; }}})];
				}
				return [buildFn({Email : {set : () => ({updated : {'draft-id' : {}}, notUpdated : {}})}})];
			},
		};
		const jmap = createJmap();
		primeToken(jmap, "1:15", client);

		const result = await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(result.emailId, 'draft-id', "must succeed after the self-heal retry");
		assert.deepEqual(setCalls[1].destroy, ['old1'], "must have purged the stale backlog");
		assert.deepEqual(setCalls[2].create.sub1, {emailId : 'draft-id', identityId : 'stalwart-opaque-id'},
			"the retry must resend the exact same create");
	});

	it("gives up after exactly one retry and throws when overQuota persists", async() =>
	{
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1) return [buildFn({...resolveComposeContextStub(null)})];
				if (call === 2) return [buildFn({Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}})];
				if (call === 3 || call === 5)
				{
					return [buildFn({EmailSubmission : {set : () => ({created : {}, notCreated : {sub1 : {type : 'overQuota'}}})}})];
				}
				if (call === 4)
				{
					// nothing stale enough to purge
					return [buildFn({
						EmailSubmission : {
							query : () => ({$ref : (_path : string) => ({})}),
							get : () => ({list : []}),
						},
					})];
				}
				throw new Error(`unexpected call #${call}`);
			},
		};
		const jmap = createJmap();
		primeToken(jmap, "1:15", client);

		let threw = false;
		try
		{
			await jmap.sendNewEmail("1:15", EMAIL);
		}
		catch (e)
		{
			threw = true;
		}

		assert.isTrue(threw, "must still fail the send once the self-heal retry also hits overQuota");
		assert.strictEqual(call, 5, "exactly one retry - no self-cleanup destroy call once the create itself failed");
	});

	it("does not retry at all for any other notCreated error type", async() =>
	{
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1) return [buildFn({...resolveComposeContextStub(null)})];
				if (call === 2) return [buildFn({Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}})];
				if (call === 3) return [buildFn({EmailSubmission : {set : () => ({created : {}, notCreated : {sub1 : {type : 'invalidProperties'}}})}})];
				throw new Error(`unexpected call #${call}`);
			},
		};
		const jmap = createJmap();
		primeToken(jmap, "1:15", client);

		let threw = false;
		try
		{
			await jmap.sendNewEmail("1:15", EMAIL);
		}
		catch (e)
		{
			threw = true;
		}

		assert.isTrue(threw);
		assert.strictEqual(call, 3, "no purge, no retry for a non-overQuota error");
	});
});
