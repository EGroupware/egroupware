# Mail compose skeleton

The compose window shows a skeleton of its own while it loads, instead of the generic edit form shape.

## How compose gets its shape

`mail/compose.php` already sends the framework's skeleton (`Api\Framework::skeleton('mail.compose')`). It now also sends a
`<style>` just before it that sets the mask to compose's own image, `mail/templates/default/compose.skeleton.svg`.

There is no phone version. A phone opens compose in the in-page dialog (`openComposeDialog()`), not in `compose.php`, and
the framework's small-window rule (`Framework::skeletonCss()`: no skeleton below 32em wide or 24em high) hides every
skeleton on a phone narrower than 512 px anyway, so a phone shape could never be seen.

The rule is `#egw-skeleton{--egw-skeleton-mask:...}`: the id beats the class the page's skeleton css sets the generic mask on
(also in its phone rule), so nothing in the framework changed. The image is a CSS mask, so only its alpha matters and the colour comes from the
theme. Draw it with `viewBox="0 0 100 100" preserveAspectRatio="none"`, in percent of the window;
`api/templates/default/images/dialog-skeleton.svg` shows the conventions (blur, opacities).

A generic way for any template to bring a shape (an SVG next to its `.xet`, found by `Framework::skeletonShape()`) was
written and tested, and left out for now so the framework stays as it is: the patch is in the session notes folder as
`skeleton-shape-lookup-framework.patch`.

## What compose needs

Measured on a real compose popup (870 px wide, 843 px high), dev, 2026-11-12:

- toolbar 0 to 35 px: send, then icon buttons, "HTML" switch at the right
- header rows 40 to 45 px each, label column 85 px, field 764 px: To, Subject (From, Cc, Bcc, Reply to and Folder too, see below)
- editor from about 251 px: TinyMCE's own toolbar (47 px) and the editing area, drawn as one panel to the bottom (the toolbar's
  button groups are not drawn: they looked like a row of unrelated buttons)
- no footer, all buttons are in the toolbar
- left and right margin 11 px, so everything is drawn at 1.5% to 98.5%

Decisions and limits:

1. **Optional rows are not drawn.** From, Cc, Bcc, Reply to and Folder are hidden until they are needed. Which are shown
   depends on the user's `toggledOnActions` preference and on the message (a reply or forward fills Cc). The skeleton
   shows what every compose has (To, Subject), so for a user who keeps Cc and Folder open the real form is about 130 px
   taller above the editor when it replaces the skeleton. A skeleton per preference is possible (the server knows the
   preference), but needs a second shape or generated markup, see below.
2. **The skeleton covers the whole popup window** (`egw-skeleton--page`), so it is stretched to the window height: rows
   are as tall as in a window of about 840 px and grow in a taller one. Compose opens at 870 px by the available screen height.
3. **Only the page skeleton applies, so the window is blank until `compose.php` answers** (measured 2026-11-12: the
   skeleton shows at about 0.14 s on dev and at 0.78 s median, 0.63 to 1.08 s, on pole). The compose popup has no
   `menuaction`, so the opener-drawn skeleton page (`popupSkeletonUrl()` in `egw_open.ts`) and the remembered popup size
   (`egw_popup_size:*`) do not apply. A mail-only opener-side version was drafted (opens the window on a blob page of the
   skeleton and sends it to `compose.php` after it has painted, as `egw_open.ts` does for other forms) and left out:
   the draft is in the session notes folder as `compose-opener-skeleton.ts.txt`.
4. **The in-page compose dialog (`openComposeDialog()`, narrow viewports) has no skeleton at all.** It builds its own
   `et2-dialog`, not through `DialogSkeletonController`.
5. **The skeleton goes when the form's `load` event fires**, which is before TinyMCE has finished starting. Check on a slow
   machine that the editor area does not show a blank gap after the swap.

## Not done

- A skeleton in the in-page compose dialog (item 4) and an opener-side one for the popup (item 3).
- Rows that follow `toggledOnActions` (item 1).
- A generator that draws a skeleton from a template instead of by hand: wait to see whether anyone notices that all
  forms but compose share one shape.
