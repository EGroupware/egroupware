<!-- BEGIN header -->
<style>
textarea {
	background-color: white;
	width: 100%;
	min-height: 3em;
	border: 1px solid #e6e6e6;
}
</style>
<form method="post" action="{action_url}">
 <table width="90%" align="center">
  <tr>
   <td rowspan="2" nowrap>
     <h3>{lang_step} #1:</h3>
   </td>
   <td>{lang_source}</td>
   <td colspan="2">
    <select name="sourcelang">
{sourcelangs}
    </select>
   </td>
  </tr>
  <tr>
   <td>{lang_target}</td>
   <td colspan="2">
    <select name="targetlang" onChange="this.form.submit();">
{targetlangs}
    </select>
   </td>
  </tr>
  <tr>
  <td>&nbsp;</td>
  <input name="app_name" type="hidden" value="{app_name}">
   <td width="10%"><input type="submit" name="submitit" value="{lang_submit}" {load_help}></td>
   <td width="75%">
    <input type="submit" name="cancel" value="{lang_cancel}" {cancel_help}>
   </td>
  </tr>
 </table>
</form>
<hr>
<!-- END header -->

<!-- BEGIN postheader -->
<form method="post" action="{action_url}">
 <table width="90%" align="center" class="egwGridView_grid">
  <tr class="th">
   <th colspan="5" align="center">{lang_application}:&nbsp;{app_title}</td>
  </tr>
  <tr class="th">
   <th align="left">{lang_remove}</td>
   <th align="left">{lang_appname}</td>
   <th align="left">{lang_original}</td>
   <th align="left">{lang_translation}</td>
  </tr>
<!-- END postheader -->

<!-- BEGIN detail -->
  <tr class="{tr_class}">
   <td align="center"><input type="checkbox" name="delete[{mess_id}]"></td>
   <td>{transapp}</td>
   <td>{source_content}</td>
   <td><input name="translations[{mess_id}]" type="text" size="60" value="{content}"></td>
  </tr>
<!-- END detail -->

<!-- BEGIN detail_long -->
  <tr class="{tr_class}">
   <td align="center"><input type="checkbox" name="delete[{mess_id}]"></td>
   <td>{transapp}</td>
   <td colspan="2">
    {source_content}<br>
    <textarea name="translations[{mess_id}]" cols="90" rows="{rows}">{content}</textarea>
   </td>
  </tr>
<!-- END detail_long -->

<!-- BEGIN footer -->
</table>
<table width="90%" align="center">
  <tr><td colspan="6" align="center">
    <font color="red" size="-1">{helpmsg}</font>
  </td></tr>
  <tr valign="top">
   <td nowrap>
     <h3>{lang_step} #2:</h3>
   </td>
   <td align="center">
     <input type="submit" name="update" value="{lang_update}" {update_help}>
     <input type="checkbox" name="update_too" checked value="1" {update_help}>
   </td>
   <td align="center"><input type="submit" name="missingphrase" value="{lang_missingphrase}" {search_help}></td>
   <td align="center"><input type="submit" name="addphrase" value="{lang_addphrase}" {add_help}></td>
   <td align="center"><input type="submit" name="revert" value="{lang_revert}" {revert_help}></td>
   <td align="center"><input type="submit" name="cancel" value="{lang_cancel}" {cancel_help}></td>
  </tr>
</table>
<hr>
 <table width="90%" align="center">
  <tr>
   <td rowspan="2" nowrap>
     <h3>{lang_step} #3:</h3>
   </td>
   <td align="left">{lang_source}</td>
   <td>
     {src_file}
   </td>
   <td align="center">
     <input name="app_name" type="hidden" value="{app_name}">
     <input name="sourcelang" type="hidden" value="{sourcelang}">
     <input type="submit" name="dlsource" value="{lang_download}" {download_help}>
   </td>
   <td align="center">
<!-- BEGIN srcwrite -->
     <input type="submit" name="writesource" value="{lang_write}" {write_help}>
     <input type="checkbox" name="writesource_too" value="1" {write_help}>
<!-- END srcwrite -->
   </td>
  </tr>
  <tr>
   <td align="left">{lang_target}</td>
   <td>
     {tgt_file}
   </td>
   <td align="center">
     <input name="app_name" type="hidden" value="{app_name}">
     <input name="targetlang" type="hidden" value="{targetlang}">
     <input type="submit" name="dltarget" value="{lang_download}" {download_help}>
   </td>
   <td align="center">
<!-- BEGIN tgtwrite -->
     <input type="submit" name="writetarget" value="{lang_write}" {write_help}>
     <input type="checkbox" name="writetarget_too" value="1" {write_help}>
<!-- END tgtwrite -->
   </td>
  </tr>
 </table>
</form>
<!-- END footer -->
