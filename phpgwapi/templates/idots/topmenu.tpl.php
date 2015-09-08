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

	  <?php foreach($this->menuinfoitems as $id => $mitems):?>
	  <div class="topmenu_info_item" id="topmenu_info_<?php print $id?>"><?php print $mitems?></div>
	  <?php endforeach?>
   </div>
   <div style="clear:both;"></div>
</div>
