/*****************************************************
 * ypSlideOutMenu
 * 3/04/2001
 * 
 * a nice little script to create exclusive, slide-out
 * menus for ns4, ns6, mozilla, opera, ie4, ie5 on 
 * mac and win32. I've got no linux or unix to test on but 
 * it should(?) work... 
 *
 * --youngpup--
 *****************************************************/

//var isIE = false;
//var isOther = false;
//var isNS4 = false;
//var isNS6 = false;
// constructor

var IEzindexworkaround=false; // set this true to enable the IE z-index bugfix

function ypSlideOutMenu(id, dir, left, top, width, height,pos)
{

	this.ie  = document.all ? 1 : 0
		this.ns4 = document.layers ? 1 : 0
		this.dom = document.getElementById ? 1 : 0

		if (this.ie || this.ns4 || this.dom) {
			this.id			 = id
				this.dir		 = dir
				this.orientation = dir == "left" || dir == "right" ? "h" : "v"
				this.dirType	 = dir == "right" || dir == "down" ? "-" : "+"
				this.dim		 = this.orientation == "h" ? width : height
				//this.hideTimer	 = false
				//this.aniTimer	 = false
				this.open		 = false
				this.over		 = false
				//this.startTime	 = 0

				// global reference to this object
				//this.gRef = "ypSlideOutMenu_"+id
				//eval(this.gRef+"=this")

				// add this menu object to an internal list of all menus
				//ypSlideOutMenu.Registry[id] = this

				var d = document

				var strCSS = '<style type="text/css">';
			strCSS += '#' + this.id + 'Container { visibility:hidden; '
				if(pos)
				{
					strCSS += pos+':' + left + 'px; '
				}
				else
				{
					strCSS += 'left:' + left + 'px; '
				}
				strCSS += 'top:' + top + 'px; '
					strCSS += 'overflow:visible; z-index:10000; }'
					strCSS += '#' + this.id + 'Container, #' + this.id + 'Content { position:absolute; '
						strCSS += 'width:' + width + 'px; '
							//		strCSS += 'height:' + height + 'px; '
							//		strCSS += 'clip:rect(0 ' + width + ' ' + height + ' 0); '
							strCSS += '}'
							strCSS += '</style>';

						d.write(strCSS);
						//	alert(strCSS);
//						this.load()

					}
		}

	ypSlideOutMenu.aLs = function(layerID)
	{

		this.isIE = false;
		this.isOther = false;
		this.isNS4 = false;
		this.isNS6 = false;
		if(document.getElementById)
		{
			if(!document.all)
			{
				this.isNS6=true;
			}
			if(document.all)
			{
				this.isIE=true;
			}
		}
		else
		{
			if(document.layers)
			{
				this.isNS4=true;
			}
			else
			{
				this.isOther=true;
			}
		}

		var returnLayer;
		if(this.isIE)
		{
			returnLayer = eval("document.all." + layerID + ".style");
		}
		if(this.isNS6)
		{
			returnLayer = eval("document.getElementById('" + layerID + "').style");
		}
		if(this.isNS4)
		{
			returnLayer = eval("document." + layerID);
		}
		if(this.isOther)
		{
			returnLayer = "null";
			alert("Error:\nDue to your browser you will probably not\nbe able to view all of the following page\nas it was designed to be viewed. We regret\nthis error sincerely.");
		}
		return returnLayer;
	}
	// HideShow 1.0	Jim Cummins - http://www.conxiondesigns.com

	ypSlideOutMenu.ShowL = function(ID)
	{
		ypSlideOutMenu.aLs(ID).visibility = "visible";
	}

	ypSlideOutMenu.HideL =function(ID)
	{
		ypSlideOutMenu.aLs(ID).visibility = "hidden";
	}

	ypSlideOutMenu.HideShow = function(ID)
	{

		if((ypSlideOutMenu.aLs(ID).visibility == "visible") || (ypSlideOutMenu.aLs(ID).visibility == ""))
		{
			ypSlideOutMenu.aLs(ID).visibility = "hidden";
		}
		else if(ypSlideOutMenu.aLs(ID).visibility == "hidden")
		{
			ypSlideOutMenu.aLs(ID).visibility = "visible";
		}
	}


	ypSlideOutMenu.showMenu = function(id)
	{
		//temporarly hide all selectboxes to fix IE bug with z-index  
		if(IEzindexworkaround && document.all)
		{
			for (var i=0; i<document.all.length; i++) {
				o = document.all(i)
					if (o.type == 'select-one' || o.type == 'select-multiple') {
						if (o.style) o.style.display = 'none';// todo: add check for select in div?
					}
			}
		}


		ypSlideOutMenu.ShowL(id+'Container');

	}

	ypSlideOutMenu.hide = function(id)
	{
		ypSlideOutMenu.HideL(id+'Container');
		//show all selectboxes again to fix IE bug with z-index  
		if(document.all)
		{
			for (var i=0; i<document.all.length; i++) {
				o = document.all(i)
					if (o.type == 'select-one' || o.type == 'select-multiple') {
						// todo: add check for select in div?
						if (o.style) o.style.display = 'inline';
					}
			}
		}

	}



