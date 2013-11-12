egw_LAB.wait(function() { 
	$j(document).ready(function() {
		$j('#slidetoggle').click(function(){
			 
			
			  if ($j('#egw_fw_header').css('display') == 'none') {
				  $j("#egw_fw_header").slideToggle();
				  $j("#egw_fw_topmenu_addons").animate({'margin-right': '20px'},1000);
				  $j("#egw_fw_sidebar").animate({'top':'57px'},1000);
				  $j(this).removeClass("slidedown");
				  $j(this).addClass("slideup");
		     }
		     else {
		    	 $j("#egw_fw_header").slideToggle();
		    	  $j("#egw_fw_sidebar").animate({'top':'12px'},1000);
				  $j("#egw_fw_topmenu_info_items").show();
				  $j("#egw_fw_logout").show();
				  $j("#egw_fw_print").show();
				  $j("#egw_fw_topmenu_addons").animate({'margin-right': '250px'},1000);
				  $j(this).removeClass("slideup");
				  $j(this).addClass("slidedown");
		     
		     }
		});
		
	});
	
});