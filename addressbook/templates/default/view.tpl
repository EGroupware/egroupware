
<!-- BEGIN view header -->
{view_header}
<!-- END view header -->
<!-- BEGIN view columns-->
{cols}
<!-- END view columns -->

<!-- BEGIN view -->
 <TABLE border="0" cellpadding="1" cellspacing="1">
  <TR> 
   <TD align="left">
     {edit_link}
     <input type="hidden" name="ab_id" value="{ab_id}">
     {edit_button}
    </form>
   </TD>
   <TD align="left">
     {copy_link}
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="hidden" name="fields" value="{copy_fields}">
     <input type="hidden" name="cat_id" value="{cat_id}">
     <input type="submit" name="submit" value="{lang_copy}">
    </form>
   </TD>
   <TD align="left">
     {vcard_link}
     <input type="hidden" name="ab_id" value="{ab_id}">
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="hidden" name="cat_id" value="{cat_id}">
     <input type="submit" name="VCardform" value="{lang_vcard}">
    </form>
   </TD>
   <TD align="left">
     {done_link}
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="hidden" name="ncat_id" value="{cat_id}">
     <input type="submit" name="Doneform" value="{lang_done}">
    </form>
   </TD>
  </TR>
 </TABLE>
<!-- END view -->
