import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase, lightDomStylesTemplate} from "./Et2CustomfieldsBase";
import {Et2LayoutController, Et2LayoutHost, Et2LayoutName} from "../Layout/Et2LayoutController/Et2LayoutController";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {html, nothing, PropertyValues} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import {ref} from "lit/directives/ref.js";
import type {Et2CustomfieldWidgetMapping} from "./Et2CustomfieldWidgetMapper";
import {applyCustomfieldWidgetMapping, mapCustomfieldToWidgets} from "./Et2CustomfieldWidgetMapper";

import styles from "./Et2Customfields.styles";

/**
 * @summary Renders editable customfield widgets.
 *
 * Field widgets render in light DOM so eTemplate widget lookup,
 * validation, and event paths can discover generated child widgets.
 *
 * Each generated widget carries its own label rather than getting one from a sibling
 * element: a custom element is not labelable, so a `<label for="#name">` beside it would
 * neither focus it on click nor name it for a screen reader.
 *
 * This widget is the submitted input, not the widgets it generates: `Customfields::validate()`
 * reads one `{"#name": value}` map out of our own id, so the generated widgets stay out of the
 * eTemplate widget tree and we report their values together.
 *
 * @csspart base - Container around all customfield rows.
 * @csspart field - Container for one rendered customfield widget.
 */
@customElement("et2-customfields")
export class Et2Customfields extends Et2CustomfieldsBase implements Et2LayoutHost
{
	/**
	 * How the fields are arranged - see Et2LayoutController.
	 *
	 * `2-column` puts two fields side by side while the dialog is wide enough and collapses to one
	 * when it is not, which is what a tab full of customfields wants.  The default stays `stack`
	 * so a template that says nothing keeps one field per row, as the table this replaced did.
	 */
	@property({reflect: true})
	layout : Et2LayoutName = "stack";

	private _layout = new Et2LayoutController(<Et2LayoutHost><unknown>this);

	/**
	 * Id used when a template places `<customfields/>` without one.
	 *
	 * Some templates do - infolog's edit dialog, filemanager's file dialog - and the server reads
	 * their values from this key (`Customfields::GLOBAL_ID`) rather than a per-widget one.
	 */
	static readonly DEFAULT_ID = "custom_fields";

	/**
	 * Key prefix the values are submitted under, eg. `#name`.
	 *
	 * An application always uses `#`; importexport's filter stores customfields under its own
	 * prefix, and the server reads back whatever this says.
	 */
	@property({type: String})
	prefix : string = CUSTOMFIELD_PREFIX;

	/**
	 * Show the customfields as text rather than as inputs.
	 *
	 * Print and view templates ask for this.  Et2Widget has no readonly of its own, so without
	 * declaring it here `readonly="true"` would stay an inert DOM attribute.
	 */
	@property({type: Boolean})
	readonly : boolean = false;

	/**
	 * Show just this one customfield, by name.
	 *
	 * `<et2-customfields field="Mitgliedsart" label="Membership type"/>` is the readable way to
	 * place a single field.  It becomes our id, which is where the server reads the value back
	 * from - the server builds the same id from the same attribute.
	 */
	@property({type: String})
	field : string = "";

	/**
	 * Label for a customfields widget placed as a single field, eg. `field="Mitgliedsart"`.
	 *
	 * It names that one field instead of what the customfield itself is called, which is how
	 * templates give a customfield a context-specific name.  With more than one field showing
	 * there is nothing for it to name, so it is ignored.
	 */
	@property({type: String})
	label : string = "";

	/**
	 * Handler for a change to any of the customfields.
	 *
	 * It is put on each generated widget rather than on this one, so `widget` in the handler is
	 * the input that changed - templates read its value and options.
	 */
	@property({type: Function})
	onchange : any;

	/**
	 * Application the customfields belong to, when they are not the current application's.
	 */
	@property({type: String, attribute: "sub-app"})
	subApp : string = "";

	/**
	 * The generated field widgets, by unprefixed customfield name.
	 *
	 * These are the real inputs, so getValue() and the dirty/validity checks read them straight
	 * from here.  They are deliberately not added to the eTemplate widget tree: lit places each
	 * one in its own row, and Et2Widget.addChild() would move it out again.
	 */
	protected widgets : Record<string, any> = {};

	/** Fields already given their starting value, so a re-render does not undo the user's edits. */
	private _valued : WeakSet<Element> = new WeakSet();

	/**
	 * Widgets a customfield renders in addition to the one holding its value - the button beside a
	 * filemanager upload, the extra buttons of a multi-value button field.  Kept apart from
	 * `widgets` so one customfield name never stands for two values when we report them.
	 */
	private _extraWidgets : Record<string, any[]> = {};

	/**
	 * Field widgets are intentionally rendered into light DOM so legacy widget lookup,
	 * validation, and event paths can see the generated child widgets.  That also means Lit
	 * never adopts `static styles`, so our CSS is rendered as a <style> of our own.
	 */
	protected createRenderRoot()
	{
		return this;
	}

	transformAttributes(attrs : Record<string, any>)
	{
		if(!attrs.id && !this.id)
		{
			const field = attrs.field ?? this.field;
			attrs.id = field ? (attrs.prefix ?? this.prefix) + field : Et2Customfields.DEFAULT_ID;
		}
		super.transformAttributes(attrs);
	}

	/**
	 * et2_IInput: every editable customfield's value, keyed the way the server stores them.
	 *
	 * `Customfields::validate()` looks this map up under our own id, so the whole set is reported
	 * here rather than one widget per field.  Readonly fields are left out, as the server has
	 * nothing to validate for them.
	 */
	/**
	 * @deprecated Legacy et2_customfields_list method name, kept for templates whose onchange
	 * handlers still call it.  Use getValue() instead.
	 */
	get_value() : Record<string, any>
	{
		return this.getValue();
	}

	getValue() : Record<string, any>
	{
		const value = {};
		for(const [fieldName, widget] of Object.entries(this.widgets))
		{
			if(typeof widget?.getValue === "function" && widget.readonly !== true)
			{
				value[this.prefix + fieldName] = widget.getValue();
			}
		}
		return value;
	}

	/**
	 * et2_IInput: true once any generated field has been changed.
	 */
	isDirty() : boolean
	{
		return Object.values(this.widgets).some((widget) => typeof widget?.isDirty === "function" && widget.isDirty());
	}

	/**
	 * et2_IInput: take the current values as the unchanged ones.
	 */
	resetDirty() : void
	{
		Object.values(this.widgets).forEach((widget) => widget?.resetDirty?.());
	}

	/**
	 * et2_IInput: ask each generated field, since none of them is in the widget tree to be asked
	 * on its own.
	 *
	 * @param {String[]} messages Filled with what is wrong, for the user.
	 */
	isValid(messages : string[]) : boolean
	{
		let valid = true;
		for(const widget of Object.values(this.widgets))
		{
			if(typeof widget?.isValid === "function" && !widget.isValid(messages))
			{
				valid = false;
			}
		}
		return valid;
	}

	private _fieldValue(fieldName : string)
	{
		return this.value?.[this.prefix + fieldName] ?? this.value?.[fieldName] ?? "";
	}

	private _fieldWidgetMappings(fieldName : string, field : Record<string, any>, value : any, onlyField : boolean) : Et2CustomfieldWidgetMapping[]
	{
		const mappings = mapCustomfieldToWidgets(fieldName, field, value, {
			context: "field",
			readonly: this.readonly === true,
			prefix: this.prefix,
			onchange: this.onchange,
			// Our own label names the field only when it is the only one we show
			label: onlyField && this.label ? this.label : undefined
		});
		// The file dialog opens in whichever mode the upload beside it accepts
		const upload = mappings[0];
		const select = mappings.find((m) => m.tagName === "et2-vfs-select");
		if(select)
		{
			select.attrs.multiple = upload.attrs.multiple === true;
			select.attrs.mode = select.attrs.multiple ? "open-multiple" : "open";
		}
		return mappings;
	}

	private _fieldWidgetTemplate(fieldName : string, mapping : Et2CustomfieldWidgetMapping, index : number)
	{
		const tag = unsafeStatic(mapping.tagName);
		const widget = staticHtml`
			<${tag}
				${ref((element) => this._adoptFieldWidget(fieldName, element, mapping, index))}
			></${tag}>
		`;
		// Offer the AI assistant around the field, which works on whatever is slotted into it.
		// Off unless the customfield asks for it, and only for a plain textarea: an htmlarea
		// carries its own tools, and there is nothing to rewrite in a field only being displayed.
		if(mapping.tagName !== "et2-textarea" || mapping.attrs.noAiTools)
		{
			return widget;
		}
		return html`
			<et2-ai>${widget}</et2-ai>`;
	}

	/**
	 * Take ownership of a field widget lit has just placed, or let go of one it removed.
	 *
	 * The widget gets our array managers and instance manager - it needs them to reach the server
	 * for a link-entry search or a select's searchUrl, and to resolve its own readonly state -
	 * which is the only part of being a child it actually needs.  Set before its attributes are
	 * applied, since transformAttributes() reads the content array manager.
	 *
	 * Only a widget that holds the field's value is kept in `widgets`, which is what we report,
	 * revalue and check for changes.  A customfield of type label or header renders a caption
	 * instead, and the mapping gives it no id to say so - keeping it out matters twice over: it has
	 * no value to submit, and the revalue in updated() would hand it the field's empty value and
	 * blank the caption out.
	 *
	 * @param {string} fieldName Unprefixed customfield name.
	 * @param {Element} element The generated widget, or undefined when it was removed.
	 * @param {Et2CustomfieldWidgetMapping} mapping Tag and attributes for it.
	 */
	private _adoptFieldWidget(fieldName : string, element : Element | undefined, mapping : Et2CustomfieldWidgetMapping, index : number = 0)
	{
		if(!element)
		{
			if(index === 0)
			{
				delete this.widgets[fieldName];
			}
			return;
		}
		// Only the parent link, not addChild(): that appends, which would move the widget out of
		// the row lit put it in.  _parent is protected on a different instance, hence the cast.
		(<any>element)._parent = this;
		const attrs = {...mapping.attrs};
		if(this._valued.has(element))
		{
			// We have placed this field before, and by now it holds whatever the user has typed -
			// handing it the value we started with would throw their input away.  A later value
			// reaches it through updated() instead.  Tracked per element rather than per name
			// because lit hands us a fresh callback each render, detaching and re-attaching.
			delete attrs.value;
		}
		this._valued.add(element);
		applyCustomfieldWidgetMapping(element, {tagName: mapping.tagName, attrs});
		if(index === 0 && typeof mapping.attrs.id !== "undefined")
		{
			this.widgets[fieldName] = element;
			return;
		}
		(this._extraWidgets[fieldName] ??= [])[index] = element;
		if(mapping.tagName === "et2-vfs-select")
		{
			this._wireVfsSelect(fieldName, element);
		}
	}

	/**
	 * Tie a filemanager customfield's "link a file" button to the upload beside it.
	 *
	 * Picking a file in the dialog has to end up in the upload's own value, or the field still looks
	 * empty and submits nothing.  The link lists elsewhere on the dialog are refreshed too, since
	 * linking the file is what the button just did.
	 *
	 * @param {string} fieldName Unprefixed customfield name.
	 * @param {Element} button The generated et2-vfs-select.
	 */
	private _wireVfsSelect(fieldName : string, button : Element)
	{
		if(this._valued.has(button) && (<any>button)._customfieldsWired)
		{
			return;
		}
		(<any>button)._customfieldsWired = true;
		button.addEventListener("change", (event) =>
		{
			const select = <any>event.target;
			const upload = this.widgets[fieldName];
			if(!upload)
			{
				return;
			}
			document.querySelectorAll("et2-link-list").forEach((list) => (<any>list).get_links?.());
			const value = upload.multiple ? upload.value : {};
			const picked = typeof select.value == "string" ? [select.value] : (select.value || []);
			picked.forEach((entry) =>
			{
				const path = typeof entry == "string" ? entry : entry.path;
				const info = select._dialog?.fileInfo(path);
				if(!info)
				{
					return;
				}
				const key = Object.values(value).length + info.path;
				value[key] = {...info, type: info.mime, uniqueIdentifier: key};
				if(typeof entry !== "string")
				{
					Object.assign(value[key], entry);
				}
			});
			upload.value = value;
		});
	}

	/**
	 * Give the fields a new value when ours changes, eg. the entry was reloaded.
	 *
	 * Rendering deliberately leaves an already-placed field's value alone, so this is the one path
	 * that replaces what the user has in front of them.
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(!changedProperties.has("value"))
		{
			return;
		}
		for(const [fieldName, widget] of Object.entries(this.widgets))
		{
			if(this.customfields?.[fieldName] && typeof widget?.set_value === "function")
			{
				widget.set_value(this._fieldValue(fieldName));
			}
		}
	}

	/**
	 * A caption for a filemanager field, which cannot show its own.
	 *
	 * An upload renders its label inside its own button: in a row of customfields that puts the
	 * field's name somewhere different from all the others, and a field set to noUpload has that
	 * button hidden, so it showed a lone folder icon with nothing saying which field it was.  The
	 * legacy layout never had either problem - it kept every label in its own table cell.
	 *
	 * Rendered here rather than as another generated widget so it stays out of the values we report
	 * and never takes the place of the one holding the field's value.
	 *
	 * @param {string} fieldName Unprefixed customfield name, the caption of last resort.
	 * @param {object} field The customfield's definition.
	 * @param {Et2CustomfieldWidgetMapping[]} mappings Widgets this field renders as.
	 */
	private _captionFor(fieldName : string, field : Record<string, any>, mappings : Et2CustomfieldWidgetMapping[])
	{
		if(!mappings.some((mapping) => mapping.tagName === "et2-vfs-upload" && !mapping.attrs.readonly))
		{
			return nothing;
		}
		return html`
            <et2-label class="customfields__caption et2-label-fixed" .value=${field.label || fieldName}></et2-label>`;
	}

	/**
	 * Whether a field needs the whole width of a column layout.
	 *
	 * An editor or a multi-line text is far taller than the single-line controls beside it, and in a
	 * grid it would either squash them or spill over the next column.  `span="all"` is what the
	 * layout rules look for to give a row the full width.
	 *
	 * @param {Et2CustomfieldWidgetMapping[]} mappings Widgets this field renders as.
	 */
	private _spansFullWidth(mappings : Et2CustomfieldWidgetMapping[]) : boolean
	{
		return mappings.some((mapping) => ["et2-htmlarea", "et2-textarea"].includes(mapping.tagName));
	}

	render()
	{
		const fields = this.getVisibleFieldNames();
		const onlyField = fields.length === 1;
		return html`
            ${lightDomStylesTemplate(styles)}
            <div class="customfields" part="base">
                ${repeat(fields, (fieldName) => fieldName, (fieldName) =>
                {
                    const field = this.customfields?.[fieldName] || {};
                    const value = this._fieldValue(fieldName);
                    const mappings = this._fieldWidgetMappings(fieldName, field, value, onlyField);
                    if(!mappings.length)
                    {
                        return html``;
                    }
                    return html`
                        <div class="customfields__field" data-field=${fieldName} part="field"
                             span=${this._spansFullWidth(mappings) ? "all" : nothing}>
                            ${this._captionFor(fieldName, field, mappings)}
                            ${mappings.map((mapping, index) => this._fieldWidgetTemplate(fieldName, mapping, index))}
                        </div>
                    `;
                })}
            </div>
		`;
	}
}
