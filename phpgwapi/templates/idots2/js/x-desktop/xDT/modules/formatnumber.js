xDesktop.prototype.formatNumber = function(number,fnum,format,decp,curr) {
  var separator = ",";  
  var decpoint = ".";  
  var percent = "%";
  var currency = "&euro;";  
  (curr) ? currency = curr: currency;
  (format) ? format : format = ",0.00";
  if (fnum) format = format.replace(/^\,/,"");
  var test = number.toString();
  if (test.indexOf(',') != -1 && test.indexOf(',') > test.indexOf('.') ) { // european format
   test = test.replace(/\./g,""); // remove all 1000 separators
   test = test.replace(/\,/,".");
   number = parseFloat(test);
  }
  else if (test.indexOf('.') != -1 && test.indexOf('.') > test.indexOf(',') ) {
   test = test.replace(/\,/g,""); // remove all 1000 separators
   number = parseFloat(test);
  }	
    if (number - 0 != number) return null;  // if number is NaN return null
    var useSeparator = format.indexOf(separator) != -1;  // use separators in number
    var usePercent = format.indexOf(percent) != -1;  // convert output to percentage
    var useCurrency = format.indexOf(currency) != -1;  // use currency format
    var isNegative = (number < 0);
    number = Math.abs (number);
    if (usePercent) number *= 100;
    format = strip(format, separator + percent + currency);  // remove key characters
    number = "" + number;  // convert number input to string
    var dec = number.indexOf(decpoint) != -1;
    var nleftEnd = (dec) ? number.substring(0, number.indexOf(".")) : number;
    var nrightEnd = (dec) ? number.substring(number.indexOf(".") + 1) : "";
    dec = format.indexOf(decpoint) != -1;
    var sleftEnd = (dec) ? format.substring(0, format.indexOf(".")) : format;
    var srightEnd = (dec) ? format.substring(format.indexOf(".") + 1) : "";
    if (srightEnd.length < nrightEnd.length) {
      var nextChar = nrightEnd.charAt(srightEnd.length) - 0;
      nrightEnd = nrightEnd.substring(0, srightEnd.length);
      if (nextChar >= 5) nrightEnd = "" + ((nrightEnd - 0) + 1);  // round up
      while (srightEnd.length > nrightEnd.length) {
        nrightEnd = "0" + nrightEnd;
      }
      if (srightEnd.length < nrightEnd.length) {
        nrightEnd = nrightEnd.substring(1);
        nleftEnd = (nleftEnd - 0) + 1;
      }
    } else {
      for (var i=nrightEnd.length; srightEnd.length > nrightEnd.length; i++) {
        if (srightEnd.charAt(i) == "0") nrightEnd += "0";  // append zero to RHS of number
        else break;
      }
    }
    sleftEnd = strip(sleftEnd, "#");  // remove hashes from LHS of format
    while (sleftEnd.length > nleftEnd.length) {
      nleftEnd = "0" + nleftEnd;  // prepend zero to LHS of number
    }
    if (useSeparator) nleftEnd = separate(nleftEnd, separator);  // add separator
    var output = nleftEnd + ((nrightEnd != "") ? "." + nrightEnd : "");  // combine parts
    output = ((useCurrency) ? currency : "") + output + ((usePercent) ? percent : "");
    if (isNegative) {
      output = (useCurrency) ? "(" + output + ")" : "-" + output;
    }
  if (! fnum) {   
   output = output.replace(/\,/g,":");
   output = output.replace(/\./,",");
   output = output.replace(/:/g,".");
  }
  return output;
  
  function strip(input, chars) {  // strip all characters in 'chars' from input
    var output = "";  // initialise output string
    for (var i=0; i < input.length; i++)
      if (chars.indexOf(input.charAt(i)) == -1)
        output += input.charAt(i);
    return output;
  }
  function separate(input, separator) {  // format input using 'separator' to mark 000's
    input = "" + input;
    var output = "";  // initialise output string
    for (var i=0; i < input.length; i++) {
      if (i != 0 && (input.length - i) % 3 == 0) output += separator;
      output += input.charAt(i);
    }
    return output;
  }
}