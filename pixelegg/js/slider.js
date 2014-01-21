/**
 * EGroupware: Stylite Pixelegg template: hiding/showing header
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Wolfgang Ott <wolfgang.ott@pixelegg.de>
 * @package pixelegg
 * @version $Id: class.pixelegg_framework.inc.php 2741 2013-11-14 13:53:24Z ralfbecker $
 */

//open
function show_pixelegg_header(_toggle, _delay)
{
	$j("#egw_fw_header").slideToggle();
        
	$j("#egw_fw_topmenu_info_items").animate({"margin-right": "20px","bottom": "0px","padding-right" : "0", "height": "0px"},_delay);
	$j("#egw_fw_topmenu_info_items").css("position", "relative");
        $j("#egw_fw_topmenu_info_items").css("display", "flex");
        $j("#egw_fw_topmenu_info_items").css("float", "right");
	
    $j("#egw_fw_sidebar").animate({'top':'52px'},_delay);
        $j("#egw_fw_tabs").animate({'margin-top':'12px'},_delay);
        $j(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% -3000px");
	$j(_toggle).parent().removeClass("slidedown");
	$j(_toggle).parent().addClass("slideup");
}

//closed
function hide_pixelegg_header(_toggle, _delay)
{
	$j("#egw_fw_header").slideToggle();
	$j("#egw_fw_sidebar").animate({'top':'-3px'},_delay);
	$j("#egw_fw_topmenu_info_items").show();
	$j("#egw_fw_logout").show();
	$j("#egw_fw_print").show();
        $j("#egw_fw_tabs").animate({'margin-top':'-1px'},_delay);
	$j("#egw_fw_topmenu_info_items").animate({
			"bottom": "3px",
                        "right": "5px",
			"display": "flex",
			"padding-right" : "20px",
			"text-align": "right",
			"white-space": "nowrap",
			},_delay);
	$j(".egw_fw_ui_sidemenu_entry_header_active").css("background-position","95% 50%");
      
	$j("#egw_fw_topmenu_info_items").css("position", "fixed");
	$j("#egw_fw_topmenu_info_items").css("z-index", "1000");
        
        $j(".egw_fw_ui_tabs_header").css("height", "34px");
        
        //Tab
        $j(".egw_fw_ui_tab_header").css("height", "24px");
            // ICON
            //$j(".egw_fw_ui_tab_icon").css("height", "17px");
            $j(".egw_fw_ui_tab_icon").css("display", "inline-block");
            $j(".egw_fw_ui_tab_icon").css("margin-right", "5px");
            // H1
            $j(".egw_fw_ui_tabs_header h1").css("float", "none");
            $j(".egw_fw_ui_tabs_header h1").css("display", "inline");
            

	$j(_toggle).parent().removeClass("slideup");
	$j(_toggle).parent().addClass("slidedown");
}

egw_LAB.wait(function() {
	$j(document).ready(function() {

		$j('#slidetoggle').click(function(){
			if ($j('#egw_fw_header').css('display') === 'none') {
				show_pixelegg_header(this, 1000);
				egw.set_preference('common', 'pixelegg_header_hidden', '');
			}
			else {
				hide_pixelegg_header(this, 1000);
				egw.set_preference('common', 'pixelegg_header_hidden', 'true');
			}
		});

		// hide header, if pref says it is not shown
		if (egw.preference('pixelegg_header_hidden')) {
			hide_pixelegg_header($j('#slidetoggle'),0);
		}
         
	});

	// Override jdots height calcluation
	egw_fw.prototype.getIFrameHeight = function()
	{
		$header = $j(this.tabsUi.appHeaderContainer);
		var content = $j(this.tabsUi.activeTab.contentDiv);
		//var height = $j(this.sidemenuDiv).height()-this.tabsUi.appHeaderContainer.outerHeight() - this.tabsUi.appHeader.outerHeight();
		var height = $j(this.sidemenuDiv).height()
			- $header.outerHeight() - $j(this.tabsUi.contHeaderDiv).outerHeight() - (content.outerHeight(true) - content.height())
			// Not sure where this comes from...
			+ 5;
		return height;
	};
});




// ADD 

    function addListeners(){
   
        if(window.addEventListener) {
            // ADD
            document.getElementById('quick_add').addEventListener("mouseover",quick_add_func_over,false);
            document.getElementById('quick_add').addEventListener("mouseout",quick_add_func_out,false);

            
    
        } else if (window.attachEvent){ // Added For Inetenet Explorer versions previous to IE9

            document.getElementById('quick_add').attachEvent("onmouseover",quick_add_func_over);
            document.getElementById('quick_add').attachEvent("onmouseout",quick_add_func_out);
    }
    
        // Write your functions here
    
    function quick_add_func_over(){
            this.style.transition = "0.2s ease-out 0s";
            this.style.width = "166px";
            this.style.borderTopLeftRadius = "20px";
            this.style.backgroundColor = "#0B5FA4";
            quick_add_selectbox.style.transition = "0.1s linear 0.2s";
            quick_add_selectbox.style.visibility = "visible";      
    }
    
    function quick_add_func_out(){
            this.style.transition = "0.2s ease-out 0s";
            this.style.width = "16px"; 
            this.style.borderTopLeftRadius = "0px";
            this.style.backgroundColor = "transparent";
            quick_add_selectbox.style.transition = "0s linear 0s";
            quick_add_selectbox.style.visibility = "hidden"; 
    }
    

    
 }
    window.onload = addListeners; 

/* #egw_fw_topmenu_info_items {
    bottom: 0;
    display: flex;
    float: right;
    padding-right: 20px;
    position: fixed;
    text-align: right;
    white-space: nowrap;
    z-index: 1000;
} */

 /*
     * Replace all SVG images with inline SVG
     */
        $j('img.svg').each(function(){
            var $img = $j(this);
            var imgID = $img.attr('id');
            var imgClass = $img.attr('class');
            var imgURL = $img.attr('src');

            $j.get(imgURL, function(data) {
                // Get the SVG tag, ignore the rest
                var $svg = $j(data).find('svg');

                // Add replaced image's ID to the new SVG
                if(typeof imgID !== 'undefined') {
                    $svg = $svg.attr('id', imgID);
                }
                // Add replaced image's classes to the new SVG
                if(typeof imgClass !== 'undefined') {
                    $svg = $svg.attr('class', imgClass+' replaced-svg');
                }

                // Remove any invalid XML tags as per http://validator.w3.org
                $svg = $svg.removeAttr('xmlns:a');

                // Replace image with new SVG
                $img.replaceWith($svg);

            }, 'xml');

        });