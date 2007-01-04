<!-- Savant template - sidebox dhtml -->
<script language="JavaScript" type="text/javascript">SET_DHTML("thesideboxcolumn"+NO_DRAG)</script>
<script language="JavaScript" type="text/javascript">ADD_DHTML("sideresize"+CURSOR_W_RESIZE+MAXOFFBOTTOM+0+MAXOFFTOP+0+MAXOFFLEFT+1000+MAXOFFRIGHT+1000)</script>
<script language="JavaScript" type="text/javascript">
   var mainbox = dd.elements.thesideboxcolumn;
   var rt = dd.elements.sideresize;
   var rtxstart= rt.x;

   function my_DragFunc()
   {
		 if (dd.obj == rt)
		 {
			   mainbox.resizeTo(rt.x-rtxstart+<?=$this->sideboxwidth?>, mainbox.h);
		 }
   } 

   function my_DropFunc()
   {
		 xajax_doXMLHTTP("preferences.ajaxpreferences.storeEGWPref","common","idotssideboxwidth",mainbox.w);
   }
</script>
