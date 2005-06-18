/*

xmlrpc.js beta version 1
Tool for creating XML-RPC formatted requests in JavaScript

Copyright 2001 Scott Andrew LePera
scott@scottandrew.com
http://www.scottandrew.com/xml-rpc

License: 
You are granted the right to use and/or redistribute this 
code only if this license and the copyright notice are included 
and you accept that no warranty of any kind is made or implied 
by the author.

*/

function XMLRPCMessage(methodname){
  this.method = methodname||"system.listMethods";
  this.params = [];
  return this;
}

XMLRPCMessage.prototype.setMethod = function(methodName){
  if (!methodName) return;
  this.method = methodName;
}

XMLRPCMessage.prototype.addParameter = function(data){
  if (arguments.length==0) return;
  this.params[this.params.length] = data;
}

XMLRPCMessage.prototype.xml = function(){

  var method = this.method;
  
  // assemble the XML message header
  var xml = "";
  
  xml += "<?xml version=\"1.0\"?>\n";
  xml += "<methodCall>\n";
  xml += "<methodName>" + method+ "</methodName>\n";
  xml += "<params>\n";
  
  // do individual parameters
  for (var i = 0; i < this.params.length; i++){
    var data = this.params[i];
    xml += "<param>\n";

    xml += "<value>" + XMLRPCMessage.getParamXML(XMLRPCMessage.dataTypeOf(data),data) + "</value>\n";
    
    xml += "</param>\n";
  }
  
  xml += "</params>\n";
  xml += "</methodCall>";
  
  return xml; // for now
}

XMLRPCMessage.dataTypeOf = function (o){
  // identifies the data type
  
  if (o == null)
  {
  	return 'null';
  }
  
  var type = typeof(o);
  type = type.toLowerCase();
  switch(type){
    case "number":
      if (Math.round(o) == o) type = "i4";
      else type = "double";
      break;
    case "object":
      var con = o.constructor;
      if (con == Date) type = "date";
      else if (con == Array) type = "array";
      else type = "struct";
      break;
  }
  return type;
}

XMLRPCMessage.doValueXML = function(type,data){
  switch (type)
  {
  	case 'null':
	  	type = 'boolean';
		data = 0;
		break;
		
	case 'string':
		data = data.split('&').join('&amp;');
		data = data.split('<').join('&lt;');
		data = data.split('>').join('&gt;');
		break;
  }

  var xml = "<" + type + ">" + data + "</" + type + ">";
  return xml;
}

XMLRPCMessage.doBooleanXML = function(data){
  var value = (data==true)?1:0;
  var xml = "<boolean>" + value + "</boolean>";
  return xml;
}

XMLRPCMessage.doDateXML = function(data){
  var xml = "<dateTime.iso8601>";
  xml += dateToISO8601(data);
  xml += "</dateTime.iso8601>";
  return xml;
}

XMLRPCMessage.doArrayXML = function(data){
  var xml = "<array><data>\n";
  for (var i = 0; i < data.length; i++){
    xml += "<value>" + XMLRPCMessage.getParamXML(XMLRPCMessage.dataTypeOf(data[i]),data[i]) + "</value>\n";
  }
  xml += "</data></array>\n";
  return xml;
}

XMLRPCMessage.doStructXML = function(data){
  var xml = "<struct>\n";
  for (var i in data){
    xml += "<member>\n";
    xml += "<name>" + i + "</name>\n";
    xml += "<value>" + XMLRPCMessage.getParamXML(XMLRPCMessage.dataTypeOf(data[i]),data[i]) + "</value>\n";
    xml += "</member>\n";
  }
  xml += "</struct>\n";
  return xml;
}

XMLRPCMessage.getParamXML = function(type,data){
  var xml;
  switch (type){
    case "date":
      xml = XMLRPCMessage.doDateXML(data);
      break;
    case "array":
      xml = XMLRPCMessage.doArrayXML(data);
      break;
    case "struct":
      xml = XMLRPCMessage.doStructXML(data);
      break;
	  case "boolean":
      xml = XMLRPCMessage.doBooleanXML(data);
      break;
    default:
      xml = XMLRPCMessage.doValueXML(type,data);
      break;
  }
  return xml;
}

function dateToISO8601(date){
  // wow I hate working with the Date object
  var year = new String(date.getYear());
  var month = leadingZero(new String(date.getMonth()));
  var day = leadingZero(new String(date.getDate()));
  var time = leadingZero(new String(date.getHours())) + ":" + leadingZero(new String(date.getMinutes())) + ":" + leadingZero(new String(date.getSeconds()));

  var converted = year+month+day+"T"+time;
  return converted;
} 
  
function leadingZero(n){
  // pads a single number with a leading zero. Heh.
  if (n.length==1) n = "0" + n;
  return n;
}
