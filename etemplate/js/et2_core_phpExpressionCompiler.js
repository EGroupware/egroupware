/**
 * EGroupware eTemplate2 - A simple PHP expression parser written in JS
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_common;
*/

(function() {

	var STATE_DEFAULT = 0;
	var STATE_ESCAPED = 1;
	var STATE_CURLY_BRACE_OPEN = 2;
	var STATE_EXPECT_CURLY_BRACE_CLOSE = 3;
	var STATE_EXPECT_RECT_BRACE_CLOSE = 4;
	var STATE_EXPR_BEGIN = 5;
	var STATE_EXPR_END = 6;

	function _throwParserErr(_p, _err)
	{
		throw("Syntax error while parsing '" + _p.expr + "' at " + 
			_p.pos + ", " + _err);
	}

	function _php_parseDoubleQuoteString(_p, _tree)
	{
		// Extract all PHP variables from the string
		var state = STATE_DEFAULT;
		var str = "";

		while (_p.pos < _p.expr.length)
		{
			// Read the current char and then increment the parser position by
			// one
			var c = _p.expr.charAt(_p.pos++);

			switch (state)
			{

				case STATE_DEFAULT:
				case STATE_CURLY_BRACE_OPEN:

					switch (c)
					{
						case '\\':
							state = STATE_ESCAPED;
							break;

						case '$':
							if (str)
							{
								_tree.push(str); str = "";
							}

							// Support for the ${[expr] sytax
							if (_p.expr.charAt(_p.pos) == "{" && state != STATE_CURLY_BRACE_OPEN)
							{
								state = STATE_CURLY_BRACE_OPEN;
								_p.pos++;
							}

							if (state == STATE_CURLY_BRACE_OPEN)
							{
								_tree.push(_php_parseVariable(_p));
								state = STATE_EXPECT_CURLY_BRACE_CLOSE;
							}
							else
							{
								_tree.push(_php_parseVariable(_p));
							}

							break;

						case '{':
							state = STATE_CURLY_BRACE_OPEN;
							break;

						default:
							if (state == STATE_CURLY_BRACE_OPEN)
							{
								str += '{';
								state = STATE_DEFAULT;
							}
							str += c;
					}

					break;

				case STATE_ESCAPED:
					str += c;
					break;

				case STATE_EXPECT_CURLY_BRACE_CLOSE:
					// When returning from the variableEx parser,
					// the current char must be a "}"
					if (c != "}")
					{
						_throwParserErr(_p, "expected '}', but got " + c);
					}

					state = STATE_DEFAULT;
					break;
			}
		}

		// Throw an error when reaching the end of the string but expecting
		// "}"
		if (state == STATE_EXPECT_CURLY_BRACE_CLOSE)
		{
			_throwParserErr(_p, "unexpected end of string, expected '}'");
		}

		// Push the last part of the string onto the syntax tree
		if (state == STATE_CURLY_BRACE_OPEN)
		{
			str += "{";
		}

		if (str)
		{
			_tree.push(str);
		}
	}

	// Regular expression which matches on PHP variable identifiers (without the $)
	var PHP_VAR_PREG = /^([A-Za-z0-9_]+)/;

	function _php_parseVariableName(_p)
	{
		// Extract the variable name form the expression
		var vname = PHP_VAR_PREG.exec(_p.expr.substr(_p.pos));

		if (vname)
		{
			// Increment the parser position by the length of vname
			_p.pos += vname[0].length;
			return {"variable": vname[0], "accessExpressions": []};
		}

		_throwParserErr(_p, "expected variable identifier.");
	}

	function _php_parseVariable(_p)
	{
		// Parse the first variable
		var variable = _php_parseVariableName(_p);

		// Parse all following variable access identifiers
		var state = STATE_DEFAULT;

		while (_p.pos < _p.expr.length)
		{
			var c = _p.expr.charAt(_p.pos++);

			switch (state)
			{
				case STATE_DEFAULT:
					switch (c)
					{
						case "[":
							// Parse the expression inside the rect brace
							variable.accessExpressions.push(_php_parseExpression(_p));
							state = STATE_EXPECT_RECT_BRACE_CLOSE;
							break;

						default:
							_p.pos--;
							return variable;
					}
					break;

				case STATE_EXPECT_RECT_BRACE_CLOSE:
					if (c != "]")
					{
						_throwParserErr(_p, " expected ']', but got " + c);
					}

					state = STATE_DEFAULT;
					break;
			}
		}

		return variable;
	}

	/**
	 * Reads a string delimited by the char _delim or the regExp _delim from the
	 * current parser context and returns it.
	 */
	function _php_readString(_p, _delim)
	{
		var state = STATE_DEFAULT;
		var str = "";

		while (_p.pos < _p.expr.length)
		{
			var c = _p.expr.charAt(_p.pos++);

			switch (state)
			{

				case STATE_DEFAULT:
					if (c == "\\")
					{
						state = STATE_ESCAPED;
					}
					else if (c === _delim || (typeof _delim != "string" && _delim.test(c)))
					{
						return str;
					}
					else
					{
						str += c;
					}
					break;

				case STATE_ESCAPED:
					str += c;
					state = STATE_DEFAULT;
					break;
			}
		}

		_throwParserErr(_p, "unexpected end of string while parsing string!");
	}

	function _php_parseExpression(_p)
	{
		var state = STATE_EXPR_BEGIN;
		var result = null;

		while (_p.pos < _p.expr.length)
		{
			var c = _p.expr.charAt(_p.pos++);

			switch (state)
			{
				case STATE_EXPR_BEGIN:
					switch(c)
					{
						// Skip whitespace
						case " ": case "\n": case "\r": case "\t":
							break;

						case "\"":
							result = [];

							var p = _php_parser(_php_readString(_p, "\""));
							_php_parseDoubleQuoteString(p, result);
							state = STATE_EXPR_END;
							break;
						case "\'":
							var result = _php_readString(_p, "'");
							state = STATE_EXPR_END;
							break;
						case "$":
							var result = _php_parseVariable(_p);
							state = STATE_EXPR_END;
							break;
						default:
							_p.pos--;
							var result = _php_readString(_p, /[^A-Za-z0-9_#]/);

							if (!result)
							{
								_throwParserErr(_p, "unexpected char " + c);
							}

							_p.pos--;
							state = STATE_EXPR_END;
							break;
					}
					break;

				case STATE_EXPR_END:
					switch(c)
					{
						// Skip whitespace
						case " ": case "\n": case "\r": case "\t":
							break;

						default:
							_p.pos--;
							return result;
					}

			}
		}

		_throwParserErr(_p, "unexpected end of string while parsing access expressions!");
	}

	function _php_parser(_expr)
	{
		return {
			expr: _expr,
			pos: 0
		};
	}

	function _throwCompilerErr(_err)
	{
		throw("PHP to JS compiler error, " + _err);
	}

	function _php_compileVariable(_vars, _variable)
	{
		if (_vars.indexOf(_variable.variable) >= 0)
		{
			// Attach a "_" to the variable name as PHP variable names may start
			// with numeric values
			var result = "_" + _variable.variable;

			// Create the access functions
			for (var i = 0; i < _variable.accessExpressions.length; i++)
			{
				result += "[" +
					_php_compileString(_vars, _variable.accessExpressions[i]) +
					"]";
			}

			return '(typeof _'+_variable.variable+' != "undefined" && typeof '+result + '!="undefined" ? ' + result + ':"")';
		}

		_throwCompilerErr("Variable $" + _variable.variable + " is not defined.");
	}

	function _php_compileString(_vars, _string)
	{
		if (!(_string instanceof Array))
		{
			_string = [_string];
		}

		var parts = [];
		var hasString = false;
		for (var i = 0; i < _string.length; i++)
		{
			var part = _string[i];

			if (typeof part == "string")
			{
				hasString = true;
				// Escape all "'" and "\" chars and add the string to the parts array
				parts.push("'" + part.replace(/\\/g, "\\\\").replace(/'/g, "\\'") + "'");
			}
			else
			{
				parts.push(_php_compileVariable(_vars, part));
			}
		}

		if (!hasString) // Force the result to be of the type string
		{
			parts.push('""');
		}

		return parts.join(" + ");
	}

	function _php_compileJSCode(_vars, _tree)
	{
		// Each tree starts with a "string"
		return "return " + _php_compileString(_vars, _tree) + ";";
	}

	/**
	 * Function which compiles the given PHP string to a JS function which can be
	 * easily executed.
	 *
	 * @param _expr is the PHP string expression
	 * @param _vars is an array with variable names (without the PHP $).
	 * 	The parameters have to be passed to the resulting JS function in the same
	 * 	order.
	 */
	this.et2_compilePHPExpression = function(_expr, _vars)
	{
		if (typeof _vars == "undefined")
		{
			_vars = [];
		}

		// Initialize the parser object and create the syntax tree for the given
		// expression
		var parser = _php_parser(_expr);

		var syntaxTree = [];

		// Parse the given expression as if it was a double quoted string
		_php_parseDoubleQuoteString(parser, syntaxTree);

		// Transform the generated syntaxTree into a JS string
		var js = _php_compileJSCode(_vars, syntaxTree);

		// Log the successfull compiling
		egw.debug("log", "Compiled PHP " + _expr + " --> " + js);

		// Prepate the attributes for the function constuctor
		var attrs = [];
		for (var i = 0; i < _vars.length; i++)
		{
			attrs.push("_" + _vars[i]);
		}
		attrs.push(js);

		// Create the function and return it
		return (Function.apply(Function, attrs));
	};

}).call(window);

// Include this code in in order to test the above code
/*(function () {
	var row = 10;
	var row_cont = {"title": "Hello World!"};
	var cont = {10: row_cont};

	function test(_php, _res)
	{
		console.log(
			et2_compilePHPExpression(_php, ["row", "row_cont", "cont"])
				(row, row_cont, cont) === _res);
	}

	test("${row}[title]", "10[title]");
	test("{$row_cont[title]}", "Hello World!");
	test('{$cont["$row"][\'title\']}', "Hello World!");
	test("$row_cont[${row}[title]]");
	test("\\\\", "\\");
	test("", "");
})();*/

