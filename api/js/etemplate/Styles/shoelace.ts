import {css} from "lit";

import {registerIconLibrary} from '@shoelace-style/shoelace/dist/utilities/icon-library.js';
import {egw} from "../../jsapi/egw_global";


/**
 * Here is the common overrides and customisations for Shoelace
 */

/**
 * Make shoelace icons available
 */
registerIconLibrary('default', {
	resolver: name =>
	{
		return typeof egw !== "undefined" && typeof egw.image == "function" ? (egw.image(name) ?? `${egw.webserverUrl || ""}/node_modules/@shoelace-style/shoelace/dist/assets/icons/${name}.svg`) : ''
	},
});

/**
 * Register egw images as an icon library
 * @example <sl-icon library="egw" name="infolog/navbar"/>
 * @example <sl-icon library="egw" name="5_day_view"/>
 */
if(typeof egw !== "undefined" && typeof egw.image == "function")
{
	registerIconLibrary('egw', {
		resolver: name =>
		{
			return (egw.image(name) || '');
		},
	});
}

/**
 * Customise shoelace styles to match our stuff
 * External CSS & widget styles will override this
 */
export default [css`
	.tab-group--top .tab-group__tabs {
		--track-width: var(--track-width);
	}
	.form-control--has-label .form-control__label {
		display: inline-block;
		color: var(--sl-input-label-color);
		margin-right: var(--sl-spacing-medium);
	}
`];