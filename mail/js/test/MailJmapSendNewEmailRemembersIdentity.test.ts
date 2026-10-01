import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #125092: end-to-end wiring check that MailJmap.sendNewEmail() actually calls
 * rememberLastUsedIdentity() (see MailJmapRememberLastUsedIdentity.test.ts for that method's own,
 * much more thorough permutation coverage) with the account's real acc_id and the identity that
 * was ACTUALLY used for this send - not Stalwart's own opaque submissionIdentityId (see
 * resolveComposeContext()'s own docblock on why that one is meaningless as a stored value).
 *
 * Same "sequential requestMany() calls, counted by call order" harness as
 * MailJmapSaveDraft.test.ts's own createFakeClient() (resolveComposeContext()'s combined
 * Mailbox.get+Identity.get first, Email/set create second, EmailSubmission/set third) - extended
 * here for sendNewEmail()'s own needSent=true requirements (a Sent-role mailbox, and Stalwart's
 * own Identity.get list to resolve submissionIdentityId from).
 */

const EMAIL = {to : ["recipient@example.org"], subject : "Hello", body : "Hi there", isHtml : false};

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

function createFakeClient(identity : {id : string, email : string}) : {client : any, rememberCalls : {accId : string, identId : string}[]}
{
	let call = 0;
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			call++;
			if (call === 1)
			{
				const t = {
					Mailbox : {get : (_args : any) => ({
						list : [{id : 'drafts-id', role : 'drafts'}, {id : 'sent-id', role : 'sent'}],
					})},
					Identity : {get : (_args : any) => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
				};
				return [buildFn(t)];
			}
			if (call === 2)
			{
				const t = {Email : {set : (_args : any) => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
				return [buildFn(t)];
			}
			const t = {
				EmailSubmission : {
					set : (_args : any) => ({created : {sub1 : {id : 'sub-id', blobId : 'blob-id'}}, notCreated : {}}),
				},
			};
			return [buildFn(t)];
		},
	};
	return {client, rememberCalls : []};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.sendNewEmail() calls rememberLastUsedIdentity() after a successful send", () =>
{
	it("with the account's bare acc_id and the identity actually used (its ident_id, not Stalwart's opaque submissionIdentityId)", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		const {client} = createFakeClient(identity);
		primeToken(jmap, "1:15", client);

		const rememberCalls : {accId : string, identId : string}[] = [];
		(jmap as any).rememberLastUsedIdentity = (accId : string, identId : string) => { rememberCalls.push({accId, identId}); };

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(rememberCalls.length, 1);
		assert.deepEqual(rememberCalls[0], {accId : '1', identId : '15'});
	});

	it("is NOT called at all when the send itself fails (the server rejects the submission)", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1)
				{
					const t = {
						Mailbox : {get : () => ({list : [{id : 'drafts-id', role : 'drafts'}, {id : 'sent-id', role : 'sent'}]})},
						Identity : {get : () => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
					};
					return [buildFn(t)];
				}
				if (call === 2)
				{
					const t = {Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				const t = {EmailSubmission : {set : () => ({created : {}, notCreated : {sub1 : {type : 'invalidProperties'}}})}};
				return [buildFn(t)];
			},
		};
		primeToken(jmap, "1:15", client);

		const rememberCalls : any[] = [];
		(jmap as any).rememberLastUsedIdentity = (...args : any[]) => { rememberCalls.push(args); };

		let threw = false;
		try
		{
			await jmap.sendNewEmail("1:15", EMAIL);
		}
		catch (e)
		{
			threw = true;
		}

		assert.isTrue(threw, "the submission failure must still propagate");
		assert.strictEqual(rememberCalls.length, 0, "a failed send must never update the last-used identity");
	});
});
