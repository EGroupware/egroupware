<div id="topmenu">
   <div id="topmenu_items">
	  <?php foreach($this->menuitems as $mitems):?>
	  <div style="padding:0px 10px 0px 10px;position:relative;float:left;"><img src="<?=$this->icon_or_star?>" />&nbsp;<a href="<?=$mitems['url']?>"><?=$mitems['label']?></a></div>
	  <?php endforeach?>
   </div>

   <div id="topmenu_info">
	  <?php foreach($this->menuinfoitems as $mitems):?>
	  <div style="padding:0px 10px 0px 10px;position:relative;float:left;"><?=$mitems?></div>
	  <?php endforeach?>
   </div>
   <div style="clear:both;"></div>
</div>
