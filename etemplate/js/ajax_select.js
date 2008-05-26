/**
*	Javascript file for AJAX select widget
*	
*	@author Nathan Gray <nathangray@sourceforge.net>
*
*	@param widget_id the id of the ajax_select_widget
*	@param onchange function to call if the value of the select widget is changed
*	@param options the query object containing callback and settings
*	
*   @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
*   @package etemplate
*   @subpackage extensions
*   @link http://www.egroupware.org
*
*   @version $Id$
*/

//xajaxDebug = 1;
var current_app = 'etemplate';

/**
 * Settings for the timeout to prevent flooding the server with requests
 *
 * Adjust ajax_select_timeout to change how long to wait before sending the request (in ms)
 */
var ajax_select_timer_id = 0;
var ajax_select_timeout = 300;

function ajax_select_widget_setup(widget_id, onchange, options, currentapp) {
	current_app = currentapp;
	if(onchange) {
		if(onchange == 1) {
			onchange = function() {submitit(this.form, this.value);};
		} else {
			eval("onchange = function(e) { " + onchange + ";}");
		}

		var value = document.getElementById(widget_id + '[value]');
		if(value) {
			if(value.addEventListener) {
				value.addEventListener('change', onchange, true);
			} else {
				var old = (value.onchange) ? value.onchange : function() {};
				value.onchange = function(e) {
					old(e);
					onchange(e);
				};
			}
		}
	}

	var widget = document.getElementById(widget_id + '[search]');
	if(widget) {
		widget.setAttribute('autocomplete', 'off');

		if(widget.addEventListener) {
			widget.addEventListener('keydown', checkKey, true);
			widget.addEventListener('keyup', timer_change, false);
			widget.addEventListener('blur', hideBox, false);
		} else {
			widget.onkeyup = timer_change;
			widget.onblur = hideBox;
			widget.onkeydown = checkKey;
		}

		// Set results
		var results = document.createElement('div');
		results.id = widget_id + '[results]';
		results.className = 'resultBox';
		results.style.position = 'absolute';
		// check if IE, if it is, fix the position
		if (document.all) {
			results.style.marginLeft = 0 - widget.offsetWidth - 2 * widget.offsetLeft - 1;
			results.style.marginTop = widget.offsetHeight + 1;
		}
		results.style.zIndex = 50;
		results.options = options;
		results.innerHTML = "";

		widget.parentNode.appendChild(results);
	}

	var value = document.getElementById(widget_id + '[value]');
	if(value) {
		value.style.display = 'none';
	}
}

function checkKey(e, value) {
	if(!e) {
		var e = window.event;
	}
	/*
	 * We check for Tab, Up and Down
	 */
	if (e.keyCode != '9' // Tab
		&& e.keyCode != '38' && e.keyCode != '63232' 	// Up
		&& e.keyCode != '40' && e.keyCode != '63233'	// Down
	) return; // The user has not pressed anything we're interested in
	if(e.target) {
		var target = e.target;
	} else if (e.srcElement) {
		var target = e.srcElement;
	}
	if(target) {
		if (target.nodeType == 3) { // defeat Safari bug
			target = target.parentNode;
		}
	}

	var id = target.id;
	var base_id = id.substr(0, id.lastIndexOf('['));
	var results = document.getElementById(base_id + '[results]');
	
	// Consume event so search doesn't go
	if(results.childNodes.length > 0) {
		e.cancelBubble = true;
		if(e.stopPropegation) e.stopPropegation();
	} else {
		return false;
	}

	// Up and down arrows
	switch (e.keyCode) {
		// Up
		case 38:
		case 63232:
			if(results.current) {
				results.current.className = results.current.className.replace(' resultBoxSelected','');
			}
			if(results.current && results.current.previousSibling && results.current.previousSibling.childNodes.length > 2) {
				results.current = results.current.previousSibling;
			} else {
				var elements = results.childNodes;
				results.current = elements[elements.length - 1];
			}
			results.current.className += ' resultBoxSelected';
		break;

		// Down
		case 40:
		case 63233:
			if(results.current) {
				results.current.className = results.current.className.replace(' resultBoxSelected','');
			}
			if(results.current && results.current.nextSibling && results.current.nextSibling.childNodes.length > 2) {
				results.current = results.current.nextSibling;
			} else {
				var elements = results.childNodes;
				for (var i = 0; i < elements.length; i++) {
					elem = elements.item(i);
					if (elem.value) {
						results.current = elem;
						break; // We have found the first selection
					}
				}
			}
			results.current.className += ' resultBoxSelected';
		break;
			
	// Tab: Select current element 
		case 9:
			var elem;
			if(results.current && results.current.value) {
				elem = results.current;
			} else {
				var elements = document.getElementById(base_id + '[results]').childNodes;
				for (var i = 0; i < elements.length; i++) {
					elem = elements.item(i);
					if (elem.value) {
						break; // We have found the first selection
					}
				}
			}
			if (!elem) return;
			var event;
			if(document.createEvent && document.dispatchEvent) {
				// Most
				event = document.createEvent('MouseEvents');
				event.initEvent('click', true, true);
				elem.dispatchEvent(event);
			} else {
				// IE
				event = document.createEventObject();
				elem.fireEvent('onclick', event);
			}
		break;
	}
}

/**
 * Set a timeout to prevent user from flooding the server with requests as
 * they type.  Waits to see if the user is still typing before sending the 
 * request.  Adjust ajax_select_timeout to change how long to wait (in ms).
 */
function timer_change(e, value) {
	if ( ajax_select_timer_id != 0) {
		clearTimeout(ajax_select_timer_id);
	}
	ajax_select_timer_id = setTimeout(
		function() {
			change(e, value);
		}, 
		ajax_select_timeout
	);
}

function change(e, value) {
	if(!e) {
		var e = window.event;
	}
	if(e.target) {
		var target = e.target;
	} else if (e.srcElement) {
		var target = e.srcElement;
	}
	if(target) {
		if (target.nodeType == 3) { // defeat Safari bug
			target = target.parentNode;
		} 
		var id = target.id;
		var value = target.value;
	} else if (e) {
		var id = e;
		if(value) {
			var value = value;
		} else {
			var value = e.value;
		}
		var set_id = id.substr(0, id.lastIndexOf('['));
	}

	var base_id = id.substr(0, id.lastIndexOf('['));
	if(document.getElementById(base_id + '[results]')) {
		set_id = base_id + '[results]'; 
		if(document.getElementById(set_id).style.display == 'block') {
			// Tab and arrow keys don't trigger the search
			if(e.keyCode == 9 
				|| e.keyCode == 38 || e.keyCode == 40 
				|| e.keyCode == 63232 || e.keyCode == 63233) {
				return;
			}
		}
	} else {
		set_id = base_id + '[search]';
	}

	var query = document.getElementById(set_id).options;
	if(document.getElementById(base_id + '[filter]')) {
		query.filter = document.getElementById(base_id + '[filter]').value;
	}

	// Hide selectboxes for IE
	if(document.all) {
		var selects = document.getElementsByTagName('select');
		for(var i = 0; i < selects.length; i++) {
			selects[i].style.visibility = 'hidden';
		}
	}
	xajax_doXMLHTTP(current_app + ".ajax_select_widget.ajax_search.etemplate", id, value, set_id, query);
}


/* Remove options from a results box
*  @param id - The id of the select
*/
function remove_ajax_results(id) {
	if(document.getElementById(id)) {
		var element = document.getElementById(id);
		if (element.tagName == 'DIV') {
			element.innerHTML = '';
		}
	}
}

/* Add an option to a result box
*  @param id - The id of the result box
*  @param key - The key of the option
*  @param value - The value of the option
*  @param row - The html for the row to display
*/
function add_ajax_result(id, key, value, row) {
	var resultbox = document.getElementById(id);
	if(resultbox) {
		if (resultbox.tagName == 'DIV') {
			var base_id = resultbox.id.substr(0, resultbox.id.lastIndexOf('['));
			var search_id = base_id + '[search]';
			var value_id = base_id + '[value]';

			resultbox.style.display = 'block';
			var result = document.createElement('div');

			result.className = (resultbox.childNodes.length % 2) ? 'row_on' : 'row_off';
			if(key) {
				result.value = new Object();
				result.value.key = key;
				result.value.value = value;
				result.value.search_id = search_id;
				result.value.value_id = value_id;

				result.innerHTML = row;

				// when they click, add that item to the value hidden textbox
				if(result.addEventListener) {
					result.addEventListener('click', select_result, true);
				} else {
					result.onclick = select_result;
				}
			} else {
				result.innerHTML += row + "<br />";
			}
			resultbox.appendChild(result);
		}
	}
}

function select_result(e) {
	// when they click, add that item to the value textbox & call onchange()
	if(!e) {
		var e = window.event;
	}
	if(e.target) {
		var target = e.target;
	} else if (e.srcElement) {
		var target = e.srcElement;
	}
	while(!target.value && target != document) {
		target = target.parentNode;
	}

	var value = document.getElementById(target.value.value_id);
	var search = document.getElementById(target.value.search_id);
	if(value) {
		value.value = target.value.key;
	}
	if(search) {
		search.value = target.value.value;
		var event;
		if(document.createEvent) {
			// Most
			event = document.createEvent('HTMLEvents');
			event.initEvent('change', true, true);
			return value.dispatchEvent(event);
		} else {
			// IE
			event = document.createEventObject();
			return value.fireEvent('onchange', event);
		}
	}
}

function hideBox(e) {
	if(!e) {
		var e = window.event;
	}
	if(e.target) {
		var target = e.target;
	} else if (e.srcElement) {
		var target = e.srcElement;
	}
	if(target) {
		if (target.nodeType == 3) { // defeat Safari bug
			target = target.parentNode;
		} 
	}
	var set_id = target.id.substr(0, target.id.lastIndexOf('[')) + '[results]';
	setTimeout("document.getElementById('" + set_id + "').style.display = 'none'", 200);
	var selects = document.getElementsByTagName('select');

	// Un-hide select boxes for IE
	if(document.all) {
		for(var i = 0; i < selects.length; i++) {
			selects[i].style.visibility = 'visible';
		}
	}
}
