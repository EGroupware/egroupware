import {css} from "lit";

export default css`
	:host {
		list-style-type: none;
		display: inline;
		padding: 0px;
	}

	et2-link, et2-link::part(base), et2-description {
		display: inline;
	}

	et2-link::part(icon), et2-link::part(remark) {
		display: none;
	}

	et2-link:hover {
		text-decoration: underline;
	}


	/* CSS for child elements */

	et2-link::part(title):after {
		content: ", "
	}

	et2-link:last-child::part(title):after {
		content: initial;
	}
`;
