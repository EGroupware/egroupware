import {css} from 'lit';
//this is inside the sl tree set as the color of a selected item
//background-color: var(--sl-color-neutral-100);
//         border-inline-start-color: var(--sl-color-primary-600);
export const mobileCss = css`
    ::part(expand-button) {
        rotate: none;
        padding: 0 var(--sl-spacing-small);
        width: 5em;
        height: 1.2em;
        margin-left: -2.4em;
        margin-right: calc(-2em + 10px);
    }
`

export default css`
	:host {
        --sl-color-primary-600:rgb(0, 124, 255);/*This is nextmatch selected color but with no transparency or white*/
        --sl-color-neutral-100:rgba(153, 204, 255, 0.7);/*This is nextmatch selected color*/
		--sl-spacing-large: 1rem;
		display: block;
	}

/* Style expand and collapse buttons so we can use technically larger images to increase clickable surface*/
	::part(expand-button) {
		rotate: none;
		padding: 0 var(--sl-spacing-2x-small);
	}

    sl-icon[slot='collapse-icon'],sl-icon[slot='expand-icon']{
        width: inherit;
        height: inherit;
    }

	/* Stop icon from shrinking if there's not enough space */
	/* increase font size by 2px this was previously done in pixelegg css but document css can not reach shadow root*/

	sl-tree-item et2-image {
		flex: 0 0 1em;
		font-size: calc(100% + 2px);
		line-height: calc(100% - 2px);
		padding-right: .4em;
		width: 1em;
		height: 1em;
		display: inline-block;
	}

	::part(label) {
		overflow: hidden;
		flex: 1 1 auto;
	}

	::part(label):hover {
		text-decoration: underline;
	}

	.tree-item__label {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	sl-tree-item.drop-hover {
		background-color: var(--highlight-background-color);
	}

	sl-tree-item.drop-hover > *:not(sl-tree-item) {
		pointer-events: none;
	}

	/*Mail specific style TODO move it out of the component*/

	sl-tree-item.unread > .tree-item__label {
		font-weight: bold;
	}
	sl-tree-item[selected] > .tree-item__label {
		font-weight: bold;
	}

	sl-tree-item.mailAccount > .tree-item__label {
		font-weight: bold;
	}

	sl-tree > sl-tree-item:nth-of-type(n+2) {
		margin-top: 2px;
	}

	/* End Mail specific style*/

	sl-tree-item.drop-hover sl-tree-item {
		background-color: var(--sl-color-neutral-0);
	}

	sl-tree-item[unselectable]::part(item) {
		outline: none;
		opacity: 0.5;
	}

	/*TODO color of selected marker in front should be #006699 same as border top color*/

	sl-badge::part(base) {

		background-color: var(--badge-color); /* This is the same color as app color mail */
		font-size: 1em;
		font-weight: 900;
		position: absolute;
		top: 0;
		right: 0.5em;
		line-height: 60%;
	}


	@media only screen and (max-device-width: 768px) {
		:host {
			--sl-font-size-medium: 1.2rem;
		}

		sl-tree-item {
			padding: 0.1em;
		}


	}
`;