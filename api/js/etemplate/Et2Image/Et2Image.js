/**
 * EGroupware eTemplate2 - Image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
var Et2Image_1;
import { html, LitElement } from "lit";
import { Et2Widget } from "../Et2Widget/Et2Widget";
import { property } from "lit/decorators/property.js";
import { customElement } from "lit/decorators/custom-element.js";
import { until } from "lit/directives/until.js";
import { unsafeSVG } from "lit/directives/unsafe-svg.js";
import DOMPurify from 'dompurify';
let Et2Image = Et2Image_1 = class Et2Image extends Et2Widget(LitElement) {
    constructor() {
        super();
        /** Et2Image has no shadow DOM, styles in etemplate2.css
        static get styles()
        {
            return [
                ...super.styles,
                css`
                    :host {
                        display: inline-block;
                    }
    
                    ::slotted(img) {
                        max-height: 100%;
                        max-width: 100%;
                    }
    
                    :host([icon]) {
                        height: 1.3rem;
                        font-size: 1.3rem !important;
                    }
                `];
        }
         */
        /**
         * The label of the image
         * Actually not used as label, but we put it as title
         */
        this.label = "";
        /**
         * Default image
         * Image to use if src is not found
         */
        this.defaultSrc = "";
        /**
         * Link Target
         * Link URL, empty if you don't wan't to display a link.
         */
        this.href = "";
        /**
         * Link target
         * Link target descriptor
         */
        this.extraLinkTarget = "_self";
        /**
         * Popup
         * widthxheight, if popup should be used, eg. 640x480
         */
        this.extraLinkPopup = "";
        /**
         * Inline the svg image if possible
         * only works if there is a valid svg behind the source and it is inside our server root
         */
        this.inline = false;
        this._handleClick = this._handleClick.bind(this);
    }
    /**
     * Image
     * Displayed image
     */
    set src(_src) {
        this.classList.forEach(_class => {
            if (_class.startsWith('bi-')) {
                this.classList.remove(_class);
            }
        });
        this.__src = _src;
        let url = this.parse_href(_src) || this.parse_href(this.defaultSrc);
        if (!url) {
            // Hide if no valid image
            if (this._img)
                this._img.src = '';
            return;
        }
        const bootstrap = url.match(/\/node_modules\/bootstrap-icons\/icons\/([^.]+)\.svg/);
        if (bootstrap && !this._img) {
            this.classList.add('bi-' + bootstrap[1]);
            return;
        }
        // change between bootstrap and regular img
        this.requestUpdate();
    }
    get src() {
        return this.__src;
    }
    /**
     * Width of image:
     * - either number of px (e.g. 32) or
     * - string incl. CSS unit (e.g. "32px") or
     * - even CSS functions like e.g. "calc(1rem + 2px)"
     */
    set width(_width) {
        if (this.style) {
            this.style.width = isNaN(_width) ? _width : _width + 'px';
        }
    }
    get width() {
        var _a;
        return (_a = this.style) === null || _a === void 0 ? void 0 : _a.width;
    }
    /**
     * Height of image:
     * - either number of px (e.g. 32) or
     * - string incl. CSS unit (e.g. "32px") or
     * - even CSS functions like e.g. "calc(1rem + 2px)"
     */
    set height(_height) {
        if (this.style) {
            this.style.height = isNaN(_height) ? _height : _height + 'px';
        }
    }
    get height() {
        return this.style.height;
    }
    connectedCallback() {
        super.connectedCallback();
    }
    /**
     *
     * takes a svg as text and does some replacement to force the inlined svgs to always have a uniform size
     * @param svg {string} the input text. This should be a valid <svg> file content
     * @param purify set to true if DOMPurify should be run on the string(default).
     * This might decrease performance. Only set to false, if the source can be trusted
     * @returns altered valid svg file content
     */
    transformSvg(svg, purify = true) {
        const svgTagMatch = svg.match(/<svg\b[^>]*>/i);
        if (!svgTagMatch)
            return svg; // not an SVG
        let svgTag = svgTagMatch[0];
        // 1) normalize existing width/height to 100%
        svgTag = svgTag.replace(/\b(width|height)=(['"])[^'"]*\2/g, '$1="100%"');
        // 2) add missing width
        if (!/\bwidth=/.test(svgTag)) {
            svgTag = svgTag.replace(/^<svg\b/i, '<svg width="100%"');
        }
        // 3) add missing height
        if (!/\bheight=/.test(svgTag)) {
            svgTag = svgTag.replace(/^<svg\b/i, '<svg height="100%"');
        }
        // add part="image" for consistent styling
        if (!/\bpart=/.test(svgTag)) {
            svgTag = svgTag.replace(/^<svg\b/i, '<svg part="image"');
        }
        // Replace the original opening tag with the modified one
        svg = svg.replace(svgTagMatch[0], svgTag);
        // Purify if requested
        if (purify) {
            svg = DOMPurify.sanitize(svg);
        }
        return svg;
    }
    render() {
        const url = this.parse_href(this.src) || this.parse_href(this.defaultSrc);
        if (!url) {
            // Hide if no valid image
            return html ``;
        }
        // set title on et2-image for both bootstrap-image via css-class and embedded img tag
        this.title = this.statustext || this.label || "";
        const bootstrap = url.match(/\/node_modules\/bootstrap-icons\/icons\/([^.]+)\.svg/);
        if (bootstrap) {
            this.classList.add('bi-' + bootstrap[1]);
            return html ``;
        }
        // our own svg images
        // We have svg images prefixed "bi-". These are used like bootstrap font icons.
        // We inline them to be able to control there color etc. directly via css
        //only call unsafeHtml when we are inside /egroupware/
        // ensure a safe origin
        //const ourSvg = url.startsWith(this.egw().webserverUrl + '/') //checks if source is trusted
        const ourSvg = new URL(url, window.location.origin).origin === window.location.origin;
        if (ourSvg && url.match(/\/bi-.*\.svg/)) {
            const svg = fetch(url)
                .then(res => res.text()
                .then(text => unsafeSVG(text)));
            return html `
                ${until(svg, html `<span>...</span>`)}
            `;
        }
        // also inline other svg like our kdots specific navbar icons, so we have full control over them
        if (ourSvg && this.inline && url.endsWith('.svg')) {
            const svg = fetch(url)
                .then(res => res.text()
                .then(text => {
                //if we inline a svg into our et2-image we always want it the fill all the available space of the et2-image, no matter what the svg sais as its size
                //change the size of the et2-image if you want a different size
                const res = this.transformSvg(text);
                const svg = unsafeSVG(res);
                return svg;
            }));
            return html `
                ${until(svg, html `<span>...</span>`)}
                </div>
			`;
        }
        // fallback case (no svg, web source)
        return html `
            <img ${this.id ? html `id="${this.id}"` : ''}
                 src="${url}"
                 alt="${this.label || this.statustext}"
				 style="${this.height ? 'max-height: 100%; width: auto' : 'max-width: 100%; height: auto'}"
                 part="image"
                 loading="lazy"
            >`;
    }
    /**
     * Puts the rendered content / img-tag in light DOM
     * @link https://lit.dev/docs/components/shadow-dom/#implementing-createrenderroot
     */
    createRenderRoot() {
        return this;
    }
    parse_href(img_href) {
        var _a;
        img_href = img_href || '';
        // allow url's too
        if (img_href[0] == '/' || img_href.substr(0, 4) == 'http' ||
            img_href.substr(0, 5) == 'data:' ||
            img_href.substr(0, 5) == 'blob:') {
            return img_href;
        }
        let src = this.egw() && typeof this.egw().image == "function" ? (_a = this.egw()) === null || _a === void 0 ? void 0 : _a.image(img_href) : "";
        if (src) {
            return src;
        }
        return "";
    }
    _handleClick(_ev) {
        if (this.href) {
            this.egw().open_link(this.href, this.extraLinkTarget, this.extraLinkPopup);
        }
        else {
            return super._handleClick(_ev);
        }
    }
    get _img() {
        return this.querySelector('img');
    }
    /**
     * Handle changes that have to happen based on changes to properties
     *
     */
    updated(changedProperties) {
        super.updated(changedProperties);
        // if there's an href or onclick, make it look clickable
        if (changedProperties.has("href") || typeof this.onclick !== "undefined") {
            this.classList.toggle("et2_clickable", this.href || typeof this.onclick !== "undefined");
        }
        for (const changedPropertiesKey in changedProperties) {
            if (Et2Image_1.getPropertyOptions()[changedPropertiesKey] &&
                !(changedPropertiesKey === 'label' || changedPropertiesKey === 'statustext')) {
                this._img[changedPropertiesKey] = this[changedPropertiesKey];
            }
        }
    }
    transformAttributes(_attrs) {
        super.transformAttributes(_attrs);
        // Expand src with additional stuff
        // This should go away, since we're not checking for $ or @
        if (typeof _attrs["src"] != "undefined") {
            let manager = this.getArrayMgr("content");
            if (manager && _attrs["src"]) {
                let src = manager.getEntry(_attrs["src"], false, true);
                if (typeof src != "undefined" && src !== null) {
                    if (typeof src == "object") {
                        this.src = this.egw().link('/index.php', src);
                    }
                    else {
                        this.src = src;
                    }
                }
            }
        }
    }
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
     *
     * @param {array} _attrs array to add further attributes to
     */
    getDetachedAttributes(_attrs) {
        _attrs.push("src", "label", "href", "statustext");
    }
    getDetachedNodes() {
        return [this];
    }
    setDetachedAttributes(_nodes, _values) {
        for (let attr in _values) {
            this[attr] = _values[attr];
        }
    }
};
__decorate([
    property({ type: String })
], Et2Image.prototype, "label", void 0);
__decorate([
    property({ type: String })
], Et2Image.prototype, "src", null);
__decorate([
    property({ type: String })
], Et2Image.prototype, "defaultSrc", void 0);
__decorate([
    property({ type: String })
], Et2Image.prototype, "href", void 0);
__decorate([
    property({ type: String })
], Et2Image.prototype, "extraLinkTarget", void 0);
__decorate([
    property({ type: String })
], Et2Image.prototype, "extraLinkPopup", void 0);
__decorate([
    property({ type: String })
], Et2Image.prototype, "width", null);
__decorate([
    property({ type: String })
], Et2Image.prototype, "height", null);
__decorate([
    property({ type: Boolean })
], Et2Image.prototype, "inline", void 0);
Et2Image = Et2Image_1 = __decorate([
    customElement("et2-image")
], Et2Image);
export { Et2Image };
//# sourceMappingURL=Et2Image.js.map