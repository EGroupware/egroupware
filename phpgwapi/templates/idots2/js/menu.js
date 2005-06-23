var curmenu;
/*
* document.onclick
*
* Checks the position of the mouse, if it is clicked outside the menu, the menu will close
*
* @param e mouseEvent
*
*/
document.onclick = function(e) 
{	
	if (!e) var e = window.event;
	var el = (e.target) ? e.target : e.srcElement;
	showObjects();
   	if(el.id != "launch" && parent.document.getElementById('divMain'))
	{
		parent.document.getElementById('divMain').style.display = "none";
	}
	
	if(curmenu != undefined) {
		for(i=0;i<curmenu.childNodes.length;i++) {
			if(curmenu.childNodes[i].tagName == "UL") {
				curmenu.childNodes[i].style.display = "none";
			}
			if(curmenu.childNodes[i].tagName == "A") {	
				curmenu.childNodes[i].className = "";	
				
			}
					
		}
		curmenu.firstChild.className = "";
		curmenu = undefined;
	}
	/*
	Used for opening new pages onClick currently disabled until we know what we will do with it
	
	if(el.tagName == "A" && el.href != "#" && el.href != "") {
		parent.openX(el.innerHTML, el.href);
		return false;
	
	} */
}

/*
* function.itemclick
* 
*  When clicked on an item in the menu this will be activated
* 
* @param e mouseEvent
*/

function itemClick(e) {
	if (!e) var e = window.event;
	var el = (e.target) ? e.target : e.srcElement;
	hideObjects();
	
	pel = (el.parentNode) ? el.parentNode : el;
	if(curmenu == undefined) {
		curmenu = pel;
		el.className = "activated";	
		for(i=0;i<pel.childNodes.length;i++) {
			if(pel.childNodes[i].tagName == "UL") {
				pel.childNodes[i].style.display = "block";
				pel.childNodes[i].style.left = findPosX(pel) + "px";
				pel.childNodes[i].style.top = (findPosY(pel) + 15) + "px";	
			}		
		}
	}
	else {
		for(i=0;i<curmenu.childNodes.length;i++) {
			if(curmenu.childNodes[i].tagName == "UL") {
				curmenu.childNodes[i].style.display = "none";
			}	
			if(curmenu.childNodes[i].tagName == "A") {	
				curmenu.childNodes[i].className = "";	
				
			}		
		}
		curmenu = undefined;
	
	
	}
	e.cancelBubble=true;
	
}

/*
* itemHover
*
* When hoovering an item in the menu this is activated
* 
* @param e mouseEvent
*
*/
function itemHover(e) {
	if (!e) var e = window.event;
	var el = (e.target) ? e.target : e.srcElement;
	pel = (el.parentNode) ? el.parentNode : el;
	if(curmenu != pel) {
		
		if(curmenu != undefined) {
		
			
			var changed = false;
			for(i=0;i<pel.childNodes.length;i++) {
				if(pel.childNodes[i].tagName == "UL") {
					pel.childNodes[i].style.display = "block";
					pel.childNodes[i].style.left = findPosX(pel) + "px";
					pel.childNodes[i].style.top = (findPosY(pel) + 15) + "px";					
					changed = true;
					
				}
				
			}
			if(changed) {
				for(i=0;i<curmenu.childNodes.length;i++) {
					if(curmenu.childNodes[i].tagName == "UL") {
						curmenu.childNodes[i].style.display = "none";
					}
					if(curmenu.childNodes[i].tagName == "A") {	
						curmenu.childNodes[i].className = "";	
						
					}						
				}
	
				curmenu = pel;
				el.className = "activated";
			}
		}
	}
}
/*
* findPosX
*
* Get current positioning of an object relative to the whole document
*
* @param obj htmlObject
*
* returns X(left) coordinate of the object
*/

function findPosX(obj)
{
        var curleft = 0;
        if (obj.offsetParent)
        {
                while (obj.offsetParent)
                {
                        curleft += obj.offsetLeft;
                        obj = obj.offsetParent;
                }
        }
        if (obj.offsetLeft)
                curleft += obj.offsetLeft;
        return curleft;
}
/*
* findObjY
* 
* Get current positioning of an object relative to the whole document
*
* @param obj htmlObejct
*
* returns Y(top) coordinate of the object
*/
function findPosY(obj)
{
        var curtop = 0;
        if (obj.offsetParent)
        {
                while (obj.offsetParent)
                {
                        curtop += obj.offsetTop;
                        obj = obj.offsetParent;
                }
        }
        if (obj.offsetTop)
                curtop += obj.offsetTop;
        return curtop;
}

function hideObjects() {
 if(document.all)
 for (f=0;f<document.forms.length;f++) {
  for (i=0;i<document.forms[f].elements.length;i++) {
    document.forms[f].elements[i].style.visibility='hidden';
  }
 }
}

function showObjects() {
 if(document.all)
 for (f=0;f<document.forms.length;f++) {
  for (i=0;i<document.forms[f].elements.length;i++) {
    document.forms[f].elements[i].style.visibility='visible';
  }
 }
}
