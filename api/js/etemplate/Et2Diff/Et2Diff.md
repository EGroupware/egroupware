```html:preview
<et2-diff class="diff-example"></et2-diff>
<script>
document.querySelector(".diff-example").value = `--- diff
+++ diff
@@ -1,2 +1,3 @@
Diff widget shows changes
-highlighting removed words
+highlighting added words
+and lines
`;
</script>
```

Shows a snippet of a [diff](https://www.gnu.org/software/diffutils/manual/html_node/Unified-Format.html), and if you
click on it shows a dialog with the whole diff.

## Examples

### noDialog

Use the noDialog property to disable the size limit and show the whole diff

```html:preview
<et2-diff noDialog class="big-diff"></et2-diff>
<script>
document.querySelector(".big-diff").value = `
--- a/doc/etemplate2/etemplate2.0.dtd	(revision 30665eb1c58c6d903dde091382c7121f5cf5de88)
+++ b/doc/etemplate2/etemplate2.0.dtd	(revision e96e8d9469bf4bc5f52a58d0dd8d9c2b87bc914e)
@@ -27,21 +27,22 @@
                     |barcode|itempicker|script|countdown|customfields-types|nextmatch|nextmatch-header
                     |nextmatch-customfields|nextmatch-sortheader|et2-nextmatch-header-account|et2-appicon|et2-avatar
                     |et2-avatar-group|et2-box|et2-button|et2-button-copy|et2-button-icon|et2-button-scroll
-                    |et2-button-timestamp|et2-category-tag|et2-checkbox|et2-colorpicker|et2-nextmatch-columnselection
-                    |et2-nextmatch-header-custom|et2-date|et2-date-duration|et2-date-range|et2-date-since|et2-date-time
-                    |et2-date-timeonly|et2-date-time-today|et2-description|et2-description-expose|et2-details|et2-dialog
-                    |et2-dropdown-button|et2-email|et2-email-tag|et2-nextmatch-header-entry|et2-favorites
+                    |et2-button-timestamp|et2-button-toggle|et2-category-tag|et2-checkbox|et2-colorpicker
+                    |et2-nextmatch-columnselection|et2-nextmatch-header-custom|et2-date|et2-date-duration|et2-date-range
+                    |et2-date-since|et2-date-time|et2-date-timeonly|et2-date-time-today|et2-description
+                    |et2-description-expose|et2-details|et2-dialog|et2-dropdown|et2-dropdown-button|et2-email
+                    |et2-email-tag|et2-nextmatch-header-entry|et2-favorites|et2-favorites-menu
`;
</script>
```