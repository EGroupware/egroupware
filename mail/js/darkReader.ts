/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, you can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * EGroupware - Mail - dark mode for message bodies
 *
 * Port of Thunderbird's "Dark Reader" - all credit for the approach and the algorithm goes to the Thunderbird developers:
 * https://github.com/thunderbird/thunderbird-desktop/blob/main/mail/base/content/modules/DarkReader.mjs
 *
 * The message is shown with a dark background and light text (html.mailDarkmode in preview.css),
 * and all colors the message sets itself, which would not be readable on that, get removed.
 * Images stay untouched.
 *
 * Changes to the original:
 * - Gecko-only InspectorUtils.isValidCSSColor()/colorToRGBA() replaced by CSS.supports() and a canvas,
 *   and the Gecko-only isInstance() checks by ones working for elements of an iframe too
 * - every change gets recorded, so restoreMessageFromDarkMode() can undo it, when the user switches dark mode off
 * - the "mail.dark-reader.enabled" preference is checked by the caller (MailApp.isBodyDarkmode())
 */

const LUMINANCE_THRESHOLD = 200;
export const CONTRAST_THRESHOLD = 3.5;
const VARIABLE_COLOR_PROPERTIES = [
	"color",
	"background",
	"background-color",
	"background-image",
];

/**
 * Changes made by adaptMessageForDarkMode() to a document, to undo them again
 */
const changesMade = new WeakMap<Document, (() => void)[]>();

let canvasContext: CanvasRenderingContext2D;

const isValidCSSColor = (color: string): boolean => !!color && CSS.supports("color", color);

/**
 * Convert a color string into RGBA values (0-255 and alpha 0-1)
 *
 * @param {string} color
 * @returns null if the color can not be converted, eg. var() or currentcolor
 */
const colorToRGBA = (color: string): { r: number, g: number, b: number, a: number } | null =>
{
	if (!isValidCSSColor(color))
	{
		return null;
	}
	canvasContext ??= document.createElement("canvas").getContext("2d");
	// the canvas ignores colors it can not convert, which we detect by starting from two different colors
	canvasContext.fillStyle = "#000000";
	canvasContext.fillStyle = color;
	const rgba = String(canvasContext.fillStyle);
	canvasContext.fillStyle = "#ffffff";
	canvasContext.fillStyle = color;
	if (String(canvasContext.fillStyle) !== rgba)
	{
		return null;
	}
	// opaque colors are serialized as "#rrggbb", others as "rgba(r, g, b, a)"
	if (rgba[0] === "#")
	{
		const [r, g, b] = [1, 3, 5].map(pos => parseInt(rgba.substring(pos, pos + 2), 16));
		return {r, g, b, a: 1};
	}
	const values = rgba.match(/^rgba?\(([^)]+)\)$/)?.[1].split(",").map(Number);
	return values?.length >= 3 ? {r: values[0], g: values[1], b: values[2], a: values[3] ?? 1} : null;
};

/**
 * Convert a color string into an RGB array and returns the luminance value.
 *
 * @param {string} color - The color string that needs to be turned into RGB.
 * @returns {number}
 */
export const luminance = (color: string): number =>
{
	const rgba = colorToRGBA(color);
	if (!rgba)
	{
		return 0;
	}

	const {r, g, b} = rgba;
	return 0.2125 * r + 0.7154 * g + 0.0721 * b;
};

/**
 * Return the contrast ratio between a background and foreground color for
 * readability validation.
 *
 * @param {string} background - The background color.
 * @param {string} foreground - The foreground color.
 * @returns {number}
 */
export const contrast = (background: string, foreground: string): number =>
{
	const bgLuminance = luminance(background);
	const fgLuminance = luminance(foreground);
	const brightest = Math.max(bgLuminance, fgLuminance);
	const darkest = Math.min(bgLuminance, fgLuminance);
	return (brightest + 0.05) / (darkest + 0.05);
};

/**
 * Check if a color has an alpha level that makes it transparent.
 *
 * @param {string} color - The color to evaluate for transparency.
 * @returns {boolean}
 */
export const isTransparent = (color: string): boolean =>
{
	const rgba = colorToRGBA(color);
	if (!rgba)
	{
		return true;
	}
	// We consider an alpha level below 20% to be transparent.
	return rgba.a <= 0.2;
};

type ResolvedStyle = Pick<CSSStyleDeclaration, "color" | "background" | "backgroundColor" | "backgroundImage">;

/**
 * Check if the various color customization for the current style are suitable
 * for dark mode, and sanitize them if not.
 *
 * @param {CSSStyleDeclaration} style - The style to sanitize.
 * @param {object} [resolvedStyle=style] - Resolved style values.
 */
function sanitizeStyle(style: CSSStyleDeclaration, resolvedStyle: ResolvedStyle = style): void
{
	const {color, background, backgroundColor, backgroundImage} = resolvedStyle;
	// A blend mode is written for the backdrop the sender had in mind. Against a
	// dark one it may blend the content away, and we cannot inspect what it will
	// end up blending with, so drop it.
	if (style.mixBlendMode && style.mixBlendMode !== "normal")
	{
		style.removeProperty("mix-blend-mode");
	}

	if (
		!style.color &&
		!style.background &&
		!style.backgroundColor &&
		!style.backgroundImage
	)
	{
		// Ignore this node if there's no manipulation of colors.
		return;
	}

	// Strip background images that assume a light context since we cannot
	// inspect their luminance.
	if (
		style.backgroundImage &&
		backgroundImage !== "none" &&
		!backgroundImage.includes("gradient")
	)
	{
		style.removeProperty("background-image");
	}

	// Clear text color.
	if (
		(!style.background || style.background == "none") &&
		(!style.backgroundColor || isTransparent(backgroundColor))
	)
	{
		// If no background color is specified, test the color luminance.
		if (luminance(color) <= LUMINANCE_THRESHOLD)
		{
			style.removeProperty("color");
		}
		return;
	}

	// Clear background color.
	if (style.backgroundColor && isValidCSSColor(backgroundColor))
	{
		// Check if the background color luminance is too bright or if the color
		// contrast with foreground is not enough if we have a style color.
		if (
			luminance(backgroundColor) > LUMINANCE_THRESHOLD ||
			(style.color && contrast(color, backgroundColor) < CONTRAST_THRESHOLD)
		)
		{
			style.removeProperty("background-color");
			style.removeProperty("background");

			// Check for color luminance after we removed the background.
			if (style.color && luminance(color) <= LUMINANCE_THRESHOLD)
			{
				style.removeProperty("color");
			}
		}
	}

	// Clear background style.
	if (style.background && isValidCSSColor(background))
	{
		// If there's only background color manipulation, check that its
		// luminance is not too bright.
		if (
			luminance(background) > LUMINANCE_THRESHOLD ||
			(style.color && contrast(color, background) < CONTRAST_THRESHOLD)
		)
		{
			style.removeProperty("background");

			// Check for color luminance after we removed the background.
			if (style.color && luminance(color) <= LUMINANCE_THRESHOLD)
			{
				style.removeProperty("color");
			}
		}
	}

	// Let's not take any chance that a gradient background could impact
	// readability.
	if (
		style.background.includes("gradient") ||
		style.backgroundImage.includes("gradient")
	)
	{
		style.removeProperty("background");
		style.removeProperty("background-image");
	}
}

/**
 * Adapt the message content for dark mode, trying to strip away all inline
 * styles that might interfere.
 *
 * Does nothing, if the document was already adapted.
 *
 * @param {Document} doc - The (loaded) document of the message body.
 */
export function adaptMessageForDarkMode(doc: Document): void
{
	if (!doc?.documentElement || !doc.defaultView || changesMade.has(doc))
	{
		// Bail out if for whatever reason we arrive here and we don't have a
		// document ready to consume.
		return;
	}
	const changes: (() => void)[] = [];
	changesMade.set(doc, changes);

	const removeAttribute = (node: Element, attribute: string) =>
	{
		if (node.hasAttribute(attribute))
		{
			const value = node.getAttribute(attribute);
			node.removeAttribute(attribute);
			changes.push(() => node.setAttribute(attribute, value));
		}
	};
	// restores an element's original style attribute, not just an equivalent cssText (eg. "rgb(0, 0, 0)" for "#000000")
	const recordStyleChanges = (style: CSSStyleDeclaration, change: () => void, node?: Element) =>
	{
		const cssText = style.cssText;
		const attribute = node?.getAttribute("style");
		change();
		if (style.cssText !== cssText)
		{
			changes.push(node ? () => node.setAttribute("style", attribute) : () => {style.cssText = cssText;});
		}
	};

	const documentStyle = doc.defaultView.getComputedStyle(doc.documentElement);

	// Don't do anything if the email already comes with dark mode support.
	if (
		documentStyle.filter.includes("invert(1)") ||
		documentStyle.filter.includes("prefers-color-scheme: dark")
	)
	{
		return;
	}

	// Remove hardcoded body attributes.
	if (doc.body)
	{
		for (const attribute of ["bgcolor", "text", "link", "vlink"])
		{
			removeAttribute(doc.body, attribute);
		}

		// Remove inline style from the main body.
		recordStyleChanges(doc.body.style, () =>
		{
			for (const property of ["background-color", "color"])
			{
				doc.body.style.removeProperty(property);
			}
		}, doc.body);
	}

	// Loop through all child elements that have inline style that might break in
	// dark mode and check if the contrast is not enough for readability.
	for (const node of doc.querySelectorAll<HTMLElement>(
		`:not(button):is([style*="color"],[style*="background"],[style*="blend"],[bgcolor],[color])`
	))
	{
		// Clear inline attributes, usually in tables.
		removeAttribute(node, "bgcolor");
		removeAttribute(node, "color");

		// Bail out if the node doesn't have any inline style.
		if (!node.hasAttribute("style") || !node.style)
		{
			continue;
		}

		recordStyleChanges(node.style, () => sanitizeStyle(node.style), node);
	}

	// SVG text with hardcoded fill attribute.
	for (const node of doc.getElementsByTagName("text"))
	{
		if (!node.hasAttribute("fill"))
		{
			continue;
		}
		if (luminance(node.getAttribute("fill")) <= LUMINANCE_THRESHOLD)
		{
			const fill = node.getAttribute("fill");
			node.setAttribute("fill", "currentColor");
			changes.push(() => node.setAttribute("fill", fill));
		}
	}

	// Collect resolved embedded styles before mutating any stylesheets, so the browser
	// only needs to flush styles once.
	const stylesToSanitize: [CSSStyleDeclaration, ResolvedStyle?][] = [];
	for (const node of doc.getElementsByTagName("style"))
	{
		let rules: CSSRuleList;
		try
		{
			rules = node.sheet?.cssRules;
		}
		catch (e)
		{
			// Bail out if for whatever reason we can't read the rules.
			continue;
		}

		for (const rule of Array.from(rules ?? []))
		{
			// instanceof CSSStyleRule does not work for rules of an iframe's document
			if (rule.type !== CSSRule.STYLE_RULE)
			{
				continue;
			}
			const styleRule = rule as CSSStyleRule;

			const usesVariables = VARIABLE_COLOR_PROPERTIES.some(property =>
				styleRule.style.getPropertyValue(property).includes("var(")
			);
			let element: Element = null;
			if (usesVariables)
			{
				try
				{
					element = doc.querySelector(styleRule.selectorText);
				}
				catch (e)
				{
					// selector not supported by querySelector(), eg. with a pseudo-element
				}
			}
			let resolvedStyle: ResolvedStyle;
			if (element)
			{
				// We ask the browser to resolve variables for us when needed.
				const {color, background, backgroundColor, backgroundImage} =
					doc.defaultView.getComputedStyle(element);
				resolvedStyle = {
					color,
					background,
					backgroundColor,
					backgroundImage,
				};
			}
			stylesToSanitize.push([styleRule.style, resolvedStyle]);
		}
	}

	// Remove any unsuitable embedded styles after all resolved values are read.
	for (const [style, resolvedStyle] of stylesToSanitize)
	{
		recordStyleChanges(style, () => sanitizeStyle(style, resolvedStyle));
	}
}

/**
 * Undo all changes adaptMessageForDarkMode() made to a document
 *
 * @param {Document} doc
 */
export function restoreMessageFromDarkMode(doc: Document): void
{
	const changes = changesMade.get(doc);
	if (!changes)
	{
		return;
	}
	changesMade.delete(doc);
	changes.reverse().forEach(undo => undo());
}
