import sl_css from '@shoelace-style/shoelace/dist/themes/light.styles.js';
import {css} from "lit";

/**
 * Customise shoelace styles to match our stuff
 */
export default [sl_css, css`
  :root,
  :host,
  .sl-theme-light {
      --sl-input-height-small: 18px;
      --sl-input-height-medium: 24px;
      
      --sl-spacing-small: 0.1rem;
      --sl-spacing-medium: 0.5rem;
      
      --sl-input-border-radius-small: 2px;
      --sl-input-border-radius-medium: 3px;
  }
  .menu-item {
    width: --sl-input-height-medium;
    max-height: var(--sl-input-height-medium)
  }
  `];