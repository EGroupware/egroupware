/**
* Common functions for import / export
*/

/**
* Clear a selectbox
*/
function clear_options(id) {
	var list = document.getElementById(id);
	for(var count = list.options.length - 1; count >= 0; count--)	{
		list.options[count] = null;
	}
}
