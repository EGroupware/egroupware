import {css} from 'lit';

// noinspection CssUnresolvedCustomProperty
export default css`
    :host {
        display: block;
        width: 100vw;
        height: 100vh;
        position: relative;

        --icon-size: 32px;
        --inactive-tab-opacity: 0.5;
        --header-icon-size: 1.5rem;
        --left-side-width: 200px;
    }

    .egw_fw__layout-default {
        display: grid;

        width: 100%;
        height: 100%;
    }

    .egw_fw__layout-default > * {
        position: relative;
        display: flex;
    }

    .egw_fw__layout-default .egw_fw__banner {
        grid-area: banner;
        grid-column-start: banner-start;
        grid-column-end: banner-end;
    }

    .egw_fw__layout-default .egw_fw__header {
        grid-area: header;
        align-items: center;
    }

    .egw_fw__layout-default .egw_fw__header > * {
        flex: 1 1 auto;
    }

    /* To use the sl-split-panel, we need it to have its own space & nest stuff inside */

    .egw_fw__layout-default .egw_fw__divider {
        max-width: 100vw;
        grid-column-start: start;
        grid-column-end: status-start;
        grid-row-start: main-header;
        grid-row-end: footer;
        display: flex;
        justify-content: stretch;
    }

    .egw_fw__layout-default sl-split-panel {
        width: 100%;
    }

    .egw_fw__layout-default sl-split-panel::part(divider) {
        color: var(--sl-color-primary-500);
    }

    .egw_fw__layout-default .egw_fw__sidemenu {
        overflow-x: hidden;
        overflow-y: auto;
    }

    .egw_fw__layout-default .egw_fw__status {
        overflow-x: hidden;
        overflow-y: auto;
    }

    .egw_fw__layout-default .egw_fw__footer {
        grid-area: footer;
    }


    @media (min-width: 600px) {
        .egw_fw__main {
            overflow: hidden;
        }

        .egw_fw__layout-default {
            grid-template-columns: [start banner-start header-start footer-start main-start] 1fr [main-end header-end banner-end end];
            grid-template-rows: [top banner] fit-content(2em) [header] fit-content(2em) [main-header] fit-content(2em) [main] 1fr [footer bottom] fit-content(2em);
        }
    }
    @media (max-width: 599px) {
        .egw_fw__layout-default {
            grid-template-columns: [start banner-start header-start footer-start main-start] 1fr [main-end header-end banner-end end];
            grid-template-rows: [top banner] fit-content(2em) [header] fit-content(2em) [main-header] fit-content(2em) [main] 1fr [footer bottom] fit-content(2em);
        }

        ::slotted([slot="logo"]) {
            display: none;
        }
    }
    @media print {
        /* Hide the header */
        .egw_fw__header {
            display: none;
        }

        /* Hide status */
        .egw_fw__divider {
            sl-split-panel {
                grid-template-columns: auto 0px 0px !important;
            }
        }

        /* Show all content */
        :host {
            height: auto;
        }

        .egw_fw__main {
            overflow: auto;
        }

        ::slotted(egw-app[active]) {
            display: block;
            height: auto;
        }
    }

    /* Actual styles */

    .egw_fw__header sl-icon-button {
        color: inherit;
    }

    .egw_fw__header .egw_fw__logo_apps {
        container: logo / inline-size;
        flex: 1 1 var(--left-side-width);
        max-width: var(--left-side-width);
        display: flex;
        overflow: hidden;
        justify-content: space-between;
        align-items: center;
    }

    /* Hide logo when things get small (no CSS vars or calc() here) */
    @container logo (width < 150px) {
        slot {
            display: none;
        }
    }

    .egw_fw__header .egw_fw__app_list {
        flex: none;
        font-size: var(--header-icon-size, var(--sl-font-size-2x-large));
        padding-left: 0.5rem;
    }

    .egw_fw__header .spacer {
        flex: 6 0 auto;
        min-width: 2rem;
    }

    .egw_fw__header .spacer_end {
        margin-left: -2rem;
    }

    .egw_fw__app_list::part(panel) {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        background-color: var(--sl-color-neutral-0);
        font-size: var(--icon-size);
        border-radius: var(--icon-size);
        position: relative;
        top: 0.8rem;
        left: 4em;
    }

    .egw_fw__app_list img {
        height: var(--icon-size);
        width: var(--icon-size);
    }

    .egw_fw__open_applications {
        --track-width: 0px;
        max-width: calc(100vw - 13em);
    }

    .egw_fw__open_applications::part(tabs) {
        align-items: baseline;
    }

    .egw_fw__open_applications sl-tab {
        width: auto;
        flex: 1 1 auto;
    }

    .egw_fw__open_applications sl-tab:last-of-type {
        flex: 0 0 auto;
    }

    /*make non active tabs a little transparent*/

    .egw_fw__open_applications sl-tab:not([active]) *[part='tab-icon'] {
        opacity: var(--inactive-tab-opacity);
    }

    .egw_fw__open_applications sl-tab::part(base) {
        padding: 0px;
        font-size: var(--tab-icon-size);
    }

    .egw_fw__open_applications sl-tab::part(close-button) {
        visibility: hidden;
        margin: 0;
        position: relative;
        bottom: 1.5em;
        right: .6em;
        color: var(--sl-color-neutral-900);
    }

    /*Icons for open applications that do not have kdots specific icon*/

    .egw_fw__open_applications et2-image:not([src*='/kdots/']), .egw_fw__app_list et2-image:not([src*='/kdots/']) {
        /* Always force icons to be the same size */
        height: var(--tab-icon-size, 32px);
        min-width: calc(var(--tab-icon-size, 32px));
        min-height: calc(var(--tab-icon-size, 32px));
        /* Prevent large icons from causing problems */
        max-width: var(--tab-icon-size, 32px);
        max-height: var(--tab-icon-size, 32px);

        /*align items centered on round app colored background*/
        padding: var(--sl-spacing-2x-small);
        background-color: var(--application-color, var(--default-color, var(--sl-color-neutral-600)));
        border-radius: var(--sl-border-radius-circle);
        text-align: center;
        line-height: 100%;
        align-content: end;

        *[part="image"] {
            position: relative;
            /*turn all app icons white*/
            filter: brightness(0) invert(1);
            width: 70%;

            /*keep avatar images colored*/

            &[src*="avatar.php"] {
                filter: none;
                width: 100%;
                border-radius: var(--sl-border-radius-circle);
                vertical-align: bottom;
            }
        }
    }

    /*Icons for applications that have a kdots specific icon*/

    .egw_fw__open_applications et2-image[src*='/kdots/'], .egw_fw__app_list et2-image[src*='/kdots/'] {
        /* Always force icons to be the same size */
        height: calc(
                calc(2 * var(--sl-spacing-2x-small) +
                var(--tab-icon-size, 32px)));
        min-width: calc(
                calc(2 * var(--sl-spacing-2x-small) +
                var(--tab-icon-size, 32px)));
        min-height: calc(
                calc(2 * var(--sl-spacing-2x-small) +
                var(--tab-icon-size, 32px)));
        /* Prevent large icons from causing problems */
        max-width: calc(
                calc(2 * var(--sl-spacing-2x-small) +
                var(--tab-icon-size, 32px)));
        max-height: calc(
                calc(2 * var(--sl-spacing-2x-small) +
                var(--tab-icon-size, 32px)));

        *[part="image"] {
            vertical-align: bottom;
            width: calc(
                    calc(2 * var(--sl-spacing-2x-small) +
                    var(--tab-icon-size, 32px)));
        }
    }


    .egw_fw__open_applications sl-tab:hover::part(close-button) {
        visibility: visible;
    }

    ::slotted(egw-app:not([active])) {
        display: none;
    }

`