# Markdown editing affordance for plain-text fields

## Context

Markdown *rendering* shipped in `e79a102fce` — `markdownToHtml()`, `MarkdownController`,
`Et2MarkdownMixin` and `markdown.less`, consumed today by `Et2Description` (and through it
`et2-label`, `et2-textbox_ro`, `et2-textarea_ro`, `et2-number_ro`, `et2-description-expose`) and by
`Et2Ai`. That covers *display* only: you can render markdown, but there is nothing to help you
write it. Users type markdown blind and only see the result once the record is readonly.

This adds the writing half, deliberately narrowly: in the **plain-text editing surfaces only**, a
widget carrying `markdown="true"` offers a PhpStorm-style view toggle — edit / split / preview —
over the same textarea it renders today, plus a PhpStorm-style format popup on selection.

**Explicitly out of scope, by decision:**

- HTML / WYSIWYG mode is untouched. `markdown` has **no effect** when a widget is in an HTML mode.
- No HTML → markdown conversion, therefore no `turndown` and no second markdown parser.
- `mode` is **not** a runtime switch. It stays whatever the template/record declares.
- No new editor library. The editing surface remains the existing `<textarea>`.

The one thing that makes this safe: `HtmlArea::validate()` already skips `HtmLawed::purify()` for
ascii mode, and `Textbox::validate()` purifies nothing at all — so markdown source is stored
verbatim on both paths. Nothing about the storage format changes; `ascii` simply comes to mean
"markdown source", and every existing plain-text value is already valid markdown.

## Scope

Two editing surfaces, both of which render a bare `<textarea>`:

| Widget | Condition | Reaches |
|---|---|---|
| `et2-htmlarea` | `mode="ascii"` only | tracker `tr_description` + `reply_message`, via `tr_edit_mode` |
| `et2-textarea` | always | infolog `info_des`, addressbook `note`, timesheet `ts_description`, filemanager `comment`, calendar `description` |

`mode="ascii"` appears in **zero** templates statically — the only route to it is
`tracker/templates/default/edit.xet:16` (`mode="@tr_edit_mode"`), whose value comes from the
`tr_edit_mode` column (`'ascii'` default, `'html'` when the queue has `htmledit` on). So no new
column and no new mode value are needed.

Readonly twins render the markdown rather than the source:

- `et2-textarea_ro` — **already works**. `Et2TextareaReadonly extends Et2Description`, which already
  carries `Et2MarkdownMixin`. Nothing to do.
- `et2-htmlarea_ro` — needs the mixin and a markdown branch (see below).

## Commit 1 — prerequisite: HtmLawed is purifying ascii fields today

A pre-existing bug that blocks the feature, landed on its own ahead of it.

`Widget::expand_widget()` (`api/src/Etemplate/Widget.php:609-632`) expands every attribute into a
local `$attrs`, but only commits it back onto the widget inside the `if` at `:622`, which requires
`attrs['type']` to contain `@` or `$`. Tracker's type is a plain `htmlarea`, so
`$this->attrs['mode']` stays the literal string `"@tr_edit_mode"`. `HtmlArea::validate():180` then
evaluates `"@tr_edit_mode" != 'ascii'` → true → **tracker's ascii comments are purified today**.

For plain text that is merely wrong (`&` gets entity-normalised, `<user@example.com>` and `<TODO>`
are eaten as bogus tags). For markdown source it is fatal.

Fix: expand `mode` before the comparison in `HtmlArea::validate()` — the method already receives
`$expand`, and `self::expand_name()` is the same helper `expand_widget()` uses.

```php
$mode = self::expand_name($this->attrs['mode'] ?? '', $expand['c'] ?? null, $expand['row'] ?? null,
    $expand['c_'] ?? null, $expand['row_'] ?? null, $expand['cont'] ?? []);
if ($mode != 'ascii')
{
    $value = Api\Html\HtmLawed::purify(...);
}
```

Keep the fix to `mode`. Committing the whole expanded `$attrs` in `expand_widget()` would change
behaviour for every widget in the suite and is not this change's business.

## Commit 2 — the feature

### New: `api/js/etemplate/Markdown/Et2MarkdownEditMixin.ts`

A **second** mixin, composing the existing one rather than extending it. `Et2MarkdownMixin` is
applied to `Et2Description` and `Et2Ai`, which are display-only — adding editor chrome and a view
mode to it would give a nextmatch cell a `markdownMode` property that means nothing.

```ts
export const Et2MarkdownEditMixin = dedupeMixin(<T extends Constructor<LitElement>>(superclass: T) =>
{
    class Et2MarkdownEdit extends Et2MarkdownMixin(superclass)
    {
        /** Which view the markdown editor is showing.  No effect unless markdown is set. */
        @property({type: String, reflect: true, attribute: "markdown-mode"})
        markdownMode: "edit" | "split" | "view" = "view";
        ...
    }
    return Et2MarkdownEdit;
});
```

It supplies:

- **`markdownMode`** — `@property`, never a bare class field (the babel `loose:false` trap that
  `Et2MarkdownMixin` documents: a bare field lowers to `Object.defineProperty` and shadows Lit's
  accessor, so it stays reactive under esbuild tests and goes dead in the rollup build).
- **`_markdownToggleTemplate()`** — the three-state view control, as a **compact `et2-dropdown`**
  (`Layout/Et2Dropdown/Et2Dropdown.ts:7`, `Et2Widget(SlDropdown)`) pinned top-left. The trigger shows
  only the icon of the *current* mode; opening it reveals all three, with the active one highlighted.
  This keeps the chrome over the textarea to a single button.

  - icons: bootstrap-icons `pencil` (edit) / `layout-split` (split) / `eye` (preview), all three
    verified present
  - panel holds three `et2-button-icon`s; the active one gets a class inferred from `markdownMode`,
    e.g. `classMap({active: this.markdownMode === "edit"})`
  - the trigger goes in `slot="trigger"`, and its icon is derived from `markdownMode` too, so the
    collapsed state always reports the current view
  - **close the panel explicitly after a choice.** `sl-menu`'s `sl-select` does that for you, but
    plain buttons in the panel do not — set `open = false` in the click handler or the dropdown
    stays hanging over the field.
  - `noSubmit` on every button, as `Et2Ai.ts:1036` does, or a toolbar click submits the form
  - `Et2Dropdown` has a `toggleOnHover` property (`:31`) that opens the panel on hover instead of
    click. Worth trying — it takes the switch back down to effectively one click without pinning
    three buttons over the text.

  Note `et2-button-toggle` (`Et2Button/Et2ButtonToggle.ts:14`) would **not** have given pressed state
  for free: it is an `sl-switch` with `on`/`off` slots and `icon`/`offIcon` properties, i.e. a
  two-state boolean, not a member of a three-way exclusive group. The class-on-`et2-button-icon`
  approach is the right one.
- **`_markdownShellTemplate(source)`** — wraps a source template and the preview pane in the split
  layout, keyed off `markdownMode`. The element is **`et2-split`**
  (`api/js/etemplate/Layout/Et2Split/Et2Split.ts:418`; there is no `egw-split-pane`), already used in
  templates as `<et2-split vertical="true" primary="start">`. Two things come with it: it persists
  its own position via `PREF_PREFIX = "splitter-size-"` keyed on the widget `id` and app (`:215`,
  `:242`), so the user's chosen ratio survives for free — but only if we give it an `id`, and it
  writes one preference per id. Only mount it in `split`; `edit` and `preview` render the single pane
  directly, so we don't pay for a splitter that isn't visible. Preview content comes from the
  inherited `_markdownTemplate(this.value)`, so parsing, sanitizing and the parse cache are all
  reused unchanged.
- **`_markdownFormatPopupTemplate()`** — the on-selection format popup, below.
- **preference persistence** — reads `markdown_view` on first render, writes it on toggle.

Crucially, when `markdown` is false the mixin contributes nothing to the render path — see the
guard in each widget below. That is the regression contract.

### Selection format popup

The PhpStorm affordance: select a word and a compact floating bar appears above it with a block-style
dropdown ("Normal"), **B**, *I*, ~~S~~, `<>`, link, and a list control. Active in `edit` and `split`,
never in `preview`.

**Anchoring** — `sl-popup` accepts `anchor: Element | string | VirtualElement`
(`popup.component.d.ts:49`) and is already used in-house at `Et2TreeDropdown.ts:991`. A
`VirtualElement` only has to supply `getBoundingClientRect()`, which is exactly what we can compute
for a selection.

The catch: a `<textarea>`'s text is not in the DOM, so `Range.getBoundingClientRect()` is
unavailable and there are no per-character rects. The measurement needs a **mirror div** — an
absolutely-positioned, `visibility: hidden` div that copies the textarea's computed font, padding,
border, width and `white-space`, holds `value.slice(0, selectionEnd)` plus a marker `<span>`, and is
measured via the marker, offset by the textarea's own rect and its `scrollTop`/`scrollLeft`. This is
the well-known `textarea-caret-position` technique, ~60 lines, and it is the only genuinely fiddly
part of this change. It goes in its own file so it can be tested on its own:
`api/js/etemplate/Markdown/textareaSelectionRect.ts`.

**Commands** — pure string transforms, no DOM, in a new
`api/js/etemplate/Markdown/MarkdownCommands.ts`:

```ts
export type MarkdownCommand = "bold" | "italic" | "strikethrough" | "code" | "link"
    | "normal" | "h1" | "h2" | "h3" | "h4" | "h5" | "h6" | "quote" | "ul" | "ol" | "checklist";

export function applyCommand(value: string, start: number, end: number, command: MarkdownCommand)
    : {value: string, start: number, end: number}
```

- inline commands wrap the selection in `**` / `*` / `~~` / `` ` ``, and **unwrap** when the
  delimiters already surround it, so the buttons toggle rather than nest
- `link` produces `[selection](url)` with the caret landing inside the parentheses
- block commands rewrite the line prefix of every selected line (`# `…`###### `, `> `, `- `, `1. `,
  `- [ ] `); `normal` strips any existing heading or quote prefix

Keeping these pure mirrors how `markdownToHtml()` is a pure, separately-tested function — the whole
command matrix is unit-testable with no fixture and no `egw()` stub.

**Applying the result without destroying undo.** Writing `textarea.value = …` (what
`Et2ButtonTimestamper.ts:143-152` does) wipes the browser's native undo stack, so Ctrl+Z after a
bold would discard the user's typing instead of the formatting. Select the range and use
`document.execCommand("insertText", false, replacement)` instead, which the browser records as a
normal edit; fall back to direct assignment if it returns false. Either way, heed the timestamper's
own warning at `:147-151` — **the web component's `value` has to be set too, or the change is lost
on submit.**

**Two interaction traps, both classic:**

- The toolbar's `mousedown` must `preventDefault()`. Otherwise the textarea loses focus and its
  selection collapses before the click handler runs, and every button becomes a no-op.
- `Et2Ai._getSelectedText()` (`Et2Ai.ts:685-746`) reads the same `selectionStart`/`selectionEnd`. If
  the popup steals the selection, "ask AI about the selected text" silently starts operating on the
  whole field.

On mobile the popup competes with the native selection handles and context menu, so suppress it
there (`egwIsMobile()`) and leave the always-visible view toggle as the affordance.

### Keyboard shortcuts

Ctrl/Cmd+B, +I and +K (link) route to the same `applyCommand()`. There is no shared shortcut
registry in `api/js/etemplate/` — each widget handles its own keys — so this is a `@keydown` handler
following `Et2DatagridSelectionController.ts:98-111` (`event.ctrlKey || event.metaKey`, then
`preventDefault()` + `stopPropagation()`). It **must** claim the combos explicitly, because
`Et2Textarea.handleKeyUp()` (`:97-105`) deliberately lets modified keystrokes bubble out to the
nextmatch and document handlers.

### Preference

New `markdown_view` in the existing **"Text editor settings"** section of
`preferences/inc/class.preferences_hooks.inc.php` (`:446-521`, alongside `rte_font`, `rte_toolbar`,
`rte_menubar`). Values `edit` / `split` / `view`, default `view`.

Read `this.egw().preference('markdown_view', 'common')`, write
`this.egw().set_preference('common', 'markdown_view', value)` — the signature at
`api/js/jsapi/egw_preferences.ts:58`, used the same way throughout egroupware.

The preference seeds the initial value only; an explicit `markdown-mode` attribute in a template
wins, and toggling within a session updates both the widget and the preference.

### `api/js/etemplate/Et2Textarea/Et2Textarea.ts`

Apply the mixin and wrap Shoelace's own render, with an early return that is the key regression
guard:

```ts
export class Et2Textarea extends Et2MarkdownEditMixin(Et2InputWidget(SlTextarea))

render()
{
    if(!this.markdown)
    {
        return super.render();          // byte-identical to today
    }
    return this._markdownShellTemplate(super.render());
}
```

`this.value` already updates on every keystroke through Shoelace's own input handling, so the
preview re-renders reactively with no extra wiring.

Watch the existing height CSS (`Et2Textarea.ts:20-52`): `:host::part(form-control){height:100%}`
still resolves because `part` selectors are not scoped by nesting, but the new shell element needs
`height:100%` of its own or the field collapses in split view.

### `api/js/etemplate/Et2HtmlArea/Et2HtmlArea.ts`

Apply the mixin, and wrap **only** the ascii branch of `_renderEditor()` (`:1243-1256`). The
`<tinymce-editor>` branch at `:1257-1283` is not touched, which is what makes "no effect in HTML
mode" structural rather than a promise:

```ts
if(this._isAsciiMode)
{
    const source = html`<textarea ...></textarea>`;    // unchanged
    return this.markdown ? this._markdownShellTemplate(source) : source;
}
```

`_handleAsciiInput()` (`:1212-1219`) already writes the raw textarea value into `this.value`, so the
preview updates live here too.

Placement note: put the view toggle inside `.form-control-input` (`:1302`), **not** at the top-left
of `form-control`. The shoelace-style `<label part="form-control-label">` emitted by
`_labelTemplate()` (`Et2InputWidget.ts:807-822`) occupies that corner and is present in the DOM even
when unlabelled. The `et2-ai` floating button sits top-**right** (`Et2Ai.styles.ts:33-38`,
`position:absolute; top:0; right:0`) and *is* visible in ascii mode — `_adoptHTMLAreaTarget()` bails
on `mode === "ascii"` (`Et2Ai.ts:546`), so the `.et2-ai--has-html-target` rule that normally hides it
(`Et2Ai.styles.ts:132-137`) does not apply. Top-left keeps them clear of each other.

### `api/js/etemplate/Et2HtmlArea/Et2HtmlAreaReadonly.ts`

Apply the plain `Et2MarkdownMixin` (display only — no toggle, no popup on a readonly field), add a
markdown branch to the ascii path at `:129`, and add `"markdown"` to `getDetachedAttributes()`
(`:88`) so it survives nextmatch row recycling the way
`Et2Description.getDetachedAttributes():298` already does:

```ts
${this._isAsciiMode
    ? (this.markdown ? this._markdownTemplate(value) : html`${value}`)
    : unsafeHTML(value)}
```

### Styles

Two distinct concerns, kept apart:

- **rendered markdown content** — already solved. `markdownStyles` arrives via the mixin, scoped to
  `.et2_markdown`, single-sourced from `markdown.less`.
- **editor chrome** — the toggle, the popup and the split layout are widget chrome, not markdown
  content, so they belong in the edit mixin's own `css` block. Nothing to add to `markdown.less`, and
  therefore nothing to add to the light-DOM `@import` in `kdots/css/src/widgets.less:427`.

Reminder for whenever `markdown.less` *is* touched: nothing in `build-css.mjs`, `rollup.config.js`
or `package.json` compiles it to `markdown.css`, which is a checked-in artifact. It must be
regenerated by hand.

### DTD

`markdown (false|true|1) #IMPLIED` and `markdownMode (edit|split|view) #IMPLIED` on `et2-textarea`
(ATTLIST at `doc/etemplate2/etemplate2.0.dtd:4269-4313`) and on the legacy `htmlarea` (`:639-680`;
note `et2-htmlarea` itself has no DTD entry — templates write `<htmlarea>` and the preprocessor
prefixes it).

**Do not hand-edit the DTD.** Per `doc/etemplate2/pages/tutorials/creating-a-widget.md:76-94` the
pipeline is `npm run docs` → `php doc/etemplate2-rng.php > doc/etemplate2/etemplate2.0.rng` →
PhpStorm *Tools > XML Actions > Convert Schema*. Overrides live in `doc/etemplate2-rng.php`.

## Commit 3 — tracker opt-in

Add `markdown="true"` to `tracker/templates/default/edit.xet:5` (`tr_description`) and `:16`
(`reply_message`), plus the mobile equivalents. Separately revertable, and the only commit that
changes what any user sees by default.

The readonly display of existing comments at `edit.xet:39-40` already switches on `tr_edit_mode` and
already routes the non-HTML case to `<et2-description>` — which is markdown-capable today, so it
takes the same `markdown="true"` with no widget work.

## Risks

- **The `markdown=false` path must be byte-identical.** Both widgets early-return `super.render()` /
  the bare textarea. This is the single most important test.
- **`@property`, never a bare class field**, for `markdownMode` — see the mixin section.
- **`et2-ai` strips `height`** from the wrapped widget (`api/etemplate.php:543`) and sets
  `flex: 1 1 auto` on slotted children. The split layout has to live inside that box rather than
  assume an explicit height.
- **The mirror-div selection rect is the fragile piece.** It has to track font, padding, border,
  width, `white-space` and both scroll offsets, and it drifts if any of those change without a
  re-measure. Re-measure on `selectionchange`, `scroll` and `resize`; if it proves unreliable in
  practice, anchoring the popup to the top of the field is the graceful degradation, not dropping
  the feature.
- **Undo.** `execCommand("insertText")` is formally deprecated but is still the only way to edit a
  textarea while preserving the native undo stack. If it is ever removed, the fallback silently
  degrades Ctrl+Z rather than breaking the command — worth a comment at the call site so the
  tradeoff is not "fixed" later by someone replacing it with `setRangeText()`.
- **Custom fields are a separate code path** — `et2_extension_customfields.ts:456-511` re-implements
  the AI wrapping client-side and `Customfields.php:373` sets `noAiTools` for text/textarea/htmlarea
  CFs. Textarea custom fields will not pick up `markdown` without explicit handling; out of scope
  here, worth a follow-up.
- **Scroll sync** in split view is out of scope for now.
- `Et2Textarea` has two unrelated pre-existing bugs visible while working here:
  `setTextareaMaxHeight()` (`:133`) overrides a method that does not exist in shoelace 2.20.1 (the
  real one is `setTextareaHeight()`), and `disconnectedCallback()` (`:91-95`) never calls
  `super.disconnectedCallback()`, so shoelace's `ResizeObserver.unobserve` never runs. Neither is
  this change's problem — do not fold them in.

## Verification

```bash
npm run typecheck
npm run jstest
```

Tests:

- `api/js/etemplate/Markdown/test/MarkdownCommands.test.ts` — the whole command matrix as pure
  functions: every inline command wraps *and* unwraps, block prefixes apply across multi-line
  selections, `normal` strips headings and quotes, caret/selection offsets come back correct, and
  empty / whole-field / zero-width selections don't throw
- extend `api/js/etemplate/Markdown/test/` for the mixin's mode handling and preference seeding
- `api/js/etemplate/Et2HtmlArea/test/Et2HtmlArea.test.ts` already exists with `TinyMceStub.ts` /
  `TinyMceSideEffectStub.ts` — add ascii+markdown cases there, and assert the HTML-mode branch is
  untouched
- new `api/js/etemplate/Et2Textarea/test/` (none today), using `inputBasicTests()` from
  `Et2InputWidget/test/InputBasicTests.ts`
- the regression case in both: `markdown=false` renders exactly what it renders today

Browser, on the dev instance:

1. A tracker queue with `htmledit` off → ticket description and comments are ascii. Confirm the
   view dropdown appears top-left, all three views work, the panel closes after a choice, the
   trigger icon tracks the current mode, and the preview matches the readonly rendering.
2. Select a word → the popup appears above it, correctly positioned mid-paragraph, on a wrapped
   line, and after scrolling the textarea. Bold it, then bold it again → it unwraps. Ctrl+Z once
   → the formatting is undone, the typing is not. Then select text and use the AI button → it
   still sees the selection, not the whole field.
3. Toggle to split, reload another record → the preference persisted.
4. A tracker queue with `htmledit` on → TinyMCE, no toggle, no popup, `markdown` inert.
5. Type `<user@example.com>` and `<TODO>` into an ascii comment, save, reopen → both survive
   (this is what commit 1 fixes; verify it fails before that commit and passes after).

Do **not** run `npx rollup -c` — a build watch is already running; reload the tab after editing.
