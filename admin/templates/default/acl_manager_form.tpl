<!-- BEGIN form -->
<form method="POST" action="{form_action}">
 <input type="hidden" name="csrf_token" value="{csrf_token}"/>
 <div align="left">
  <p>{lang_message}</p>
  <p>{select_values}</p>
  <p><input type="submit" name="submit" value="{lang_submit}"> &nbsp;
     <input type="submit" name="cancel" value="{lang_cancel}"></p>
 </div>
</form>

<!-- END form -->
