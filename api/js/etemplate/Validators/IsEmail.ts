import {Pattern} from "./StringValidators";

export class IsEmail extends Pattern
{
	/**
	 * Regexes for validating email addresses incl. email in angle-brackets eg.
	 * + "Ralf Becker <rb@egroupware.org>"
	 * + "Ralf Becker (EGroupware GmbH) <rb@egroupware.org>"
	 * + "<rb@egroupware.org>" or "rb@egroupware.org"
	 * + '"Becker, Ralf" <rb@egroupware.org>'
	 * + "'Becker, Ralf' <rb@egroupware.org>"
	 * but NOT:
	 * - "Becker, Ralf <rb@egroupware.org>" (contains comma outside " or ' enclosed block)
	 * - "Becker < Ralf <rb@egroupware.org>" (contains <    ----------- " ---------------)
	 *
	 * About umlaut or IDN domains: we currently only allow German umlauts in domain part!
	 * We forbid all non-ascii chars in local part, as Horde does not yet support SMTPUTF8 extension (rfc6531)
	 * and we get a "SMTP server does not support internationalized header data" error otherwise.
	 *
	 * Using \042 instead of " to NOT stall minifyer!
	 *
	 * Similar, but not identical, preg is in Etemplate\Widget\Url PHP class!
	 * We can not use "(?<![.\s])", used to check that name-part does not end in
	 * a dot or white-space. The expression is valid in recent Chrome, but fails
	 * eg. in Safari 11.0 or node.js 4.8.3 and therefore grunt uglify!
	 * Server-side will fail in that case because it uses the full regexp.
	 */
	static EMAIL_PREG : RegExp = /^(([^\042',<][^,<]+|\042[^\042]+\042|\'[^\']+\'|"(?:[^"\\]|\\.)*")\s?<)?[^\x00-\x20()\xe2\x80\x8b<>@,;:\042\[\]\x80-\xff]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,}>?$/i;

	/**
	 * Allow everything containing at least one placeholder e.g.:
	 * - "{{email}}"
	 * - "{{n_fn}} <{{email}}"
	 * - "{{#<custom-field-name>}}"
	 * - "{{info_contact/email}}" or "{{user/#<custom-field-name}}"
	 * - we do NOT check if the placeholder is implemented by addressbook or a valid custom-field name!
	 * - "test" or "{test}}" are NOT valid
	 */
	static EMAIL_PLACEHOLDER_PREG = new RegExp('^(.*{{[a-z0-9_/#]+}}.*|'+IsEmail.EMAIL_PREG.source.substr(1, IsEmail.EMAIL_PREG.source.length-2)+')$', 'i');

	/**
	 *
	 * @param _allowPlaceholders true: allow valid email-addresses OR something with placeholder(s)
	 */
	constructor(_allowPlaceholders: boolean)
	{
		super(_allowPlaceholders ? IsEmail.EMAIL_PLACEHOLDER_PREG : IsEmail.EMAIL_PREG);
	}

	/**
	 * Give a message about this field being required.  Could be customised according to MessageData.
	 * @param {MessageData | undefined} data
	 * @returns {Promise<string>}
	 */
	static async getMessage(data)
	{
		return data.formControl.egw().lang("Invalid email") + (data.modelValue ? ' "' + data.modelValue + '"' : "");
	}
}