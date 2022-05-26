import sl_css from '@shoelace-style/shoelace/dist/themes/light.styles.js';
import {css} from "lit";

import {registerIconLibrary} from '@shoelace-style/shoelace/dist/utilities/icon-library.js';
import {egw} from "../../jsapi/egw_global";

/**
 * This makes sure the built-in icons can be found
 */
registerIconLibrary('default', {
	resolver: name => `${egw.webserverUrl}/node_modules/@shoelace-style/shoelace/dist/assets/icons/${name}.svg`,
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