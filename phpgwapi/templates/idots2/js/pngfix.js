/*
* correctPNG
*
* correctly handle PNG transparancy in Win IE 5.5 or Higher.
*
*
*/
function correctPNG() 
{
	for(var i=0; i<document.images.length; i++)
	{
		var img = document.images[i];
		var imgName = img.src.toUpperCase();
		if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
		{
			w = img.width;
			h = img.height;
			if(w != 0) {
				img.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + img.src + "\', sizingMethod='scale')";
				img.src = "phpgwapi/templates/idots2/images/spacer.gif";
				img.style.width = w + "px";
				img.style.height = h + " px";
			}
		}
	}
}
window.attachEvent("onload", correctPNG);

