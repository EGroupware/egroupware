<!-- BEGIN main -->
<script language="JavaScript1.2">
// some translations needed for javascript functions
var lang_reallyDeleteSignatures	= '{lang_really_delete_signatures}';
</script>

<button type="button" onclick="fm_addSignature('{url_addSignature}')"><image src="{url_image_add}" alt="{lang_add}" title="{lang_add}"></button>
<button type="button" onclick="fm_deleteSignatures()"><image src="{url_image_delete}" alt="{lang_delete}" title="{lang_delete}"></button>
<form name="signatureList">
<div id="signatureTable" style="border:1px solid silver;">
{table}
</div>
</form>
<!-- END main -->
