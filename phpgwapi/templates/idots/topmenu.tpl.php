<script language="JavaScript" type="text/javascript">
   function opacity(id, opacStart, opacEnd, millisec) {
		 //speed for each frame
		 var speed = Math.round(millisec / 100);
		 var timer = 0;

		 //determine the direction for the blending, if start and end are the same nothing happens
		 if(opacStart > opacEnd) {
			   for(i = opacStart; i >= opacEnd; i--) {
					 setTimeout("changeOpac(" + i + ",'" + id + "')",(timer * speed));
					 timer++;
			   }
		 } 
		 else if(opacStart < opacEnd) {
			   for(i = opacStart; i <= opacEnd; i++)
			   {
					 setTimeout("changeOpac(" + i + ",'" + id + "')",(timer * speed));
					 timer++;
			   }
		 }
   }

   //change the opacity for different browsers
   function changeOpac(opacity, id) {
		 var object = document.getElementById(id).style;
		 object.opacity = (opacity / 100);
		 object.MozOpacity = (opacity / 100);
		 object.KhtmlOpacity = (opacity / 100);
		 object.filter = "alpha(opacity=" + opacity + ")";
   } 
   function shiftOpacity(id, millisec) {
		 //if an element is invisible, make it visible, else make it ivisible
		 if(document.getElementById(id).style.opacity == 0) {
			   opacity(id, 0, 100, millisec);
			} else {
			   opacity(id, 100, 0, millisec);
		 }
   } 
</script>

<div id="topmenu">
   <div id="topmenu_items">
	  <?php foreach($this->menuitems as $mitems):?>
	  <?php if($mitems['url'] && $mitems['label']):?>
	  <div style="padding:0px 0px 0px 10px;position:relative;float:left;"><img src="<?php print $this->icon_or_star?>" />&nbsp;<a href="<?php print $mitems['url']?>"<?php print $mitems['urlextra']?>><?php print $mitems['label']?></a></div>
	  <?php endif?>
	  <?php endforeach?>
   </div>

   <div id="topmenu_info">
	  <?php foreach($this->info_icons as $iicon):?>
	  <div style="padding:0px 10px 0px 0px;position:relative;float:left;">
		 <?php if(trim($iicon['link'])):?>
		 <a href="<?php print $iicon['link']?>"><img id="<?php print $iicon['id']?>" src="<?php print $iicon['image']?>" <?php print $iicon['tooltip']?>/></a>
		 <?php else:?>
		 <img id="<?php print $iicon['id']?>" src="<?php print $iicon['image']?>" <?php print $iicon['tooltip']?>/>
		 <?php endif?>
	  </div>
	  <?php endforeach?>

	  <?php foreach($this->menuinfoitems as $mitems):?>
	  <div style="padding:0px 10px 0px 0px;position:relative;float:left;"><?php print $mitems?></div>
	  <?php endforeach?>
   </div>
   <div style="clear:both;"></div>
</div>

<script language="JavaScript" type="text/javascript">
	  <?php foreach($this->info_icons as $iicon):?>
	  <?php if($iicon['blink']):?>
	  setInterval("shiftOpacity('<?php print $iicon['id']?>', 500)",1500);
	  <?php endif?>
	  <?php endforeach?>
</script>
