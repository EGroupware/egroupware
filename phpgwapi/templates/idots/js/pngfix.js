function correctPNG() // correctly handle PNG transparency in Win IE 5.5 or higher.
   {
   for(var i=0; i<document.images.length; i++)
      {
	  var img = document.images[i]
	  var imgName = img.src.toUpperCase()
	  if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
	     {
		 var imgID = (img.id) ? "id='" + img.id + "' " : ""
		 var imgClass = (img.className) ? "class='" + img.className + "' " : ""
		 var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' "
		 var imgStyle = "display:inline-block;" + img.style.cssText 
		 var imgAttribs = img.attributes;
		 for (var j=0; j<imgAttribs.length; j++)
			{
			var imgAttrib = imgAttribs[j];
			if (imgAttrib.nodeName == "align")
			   {		  
			   if (imgAttrib.nodeValue == "left") imgStyle = "float:left;" + imgStyle
			   if (imgAttrib.nodeValue == "right") imgStyle = "float:right;" + imgStyle
			   break
			   }
            }
		 var strNewHTML = "<span " + imgID + imgClass + imgTitle
		 strNewHTML += " style=\"" + "width:" + img.width + "px; height:" + img.height + "px;" + imgStyle + ";"
	     strNewHTML += "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
		 strNewHTML += "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>" 
		 img.outerHTML = strNewHTML
		 i = i-1
	     }
      }
   }
window.attachEvent("onload", correctPNG);
