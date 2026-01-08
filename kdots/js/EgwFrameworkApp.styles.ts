import {css} from 'lit';
import SlSplitPanelStyles from "./EgwFrameworkSplitPanel.styles";

export default css`

    /* Layout */

    :host {
        position: relative;
        width: 100%;
        height: 100%;

        display: flex;
        flex-direction: column;

        --application-color: var(--primary-background-color);
		--left-min: 15em;
        --left-max: 20%;
    }

    :host > * {
        position: relative;
        display: flex;
    }

	.egw_fw_app__name {
		height: 100%;
		flex: 1 1 auto;
		min-width: 2em;
		container: name / inline-size;

		&.hasHeaderContent {
			max-width: var(--left-side-width, 20vw);
			/* Keep the collapse & app icons visible */
			min-width: 5em;
			flex: 1 1 20vw;
		}

		h2 {
			margin: 0;
			margin-inline-start: var(--sl-spacing-medium);
			font-size: 1em;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	.egw_fw_app__header {
		justify-content: flex-start;
		align-items: center;
		justify-items: stretch;
		flex: 5 5 auto;
		max-height: var(--sl-font-size-3x-large);
		/* Needed for flex to shrink nicely */
		min-width: 2em;

		background-color: var(--template-custom-color, var(--application-color, var(--primary-background-color)));
		color: var(--application-header-text-color);
		--sl-input-color: var(--application-header-text-color);
		font-size: var(--sl-font-size-x-large);

		sl-icon-button::part(base),
		et2-button-icon,
		et2-button-icon::part(base) {
			--sl-input-border-color: transparent;
			font-size: inherit;
			color: var(--application-header-text-color, var(--sl-color-neutral-0));
			border: solid var(--sl-input-border-width) var(--sl-input-border-color);
			border-radius: var(--sl-button-border-radius, var(--sl-border-radius-medium));
		}

		et2-button-icon {
			margin: 0 var(--sl-spacing-medium);
		}
        .egw_fw_app__name {
            et2-button-icon {
                margin: 0;
            }
        }

		/* Row count tooltip */

		.egw_fw_app__filter_info_tooltip {
			--sl-tooltip-background-color: var(--application-color, var(--sl-tooltip-background-color));
			--sl-tooltip-padding: var(--sl-spacing-x-small);
		}

		::slotted(*) {
			overflow: hidden;
		}
	}

    .egw_fw_app__header sl-icon-button::part(base):hover, .egw_fw_app__header et2-button-icon::part(base):hover {
        border-color: var(--sl-input-border-color-hover);
    }

    .egw_fw_app__menu > div {
        margin-left: var(--sl-spacing-medium);
        margin-right: var(--sl-spacing-medium);
        display: flex;
        align-items: center;
    }

    .egw_fw_app__menu > div > sl-icon-button {
        margin-right: var(--sl-spacing-medium);
    }

    .egw_fw_app__outerSplit {
        grid-column: start / end;
        grid-row: start / end;
        grid-template-rows: subgrid;
        --min: var(--left-min, 0px);
        --max: var(--left-max, 20%);

		&.no-content {
			--min: 0px;
		}
    }

    .egw_fw_app__innerSplit {
        grid-template-rows: subgrid;
        grid-column-end: -1;
        grid-row: start / end;
        --max: calc(100% - var(--right-min, 0px));
        --min: calc(100% - var(--right-max, 50%));

		&.no-content {
			--min: 100%;
		}
    }

	.egw_fw_app__panel.egw_fw_app--panel-collapsed {
		--min: 0px;
	}

    /*sl-split-panel style*/

    ${SlSplitPanelStyles}
    sl-split-panel::part(divider) {
        grid-row: start / end;
        font-size: var(--sl-font-size-medium);
    }

    sl-split-panel.no-content {
        --divider-width: 0px;
    }

    sl-split-panel.no-content::part(divider) {
        display: none;
    }

    .egw_fw_app__aside {
        overflow-x: hidden;
        overflow-y: auto;
        display: grid;
        grid-template-rows: subgrid;
        grid-row: start / end;
    }


    .egw_fw_app__aside_header {
        grid-row: sub-header / main;
    }

    .egw_fw_app__aside_content {
        height: 100%;
        grid-row: main / footer;
    }

    .egw_fw_app__aside_footer {
        grid-row: footer / end;
    }

    .egw_fw_app__main {
        flex: 1 1 100%;
        display: grid;
        align-items: stretch;
        justify-content: stretch;
        overflow: hidden;

		grid-template-columns: [start left] min-content [ main] 1fr [right] min-content [end];
		grid-template-rows: [start sub-header] fit-content(2em) [main] auto [footer] fit-content(4em) [end];
	}

    .egw_fw_app__filter_drawer [slot="header-actions"] {
        /* Fixes vertical alignment of et2-button-icon buttons in header actions */
        display: flex;
    }

    .egw_fw_app__header {
        grid-row: sub-header / main;
    }

    .egw_fw_app__main_content {
        grid-row: main / footer;
    }

    .egw_fw_app__footer {
        grid-row: footer / end;
    }

    .header, .footer {
        overflow: hidden;
    }

    .egw_fw_app__loading {
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
		grid-row: sub-header / footer;
		grid-column: start / end;
		z-index: calc(var(--sl-z-index-toast) - 1);

		background-color: var(--sl-panel-background-color);

        sl-spinner {
            --track-width: 1rem;
            font-size: 10rem;
            --indicator-color: var(--application-color, var(--primary-background-color, var(--sl-color-primary-600)));
        }
    }

	.egw_fw_app__aside_content, .egw_fw_app__main_content {
		overflow-x: hidden;
		overflow-y: auto;
		display: flex;
		flex-direction: column;
	}

	.egw_fw_app--only_mobile {
		display: none;
	}
	::slotted(*) {
		flex: 1 1 auto;
	}

    @media (min-width: 600px) {

        .egw_fw_app__aside {
            overflow-y: hidden;
        }
		
		::slotted(iframe) {
            width: 100%;
        }
    }
    @media (max-width: 799px) {
		.egw_fw_app--no_mobile {
			display: none;
		}

		.egw_fw_app--only_mobile {
			display: initial;
		}
		.egw_fw_app__header{
			et2-button-icon {
				margin-inline: var(--sl-spacing-x-small);
            }
			.egw_fw_app__menu {
				[slot="trigger"] {
					margin:0;
				}
            }
		}
        sl-split-panel::part(divider) {
            display: none;
        }
        

		--left-max: fit-content;
    }
	@media (max-width: 1200px) {
        .egw_fw_app__name {
            /* Keep the expand chevron and icon visible */
            min-width: 3em !important;
	        &.hasHeaderContent {
		        flex-basis: 8vw;
	        }

            h2 {
                width: 0px;
                margin: 0px !important;
            }

        }
	}
    @media print {
        .content {
            overflow-y: visible !important;
        }

        .egw_fw_app__header > *:not(.egw_fw_app__name) {
            display: none;
        }

        /* hide side menu */
        .egw_fw_app__outerSplit {
            grid-template-columns: 0px 0px auto !important;
        }

        /* Show all content */
        .egw_fw_app__main {
            overflow: auto !important;

            /* Hide spitter icons */

            [slot="divider"] {
                display: none;
            }
        }
    }

    /* End layout */

    /* Styling */

    .egw_fw_app__menu {
        sl-menu-item::part(checked-icon) {
            width: 1em;
        }

        sl-menu-item::part(prefix) {
            min-width: var(--sl-spacing-2x-large);
        }
    }

	et2-image {
		filter: var(--image-filter);
	}
    sl-details.favorites {
		&::part(content) {
			padding: 0;
		}
	}

	sl-menu-item::part(popup) {
		border: none;
		border-radius: var(--sl-panel-border-radius);
	}

	sl-menu {
		border-radius: var(--sl-panel-border-radius);
	}

	et2-favorites-menu::part(menu), &::part(base) {
		border: none;
		border-radius: initial;
	}

`