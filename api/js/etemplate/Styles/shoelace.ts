import sl_css from '@shoelace-style/shoelace/dist/themes/light.styles.js';
import {css} from "lit";

import {registerIconLibrary} from '@shoelace-style/shoelace/dist/utilities/icon-library.js';
import {egw} from "../../jsapi/egw_global";

/**
 * This makes sure the built-in icons can be found
 */
registerIconLibrary('default', {
	resolver: name =>
	{
		return typeof egw !== "undefined" ? `${egw.webserverUrl}/node_modules/@shoelace-style/shoelace/dist/assets/icons/${name}.svg` : ''
	},
});

/**
 * Override some shoelace icons with EGroupware icons
 * In particular, the data: ones give errors with our CSP
 * hacky hack to temporarily work around until CSP issue is fixed
 *
 * @see https://my.egroupware.org/egw/index.php?menuaction=tracker.tracker_ui.edit&tr_id=68774
 */
const egw_icons = {'chevron-down': 'arrow_down', 'x': 'close', 'x-circle-fill': 'close'}
registerIconLibrary("system", {
	resolver: (name) =>
	{
		if(egw_icons[name] && egw)
		{
			return `${egw.webserverUrl}/pixelegg/images/${egw_icons[name]}.svg`;
		}
		return "";
	}
});


/**
 * Customise shoelace styles to match our stuff
 * External CSS will override this
 */
export default [sl_css, css`
  :root,
  :host,
  .sl-theme-light {
  	  --sl-font-size-medium: 11px;
  		
      --sl-input-height-small: 18px;
      --sl-input-height-medium: 24px;
      
      --sl-spacing-small: 0.1rem;
      --sl-spacing-medium: 0.5rem;
      
      --sl-input-border-radius-small: 2px;
      --sl-input-border-radius-medium: 3px;
      --sl-input-border-color-focus: #E6E6E6;
  }
 
  `];