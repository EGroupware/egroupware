# Web Component Authoring

These standards apply when creating or modifying EGroupware eTemplate Web Components.

## Minimal Example

This example shows the expected structure for a small, non-input component. It uses the eTemplate widget mixin,
documents
its public API, keeps styles local, exposes supported styling hooks, and emits a namespaced event only in response to
user interaction.

```ts
import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";

/**
 * @summary Displays content with an optional action.
 *
 * Use the default slot for the card content. The action is hidden when `no-action` is set.
 *
 * @slot - The card content.
 *
 * @event {CustomEvent<{action: string}>} et2-action - Emitted when the user activates the action.
 *
 * @csspart base - The component's internal wrapper.
 * @csspart action - The action button.
 *
 * @cssproperty [--example-color=var(--sl-color-primary-600)] - Color of the action button.
 */
@customElement("et2-example-card")
export class Et2ExampleCard extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					--example-color: var(--sl-color-primary-600);
					display: block;
				}

				.example-card {
					display: flex;
					align-items: center;
					gap: var(--sl-spacing-small);
					padding: var(--sl-spacing-medium);
					border: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
					border-radius: var(--sl-border-radius-medium);
				}

				:host([no-border]) .example-card {
					border: 0;
				}

				.example-card__content {
					flex: 1 1 auto;
					min-width: 0;
				}

				.example-card__action {
					flex: 0 0 auto;
					color: var(--example-color);
				}
			`
		];
	}

	/**
	 * Removes the border around the component.
	 */
	@property({type: Boolean, attribute: "no-border", reflect: true})
	noBorder = false;

	/**
	 * Hides the action button.
	 */
	@property({type: Boolean, attribute: "no-action", reflect: true})
	noAction = false;

	private _handleAction()
	{
		this.dispatchEvent(new CustomEvent("et2-action", {
			bubbles: true,
			composed: true,
			detail: {action: "primary"}
		}));
	}

	render()
	{
		return html`
			<div class="example-card" part="base">
				<div class="example-card__content">
					<slot></slot>
				</div>
				${this.noAction ? "" : html`
					<button
						class="example-card__action"
						part="action"
						type="button"
						@click=${this._handleAction}
					>
						${this.egw().lang("Open")}
					</button>
				`}
			</div>
		`;
	}
}
```

Use `Et2InputWidget` instead of `Et2Widget` when the component has a value that must be loaded from and returned to an
eTemplate. Keep the example's overall ordering: static definitions, reactive properties, private implementation, public
methods and sub-templates when needed, then `render()`.

## Accessibility

Accessibility is important to EGroupware, so please keep this goal in mind. Use best practices
and ensure we’re providing an optimal experience for all of our users.

## Composability

Components should be composable, meaning you can easily reuse them with and within other components. This reduces the
overall size, expedites feature development, and maintains a consistent user experience.

## Component Structure

All components have a host element, which is a reference to the <et2-*> element itself. Make sure to always set the host
element’s display property to the appropriate value depending on your needs, as the default is inline per the custom
element spec.

```css
:host {
	display: block;
}
```

Aside from display, avoid setting styles on the host element when possible. The reason for this is that styles applied
to the host element are not encapsulated. Instead, create a base element that wraps the component’s internals and style
that instead. This convention also makes it easier to use BEM in components, as the base element can serve as the
“block” entity.

When authoring components, please try to follow the same structure and conventions found in other components. Classes,
for example, generally follow this structure:

- Static properties/methods
- `@property` decorators
- `@query` decorators
- `@state` decorators
- Lifecycle methods (`connectedCallback()`, `disconnectedCallback()`, `firstUpdated()`, etc.)
- Private/public properties that are not reactive
- Private methods
- Internal event handlers (`_handleClick()`)
- `@watch` decorators
- Public methods
- Sub-templates
- The `render()` method

Please avoid using the public keyword for class fields. It’s simply too verbose when combined with decorators, property
names, and arguments. However, please do add private in front of any property or method that is intended to be private.

## Component Documentation

Every public web component must have a class docblock immediately before its `@customElement()` decorator. The docblock
is used for generated component documentation, so keep it aligned with the rendered template and public API.

Include:

- `@summary` with a concise description of the component.
- A short paragraph for important behavior, legacy compatibility, or integration details that consumers need to know.
- `@slot` entries for every public slot, including inherited slots rendered by helper templates such as labels and help
  text.
- `@event` entries for every event the component emits or intentionally re-emits. Use the actual event name and document
  the `detail` payload when one exists.
- `@csspart` entries for every exposed `part`.
- `@cssproperty` entries for every component-scoped CSS custom property.

Document reactive properties on the property field itself, using the public attribute name when it differs from the
TypeScript property. Document public methods that are intended for consumers. Private implementation
details, internal bridge objects, or CSS classes should still be documented internally ,but are not used as part of the
component API documentation.

## Boolean Props

Boolean props should always default to false, otherwise there’s no way for the user to unset them using only attributes.
To keep the API as friendly and consistent as possible, use a property such as noHeader and a corresponding kebab-case
attribute such as no-header.

When naming boolean props that hide or disable things, prefix them with no-, e.g. no-spin-buttons and avoid using other
verbs such as hide- and disable- for consistency.

This naming guidance applies to new, component-specific properties. The established eTemplate state properties
`readonly`, `hidden`, and `disabled` are valid exceptions and should continue to be used for their standard meanings.

## Conditional Slots

When a component relies on the presence of slotted content to do something, don’t assume its initial state is permanent.
Slotted content can be added or removed any time and components must be aware of this. A good practice to manage this
is:

- Use `HasSlotController` to track the slots the component depends on.
- Never conditionally render `<slot>` elements. Use `hidden` so the slot remains in the DOM and changes can be detected.
- Use `slotchange` directly when the assigned content itself must be inspected.

See [Et2Dialog](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Dialog/Et2Dialog.ts) for an
example.

## Dynamic Slot Names and Expand/Collapse Icons

A pattern has been established in `<sl-details>` and `<sl-tree-item>` for expand/collapse icons that animate on
open/close and we're following that pattern.
In short, create two slots called expand-icon and collapse-icon and render them both in the DOM, using CSS to show/hide
only one based on the current open state. Avoid conditionally rendering them. Also avoid using dynamic slot names, such
as `<slot name=${open ? 'open' : 'closed'}>`, because Firefox will not animate them.

There should be a container element immediately surrounding both slots. The container should be animated with CSS by
default and it should have a part so the user can override the animation or disable it. Please refer to the source and
documentation for <sl-details> and/or <sl-tree-item> for details.

## Fallback Content in Slots

When providing fallback content inside <slot> elements, avoid adding parts, e.g.:

```html

<slot name="icon">
    <sl-icon part="close-icon"></sl-icon>
</slot>
```

This creates confusion because the part will be documented, but it won’t work when the user slots in their own content.
The recommended way to customize this example is for the user to slot in their own content and target its styles with
CSS as needed.

## Custom Events

Components must only emit custom events, and all custom events must start with et2- as a namespace. For compatibility
with frameworks that utilize DOM templates, custom events must have lowercase, kebab-style names. For example, use
et2-change instead of et2Change.

This convention avoids the problem of browsers lowercasing attributes, causing some frameworks to be unable to listen to
them. This problem isn’t specific to one framework, but Vue’s documentation provides a good explanation of the problem.

## Change Events

When change events are emitted by ETemplate components, they should be named et2-change and they should only be emitted
as a result of user input. Programmatic changes, such as setting el.value = '…' should not result in a change event
being
emitted. This is consistent with how native form controls work.

## CSS

Keep component CSS minimal and local to the component. Start with the smallest selectors that express the component's
own layout and state, and avoid styling that belongs to the shared theme.
Use Shoelace design tokens or variables from `api/templates/default/etemplate2.css` for colors, spacing, borders,
shadows, font sizes, and z-index values. Avoid magic numbers such as one-off pixel values unless the value is tied to a
specific external constraint, and document that reason close to the declaration. If a component needs a reusable local
value, expose it as a CSS custom property with a sensible token-based default.

```css
.toolbar {
	gap: var(--sl-spacing-small);
	border-bottom: var(--sl-panel-border-width) solid var(--sl-color-neutral-300);
}
```

Always define styles as a **static getter** (`static get styles()`), not a static field (`static styles = [...]`).
TypeScript compiles static field initializers before the prototype chain is fully wired, so `super.styles` is
`undefined` inside a field initializer. The getter is evaluated lazily at call time, where `super` resolves correctly.

Small amounts of component CSS can be inlined in the component's static `styles`, especially when the styles are only a
few rules and are easier to read beside the template.

```ts
class Et2Example
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: block;
					min-width: 0;
				}
			`
		];
	}
}
```

Move larger style blocks into a sibling `<class>.styles.ts` file and import them into the component. This keeps the
component readable.

```ts
import styles from "./Et2Example.styles";

class Et2Example
{
	static get styles()
	{
		return [
			...super.styles,
			styles
		];
	}
}
```

The matching `Et2Example.styles.ts` file imports Lit's `css` tag and exports one stylesheet:

```ts
import {css} from "lit";

export default css`
	:host {
		display: block;
		min-width: 0;
	}

	.example {
		display: grid;
		gap: var(--sl-spacing-small);
	}
`;
```

Lit supports a limited, build-time CSS nesting syntax in `css` template literals. Use it for short, obvious parent-child
relationships, but keep nesting shallow and avoid Sass-style assumptions. When nesting makes selectors harder to scan,
write the full selector instead.

## Class Names

All components use a shadow DOM, so styles are completely encapsulated from the rest of the document. As a result, class
names used inside a component won’t conflict with class names outside the component, so we’re free to name them anything
we want.

Internally, each component uses the BEM methodology for class names.

BEM names classes according to their role:

- **Block**: the component's base element, such as `.example-card`.
- **Element**: a part of the block, separated with two underscores, such as `.example-card__action`.
- **Modifier**: a variation or state, separated with two hyphens, such as `.example-card--compact`.

Keep names scoped to the component's block and describe what an element is rather than how it looks. For example, prefer
`.example-card__action` over `.blue-button`.

### Standard eTemplate classes

Some classes have established framework behaviour and should be used where their meaning applies:

- `.form-control` is the outer layout wrapper for an input-like component.
- `.form-control--has-label` and `.form-control--has-help-text` indicate which supporting content is present.
- `.form-control--small`, `.form-control--medium`, and `.form-control--large` identify the control size.
- `.form-control__label` is the label element generated by `_labelTemplate()`.
- `.form-control-input` wraps the actual input or interactive control.
- `.form-control__help-text` contains help text and validation feedback generated by `_helpTextTemplate()`.

These names are not arbitrary BEM examples. Shared `Et2Widget` and `Et2InputWidget` styles depend on them for label
alignment, sizing, help text, and classes such as `et2-label-fixed`. Input components should use the shared label and
help
text templates where possible instead of recreating this structure.

Component-specific classes should still use the component's own BEM block. A form control can therefore combine both
conventions:

```ts
html`
	<div class="example form-control" part="base form-control">
		${this._labelTemplate()}
		<div class="example__input form-control-input" part="form-control-input">
			<!-- control -->
		</div>
		${this._helpTextTemplate()}
	</div>
`;
```

### Standard CSS parts

CSS parts are public styling API and must be documented. Use the established names consistently:

- `base` exposes the component's main internal wrapper. Most components should provide it, even when the wrapper also
  has a component-specific class.
- `form-control` exposes the complete form-control wrapper for input-like components.
- `form-control-label`, `form-control-input`, and `form-control-help-text` expose the standard label, control, and help
  text regions.

Add component-specific parts for other elements consumers may reasonably need to style. Do not expose every internal
element, and do not treat internal class names as a substitute for parts.

### Naming CSS Parts

While CSS parts can be named virtually anything, within EGroupware they must use the kebab-case convention and lowercase
letters. Additionally, a BEM-inspired naming convention is used to distinguish parts, subparts, and states.

When composing elements, use `part` to expose an element and `exportparts` to forward parts from a nested component.

```ts
render()
{
	return html`
		<div part="base">
			<sl-icon part="icon" exportparts="base:icon__base"></sl-icon>
		</div>
	`;
}
```

This results in a consistent structure for parts. In this example, `icon` targets the `sl-icon` host element and
`icon__base` forwards the icon's internal `base` part.

## CSS Custom Properties

To expose custom properties as part of a component’s API, scope them to the :host block and provide a default value.

```css
:host {
	--color: var(--sl-color-primary-500);
	--height: var(--sl-input-height-medium);
}
```

Then use the following syntax for comments so they appear in the generated docs. Do not use the --et2- or --sl-
prefixes, as they are reserved for design tokens that live in the global scope.

```ts
/**
 * @cssproperty --color: The component's text color.
 * @cssproperty --height: The component's height.
 */
export default class Et2Example
{
	// ...
}
```

## Focusing on Disabled Items

When an item within a keyboard navigable set is disabled (e.g. tabs, trees, menu items, etc.), the disabled item should
not receive focus via keyboard, click, or tap. It should be skipped just like in operating system menus and in native
HTML form controls. There is no exception to this. If a particular item requires focus for assistive devices to provide
a good user experience, the item should not be disabled and, upon activation, it should inform the user why the
respective action cannot be completed.

## When to use a property vs. a CSS custom property

When designing a component’s API, standard properties are generally used to change the behavior of a component, whereas
CSS custom properties (“CSS variables”) are used to change the appearance of a component. Remember that properties can’t
respond to media queries, but CSS variables can.

There are some exceptions to this (e.g. when it significantly improves developer experience), but a good rule of thumbs
is “will this need to change based on screen size?” If so, you probably want to use a CSS variable.

## When to use a CSS custom property vs. a CSS part

There are two ways to enable customizations for components. One way is with CSS custom properties (“CSS variables”), the
other is with CSS parts (“parts”).

CSS variables are scoped to the host element and can be reused throughout the component. A good example of a CSS
variable would be --border-width, which might get reused throughout a component to ensure borders share the same width
for all internal elements.

Parts let you target a specific element inside the component’s shadow DOM but, by design, you can’t target a part’s
children or siblings. You can only customize the part itself. Use a part when you need to allow a single element inside
the component to accept styles.

This convention can be relaxed when the developer experience is greatly improved by not following these suggestions.
