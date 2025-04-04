import {ExposeValue} from "../Expose/ExposeMixin";
import {et2_vfsMode} from "../et2_widget_vfs";
import {Et2ImageExpose} from "../Expose/Et2ImageExpose";
import {css, html} from "lit";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

@customElement('et2-vfs-mime')
export class Et2VfsMime extends Et2ImageExpose
{
	/**
	 * @todo styles() are NOT working, probably because due to implementing createRenderRoot() returning this in Et2Image, there styles are moved to etemplate2.css
	 */
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
            	position: relative
            }
            img.overlay {
            	position: absolute;
            	bottom: 0;
            	right: 0;
            	z-index: 1;
            	width: 16px;
            	height: 16px;
            }
            `,
		];
	}

	/**
	 * Mime type we're displaying
	 */
	@property({type: String, reflect: true})
	mime = "";
	/**
	 * Mark the file as a link
	 */
	@property({type: Boolean, reflect: true})
	symlink = false;

	/**
	 * Mime type of directories
	 */
	static readonly DIR_MIME_TYPE : string = 'httpd/unix-directory';
	private __mime : string;
	private __symlink : boolean;
	private __download_url : string;

	constructor()
	{
		super();
		this.__download_url = "";
	}

	/**
	 * Used to determine if this widget is exposable.  Images always are, even if we don't actually
	 * know the mime type.
	 *
	 * @returns {ExposeValue}
	 */
	get exposeValue() : ExposeValue
	{
		return Object.assign(super.exposeValue, {
			mime: this.mime,
			download_url: this.__download_url
		});
	}

	/**
	 * Overridden here because while some files cannot actually be put in the gallery, we still want to handle them
	 * in some way.  Some files we'll open directly on click
	 *
	 * @returns {boolean}
	 */
	isExposable() : boolean
	{
		// do not try to expose directories, they are handled by the action system
		if (this.exposeValue.mime === Et2VfsMime.DIR_MIME_TYPE || !this.value.download_url)
		{
			return false;
		}

		let gallery = super.isExposable();

		// @ts-ignore Wants an argument, but does not require it
		let fe = egw.file_editor_prefered_mimes();
		if(fe && fe.mime && fe.edit && fe.mime[this.exposeValue.mime])
		{
			return true;
		}

		return gallery;
	}

	/**
	 * Override et2-image click-handler, to not call egw.open_link with href=<vfs-path>
	 *
	 * @param {MouseEvent} _ev
	 * @returns {boolean}
	 */
	_handleClick(_ev : MouseEvent) : boolean
	{
		if(this.isExposable())
		{
			this.expose_onclick(_ev);
		}
		return false;
	}

	/**
	 * Some files cannot be opened in gallery, but we still want to do something with them
	 * Editable files we open on click.
	 *
	 * @param {MouseEvent} event
	 * @returns {boolean}
	 * @protected
	 */
	protected expose_onclick(event : MouseEvent)
	{
		// super.expose_onclick returns false when it has handled the event, true if it didn't
		let super_handled = super.expose_onclick(event);
		if(true == super_handled)
		{
			// @ts-ignore Wants an argument, but does not require it
			let fe = egw.file_editor_prefered_mimes();
			if(fe && fe.mime && fe.edit && fe.mime[this.exposeValue.mime])
			{
				egw.open_link(egw.link('/index.php', {
					menuaction: fe.edit.menuaction,
					path: this.exposeValue.path,
					cd: 'no'	// needed to not reload framework in sharing
				}), '', fe.edit_popup);
				return false;
			}
		}
		return super_handled;
	}

	/**
	 * Function to get media content to feed the expose
	 *
	 * @param {type} _value
	 * @returns {Array} return an array of object consists of media content
	 */
	getMedia(_value)
	{
		let mediaContent = Object.assign(super.getMedia(_value)[0], {
			title: _value.name,
			type: _value.mime,
			href: _value.download_url
		});

		// check if download_url is not already an url (some stream-wrappers allow to specify that!)
		if(_value.download_url && (_value.download_url[0] == '/' || _value.download_url.substr(0, 4) != 'http'))
		{
			mediaContent.href = this._processUrl(_value.download_url);

			if(mediaContent.href && mediaContent.href.match(/\/webdav.php/, 'ig'))
			{
				mediaContent["download_href"] = mediaContent.href + '?download';
			}
		}
		mediaContent["thumbnail"] = this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);

		return [mediaContent];
	}

	set_value(_value : ExposeValue | any)
	{
		this.value = _value;
	}

	/**
	 * Allow to pass all data as object with attributes "mime", "path", "download_url" and "mode"
	 */
	@property({type: Object})
	set value(_value : ExposeValue | any)
	{
		if(!_value)
		{
			return;
		}
		if (typeof _value === 'string' && _value.indexOf('/') >= 0)
		{
			_value = {mime: _value};
		}
		else if(typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs array with path & mime", this.id, _value);
			// Keep going, will be 'unknown type'
		}

		if(_value.mime)
		{
			this.mime = _value.mime;
		}
		this.label = '';
		if(_value.path)
		{
			this.href = _value.path;
			const parts = _value.path.split('.');
			if (parts.length > 1)
			{
				this.label = egw.lang('%1 File', parts.pop())
			}
		}
		if(_value.download_url)
		{
			this.__download_url = _value.download_url;
		}
		let src = _value.src ?? this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);
		if(src)
		{
			this.src = src;
		}
		// add/remove link icon, if file is (not) a symlink
		this.symlink = typeof _value.mode !== "undefined" && ((_value.mode & et2_vfsMode.types.l) == et2_vfsMode.types.l)
	}

	get value()
	{
		return {
			mime: this.mime,
			symlink: this.__symlink,
			href: this.href,
			path: this.href,
			download_url: this.__download_url ?? '',
			src: this.src
		}
	}

	get src()
	{
		return super.src;
	}

	set src(_value)
	{
		super.src = _value;
		this._set_tooltip();
	}

	render()
	{
		return html`
            ${super.render()}
            ${this.symlink ? html`<img src="${this.egw().image("symlink", "api")}"
                class="overlay" style="position: absolute; bottom: 0; right: 0; height: 12px; width: 12px; z-index: 1"/>` : ""}
		`;
	}

	private _set_tooltip()
	{
		// tooltip for mimetypes with available detailed thumbnail
		if(this.mime && this.mime.match(/application\/vnd\.oasis\.opendocument\.(text|presentation|spreadsheet|chart)/))
		{
			this.egw().tooltipBind(this, '<img src="' + this.src + '&thsize=512"/>', true);
		}
		else
		{
			this.egw().tooltipUnbind(this);
		}
	}
}