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
  :root,
  :host,
  .sl-theme-light {
	  --sl-font-sans: egroupware, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
	  --sl-button-font-size-medium: var(--sl-font-size-medium);
	  --sl-input-help-text-font-size-medium: var(--sl-font-size-medium);
	  --sl-spacing-small: 0.1rem;
	  --sl-spacing-medium: 0.5rem;

	  --sl-input-border-radius-small: 2px;
	  --sl-input-border-radius-medium: 3px;
	  --sl-input-border-color-focus: #E6E6E6;
	  --indicator-color: #696969;
	  --sl-input-focus-ring-color: #26537C;
	  --sl-focus-ring-width: 2px;
	  --sl-color-gray-150: hsl(240, 4.9%, 92.5%);
  }

	.tab-group--top .tab-group__tabs {
      --track-width: 3px;
  }
  .form-control--has-label .form-control__label {
    display: inline-block;
    color: var(--sl-input-label-color);
    margin-right: var(--sl-spacing-medium);
  }
  `];