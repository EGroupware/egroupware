import {css} from 'lit';

export default css`
	:host {
		display: block;
		position: relative;
		height: 100%;
	}

	.template--loading {
		position: absolute;
		width: 100%;
		height: 100%;
		min-height: 5rem;
		display: flex;
		justify-content: center;
		align-items: center;

		background-color: var(--sl-panel-background-color);
		color: var(--application-color, var(--primary-color));

		z-index: var(--sl-z-index-dialog);

		font-size: 5rem;
	}
`;