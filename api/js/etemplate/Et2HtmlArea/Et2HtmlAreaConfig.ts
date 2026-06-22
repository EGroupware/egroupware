import {egw} from "../../jsapi/egw_global";
import type {RawEditorOptions} from "tinymce";

export type HtmlAreaMode = "" | "ascii" | "simple" | "extended" | "advanced";
export type TinyMceConfig = RawEditorOptions;
type PreferenceGetter = (name : string, app? : string) => any;

export const DEFAULT_MENUBAR = "file edit insert view format table tools help";
export const TOOLBAR_SIMPLE = "undo redo | blocks fontfamily fontsize | bold italic underline removeformat forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image pastetext | table";
export const TOOLBAR_EXTENDED = "fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | link | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | removeformat | image | fullscreen | table";
export const TOOLBAR_ADVANCED = "undo redo | blocks | fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent ltr rtl | removeformat code | link image pastetext | searchreplace | fullscreen | table";
export const BLOCK_FORMATS = "Paragraph=p;Small Paragraph=div;Heading 1=h1;Heading 2=h2;Heading 3=h3;" +
	"Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre";
/**
 * Legacy htmlarea requested plugins from client-side config, not from a
 * server-side preference. TinyMCE 8 now absorbs several of those features into
 * core, so this list contains only the GPL community plugins that still need
 * explicit bundling and activation from `node_modules/tinymce/plugins`.
 */
export const NPM_PLUGIN_SET = [
	"searchreplace",
	"autolink",
	"directionality",
	"visualblocks",
	"visualchars",
	"image",
	"link",
	"media",
	"fullscreen",
	"codesample",
	"table",
	"charmap",
	"pagebreak",
	"nonbreaking",
	"anchor",
	"insertdatetime",
	"advlist",
	"lists",
	"wordcount",
	"help",
	"code"
].join(" ");
export const LANGUAGE_CODE : Record<string, string> = {
	bg: "bg-BG",
	ca: "ca",
	cs: "cs",
	da: "da",
	de: "de",
	en: "en",
	el: "el",
	"es-es": "es",
	et: "et",
	eu: "eu",
	fa: "fa",
	fi: "fi",
	fr: "fr-FR",
	hr: "hr",
	hu: "hu-HU",
	id: "id",
	it: "it",
	ja: "ja",
	ko: "ko-KR",
	lt: "lt",
	lv: "lv",
	nl: "nl",
	no: "nb-NO",
	pl: "pl",
	pt: "pt-PT",
	"pt-br": "pt-BR",
	ru: "ru",
	sk: "sk",
	sl: "sl-SI",
	sv: "sv-SE",
	th: "th-TH",
	tr: "tr",
	uk: "uk",
	vi: "vi",
	zh: "zh-CN",
	"zh-tw": "zh-TW"
};
export const TOOLBAR_LIST = [
	"undo",
	"redo",
	"blocks",
	"fontfamily",
	"fontsize",
	"bold",
	"italic",
	"underline",
	"strikethrough",
	"forecolor",
	"backcolor",
	"link",
	"alignleft",
	"aligncenter",
	"alignright",
	"alignjustify",
	"numlist",
	"bullist",
	"outdent",
	"indent",
	"ltr",
	"rtl",
	"removeformat",
	"code",
	"image",
	"pastetext",
	"searchreplace",
	"fullscreen",
	"table"
];
export const TOOLBAR_ITEM_ALIASES : Record<string, string> = {
	formatselect: "blocks",
	fontselect: "fontfamily",
	fontsizeselect: "fontsize"
};

export function normalizeFormatBlock(formatBlock : any) : string
{
	const formatBlockAliases : Record<string, string> = {
		customparagraph: "div"
	};
	const normalizedFormatBlock = formatBlockAliases[String(formatBlock || "p")] || String(formatBlock || "p");

	return [
		"p",
		"div",
		"h1",
		"h2",
		"h3",
		"h4",
		"h5",
		"h6",
		"pre"
	].includes(normalizedFormatBlock) ? normalizedFormatBlock : "p";
}

export function paragraphStyles(preference : PreferenceGetter = egw.preference.bind(egw))
{
	return {
		"font-family": (<string>preference("rte_font", "common") || "arial, helvetica, sans-serif"),
		"font-size": (<string>preference("rte_font_size", "common") || "10") +
			(<string>preference("rte_font_unit", "common") || "pt")
	};
}

export function editorContentStyle(preference : PreferenceGetter = egw.preference.bind(egw)) : string
{
	const styles = paragraphStyles(preference);

	return `
body, p, div {
  font-family: ${styles["font-family"]};
  font-size: ${styles["font-size"]};
  line-height: 1.4;
}
p {
  margin: 1rem 0;
  margin-block: 1rem;
  margin-inline: 0;
}
div {
  margin: 0 !important;
  margin-block: 0 !important;
  margin-inline: 0 !important;
}
body {
  margin: 1rem;
}
`;
}

export function htmlAreaFormats(preference : PreferenceGetter = egw.preference.bind(egw)) : NonNullable<TinyMceConfig["formats"]>
{
	const styles = paragraphStyles(preference);

	return {
		p: {
			block: "p",
			remove: "all",
			styles
		},
		div: {
			block: "div",
			remove: "all"
		}
	};
}

export function toolbarFromSetting(toolbar : string) : string
{
	if(toolbar.includes("|"))
	{
		const normalizedToolbar = toolbar
			.split(/\s+/)
			.map(item => TOOLBAR_ITEM_ALIASES[item] || item)
			.join(" ");

		return normalizedToolbar.includes("false") ? "" : normalizedToolbar;
	}

	const actions = toolbar
		.split(",")
		.map(action => action.trim())
		.map(action => TOOLBAR_ITEM_ALIASES[action] || action)
		.filter(Boolean);

	if(actions.length === 0)
	{
		return TOOLBAR_SIMPLE;
	}

	const disabledActions = TOOLBAR_LIST.filter(action => !actions.includes(action));
	let filteredToolbar = TOOLBAR_ADVANCED;

	disabledActions.forEach(action =>
	{
		filteredToolbar = filteredToolbar.replace(new RegExp(`\\b${action}\\b`, "g"), "");
	});

	filteredToolbar = filteredToolbar
		.split("|")
		.map(group => group.trim().replace(/\s+/g, " "))
		.filter(Boolean)
		.join(" | ");

	return filteredToolbar || TOOLBAR_SIMPLE;
}

export function requestedToolbarSetting(preferenceValue : any, noToolbar = false) : string
{
	if(noToolbar)
	{
		return "";
	}
	if(Array.isArray(preferenceValue))
	{
		return preferenceValue.join(",");
	}
	if(preferenceValue && typeof preferenceValue === "object")
	{
		return Object.values(preferenceValue).join(",");
	}

	return typeof preferenceValue === "string" ? preferenceValue : "";
}

export function toolbarForMode(
	mode : HtmlAreaMode,
	requestedToolbar : string,
	noToolbar = false,
	extraItems : string[] = []
) : string | false
{
	let toolbar : string | false;
	switch(mode)
	{
		case "advanced":
			toolbar = TOOLBAR_ADVANCED;
			break;
		case "extended":
			toolbar = TOOLBAR_EXTENDED;
			break;
		case "ascii":
			toolbar = "";
			break;
		case "simple":
			toolbar = TOOLBAR_SIMPLE;
			break;
		default:
			if(noToolbar)
			{
				toolbar = false;
				break;
			}
			toolbar = requestedToolbar ? toolbarFromSetting(requestedToolbar) : TOOLBAR_SIMPLE;
			break;
	}

	if(toolbar === false || extraItems.length === 0)
	{
		return toolbar;
	}

	const extraToolbar = extraItems.join(" ");
	return toolbar ? `${toolbar} | ${extraToolbar}` : extraToolbar;
}

export function menubarFromPreference(preferenceValue : any, noMenubar = false) : string | false
{
	if(typeof preferenceValue === "undefined" || preferenceValue === null)
	{
		return noMenubar ? false : DEFAULT_MENUBAR;
	}

	return Boolean(parseInt(String(preferenceValue), 10)) && !noMenubar ?
	       DEFAULT_MENUBAR :
	       false;
}