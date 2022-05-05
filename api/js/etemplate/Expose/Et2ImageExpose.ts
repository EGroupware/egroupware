import {ExposeMixin, ExposeValue, MediaValue} from "./ExposeMixin";
import {Et2Image} from "../Et2Image/Et2Image";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

/**
 * Shows an image and if you click on it it gets bigger
 *
 * Set src property for the thumbnail / small image
 * Set href property to the URL of the full / large image
 */
//@ts-ignore Something not right with types & inheritance according to TypeScript
export class Et2ImageExpose extends ExposeMixin(Et2Image) implements et2_IDetachedDOM
{
	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/**
	 * Used to determine if this widget is exposable.  Images always are, even if we don't actually
	 * know the mime type.
	 *
	 * @returns {ExposeValue}
	 */
	get exposeValue() : ExposeValue
	{
		return {
			mime: "image/*",
			path: this.href,
			download_url: this.href,
		};
	}

	/**
	 * Get the info needed to show this image as slide(s)
	 */
	getMedia(_value) : MediaValue[]
	{
		let media = super.getMedia(_value);
		media[0].title = this.label;
		media[0].thumbnail = this.src;

		return media;
	}
}

customElements.define("et2-image-expose", Et2ImageExpose as any, {extends: 'img'});