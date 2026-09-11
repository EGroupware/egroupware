import {css} from "lit";

export default css`
	:host {
	--header-spacing: var(--sl-spacing-medium);
	}

	.portlet__header {
	flex: 0 0 auto;
	display: flex;
	font-style: inherit;
	font-variant: inherit;
	font-weight: inherit;
	font-stretch: inherit;
	font-family: inherit;
	font-size: var(--sl-font-size-medium);
	line-height: var(--sl-line-height-dense);
	padding: 0px;
	padding-left: 0px;
	margin: 0px;
	position: relative;
	}

	.portlet__title {
	flex: 1 1 auto;
	font-size: var(--sl-font-size-medium);
	user-select: none;
	}

	.portlet__header .portlet__settings-icon {
	display: none;
	}

	.portlet__header:hover .portlet__settings-icon {
	display: initial;
	}

	.portlet__header #settings {
	position: absolute;
	right: 0px;
	}

	.card {
	width: 100%;
	height: 100%
	}

	.card__header {
	display: flex;
	width: 100%;
	padding: 0px;
	padding-left: var(--sl-spacing-medium);
	padding-right: calc(2em + var(--header-spacing));
	}

	.card__body {
	/* display block to prevent overflow from our size */
	display: block;
	overflow: hidden;

	flex: 1 1 auto;
	padding: 0px;
	}


	::slotted(div) {
	}
`;
