import {css} from "lit";

export default css`
	:host {
	display: block;
	cursor: pointer;
	}

	.link {
	display: flex;
	gap: 0.5rem;
	}

	.link__title {
	flex: 2 1 50%;
	          overflow: hidden;
	          text-overflow: ellipsis;
	          max-width: max-content;
	          width: 0;
	}

	.link__remark {
	flex: 1 1 50%;
	          overflow: hidden;
	          text-overflow: ellipsis;
	          max-width: max-content;
	          width: 0;
	}

	:host:hover {
	text-decoration: underline
	}

	/** Style based on parent **/

	:host(et2-link-string) div {
	display: inline;
	}

	:host-context(et2-link-list):hover {
	text-decoration: none;
	}
`;
