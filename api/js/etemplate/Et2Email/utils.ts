/**
 * Email address UI utilities
 *
 * You probably want formatEmailAddress(address)
 */

function _getEmailDisplayPreference()
{
	const pref = window.egw.preference("emailTag", "mail") ?? "";
	switch(pref)
	{
		case "fullemail":
			return "full"
		default:
		case "onlyname":
			return "name";
		case "onlyemail":
			return "email";
		case "domain":
			return "domain";
	}
}


const email_cache : { [address : string] : ContactInfo | false } = {};

let contact_request : Promise<void | boolean>;
let contact_requests : { [key : string] : Array<Function>; } = {};

/**
 * Cache information about a contact
 */
export interface ContactInfo
{
	id : number,
	n_fn : string,
	lname? : string,
	fname? : string,
	photo? : string,
	email? : string,
	email_home : string
}

/**
 * Get contact information using an email address
 *
 * @param {string} email
 * @returns {Promise<boolean | ContactInfo>}
 */
export function checkContact(email : string) : Promise<false | ContactInfo>
{
	if(typeof email_cache[email] !== "undefined")
	{
		return Promise.resolve(email_cache[email]);
	}
	if(!contact_request && window.egw)
	{
		contact_request = window.egw.jsonq('EGroupware\\Api\\Etemplate\\Widget\\Url::ajax_contact', [[]], null, null,
			(parameters) =>
			{
				for(const email in contact_requests)
				{
					parameters[0].push(email);
				}
			}).then((result) =>
		{
			for(const email in contact_requests)
			{
				email_cache[email] = result[email];
				contact_requests[email].forEach((resolve) =>
				{
					resolve(result[email]);
				});
			}
			contact_request = null;
			contact_requests = {};
		});
	}
	if(typeof contact_requests[email] === 'undefined')
	{
		contact_requests[email] = [];
	}
	return new Promise(resolve =>
	{
		contact_requests[email].push(resolve);
	});
}

/**
 * if we have a "name <email>" value split it into name & email
 * @param email_string
 *
 * @return {name:string, email:string}
 */
export function splitEmail(email_string) : { name : string, email : string }
{
	let split = {name: "", email: email_string};
	if(email_string && email_string.indexOf('<') !== -1)
	{
		const parts = email_string.split('<');
		if(parts.length > 1)
		{
			split.email = parts.pop();
			split.email = split.email.substring(0, split.email.length - 1).trim();
			split.name = parts.join("<").trim();
			// remove quotes
			while(split.name.length > 1 && (split.name[0] === '"' || split.name[0] === "'") && split.name[0] === split.name.substring(split.name.length - 1))
			{
				split.name = split.name.substring(1, split.name.length - 1);
			}
		}
		else	// <email> --> email
		{
			split.email = parts[1].substring(0, email_string.length - 1);
		}
	}
	return split;
}

/**
 * Format an email address according to user preference
 *
 * @param address
 * @param {"full" | "email" | "name" | "domain"} emailDisplayFormat
 * @returns {any}
 */
export async function formatEmailAddress(address : string, emailDisplayFormat? : "full" | "email" | "name" | "domain") : Promise<string>
{
	if(!address || !address.trim())
	{
		return "";
	}

	if(!emailDisplayFormat)
	{
		emailDisplayFormat = _getEmailDisplayPreference();
	}

	const split = splitEmail(address);
	let content = address;
	let contact;
	if(emailDisplayFormat !== 'email' && !split.name && (contact = await checkContact(address)))
	{
		split.name = contact.n_fn;
	}

	if(split.name)
	{
		switch(emailDisplayFormat)
		{
			case "full":
				content = split.name + " <" + split.email + ">";
				break;
			case "email":
				content = split.email;
				break;
			case "name":
			default:
				content = split.name;
				break;
			case "domain":
				content = split.name + " (" + split.email.split("@").pop() + ")";
				break;
		}
	}
	return content;
}