/*

 This is a generated file. DO NOT EDIT.

 Copyright (C) 2010-2015 KO GmbH <copyright@kogmbh.com>

 @licstart
 The code in this file is free software: you can redistribute it and/or modify it
 under the terms of the GNU Affero General Public License (GNU AGPL)
 as published by the Free Software Foundation, either version 3 of
 the License, or (at your option) any later version.

 The code in this file is distributed in the hope that it will be useful, but
 WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with WebODF.  If not, see <http://www.gnu.org/licenses/>.

 As additional permission under GNU AGPL version 3 section 7, you
 may distribute UNMODIFIED VERSIONS OF THIS file without the copy of the GNU AGPL normally
 required by section 4, provided you include this license notice and a URL
 through which recipients can access the Corresponding Source.

 As a special exception to the AGPL, any HTML file which merely makes function
 calls to this code, and for that purpose includes it in unmodified form by reference or in-line shall be
 deemed a separate work for copyright law purposes. In addition, the copyright
 holders of this code give you permission to combine this code with free
 software libraries that are released under the GNU LGPL. You may copy and
 distribute such a system following the terms of the GNU AGPL for this code
 and the LGPL for the libraries. If you modify this code, you may extend this
 exception to your version of the code, but you are not obligated to do so.
 If you do not wish to do so, delete this exception statement from your
 version.

 This license applies to this entire compilation.
 @licend

 @source: http://www.webodf.org/
 @source: https://github.com/kogmbh/WebODF/
*/
var webodf_version = "0.5.9";
function Runtime() {
}
Runtime.prototype.getVariable = function(name) {
};
Runtime.prototype.toJson = function(anything) {
};
Runtime.prototype.fromJson = function(jsonstr) {
};
Runtime.prototype.byteArrayFromString = function(string, encoding) {
};
Runtime.prototype.byteArrayToString = function(bytearray, encoding) {
};
Runtime.prototype.read = function(path, offset, length, callback) {
};
Runtime.prototype.readFile = function(path, encoding, callback) {
};
Runtime.prototype.readFileSync = function(path, encoding) {
};
Runtime.prototype.loadXML = function(path, callback) {
};
Runtime.prototype.writeFile = function(path, data, callback) {
};
Runtime.prototype.deleteFile = function(path, callback) {
};
Runtime.prototype.log = function(msgOrCategory, msg) {
};
Runtime.prototype.setTimeout = function(callback, milliseconds) {
};
Runtime.prototype.clearTimeout = function(timeoutID) {
};
Runtime.prototype.libraryPaths = function() {
};
Runtime.prototype.currentDirectory = function() {
};
Runtime.prototype.setCurrentDirectory = function(dir) {
};
Runtime.prototype.type = function() {
};
Runtime.prototype.getDOMImplementation = function() {
};
Runtime.prototype.parseXML = function(xml) {
};
Runtime.prototype.exit = function(exitCode) {
};
Runtime.prototype.getWindow = function() {
};
Runtime.prototype.requestAnimationFrame = function(callback) {
};
Runtime.prototype.cancelAnimationFrame = function(requestId) {
};
Runtime.prototype.assert = function(condition, message) {
};
var IS_COMPILED_CODE = true;
Runtime.byteArrayToString = function(bytearray, encoding) {
  function byteArrayToString(bytearray) {
    var s = "", i, l = bytearray.length;
    for (i = 0;i < l;i += 1) {
      s += String.fromCharCode(bytearray[i] & 255);
    }
    return s;
  }
  function utf8ByteArrayToString(bytearray) {
    var s = "", startPos, i, l = bytearray.length, chars = [], c0, c1, c2, c3, codepoint;
    if (l >= 3 && bytearray[0] === 239 && bytearray[1] === 187 && bytearray[2] === 191) {
      startPos = 3;
    } else {
      startPos = 0;
    }
    for (i = startPos;i < l;i += 1) {
      c0 = bytearray[i];
      if (c0 < 128) {
        chars.push(c0);
      } else {
        i += 1;
        c1 = bytearray[i];
        if (c0 >= 194 && c0 < 224) {
          chars.push((c0 & 31) << 6 | c1 & 63);
        } else {
          i += 1;
          c2 = bytearray[i];
          if (c0 >= 224 && c0 < 240) {
            chars.push((c0 & 15) << 12 | (c1 & 63) << 6 | c2 & 63);
          } else {
            i += 1;
            c3 = bytearray[i];
            if (c0 >= 240 && c0 < 245) {
              codepoint = (c0 & 7) << 18 | (c1 & 63) << 12 | (c2 & 63) << 6 | c3 & 63;
              codepoint -= 65536;
              chars.push((codepoint >> 10) + 55296, (codepoint & 1023) + 56320);
            }
          }
        }
      }
      if (chars.length >= 1E3) {
        s += String.fromCharCode.apply(null, chars);
        chars.length = 0;
      }
    }
    return s + String.fromCharCode.apply(null, chars);
  }
  var result;
  if (encoding === "utf8") {
    result = utf8ByteArrayToString(bytearray);
  } else {
    if (encoding !== "binary") {
      this.log("Unsupported encoding: " + encoding);
    }
    result = byteArrayToString(bytearray);
  }
  return result;
};
Runtime.getVariable = function(name) {
  try {
    return eval(name);
  } catch (e) {
    return undefined;
  }
};
Runtime.toJson = function(anything) {
  return JSON.stringify(anything);
};
Runtime.fromJson = function(jsonstr) {
  return JSON.parse(jsonstr);
};
Runtime.getFunctionName = function getFunctionName(f) {
  var m;
  if (f.name === undefined) {
    m = (new RegExp("function\\s+(\\w+)")).exec(f);
    return m && m[1];
  }
  return f.name;
};
Runtime.assert = function(condition, message) {
  if (!condition) {
    this.log("alert", "ASSERTION FAILED:\n" + message);
    throw new Error(message);
  }
};
function BrowserRuntime() {
  var self = this;
  function getUtf8LengthForString(string) {
    var l = string.length, i, n, j = 0;
    for (i = 0;i < l;i += 1) {
      n = string.charCodeAt(i);
      j += 1 + (n > 128) + (n > 2048);
      if (n > 55040 && n < 57344) {
        j += 1;
        i += 1;
      }
    }
    return j;
  }
  function utf8ByteArrayFromString(string, length, addBOM) {
    var l = string.length, bytearray, i, n, j;
    bytearray = new Uint8Array(new ArrayBuffer(length));
    if (addBOM) {
      bytearray[0] = 239;
      bytearray[1] = 187;
      bytearray[2] = 191;
      j = 3;
    } else {
      j = 0;
    }
    for (i = 0;i < l;i += 1) {
      n = string.charCodeAt(i);
      if (n < 128) {
        bytearray[j] = n;
        j += 1;
      } else {
        if (n < 2048) {
          bytearray[j] = 192 | n >>> 6;
          bytearray[j + 1] = 128 | n & 63;
          j += 2;
        } else {
          if (n <= 55040 || n >= 57344) {
            bytearray[j] = 224 | n >>> 12 & 15;
            bytearray[j + 1] = 128 | n >>> 6 & 63;
            bytearray[j + 2] = 128 | n & 63;
            j += 3;
          } else {
            i += 1;
            n = (n - 55296 << 10 | string.charCodeAt(i) - 56320) + 65536;
            bytearray[j] = 240 | n >>> 18 & 7;
            bytearray[j + 1] = 128 | n >>> 12 & 63;
            bytearray[j + 2] = 128 | n >>> 6 & 63;
            bytearray[j + 3] = 128 | n & 63;
            j += 4;
          }
        }
      }
    }
    return bytearray;
  }
  function utf8ByteArrayFromXHRString(string, wishLength) {
    var addBOM = false, length = getUtf8LengthForString(string);
    if (typeof wishLength === "number") {
      if (wishLength !== length && wishLength !== length + 3) {
        return undefined;
      }
      addBOM = length + 3 === wishLength;
      length = wishLength;
    }
    return utf8ByteArrayFromString(string, length, addBOM);
  }
  function byteArrayFromString(string) {
    var l = string.length, a = new Uint8Array(new ArrayBuffer(l)), i;
    for (i = 0;i < l;i += 1) {
      a[i] = string.charCodeAt(i) & 255;
    }
    return a;
  }
  this.byteArrayFromString = function(string, encoding) {
    var result;
    if (encoding === "utf8") {
      result = utf8ByteArrayFromString(string, getUtf8LengthForString(string), false);
    } else {
      if (encoding !== "binary") {
        self.log("unknown encoding: " + encoding);
      }
      result = byteArrayFromString(string);
    }
    return result;
  };
  this.byteArrayToString = Runtime.byteArrayToString;
  this.getVariable = Runtime.getVariable;
  this.fromJson = Runtime.fromJson;
  this.toJson = Runtime.toJson;
  function log(msgOrCategory, msg) {
    var category;
    if (msg !== undefined) {
      category = msgOrCategory;
    } else {
      msg = msgOrCategory;
    }
    console.log(msg);
    if (self.enableAlerts && category === "alert") {
      alert(msg);
    }
  }
  function arrayToUint8Array(buffer) {
    var l = buffer.length, i, a = new Uint8Array(new ArrayBuffer(l));
    for (i = 0;i < l;i += 1) {
      a[i] = buffer[i];
    }
    return a;
  }
  function stringToBinaryWorkaround(xhr) {
    var cl, data;
    cl = xhr.getResponseHeader("Content-Length");
    if (cl) {
      cl = parseInt(cl, 10);
    }
    if (cl && cl !== xhr.responseText.length) {
      data = utf8ByteArrayFromXHRString(xhr.responseText, cl);
    }
    if (data === undefined) {
      data = byteArrayFromString(xhr.responseText);
    }
    return data;
  }
  function handleXHRResult(path, encoding, xhr) {
    var r, d, a, data;
    if (xhr.status === 0 && !xhr.responseText) {
      r = {err:"File " + path + " is empty.", data:null};
    } else {
      if (xhr.status === 200 || xhr.status === 0) {
        if (xhr.response && typeof xhr.response !== "string") {
          if (encoding === "binary") {
            d = xhr.response;
            data = new Uint8Array(d);
          } else {
            data = String(xhr.response);
          }
        } else {
          if (encoding === "binary") {
            if (xhr.responseBody !== null && String(typeof VBArray) !== "undefined") {
              a = (new VBArray(xhr.responseBody)).toArray();
              data = arrayToUint8Array(a);
            } else {
              data = stringToBinaryWorkaround(xhr);
            }
          } else {
            data = xhr.responseText;
          }
        }
        r = {err:null, data:data};
      } else {
        r = {err:xhr.responseText || xhr.statusText, data:null};
      }
    }
    return r;
  }
  function createXHR(path, encoding, async) {
    var xhr = new XMLHttpRequest;
    xhr.open("GET", path, async);
    if (xhr.overrideMimeType) {
      if (encoding !== "binary") {
        xhr.overrideMimeType("text/plain; charset=" + encoding);
      } else {
        xhr.overrideMimeType("text/plain; charset=x-user-defined");
      }
    }
    return xhr;
  }
  function readFile(path, encoding, callback) {
    var xhr = createXHR(path, encoding, true);
    function handleResult() {
      var r;
      if (xhr.readyState === 4) {
        r = handleXHRResult(path, encoding, xhr);
        callback(r.err, r.data);
      }
    }
    xhr.onreadystatechange = handleResult;
    try {
      xhr.send(null);
    } catch (e) {
      callback(e.message, null);
    }
  }
  function read(path, offset, length, callback) {
    readFile(path, "binary", function(err, result) {
      var r = null;
      if (result) {
        if (typeof result === "string") {
          throw "This should not happen.";
        }
        r = result.subarray(offset, offset + length);
      }
      callback(err, r);
    });
  }
  function readFileSync(path, encoding) {
    var xhr = createXHR(path, encoding, false), r;
    try {
      xhr.send(null);
      r = handleXHRResult(path, encoding, xhr);
      if (r.err) {
        throw r.err;
      }
      if (r.data === null) {
        throw "No data read from " + path + ".";
      }
    } catch (e) {
      throw e;
    }
    return r.data;
  }
  function writeFile(path, data, callback) {
    var xhr = new XMLHttpRequest, d;
    function handleResult() {
      if (xhr.readyState === 4) {
        if (xhr.status === 0 && !xhr.responseText) {
          callback("File " + path + " is empty.");
        } else {
          if (xhr.status >= 200 && xhr.status < 300 || xhr.status === 0) {
            callback(null);
          } else {
            callback("Status " + String(xhr.status) + ": " + xhr.responseText || xhr.statusText);
          }
        }
      }
    }
    xhr.open("PUT", path, true);
    xhr.onreadystatechange = handleResult;
    if (data.buffer && !xhr.sendAsBinary) {
      d = data.buffer;
    } else {
      d = self.byteArrayToString(data, "binary");
    }
    try {
      if (xhr.sendAsBinary) {
        xhr.sendAsBinary(d);
      } else {
        xhr.send(d);
      }
    } catch (e) {
      self.log("HUH? " + e + " " + data);
      callback(e.message);
    }
  }
  function deleteFile(path, callback) {
    var xhr = new XMLHttpRequest;
    xhr.open("DELETE", path, true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        if (xhr.status < 200 && xhr.status >= 300) {
          callback(xhr.responseText);
        } else {
          callback(null);
        }
      }
    };
    xhr.send(null);
  }
  function loadXML(path, callback) {
    var xhr = new XMLHttpRequest;
    function handleResult() {
      if (xhr.readyState === 4) {
        if (xhr.status === 0 && !xhr.responseText) {
          callback("File " + path + " is empty.", null);
        } else {
          if (xhr.status === 200 || xhr.status === 0) {
            callback(null, xhr.responseXML);
          } else {
            callback(xhr.responseText, null);
          }
        }
      }
    }
    xhr.open("GET", path, true);
    if (xhr.overrideMimeType) {
      xhr.overrideMimeType("text/xml");
    }
    xhr.onreadystatechange = handleResult;
    try {
      xhr.send(null);
    } catch (e) {
      callback(e.message, null);
    }
  }
  this.readFile = readFile;
  this.read = read;
  this.readFileSync = readFileSync;
  this.writeFile = writeFile;
  this.deleteFile = deleteFile;
  this.loadXML = loadXML;
  this.log = log;
  this.enableAlerts = true;
  this.assert = Runtime.assert;
  this.setTimeout = function(f, msec) {
    return setTimeout(function() {
      f();
    }, msec);
  };
  this.clearTimeout = function(timeoutID) {
    clearTimeout(timeoutID);
  };
  this.libraryPaths = function() {
    return ["lib"];
  };
  this.setCurrentDirectory = function() {
  };
  this.currentDirectory = function() {
    return "";
  };
  this.type = function() {
    return "BrowserRuntime";
  };
  this.getDOMImplementation = function() {
    return window.document.implementation;
  };
  this.parseXML = function(xml) {
    var parser = new DOMParser;
    return parser.parseFromString(xml, "text/xml");
  };
  this.exit = function(exitCode) {
    log("Calling exit with code " + String(exitCode) + ", but exit() is not implemented.");
  };
  this.getWindow = function() {
    return window;
  };
  this.requestAnimationFrame = function(callback) {
    var rAF = window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || window.msRequestAnimationFrame, requestId = 0;
    if (rAF) {
      rAF.bind(window);
      requestId = rAF(callback);
    } else {
      return setTimeout(callback, 15);
    }
    return requestId;
  };
  this.cancelAnimationFrame = function(requestId) {
    var cAF = window.cancelAnimationFrame || window.webkitCancelAnimationFrame || window.mozCancelAnimationFrame || window.msCancelAnimationFrame;
    if (cAF) {
      cAF.bind(window);
      cAF(requestId);
    } else {
      clearTimeout(requestId);
    }
  };
}
function NodeJSRuntime() {
  var self = this, fs = require("fs"), pathmod = require("path"), currentDirectory = "", parser, domImplementation;
  function bufferToUint8Array(buffer) {
    var l = buffer.length, i, a = new Uint8Array(new ArrayBuffer(l));
    for (i = 0;i < l;i += 1) {
      a[i] = buffer[i];
    }
    return a;
  }
  this.byteArrayFromString = function(string, encoding) {
    var buf = new Buffer(string, encoding), i, l = buf.length, a = new Uint8Array(new ArrayBuffer(l));
    for (i = 0;i < l;i += 1) {
      a[i] = buf[i];
    }
    return a;
  };
  this.byteArrayToString = Runtime.byteArrayToString;
  this.getVariable = Runtime.getVariable;
  this.fromJson = Runtime.fromJson;
  this.toJson = Runtime.toJson;
  function readFile(path, encoding, callback) {
    function convert(err, data) {
      if (err) {
        return callback(err, null);
      }
      if (!data) {
        return callback("No data for " + path + ".", null);
      }
      var d;
      if (typeof data === "string") {
        d = data;
        return callback(err, d);
      }
      d = data;
      callback(err, bufferToUint8Array(d));
    }
    path = pathmod.resolve(currentDirectory, path);
    if (encoding !== "binary") {
      fs.readFile(path, encoding, convert);
    } else {
      fs.readFile(path, null, convert);
    }
  }
  this.readFile = readFile;
  function loadXML(path, callback) {
    readFile(path, "utf-8", function(err, data) {
      if (err) {
        return callback(err, null);
      }
      if (!data) {
        return callback("No data for " + path + ".", null);
      }
      var d = data;
      callback(null, self.parseXML(d));
    });
  }
  this.loadXML = loadXML;
  this.writeFile = function(path, data, callback) {
    var buf = new Buffer(data);
    path = pathmod.resolve(currentDirectory, path);
    fs.writeFile(path, buf, "binary", function(err) {
      callback(err || null);
    });
  };
  this.deleteFile = function(path, callback) {
    path = pathmod.resolve(currentDirectory, path);
    fs.unlink(path, callback);
  };
  this.read = function(path, offset, length, callback) {
    path = pathmod.resolve(currentDirectory, path);
    fs.open(path, "r+", 666, function(err, fd) {
      if (err) {
        callback(err, null);
        return;
      }
      var buffer = new Buffer(length);
      fs.read(fd, buffer, 0, length, offset, function(err) {
        fs.close(fd);
        callback(err, bufferToUint8Array(buffer));
      });
    });
  };
  this.readFileSync = function(path, encoding) {
    var s, enc = encoding === "binary" ? null : encoding, r = fs.readFileSync(path, enc);
    if (r === null) {
      throw "File " + path + " could not be read.";
    }
    if (encoding === "binary") {
      s = r;
      s = bufferToUint8Array(s);
    } else {
      s = r;
    }
    return s;
  };
  function log(msgOrCategory, msg) {
    var category;
    if (msg !== undefined) {
      category = msgOrCategory;
    } else {
      msg = msgOrCategory;
    }
    if (category === "alert") {
      process.stderr.write("\n!!!!! ALERT !!!!!" + "\n");
    }
    process.stderr.write(msg + "\n");
    if (category === "alert") {
      process.stderr.write("!!!!! ALERT !!!!!" + "\n");
    }
  }
  this.log = log;
  this.assert = Runtime.assert;
  this.setTimeout = function(f, msec) {
    return setTimeout(function() {
      f();
    }, msec);
  };
  this.clearTimeout = function(timeoutID) {
    clearTimeout(timeoutID);
  };
  this.libraryPaths = function() {
    return [__dirname];
  };
  this.setCurrentDirectory = function(dir) {
    currentDirectory = dir;
  };
  this.currentDirectory = function() {
    return currentDirectory;
  };
  this.type = function() {
    return "NodeJSRuntime";
  };
  this.getDOMImplementation = function() {
    return domImplementation;
  };
  this.parseXML = function(xml) {
    return parser.parseFromString(xml, "text/xml");
  };
  this.exit = process.exit;
  this.getWindow = function() {
    return null;
  };
  this.requestAnimationFrame = function(callback) {
    return setTimeout(callback, 15);
  };
  this.cancelAnimationFrame = function(requestId) {
    clearTimeout(requestId);
  };
  function init() {
    var DOMParser = require("xmldom").DOMParser;
    parser = new DOMParser;
    domImplementation = self.parseXML("<a/>").implementation;
  }
  init();
}
function RhinoRuntime() {
  var self = this, Packages = {}, dom = Packages.javax.xml.parsers.DocumentBuilderFactory.newInstance(), builder, entityresolver, currentDirectory = "";
  dom.setValidating(false);
  dom.setNamespaceAware(true);
  dom.setExpandEntityReferences(false);
  dom.setSchema(null);
  entityresolver = Packages.org.xml.sax.EntityResolver({resolveEntity:function(publicId, systemId) {
    var file;
    function open(path) {
      var reader = new Packages.java.io.FileReader(path), source = new Packages.org.xml.sax.InputSource(reader);
      return source;
    }
    file = systemId;
    return open(file);
  }});
  builder = dom.newDocumentBuilder();
  builder.setEntityResolver(entityresolver);
  this.byteArrayFromString = function(string, encoding) {
    var i, l = string.length, a = new Uint8Array(new ArrayBuffer(l));
    for (i = 0;i < l;i += 1) {
      a[i] = string.charCodeAt(i) & 255;
    }
    return a;
  };
  this.byteArrayToString = Runtime.byteArrayToString;
  this.getVariable = Runtime.getVariable;
  this.fromJson = Runtime.fromJson;
  this.toJson = Runtime.toJson;
  function loadXML(path, callback) {
    var file = new Packages.java.io.File(path), xmlDocument = null;
    try {
      xmlDocument = builder.parse(file);
    } catch (err) {
      print(err);
      return callback(err, null);
    }
    callback(null, xmlDocument);
  }
  function runtimeReadFile(path, encoding, callback) {
    if (currentDirectory) {
      path = currentDirectory + "/" + path;
    }
    var file = new Packages.java.io.File(path), data, rhinoencoding = encoding === "binary" ? "latin1" : encoding;
    if (!file.isFile()) {
      callback(path + " is not a file.", null);
    } else {
      data = readFile(path, rhinoencoding);
      if (data && encoding === "binary") {
        data = self.byteArrayFromString(data, "binary");
      }
      callback(null, data);
    }
  }
  function runtimeReadFileSync(path, encoding) {
    var file = new Packages.java.io.File(path);
    if (!file.isFile()) {
      return null;
    }
    if (encoding === "binary") {
      encoding = "latin1";
    }
    return readFile(path, encoding);
  }
  this.loadXML = loadXML;
  this.readFile = runtimeReadFile;
  this.writeFile = function(path, data, callback) {
    if (currentDirectory) {
      path = currentDirectory + "/" + path;
    }
    var out = new Packages.java.io.FileOutputStream(path), i, l = data.length;
    for (i = 0;i < l;i += 1) {
      out.write(data[i]);
    }
    out.close();
    callback(null);
  };
  this.deleteFile = function(path, callback) {
    if (currentDirectory) {
      path = currentDirectory + "/" + path;
    }
    var file = new Packages.java.io.File(path), otherPath = path + Math.random(), other = new Packages.java.io.File(otherPath);
    if (file.rename(other)) {
      other.deleteOnExit();
      callback(null);
    } else {
      callback("Could not delete " + path);
    }
  };
  this.read = function(path, offset, length, callback) {
    if (currentDirectory) {
      path = currentDirectory + "/" + path;
    }
    var data = runtimeReadFileSync(path, "binary");
    if (data) {
      callback(null, this.byteArrayFromString(data.substring(offset, offset + length), "binary"));
    } else {
      callback("Cannot read " + path, null);
    }
  };
  this.readFileSync = function(path, encoding) {
    if (!encoding) {
      return "";
    }
    var s = readFile(path, encoding);
    if (s === null) {
      throw "File could not be read.";
    }
    return s;
  };
  function log(msgOrCategory, msg) {
    var category;
    if (msg !== undefined) {
      category = msgOrCategory;
    } else {
      msg = msgOrCategory;
    }
    if (category === "alert") {
      print("\n!!!!! ALERT !!!!!");
    }
    print(msg);
    if (category === "alert") {
      print("!!!!! ALERT !!!!!");
    }
  }
  this.log = log;
  this.assert = Runtime.assert;
  this.setTimeout = function(f) {
    f();
    return 0;
  };
  this.clearTimeout = function() {
  };
  this.libraryPaths = function() {
    return ["lib"];
  };
  this.setCurrentDirectory = function(dir) {
    currentDirectory = dir;
  };
  this.currentDirectory = function() {
    return currentDirectory;
  };
  this.type = function() {
    return "RhinoRuntime";
  };
  this.getDOMImplementation = function() {
    return builder.getDOMImplementation();
  };
  this.parseXML = function(xml) {
    var reader = new Packages.java.io.StringReader(xml), source = new Packages.org.xml.sax.InputSource(reader);
    return builder.parse(source);
  };
  this.exit = quit;
  this.getWindow = function() {
    return null;
  };
  this.requestAnimationFrame = function(callback) {
    callback();
    return 0;
  };
  this.cancelAnimationFrame = function() {
  };
}
Runtime.create = function create() {
  var result;
  if (String(typeof window) !== "undefined") {
    result = new BrowserRuntime;
  } else {
    if (String(typeof require) !== "undefined") {
      result = new NodeJSRuntime;
    } else {
      result = new RhinoRuntime;
    }
  }
  return result;
};
var runtime = Runtime.create();
var core = {};
var gui = {};
var xmldom = {};
var odf = {};
var ops = {};
var webodf = {};
(function() {
  function getWebODFVersion() {
    var version = String(typeof webodf_version) !== "undefined" ? webodf_version : "From Source";
    return version;
  }
  webodf.Version = getWebODFVersion();
})();
(function() {
  function loadDependenciesFromManifest(dir, dependencies, expectFail) {
    var path = dir + "/manifest.json", content, list, manifest, m;
    runtime.log("Loading manifest: " + path);
    try {
      content = runtime.readFileSync(path, "utf-8");
    } catch (e) {
      if (expectFail) {
        runtime.log("No loadable manifest found.");
      } else {
        console.log(String(e));
        throw e;
      }
      return;
    }
    list = JSON.parse(content);
    manifest = list;
    for (m in manifest) {
      if (manifest.hasOwnProperty(m)) {
        dependencies[m] = {dir:dir, deps:manifest[m]};
      }
    }
  }
  function loadDependenciesFromManifests() {
    var dependencies = [], paths = runtime.libraryPaths(), i;
    if (runtime.currentDirectory() && paths.indexOf(runtime.currentDirectory()) === -1) {
      loadDependenciesFromManifest(runtime.currentDirectory(), dependencies, true);
    }
    for (i = 0;i < paths.length;i += 1) {
      loadDependenciesFromManifest(paths[i], dependencies);
    }
    return dependencies;
  }
  function getPath(dir, className) {
    return dir + "/" + className.replace(".", "/") + ".js";
  }
  function getLoadList(classNames, dependencies, isDefined) {
    var loadList = [], stack = {}, visited = {};
    function visit(n) {
      if (visited[n] || isDefined(n)) {
        return;
      }
      if (stack[n]) {
        throw "Circular dependency detected for " + n + ".";
      }
      stack[n] = true;
      if (!dependencies[n]) {
        throw "Missing dependency information for class " + n + ".";
      }
      var d = dependencies[n], deps = d.deps, i, l = deps.length;
      for (i = 0;i < l;i += 1) {
        visit(deps[i]);
      }
      stack[n] = false;
      visited[n] = true;
      loadList.push(getPath(d.dir, n));
    }
    classNames.forEach(visit);
    return loadList;
  }
  function addContent(path, content) {
    content += "\n//# sourceURL=" + path;
    return content;
  }
  function loadFiles(paths) {
    var i, content;
    for (i = 0;i < paths.length;i += 1) {
      content = runtime.readFileSync(paths[i], "utf-8");
      content = addContent(paths[i], content);
      eval(content);
    }
  }
  function loadFilesInBrowser(paths, callback) {
    var e = document.currentScript || document.documentElement.lastChild, df = document.createDocumentFragment(), script, i;
    for (i = 0;i < paths.length;i += 1) {
      script = document.createElement("script");
      script.type = "text/javascript";
      script.charset = "utf-8";
      script.async = false;
      script.setAttribute("src", paths[i]);
      df.appendChild(script);
    }
    if (callback) {
      script.onload = callback;
    }
    e.parentNode.insertBefore(df, e);
  }
  var dependencies, packages = {core:core, gui:gui, xmldom:xmldom, odf:odf, ops:ops};
  function isDefined(classname) {
    var parts = classname.split("."), i, p = packages, l = parts.length;
    for (i = 0;i < l;i += 1) {
      if (!p.hasOwnProperty(parts[i])) {
        return false;
      }
      p = p[parts[i]];
    }
    return true;
  }
  runtime.loadClasses = function(classnames, callback) {
    if (IS_COMPILED_CODE || classnames.length === 0) {
      return callback && callback();
    }
    dependencies = dependencies || loadDependenciesFromManifests();
    classnames = getLoadList(classnames, dependencies, isDefined);
    if (classnames.length === 0) {
      return callback && callback();
    }
    if (runtime.type() === "BrowserRuntime" && callback) {
      loadFilesInBrowser(classnames, callback);
    } else {
      loadFiles(classnames);
      if (callback) {
        callback();
      }
    }
  };
  runtime.loadClass = function(classname, callback) {
    runtime.loadClasses([classname], callback);
  };
})();
(function() {
  var translator = function(string) {
    return string;
  };
  function tr(original) {
    var result = translator(original);
    if (!result || String(typeof result) !== "string") {
      return original;
    }
    return result;
  }
  runtime.getTranslator = function() {
    return translator;
  };
  runtime.setTranslator = function(translatorFunction) {
    translator = translatorFunction;
  };
  runtime.tr = tr;
})();
(function(args) {
  if (args) {
    args = Array.prototype.slice.call(args);
  } else {
    args = [];
  }
  function run(argv) {
    if (!argv.length) {
      return;
    }
    var script = argv[0];
    runtime.readFile(script, "utf8", function(err, code) {
      var path = "", pathEndIndex = script.lastIndexOf("/"), codestring = code;
      if (pathEndIndex !== -1) {
        path = script.substring(0, pathEndIndex);
      } else {
        path = ".";
      }
      runtime.setCurrentDirectory(path);
      function inner_run() {
        var script, path, args, argv, result;
        result = eval(codestring);
        if (result) {
          runtime.exit(result);
        }
        return;
      }
      if (err) {
        runtime.log(err);
        runtime.exit(1);
      } else {
        if (codestring === null) {
          runtime.log("No code found for " + script);
          runtime.exit(1);
        } else {
          inner_run.apply(null, argv);
        }
      }
    });
  }
  if (runtime.type() === "NodeJSRuntime") {
    run(process.argv.slice(2));
  } else {
    if (runtime.type() === "RhinoRuntime") {
      run(args);
    } else {
      run(args.slice(1));
    }
  }
})(String(typeof arguments) !== "undefined" && arguments);
(function() {
  function createASyncSingleton() {
    function forEach(items, f, callback) {
      var i, l = items.length, itemsDone = 0;
      function end(err) {
        if (itemsDone !== l) {
          if (err) {
            itemsDone = l;
            callback(err);
          } else {
            itemsDone += 1;
            if (itemsDone === l) {
              callback(null);
            }
          }
        }
      }
      for (i = 0;i < l;i += 1) {
        f(items[i], end);
      }
    }
    function destroyAll(items, callback) {
      function destroy(itemIndex, err) {
        if (err) {
          callback(err);
        } else {
          if (itemIndex < items.length) {
            items[itemIndex](function(err) {
              destroy(itemIndex + 1, err);
            });
          } else {
            callback();
          }
        }
      }
      destroy(0, undefined);
    }
    return {forEach:forEach, destroyAll:destroyAll};
  }
  core.Async = createASyncSingleton();
})();
function makeBase64() {
  function makeB64tab(bin) {
    var t = {}, i, l;
    for (i = 0, l = bin.length;i < l;i += 1) {
      t[bin.charAt(i)] = i;
    }
    return t;
  }
  var b64chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/", b64tab = makeB64tab(b64chars), convertUTF16StringToBase64, convertBase64ToUTF16String, window = runtime.getWindow(), btoa, atob;
  function stringToArray(s) {
    var i, l = s.length, a = new Uint8Array(new ArrayBuffer(l));
    for (i = 0;i < l;i += 1) {
      a[i] = s.charCodeAt(i) & 255;
    }
    return a;
  }
  function convertUTF8ArrayToBase64(bin) {
    var n, b64 = "", i, l = bin.length - 2;
    for (i = 0;i < l;i += 3) {
      n = bin[i] << 16 | bin[i + 1] << 8 | bin[i + 2];
      b64 += b64chars[n >>> 18];
      b64 += b64chars[n >>> 12 & 63];
      b64 += b64chars[n >>> 6 & 63];
      b64 += b64chars[n & 63];
    }
    if (i === l + 1) {
      n = bin[i] << 4;
      b64 += b64chars[n >>> 6];
      b64 += b64chars[n & 63];
      b64 += "==";
    } else {
      if (i === l) {
        n = bin[i] << 10 | bin[i + 1] << 2;
        b64 += b64chars[n >>> 12];
        b64 += b64chars[n >>> 6 & 63];
        b64 += b64chars[n & 63];
        b64 += "=";
      }
    }
    return b64;
  }
  function convertBase64ToUTF8Array(b64) {
    b64 = b64.replace(/[^A-Za-z0-9+\/]+/g, "");
    var l = b64.length, bin = new Uint8Array(new ArrayBuffer(3 * l)), padlen = b64.length % 4, o = 0, i, n, a = [0, 0, 2, 1];
    for (i = 0;i < l;i += 4) {
      n = (b64tab[b64.charAt(i)] || 0) << 18 | (b64tab[b64.charAt(i + 1)] || 0) << 12 | (b64tab[b64.charAt(i + 2)] || 0) << 6 | (b64tab[b64.charAt(i + 3)] || 0);
      bin[o] = n >> 16;
      bin[o + 1] = n >> 8 & 255;
      bin[o + 2] = n & 255;
      o += 3;
    }
    l = 3 * l - a[padlen];
    return bin.subarray(0, l);
  }
  function convertUTF16ArrayToUTF8Array(uni) {
    var i, n, l = uni.length, o = 0, bin = new Uint8Array(new ArrayBuffer(3 * l));
    for (i = 0;i < l;i += 1) {
      n = uni[i];
      if (n < 128) {
        bin[o++] = n;
      } else {
        if (n < 2048) {
          bin[o++] = 192 | n >>> 6;
          bin[o++] = 128 | n & 63;
        } else {
          bin[o++] = 224 | n >>> 12 & 15;
          bin[o++] = 128 | n >>> 6 & 63;
          bin[o++] = 128 | n & 63;
        }
      }
    }
    return bin.subarray(0, o);
  }
  function convertUTF8ArrayToUTF16Array(bin) {
    var i, c0, c1, c2, l = bin.length, uni = new Uint8Array(new ArrayBuffer(l)), o = 0;
    for (i = 0;i < l;i += 1) {
      c0 = bin[i];
      if (c0 < 128) {
        uni[o++] = c0;
      } else {
        i += 1;
        c1 = bin[i];
        if (c0 < 224) {
          uni[o++] = (c0 & 31) << 6 | c1 & 63;
        } else {
          i += 1;
          c2 = bin[i];
          uni[o++] = (c0 & 15) << 12 | (c1 & 63) << 6 | c2 & 63;
        }
      }
    }
    return uni.subarray(0, o);
  }
  function convertUTF8StringToBase64(bin) {
    return convertUTF8ArrayToBase64(stringToArray(bin));
  }
  function convertBase64ToUTF8String(b64) {
    return String.fromCharCode.apply(String, convertBase64ToUTF8Array(b64));
  }
  function convertUTF8StringToUTF16Array(bin) {
    return convertUTF8ArrayToUTF16Array(stringToArray(bin));
  }
  function convertUTF8ArrayToUTF16String(bin) {
    var b = convertUTF8ArrayToUTF16Array(bin), r = "", i = 0, chunksize = 45E3;
    while (i < b.length) {
      r += String.fromCharCode.apply(String, b.subarray(i, i + chunksize));
      i += chunksize;
    }
    return r;
  }
  function convertUTF8StringToUTF16String_internal(bin, i, end) {
    var c0, c1, c2, j, str = "";
    for (j = i;j < end;j += 1) {
      c0 = bin.charCodeAt(j) & 255;
      if (c0 < 128) {
        str += String.fromCharCode(c0);
      } else {
        j += 1;
        c1 = bin.charCodeAt(j) & 255;
        if (c0 < 224) {
          str += String.fromCharCode((c0 & 31) << 6 | c1 & 63);
        } else {
          j += 1;
          c2 = bin.charCodeAt(j) & 255;
          str += String.fromCharCode((c0 & 15) << 12 | (c1 & 63) << 6 | c2 & 63);
        }
      }
    }
    return str;
  }
  function convertUTF8StringToUTF16String(bin, callback) {
    var partsize = 1E5, str = "", pos = 0;
    if (bin.length < partsize) {
      callback(convertUTF8StringToUTF16String_internal(bin, 0, bin.length), true);
      return;
    }
    if (typeof bin !== "string") {
      bin = bin.slice();
    }
    function f() {
      var end = pos + partsize;
      if (end > bin.length) {
        end = bin.length;
      }
      str += convertUTF8StringToUTF16String_internal(bin, pos, end);
      pos = end;
      end = pos === bin.length;
      if (callback(str, end) && !end) {
        runtime.setTimeout(f, 0);
      }
    }
    f();
  }
  function convertUTF16StringToUTF8Array(uni) {
    return convertUTF16ArrayToUTF8Array(stringToArray(uni));
  }
  function convertUTF16ArrayToUTF8String(uni) {
    return String.fromCharCode.apply(String, convertUTF16ArrayToUTF8Array(uni));
  }
  function convertUTF16StringToUTF8String(uni) {
    return String.fromCharCode.apply(String, convertUTF16ArrayToUTF8Array(stringToArray(uni)));
  }
  if (window && window.btoa) {
    btoa = window.btoa;
    convertUTF16StringToBase64 = function(uni) {
      return btoa(convertUTF16StringToUTF8String(uni));
    };
  } else {
    btoa = convertUTF8StringToBase64;
    convertUTF16StringToBase64 = function(uni) {
      return convertUTF8ArrayToBase64(convertUTF16StringToUTF8Array(uni));
    };
  }
  if (window && window.atob) {
    atob = window.atob;
    convertBase64ToUTF16String = function(b64) {
      var b = atob(b64);
      return convertUTF8StringToUTF16String_internal(b, 0, b.length);
    };
  } else {
    atob = convertBase64ToUTF8String;
    convertBase64ToUTF16String = function(b64) {
      return convertUTF8ArrayToUTF16String(convertBase64ToUTF8Array(b64));
    };
  }
  core.Base64 = function Base64() {
    this.convertUTF8ArrayToBase64 = convertUTF8ArrayToBase64;
    this.convertByteArrayToBase64 = convertUTF8ArrayToBase64;
    this.convertBase64ToUTF8Array = convertBase64ToUTF8Array;
    this.convertBase64ToByteArray = convertBase64ToUTF8Array;
    this.convertUTF16ArrayToUTF8Array = convertUTF16ArrayToUTF8Array;
    this.convertUTF16ArrayToByteArray = convertUTF16ArrayToUTF8Array;
    this.convertUTF8ArrayToUTF16Array = convertUTF8ArrayToUTF16Array;
    this.convertByteArrayToUTF16Array = convertUTF8ArrayToUTF16Array;
    this.convertUTF8StringToBase64 = convertUTF8StringToBase64;
    this.convertBase64ToUTF8String = convertBase64ToUTF8String;
    this.convertUTF8StringToUTF16Array = convertUTF8StringToUTF16Array;
    this.convertUTF8ArrayToUTF16String = convertUTF8ArrayToUTF16String;
    this.convertByteArrayToUTF16String = convertUTF8ArrayToUTF16String;
    this.convertUTF8StringToUTF16String = convertUTF8StringToUTF16String;
    this.convertUTF16StringToUTF8Array = convertUTF16StringToUTF8Array;
    this.convertUTF16StringToByteArray = convertUTF16StringToUTF8Array;
    this.convertUTF16ArrayToUTF8String = convertUTF16ArrayToUTF8String;
    this.convertUTF16StringToUTF8String = convertUTF16StringToUTF8String;
    this.convertUTF16StringToBase64 = convertUTF16StringToBase64;
    this.convertBase64ToUTF16String = convertBase64ToUTF16String;
    this.fromBase64 = convertBase64ToUTF8String;
    this.toBase64 = convertUTF8StringToBase64;
    this.atob = atob;
    this.btoa = btoa;
    this.utob = convertUTF16StringToUTF8String;
    this.btou = convertUTF8StringToUTF16String;
    this.encode = convertUTF16StringToBase64;
    this.encodeURI = function(u) {
      return convertUTF16StringToBase64(u).replace(/[+\/]/g, function(m0) {
        return m0 === "+" ? "-" : "_";
      }).replace(/\\=+$/, "");
    };
    this.decode = function(a) {
      return convertBase64ToUTF16String(a.replace(/[\-_]/g, function(m0) {
        return m0 === "-" ? "+" : "/";
      }));
    };
    return this;
  };
  return core.Base64;
}
core.Base64 = makeBase64();
core.CSSUnits = function CSSUnits() {
  var self = this, sizemap = {"in":1, "cm":2.54, "mm":25.4, "pt":72, "pc":12, "px":96};
  this.convert = function(value, oldUnit, newUnit) {
    return value * sizemap[newUnit] / sizemap[oldUnit];
  };
  this.convertMeasure = function(measure, newUnit) {
    var value, oldUnit, newMeasure;
    if (measure && newUnit) {
      value = parseFloat(measure);
      oldUnit = measure.replace(value.toString(), "");
      newMeasure = self.convert(value, oldUnit, newUnit);
    }
    return newMeasure;
  };
  this.getUnits = function(measure) {
    return measure.substr(measure.length - 2, measure.length);
  };
};
(function() {
  var browserQuirks;
  function getBrowserQuirks() {
    var range, directBoundingRect, rangeBoundingRect, testContainer, testElement, detectedQuirks, window, document, docElement, body, docOverflow, bodyOverflow, bodyHeight, bodyScroll;
    if (browserQuirks === undefined) {
      window = runtime.getWindow();
      document = window && window.document;
      docElement = document.documentElement;
      body = document.body;
      browserQuirks = {rangeBCRIgnoresElementBCR:false, unscaledRangeClientRects:false, elementBCRIgnoresBodyScroll:false};
      if (document) {
        testContainer = document.createElement("div");
        testContainer.style.position = "absolute";
        testContainer.style.left = "-99999px";
        testContainer.style.transform = "scale(2)";
        testContainer.style["-webkit-transform"] = "scale(2)";
        testElement = document.createElement("div");
        testContainer.appendChild(testElement);
        body.appendChild(testContainer);
        range = document.createRange();
        range.selectNode(testElement);
        browserQuirks.rangeBCRIgnoresElementBCR = range.getClientRects().length === 0;
        testElement.appendChild(document.createTextNode("Rect transform test"));
        directBoundingRect = testElement.getBoundingClientRect();
        rangeBoundingRect = range.getBoundingClientRect();
        browserQuirks.unscaledRangeClientRects = Math.abs(directBoundingRect.height - rangeBoundingRect.height) > 2;
        testContainer.style.transform = "";
        testContainer.style["-webkit-transform"] = "";
        docOverflow = docElement.style.overflow;
        bodyOverflow = body.style.overflow;
        bodyHeight = body.style.height;
        bodyScroll = body.scrollTop;
        docElement.style.overflow = "visible";
        body.style.overflow = "visible";
        body.style.height = "200%";
        body.scrollTop = body.scrollHeight;
        browserQuirks.elementBCRIgnoresBodyScroll = range.getBoundingClientRect().top !== testElement.getBoundingClientRect().top;
        body.scrollTop = bodyScroll;
        body.style.height = bodyHeight;
        body.style.overflow = bodyOverflow;
        docElement.style.overflow = docOverflow;
        range.detach();
        body.removeChild(testContainer);
        detectedQuirks = Object.keys(browserQuirks).map(function(quirk) {
          return quirk + ":" + String(browserQuirks[quirk]);
        }).join(", ");
        runtime.log("Detected browser quirks - " + detectedQuirks);
      }
    }
    return browserQuirks;
  }
  function getDirectChild(parent, ns, name) {
    var node = parent ? parent.firstElementChild : null;
    while (node) {
      if (node.localName === name && node.namespaceURI === ns) {
        return node;
      }
      node = node.nextElementSibling;
    }
    return null;
  }
  core.DomUtilsImpl = function DomUtilsImpl() {
    var sharedRange = null;
    function getSharedRange(doc) {
      var range;
      if (sharedRange) {
        range = sharedRange;
      } else {
        sharedRange = range = doc.createRange();
      }
      return range;
    }
    function findStablePoint(container, offset) {
      var c = container;
      if (offset < c.childNodes.length) {
        c = c.childNodes.item(offset);
        offset = 0;
        while (c.firstChild) {
          c = c.firstChild;
        }
      } else {
        while (c.lastChild) {
          c = c.lastChild;
          offset = c.nodeType === Node.TEXT_NODE ? c.textContent.length : c.childNodes.length;
        }
      }
      return {container:c, offset:offset};
    }
    function getPositionInContainingNode(node, container) {
      var offset = 0, n;
      while (node.parentNode !== container) {
        runtime.assert(node.parentNode !== null, "parent is null");
        node = node.parentNode;
      }
      n = container.firstChild;
      while (n !== node) {
        offset += 1;
        n = n.nextSibling;
      }
      return offset;
    }
    function splitBoundaries(range) {
      var modifiedNodes = [], originalEndContainer, resetToContainerLength, end, splitStart, node, text, offset;
      if (range.startContainer.nodeType === Node.TEXT_NODE || range.endContainer.nodeType === Node.TEXT_NODE) {
        originalEndContainer = range.endContainer;
        resetToContainerLength = range.endContainer.nodeType !== Node.TEXT_NODE ? range.endOffset === range.endContainer.childNodes.length : false;
        end = findStablePoint(range.endContainer, range.endOffset);
        if (end.container === originalEndContainer) {
          originalEndContainer = null;
        }
        range.setEnd(end.container, end.offset);
        node = range.endContainer;
        if (range.endOffset !== 0 && node.nodeType === Node.TEXT_NODE) {
          text = node;
          if (range.endOffset !== text.length) {
            modifiedNodes.push(text.splitText(range.endOffset));
            modifiedNodes.push(text);
          }
        }
        node = range.startContainer;
        if (range.startOffset !== 0 && node.nodeType === Node.TEXT_NODE) {
          text = node;
          if (range.startOffset !== text.length) {
            splitStart = text.splitText(range.startOffset);
            modifiedNodes.push(text);
            modifiedNodes.push(splitStart);
            range.setStart(splitStart, 0);
          }
        }
        if (originalEndContainer !== null) {
          node = range.endContainer;
          while (node.parentNode && node.parentNode !== originalEndContainer) {
            node = node.parentNode;
          }
          if (resetToContainerLength) {
            offset = originalEndContainer.childNodes.length;
          } else {
            offset = getPositionInContainingNode(node, originalEndContainer);
          }
          range.setEnd(originalEndContainer, offset);
        }
      }
      return modifiedNodes;
    }
    this.splitBoundaries = splitBoundaries;
    function containsRange(container, insideRange) {
      return container.compareBoundaryPoints(Range.START_TO_START, insideRange) <= 0 && container.compareBoundaryPoints(Range.END_TO_END, insideRange) >= 0;
    }
    this.containsRange = containsRange;
    function rangesIntersect(range1, range2) {
      return range1.compareBoundaryPoints(Range.END_TO_START, range2) <= 0 && range1.compareBoundaryPoints(Range.START_TO_END, range2) >= 0;
    }
    this.rangesIntersect = rangesIntersect;
    function rangeIntersection(range1, range2) {
      var newRange;
      if (rangesIntersect(range1, range2)) {
        newRange = range1.cloneRange();
        if (range1.compareBoundaryPoints(Range.START_TO_START, range2) === -1) {
          newRange.setStart(range2.startContainer, range2.startOffset);
        }
        if (range1.compareBoundaryPoints(Range.END_TO_END, range2) === 1) {
          newRange.setEnd(range2.endContainer, range2.endOffset);
        }
      }
      return newRange;
    }
    this.rangeIntersection = rangeIntersection;
    function maximumOffset(node) {
      return node.nodeType === Node.TEXT_NODE ? node.length : node.childNodes.length;
    }
    function moveToNonRejectedNode(walker, root, nodeFilter) {
      var node = walker.currentNode;
      if (node !== root) {
        node = node.parentNode;
        while (node && node !== root) {
          if (nodeFilter(node) === NodeFilter.FILTER_REJECT) {
            walker.currentNode = node;
          }
          node = node.parentNode;
        }
      }
      return walker.currentNode;
    }
    function getNodesInRange(range, nodeFilter, whatToShow) {
      var document = range.startContainer.ownerDocument, elements = [], rangeRoot = range.commonAncestorContainer, root = rangeRoot.nodeType === Node.TEXT_NODE ? rangeRoot.parentNode : rangeRoot, treeWalker = document.createTreeWalker(root, whatToShow, nodeFilter, false), currentNode, lastNodeInRange, endNodeCompareFlags, comparePositionResult;
      if (range.endContainer.childNodes[range.endOffset - 1]) {
        lastNodeInRange = range.endContainer.childNodes[range.endOffset - 1];
        endNodeCompareFlags = Node.DOCUMENT_POSITION_PRECEDING | Node.DOCUMENT_POSITION_CONTAINED_BY;
      } else {
        lastNodeInRange = range.endContainer;
        endNodeCompareFlags = Node.DOCUMENT_POSITION_PRECEDING;
      }
      if (range.startContainer.childNodes[range.startOffset]) {
        currentNode = range.startContainer.childNodes[range.startOffset];
        treeWalker.currentNode = currentNode;
      } else {
        if (range.startOffset === maximumOffset(range.startContainer)) {
          currentNode = range.startContainer;
          treeWalker.currentNode = currentNode;
          treeWalker.lastChild();
          currentNode = treeWalker.nextNode();
        } else {
          currentNode = range.startContainer;
          treeWalker.currentNode = currentNode;
        }
      }
      if (currentNode) {
        currentNode = moveToNonRejectedNode(treeWalker, root, nodeFilter);
        switch(nodeFilter(currentNode)) {
          case NodeFilter.FILTER_REJECT:
            currentNode = treeWalker.nextSibling();
            while (!currentNode && treeWalker.parentNode()) {
              currentNode = treeWalker.nextSibling();
            }
            break;
          case NodeFilter.FILTER_SKIP:
            currentNode = treeWalker.nextNode();
            break;
          default:
            break;
        }
        while (currentNode) {
          comparePositionResult = lastNodeInRange.compareDocumentPosition(currentNode);
          if (comparePositionResult !== 0 && (comparePositionResult & endNodeCompareFlags) === 0) {
            break;
          }
          elements.push(currentNode);
          currentNode = treeWalker.nextNode();
        }
      }
      return elements;
    }
    this.getNodesInRange = getNodesInRange;
    function mergeTextNodes(node, nextNode) {
      var mergedNode = null, text, nextText;
      if (node.nodeType === Node.TEXT_NODE) {
        text = node;
        if (text.length === 0) {
          text.parentNode.removeChild(text);
          if (nextNode.nodeType === Node.TEXT_NODE) {
            mergedNode = nextNode;
          }
        } else {
          if (nextNode.nodeType === Node.TEXT_NODE) {
            nextText = nextNode;
            text.appendData(nextText.data);
            nextNode.parentNode.removeChild(nextNode);
          }
          mergedNode = node;
        }
      }
      return mergedNode;
    }
    function normalizeTextNodes(node) {
      if (node && node.nextSibling) {
        node = mergeTextNodes(node, node.nextSibling);
      }
      if (node && node.previousSibling) {
        mergeTextNodes(node.previousSibling, node);
      }
    }
    this.normalizeTextNodes = normalizeTextNodes;
    function rangeContainsNode(limits, node) {
      var range = node.ownerDocument.createRange(), nodeRange = node.ownerDocument.createRange(), result;
      range.setStart(limits.startContainer, limits.startOffset);
      range.setEnd(limits.endContainer, limits.endOffset);
      nodeRange.selectNodeContents(node);
      result = containsRange(range, nodeRange);
      range.detach();
      nodeRange.detach();
      return result;
    }
    this.rangeContainsNode = rangeContainsNode;
    function mergeIntoParent(targetNode) {
      var parent = targetNode.parentNode;
      while (targetNode.firstChild) {
        parent.insertBefore(targetNode.firstChild, targetNode);
      }
      parent.removeChild(targetNode);
      return parent;
    }
    this.mergeIntoParent = mergeIntoParent;
    function removeUnwantedNodes(targetNode, nodeFilter) {
      var parent = targetNode.parentNode, node = targetNode.firstChild, filterResult = nodeFilter(targetNode), next;
      if (filterResult === NodeFilter.FILTER_SKIP) {
        return parent;
      }
      while (node) {
        next = node.nextSibling;
        removeUnwantedNodes(node, nodeFilter);
        node = next;
      }
      if (parent && filterResult === NodeFilter.FILTER_REJECT) {
        mergeIntoParent(targetNode);
      }
      return parent;
    }
    this.removeUnwantedNodes = removeUnwantedNodes;
    this.removeAllChildNodes = function(node) {
      while (node.firstChild) {
        node.removeChild(node.firstChild);
      }
    };
    function getElementsByTagNameNS(node, namespace, tagName) {
      var e = [], list, i, l;
      list = node.getElementsByTagNameNS(namespace, tagName);
      e.length = l = list.length;
      for (i = 0;i < l;i += 1) {
        e[i] = list.item(i);
      }
      return e;
    }
    this.getElementsByTagNameNS = getElementsByTagNameNS;
    function getElementsByTagName(node, tagName) {
      var e = [], list, i, l;
      list = node.getElementsByTagName(tagName);
      e.length = l = list.length;
      for (i = 0;i < l;i += 1) {
        e[i] = list.item(i);
      }
      return e;
    }
    this.getElementsByTagName = getElementsByTagName;
    function containsNode(parent, descendant) {
      return parent === descendant || parent.contains(descendant);
    }
    this.containsNode = containsNode;
    function containsNodeForBrokenWebKit(parent, descendant) {
      return parent === descendant || Boolean(parent.compareDocumentPosition(descendant) & Node.DOCUMENT_POSITION_CONTAINED_BY);
    }
    function comparePoints(c1, o1, c2, o2) {
      if (c1 === c2) {
        return o2 - o1;
      }
      var comparison = c1.compareDocumentPosition(c2);
      if (comparison === 2) {
        comparison = -1;
      } else {
        if (comparison === 4) {
          comparison = 1;
        } else {
          if (comparison === 10) {
            o1 = getPositionInContainingNode(c1, c2);
            comparison = o1 < o2 ? 1 : -1;
          } else {
            o2 = getPositionInContainingNode(c2, c1);
            comparison = o2 < o1 ? -1 : 1;
          }
        }
      }
      return comparison;
    }
    this.comparePoints = comparePoints;
    function adaptRangeDifferenceToZoomLevel(inputNumber, zoomLevel) {
      if (getBrowserQuirks().unscaledRangeClientRects) {
        return inputNumber;
      }
      return inputNumber / zoomLevel;
    }
    this.adaptRangeDifferenceToZoomLevel = adaptRangeDifferenceToZoomLevel;
    this.translateRect = function(child, parent, zoomLevel) {
      return {top:adaptRangeDifferenceToZoomLevel(child.top - parent.top, zoomLevel), left:adaptRangeDifferenceToZoomLevel(child.left - parent.left, zoomLevel), bottom:adaptRangeDifferenceToZoomLevel(child.bottom - parent.top, zoomLevel), right:adaptRangeDifferenceToZoomLevel(child.right - parent.left, zoomLevel), width:adaptRangeDifferenceToZoomLevel(child.width, zoomLevel), height:adaptRangeDifferenceToZoomLevel(child.height, zoomLevel)};
    };
    function getBoundingClientRect(node) {
      var doc = node.ownerDocument, quirks = getBrowserQuirks(), range, element, rect, body = doc.body;
      if (quirks.unscaledRangeClientRects === false || quirks.rangeBCRIgnoresElementBCR) {
        if (node.nodeType === Node.ELEMENT_NODE) {
          element = node;
          rect = element.getBoundingClientRect();
          if (quirks.elementBCRIgnoresBodyScroll) {
            return {left:rect.left + body.scrollLeft, right:rect.right + body.scrollLeft, top:rect.top + body.scrollTop, bottom:rect.bottom + body.scrollTop, width:rect.width, height:rect.height};
          }
          return rect;
        }
      }
      range = getSharedRange(doc);
      range.selectNode(node);
      return range.getBoundingClientRect();
    }
    this.getBoundingClientRect = getBoundingClientRect;
    function mapKeyValObjOntoNode(node, properties, nsResolver) {
      Object.keys(properties).forEach(function(key) {
        var parts = key.split(":"), prefix = parts[0], localName = parts[1], ns = nsResolver(prefix), value = properties[key], element;
        if (ns) {
          element = node.getElementsByTagNameNS(ns, localName)[0];
          if (!element) {
            element = node.ownerDocument.createElementNS(ns, key);
            node.appendChild(element);
          }
          element.textContent = value;
        } else {
          runtime.log("Key ignored: " + key);
        }
      });
    }
    this.mapKeyValObjOntoNode = mapKeyValObjOntoNode;
    function removeKeyElementsFromNode(node, propertyNames, nsResolver) {
      propertyNames.forEach(function(propertyName) {
        var parts = propertyName.split(":"), prefix = parts[0], localName = parts[1], ns = nsResolver(prefix), element;
        if (ns) {
          element = node.getElementsByTagNameNS(ns, localName)[0];
          if (element) {
            element.parentNode.removeChild(element);
          } else {
            runtime.log("Element for " + propertyName + " not found.");
          }
        } else {
          runtime.log("Property Name ignored: " + propertyName);
        }
      });
    }
    this.removeKeyElementsFromNode = removeKeyElementsFromNode;
    function getKeyValRepresentationOfNode(node, prefixResolver) {
      var properties = {}, currentSibling = node.firstElementChild, prefix;
      while (currentSibling) {
        prefix = prefixResolver(currentSibling.namespaceURI);
        if (prefix) {
          properties[prefix + ":" + currentSibling.localName] = currentSibling.textContent;
        }
        currentSibling = currentSibling.nextElementSibling;
      }
      return properties;
    }
    this.getKeyValRepresentationOfNode = getKeyValRepresentationOfNode;
    function mapObjOntoNode(node, properties, nsResolver) {
      Object.keys(properties).forEach(function(key) {
        var parts = key.split(":"), prefix = parts[0], localName = parts[1], ns = nsResolver(prefix), value = properties[key], valueType = typeof value, element;
        if (valueType === "object") {
          if (Object.keys(value).length) {
            if (ns) {
              element = node.getElementsByTagNameNS(ns, localName)[0] || node.ownerDocument.createElementNS(ns, key);
            } else {
              element = node.getElementsByTagName(localName)[0] || node.ownerDocument.createElement(key);
            }
            node.appendChild(element);
            mapObjOntoNode(element, value, nsResolver);
          }
        } else {
          if (ns) {
            runtime.assert(valueType === "number" || valueType === "string", "attempting to map unsupported type '" + valueType + "' (key: " + key + ")");
            node.setAttributeNS(ns, key, String(value));
          }
        }
      });
    }
    this.mapObjOntoNode = mapObjOntoNode;
    function cloneEvent(event) {
      var e = Object.create(null);
      Object.keys(event.constructor.prototype).forEach(function(x) {
        e[x] = event[x];
      });
      e.prototype = event.constructor.prototype;
      return e;
    }
    this.cloneEvent = cloneEvent;
    this.getDirectChild = getDirectChild;
    function init(self) {
      var appVersion, webKitOrSafari, ie, window = runtime.getWindow();
      if (window === null) {
        return;
      }
      appVersion = window.navigator.appVersion.toLowerCase();
      webKitOrSafari = appVersion.indexOf("chrome") === -1 && (appVersion.indexOf("applewebkit") !== -1 || appVersion.indexOf("safari") !== -1);
      ie = appVersion.indexOf("msie") !== -1 || appVersion.indexOf("trident") !== -1;
      if (webKitOrSafari || ie) {
        self.containsNode = containsNodeForBrokenWebKit;
      }
    }
    init(this);
  };
  core.DomUtils = new core.DomUtilsImpl;
})();
core.Cursor = function Cursor(document, memberId) {
  var cursorns = "urn:webodf:names:cursor", cursorNode = document.createElementNS(cursorns, "cursor"), anchorNode = document.createElementNS(cursorns, "anchor"), forwardSelection, recentlyModifiedNodes = [], selectedRange = document.createRange(), isCollapsed, domUtils = core.DomUtils;
  function putIntoTextNode(node, container, offset) {
    runtime.assert(Boolean(container), "putCursorIntoTextNode: invalid container");
    var parent = container.parentNode;
    runtime.assert(Boolean(parent), "putCursorIntoTextNode: container without parent");
    runtime.assert(offset >= 0 && offset <= container.length, "putCursorIntoTextNode: offset is out of bounds");
    if (offset === 0) {
      parent.insertBefore(node, container);
    } else {
      if (offset === container.length) {
        parent.insertBefore(node, container.nextSibling);
      } else {
        container.splitText(offset);
        parent.insertBefore(node, container.nextSibling);
      }
    }
  }
  function removeNode(node) {
    if (node.parentNode) {
      recentlyModifiedNodes.push(node.previousSibling);
      recentlyModifiedNodes.push(node.nextSibling);
      node.parentNode.removeChild(node);
    }
  }
  function putNode(node, container, offset) {
    if (container.nodeType === Node.TEXT_NODE) {
      putIntoTextNode(node, container, offset);
    } else {
      if (container.nodeType === Node.ELEMENT_NODE) {
        container.insertBefore(node, container.childNodes.item(offset));
      }
    }
    recentlyModifiedNodes.push(node.previousSibling);
    recentlyModifiedNodes.push(node.nextSibling);
  }
  function getStartNode() {
    return forwardSelection ? anchorNode : cursorNode;
  }
  function getEndNode() {
    return forwardSelection ? cursorNode : anchorNode;
  }
  this.getNode = function() {
    return cursorNode;
  };
  this.getAnchorNode = function() {
    return anchorNode.parentNode ? anchorNode : cursorNode;
  };
  this.getSelectedRange = function() {
    if (isCollapsed) {
      selectedRange.setStartBefore(cursorNode);
      selectedRange.collapse(true);
    } else {
      selectedRange.setStartAfter(getStartNode());
      selectedRange.setEndBefore(getEndNode());
    }
    return selectedRange;
  };
  this.setSelectedRange = function(range, isForwardSelection) {
    if (selectedRange && selectedRange !== range) {
      selectedRange.detach();
    }
    selectedRange = range;
    forwardSelection = isForwardSelection !== false;
    isCollapsed = range.collapsed;
    if (range.collapsed) {
      removeNode(anchorNode);
      removeNode(cursorNode);
      putNode(cursorNode, range.startContainer, range.startOffset);
    } else {
      removeNode(anchorNode);
      removeNode(cursorNode);
      putNode(getEndNode(), range.endContainer, range.endOffset);
      putNode(getStartNode(), range.startContainer, range.startOffset);
    }
    recentlyModifiedNodes.forEach(domUtils.normalizeTextNodes);
    recentlyModifiedNodes.length = 0;
  };
  this.hasForwardSelection = function() {
    return forwardSelection;
  };
  this.remove = function() {
    removeNode(cursorNode);
    recentlyModifiedNodes.forEach(domUtils.normalizeTextNodes);
    recentlyModifiedNodes.length = 0;
  };
  function init() {
    cursorNode.setAttributeNS(cursorns, "memberId", memberId);
    anchorNode.setAttributeNS(cursorns, "memberId", memberId);
  }
  init();
};
core.Destroyable = function Destroyable() {
};
core.Destroyable.prototype.destroy = function(callback) {
};
core.EventSource = function() {
};
core.EventSource.prototype.subscribe = function(eventId, cb) {
};
core.EventSource.prototype.unsubscribe = function(eventId, cb) {
};
core.EventNotifier = function EventNotifier(eventIds) {
  var eventListener = {};
  this.emit = function(eventId, args) {
    var i, subscribers;
    runtime.assert(eventListener.hasOwnProperty(eventId), 'unknown event fired "' + eventId + '"');
    subscribers = eventListener[eventId];
    for (i = 0;i < subscribers.length;i += 1) {
      subscribers[i](args);
    }
  };
  this.subscribe = function(eventId, cb) {
    runtime.assert(eventListener.hasOwnProperty(eventId), 'tried to subscribe to unknown event "' + eventId + '"');
    eventListener[eventId].push(cb);
  };
  this.unsubscribe = function(eventId, cb) {
    var cbIndex;
    runtime.assert(eventListener.hasOwnProperty(eventId), 'tried to unsubscribe from unknown event "' + eventId + '"');
    cbIndex = eventListener[eventId].indexOf(cb);
    runtime.assert(cbIndex !== -1, 'tried to unsubscribe unknown callback from event "' + eventId + '"');
    if (cbIndex !== -1) {
      eventListener[eventId].splice(cbIndex, 1);
    }
  };
  function register(eventId) {
    runtime.assert(!eventListener.hasOwnProperty(eventId), 'Duplicated event ids: "' + eventId + '" registered more than once.');
    eventListener[eventId] = [];
  }
  this.register = register;
  function init() {
    if (eventIds) {
      eventIds.forEach(register);
    }
  }
  init();
};
core.ScheduledTask = function ScheduledTask(fn, scheduleTask, cancelTask) {
  var timeoutId, scheduled = false, args = [], destroyed = false;
  function cancel() {
    if (scheduled) {
      cancelTask(timeoutId);
      scheduled = false;
    }
  }
  function execute() {
    cancel();
    fn.apply(undefined, args);
    args = null;
  }
  this.trigger = function() {
    runtime.assert(destroyed === false, "Can't trigger destroyed ScheduledTask instance");
    args = Array.prototype.slice.call(arguments);
    if (!scheduled) {
      scheduled = true;
      timeoutId = scheduleTask(execute);
    }
  };
  this.triggerImmediate = function() {
    runtime.assert(destroyed === false, "Can't trigger destroyed ScheduledTask instance");
    args = Array.prototype.slice.call(arguments);
    execute();
  };
  this.processRequests = function() {
    if (scheduled) {
      execute();
    }
  };
  this.cancel = cancel;
  this.restart = function() {
    runtime.assert(destroyed === false, "Can't trigger destroyed ScheduledTask instance");
    cancel();
    scheduled = true;
    timeoutId = scheduleTask(execute);
  };
  this.destroy = function(callback) {
    cancel();
    destroyed = true;
    callback();
  };
};
(function() {
  var redrawTasks;
  function RedrawTasks() {
    var callbacks = {};
    this.requestRedrawTask = function(callback) {
      var id = runtime.requestAnimationFrame(function() {
        callback();
        delete callbacks[id];
      });
      callbacks[id] = callback;
      return id;
    };
    this.performRedraw = function() {
      Object.keys(callbacks).forEach(function(id) {
        callbacks[id]();
        runtime.cancelAnimationFrame(parseInt(id, 10));
      });
      callbacks = {};
    };
    this.cancelRedrawTask = function(id) {
      runtime.cancelAnimationFrame(id);
      delete callbacks[id];
    };
  }
  core.Task = {};
  core.Task.SUPPRESS_MANUAL_PROCESSING = false;
  core.Task.processTasks = function() {
    if (!core.Task.SUPPRESS_MANUAL_PROCESSING) {
      redrawTasks.performRedraw();
    }
  };
  core.Task.createRedrawTask = function(callback) {
    return new core.ScheduledTask(callback, redrawTasks.requestRedrawTask, redrawTasks.cancelRedrawTask);
  };
  core.Task.createTimeoutTask = function(callback, delay) {
    return new core.ScheduledTask(callback, function(callback) {
      return runtime.setTimeout(callback, delay);
    }, runtime.clearTimeout);
  };
  function init() {
    redrawTasks = new RedrawTasks;
  }
  init();
})();
core.EventSubscriptions = function() {
  var subscriptions = [], frameEventNotifier = new core.EventNotifier, frameSubscriptions = {}, nextFrameEventId = 0;
  function addSubscription(eventSource, eventid, callback) {
    eventSource.subscribe(eventid, callback);
    subscriptions.push({eventSource:eventSource, eventid:eventid, callback:callback});
  }
  this.addSubscription = addSubscription;
  this.addFrameSubscription = function(eventSource, eventid, callback) {
    var frameSubscription, frameEventId, eventFrameSubscriptions, i;
    if (!frameSubscriptions.hasOwnProperty(eventid)) {
      frameSubscriptions[eventid] = [];
    }
    eventFrameSubscriptions = frameSubscriptions[eventid];
    for (i = 0;i < eventFrameSubscriptions.length;i += 1) {
      if (eventFrameSubscriptions[i].eventSource === eventSource) {
        frameSubscription = eventFrameSubscriptions[i];
        break;
      }
    }
    if (!frameSubscription) {
      frameEventId = "s" + nextFrameEventId;
      nextFrameEventId += 1;
      frameEventNotifier.register(frameEventId);
      frameSubscription = {frameEventId:frameEventId, eventSource:eventSource, task:core.Task.createRedrawTask(function() {
        frameEventNotifier.emit(frameEventId, undefined);
      })};
      eventFrameSubscriptions.push(frameSubscription);
      addSubscription(eventSource, eventid, frameSubscription.task.trigger);
    }
    frameEventNotifier.subscribe(frameSubscription.frameEventId, callback);
  };
  function unsubscribeAll() {
    var cleanup = [];
    subscriptions.forEach(function(subscription) {
      subscription.eventSource.unsubscribe(subscription.eventid, subscription.callback);
    });
    subscriptions.length = 0;
    Object.keys(frameSubscriptions).forEach(function(eventId) {
      frameSubscriptions[eventId].forEach(function(subscriber) {
        cleanup.push(subscriber.task.destroy);
      });
      delete frameSubscriptions[eventId];
    });
    core.Async.destroyAll(cleanup, function() {
    });
    frameEventNotifier = new core.EventNotifier;
  }
  this.unsubscribeAll = unsubscribeAll;
  this.destroy = function(callback) {
    unsubscribeAll();
    callback();
  };
};
core.LazyProperty = function(valueLoader) {
  var cachedValue, valueLoaded = false;
  this.value = function() {
    if (!valueLoaded) {
      cachedValue = valueLoader();
      valueLoaded = true;
    }
    return cachedValue;
  };
  this.reset = function() {
    valueLoaded = false;
  };
};
core.LoopWatchDog = function LoopWatchDog(timeout, maxChecks) {
  var startTime = Date.now(), checks = 0;
  function check() {
    var t;
    if (timeout) {
      t = Date.now();
      if (t - startTime > timeout) {
        runtime.log("alert", "watchdog timeout");
        throw "timeout!";
      }
    }
    if (maxChecks > 0) {
      checks += 1;
      if (checks > maxChecks) {
        runtime.log("alert", "watchdog loop overflow");
        throw "loop overflow";
      }
    }
  }
  this.check = check;
};
core.NodeFilterChain = function(filters) {
  var FILTER_REJECT = NodeFilter.FILTER_REJECT, FILTER_ACCEPT = NodeFilter.FILTER_ACCEPT;
  this.acceptNode = function(node) {
    var i;
    for (i = 0;i < filters.length;i += 1) {
      if (filters[i].acceptNode(node) === FILTER_REJECT) {
        return FILTER_REJECT;
      }
    }
    return FILTER_ACCEPT;
  };
};
core.PositionIterator = function PositionIterator(root, whatToShow, filter, expandEntityReferences) {
  var self = this, walker, currentPos, nodeFilter, TEXT_NODE = Node.TEXT_NODE, ELEMENT_NODE = Node.ELEMENT_NODE, FILTER_ACCEPT = NodeFilter.FILTER_ACCEPT, FILTER_REJECT = NodeFilter.FILTER_REJECT;
  function EmptyTextNodeFilter() {
    this.acceptNode = function(node) {
      var text = node;
      if (!node || node.nodeType === TEXT_NODE && text.length === 0) {
        return FILTER_REJECT;
      }
      return FILTER_ACCEPT;
    };
  }
  function FilteredEmptyTextNodeFilter(filter) {
    this.acceptNode = function(node) {
      var text = node;
      if (!node || node.nodeType === TEXT_NODE && text.length === 0) {
        return FILTER_REJECT;
      }
      return filter.acceptNode(node);
    };
  }
  this.nextPosition = function() {
    var currentNode = walker.currentNode, nodeType = currentNode.nodeType, text = currentNode;
    if (currentNode === root) {
      return false;
    }
    if (currentPos === 0 && nodeType === ELEMENT_NODE) {
      if (walker.firstChild() === null) {
        currentPos = 1;
      }
    } else {
      if (nodeType === TEXT_NODE && currentPos + 1 < text.length) {
        currentPos += 1;
      } else {
        if (walker.nextSibling() !== null) {
          currentPos = 0;
        } else {
          if (walker.parentNode()) {
            currentPos = 1;
          } else {
            return false;
          }
        }
      }
    }
    return true;
  };
  function setAtEnd() {
    var text = walker.currentNode, type = text.nodeType;
    if (type === TEXT_NODE) {
      currentPos = text.length - 1;
    } else {
      currentPos = type === ELEMENT_NODE ? 1 : 0;
    }
  }
  function previousNode() {
    if (walker.previousSibling() === null) {
      if (!walker.parentNode() || walker.currentNode === root) {
        walker.firstChild();
        return false;
      }
      currentPos = 0;
    } else {
      setAtEnd();
    }
    return true;
  }
  this.previousPosition = function() {
    var moved = true, currentNode = walker.currentNode;
    if (currentPos === 0) {
      moved = previousNode();
    } else {
      if (currentNode.nodeType === TEXT_NODE) {
        currentPos -= 1;
      } else {
        if (walker.lastChild() !== null) {
          setAtEnd();
        } else {
          if (currentNode === root) {
            moved = false;
          } else {
            currentPos = 0;
          }
        }
      }
    }
    return moved;
  };
  this.previousNode = previousNode;
  this.container = function() {
    var n = walker.currentNode, t = n.nodeType;
    if (currentPos === 0 && t !== TEXT_NODE) {
      n = n.parentNode;
    }
    return n;
  };
  this.rightNode = function() {
    var n = walker.currentNode, text = n, nodeType = n.nodeType;
    if (nodeType === TEXT_NODE && currentPos === text.length) {
      n = n.nextSibling;
      while (n && nodeFilter(n) !== FILTER_ACCEPT) {
        n = n.nextSibling;
      }
    } else {
      if (nodeType === ELEMENT_NODE && currentPos === 1) {
        n = null;
      }
    }
    return n;
  };
  this.leftNode = function() {
    var n = walker.currentNode;
    if (currentPos === 0) {
      n = n.previousSibling;
      while (n && nodeFilter(n) !== FILTER_ACCEPT) {
        n = n.previousSibling;
      }
    } else {
      if (n.nodeType === ELEMENT_NODE) {
        n = n.lastChild;
        while (n && nodeFilter(n) !== FILTER_ACCEPT) {
          n = n.previousSibling;
        }
      }
    }
    return n;
  };
  this.getCurrentNode = function() {
    var n = walker.currentNode;
    return n;
  };
  this.unfilteredDomOffset = function() {
    if (walker.currentNode.nodeType === TEXT_NODE) {
      return currentPos;
    }
    var c = 0, n = walker.currentNode;
    if (currentPos === 1) {
      n = n.lastChild;
    } else {
      n = n.previousSibling;
    }
    while (n) {
      c += 1;
      n = n.previousSibling;
    }
    return c;
  };
  this.getPreviousSibling = function() {
    var currentNode = walker.currentNode, sibling = walker.previousSibling();
    walker.currentNode = currentNode;
    return sibling;
  };
  this.getNextSibling = function() {
    var currentNode = walker.currentNode, sibling = walker.nextSibling();
    walker.currentNode = currentNode;
    return sibling;
  };
  function moveToAcceptedNode() {
    var node = walker.currentNode, filterResult, moveResult;
    filterResult = nodeFilter(node);
    if (node !== root) {
      node = node.parentNode;
      while (node && node !== root) {
        if (nodeFilter(node) === FILTER_REJECT) {
          walker.currentNode = node;
          filterResult = FILTER_REJECT;
        }
        node = node.parentNode;
      }
    }
    if (filterResult === FILTER_REJECT) {
      currentPos = walker.currentNode.nodeType === TEXT_NODE ? node.length : 1;
      moveResult = self.nextPosition();
    } else {
      if (filterResult === FILTER_ACCEPT) {
        moveResult = true;
      } else {
        moveResult = self.nextPosition();
      }
    }
    if (moveResult) {
      runtime.assert(nodeFilter(walker.currentNode) === FILTER_ACCEPT, "moveToAcceptedNode did not result in walker being on an accepted node");
    }
    return moveResult;
  }
  this.setPositionBeforeElement = function(element) {
    runtime.assert(Boolean(element), "setPositionBeforeElement called without element");
    walker.currentNode = element;
    currentPos = 0;
    return moveToAcceptedNode();
  };
  this.setUnfilteredPosition = function(container, offset) {
    var text;
    runtime.assert(Boolean(container), "PositionIterator.setUnfilteredPosition called without container");
    walker.currentNode = container;
    if (container.nodeType === TEXT_NODE) {
      currentPos = offset;
      text = container;
      runtime.assert(offset <= text.length, "Error in setPosition: " + offset + " > " + text.length);
      runtime.assert(offset >= 0, "Error in setPosition: " + offset + " < 0");
      if (offset === text.length) {
        if (walker.nextSibling()) {
          currentPos = 0;
        } else {
          if (walker.parentNode()) {
            currentPos = 1;
          } else {
            runtime.assert(false, "Error in setUnfilteredPosition: position not valid.");
          }
        }
      }
    } else {
      if (offset < container.childNodes.length) {
        walker.currentNode = container.childNodes.item(offset);
        currentPos = 0;
      } else {
        currentPos = 1;
      }
    }
    return moveToAcceptedNode();
  };
  this.moveToEnd = function() {
    walker.currentNode = root;
    currentPos = 1;
  };
  this.moveToEndOfNode = function(node) {
    var text;
    if (node.nodeType === TEXT_NODE) {
      text = node;
      self.setUnfilteredPosition(text, text.length);
    } else {
      walker.currentNode = node;
      currentPos = 1;
    }
  };
  this.isBeforeNode = function() {
    return currentPos === 0;
  };
  this.getNodeFilter = function() {
    return nodeFilter;
  };
  function init() {
    var f;
    if (filter) {
      f = new FilteredEmptyTextNodeFilter(filter);
    } else {
      f = new EmptyTextNodeFilter;
    }
    nodeFilter = f.acceptNode;
    nodeFilter.acceptNode = nodeFilter;
    whatToShow = whatToShow || NodeFilter.SHOW_ALL;
    runtime.assert(root.nodeType !== Node.TEXT_NODE, "Internet Explorer doesn't allow tree walker roots to be text nodes");
    walker = root.ownerDocument.createTreeWalker(root, whatToShow, nodeFilter, expandEntityReferences);
    currentPos = 0;
    if (walker.firstChild() === null) {
      currentPos = 1;
    }
  }
  init();
};
core.PositionFilter = function PositionFilter() {
};
core.PositionFilter.FilterResult = {FILTER_ACCEPT:1, FILTER_REJECT:2, FILTER_SKIP:3};
core.PositionFilter.prototype.acceptPosition = function(point) {
};
core.PositionFilterChain = function PositionFilterChain() {
  var filterChain = [], FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, FILTER_REJECT = core.PositionFilter.FilterResult.FILTER_REJECT;
  this.acceptPosition = function(iterator) {
    var i;
    for (i = 0;i < filterChain.length;i += 1) {
      if (filterChain[i].acceptPosition(iterator) === FILTER_REJECT) {
        return FILTER_REJECT;
      }
    }
    return FILTER_ACCEPT;
  };
  this.addFilter = function(filterInstance) {
    filterChain.push(filterInstance);
  };
};
core.StepDirection = {PREVIOUS:1, NEXT:2};
core.StepIterator = function StepIterator(filter, iterator) {
  var FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, NEXT = core.StepDirection.NEXT, cachedContainer, cachedOffset, cachedFilterResult;
  function resetCache() {
    cachedContainer = null;
    cachedOffset = undefined;
    cachedFilterResult = undefined;
  }
  function isStep() {
    if (cachedFilterResult === undefined) {
      cachedFilterResult = filter.acceptPosition(iterator) === FILTER_ACCEPT;
    }
    return cachedFilterResult;
  }
  this.isStep = isStep;
  function setPosition(newContainer, newOffset) {
    resetCache();
    return iterator.setUnfilteredPosition(newContainer, newOffset);
  }
  this.setPosition = setPosition;
  function container() {
    if (!cachedContainer) {
      cachedContainer = iterator.container();
    }
    return cachedContainer;
  }
  this.container = container;
  function offset() {
    if (cachedOffset === undefined) {
      cachedOffset = iterator.unfilteredDomOffset();
    }
    return cachedOffset;
  }
  this.offset = offset;
  function nextStep() {
    resetCache();
    while (iterator.nextPosition()) {
      resetCache();
      if (isStep()) {
        return true;
      }
    }
    return false;
  }
  this.nextStep = nextStep;
  function previousStep() {
    resetCache();
    while (iterator.previousPosition()) {
      resetCache();
      if (isStep()) {
        return true;
      }
    }
    return false;
  }
  this.previousStep = previousStep;
  this.advanceStep = function(direction) {
    return direction === NEXT ? nextStep() : previousStep();
  };
  this.roundToClosestStep = function() {
    var currentContainer, currentOffset, isAtStep = isStep();
    if (!isAtStep) {
      currentContainer = container();
      currentOffset = offset();
      isAtStep = previousStep();
      if (!isAtStep) {
        setPosition(currentContainer, currentOffset);
        isAtStep = nextStep();
      }
    }
    return isAtStep;
  };
  this.roundToPreviousStep = function() {
    var isAtStep = isStep();
    if (!isAtStep) {
      isAtStep = previousStep();
    }
    return isAtStep;
  };
  this.roundToNextStep = function() {
    var isAtStep = isStep();
    if (!isAtStep) {
      isAtStep = nextStep();
    }
    return isAtStep;
  };
  this.leftNode = function() {
    return iterator.leftNode();
  };
  this.snapshot = function() {
    return new core.StepIterator.StepSnapshot(container(), offset());
  };
  this.restore = function(snapshot) {
    setPosition(snapshot.container, snapshot.offset);
  };
};
core.StepIterator.StepSnapshot = function(container, offset) {
  this.container = container;
  this.offset = offset;
};
core.Utils = function Utils() {
  function hashString(value) {
    var hash = 0, i, l;
    for (i = 0, l = value.length;i < l;i += 1) {
      hash = (hash << 5) - hash + value.charCodeAt(i);
      hash |= 0;
    }
    return hash;
  }
  this.hashString = hashString;
  var mergeObjects;
  function mergeItems(destination, source) {
    if (source && Array.isArray(source)) {
      destination = destination || [];
      if (!Array.isArray(destination)) {
        throw "Destination is not an array.";
      }
      destination = destination.concat(source.map(function(obj) {
        return mergeItems(null, obj);
      }));
    } else {
      if (source && typeof source === "object") {
        destination = destination || {};
        if (typeof destination !== "object") {
          throw "Destination is not an object.";
        }
        Object.keys(source).forEach(function(p) {
          destination[p] = mergeItems(destination[p], source[p]);
        });
      } else {
        destination = source;
      }
    }
    return destination;
  }
  mergeObjects = function(destination, source) {
    Object.keys(source).forEach(function(p) {
      destination[p] = mergeItems(destination[p], source[p]);
    });
    return destination;
  };
  this.mergeObjects = mergeObjects;
};
core.Zip = function Zip(url, entriesReadCallback) {
  var self = this, zip, base64 = new core.Base64;
  function load(filename, callback) {
    var entry = zip.file(filename);
    if (entry) {
      callback(null, entry.asUint8Array());
    } else {
      callback(filename + " not found.", null);
    }
  }
  function loadAsString(filename, callback) {
    load(filename, function(err, data) {
      if (err || data === null) {
        return callback(err, null);
      }
      var d = runtime.byteArrayToString(data, "utf8");
      callback(null, d);
    });
  }
  function loadContentXmlAsFragments(filename, handler) {
    loadAsString(filename, function(err, data) {
      if (err) {
        return handler.rootElementReady(err);
      }
      handler.rootElementReady(null, data, true);
    });
  }
  function loadAsDataURL(filename, mimetype, callback) {
    load(filename, function(err, data) {
      if (err || !data) {
        return callback(err, null);
      }
      var p = data, chunksize = 45E3, i = 0, dataurl;
      if (!mimetype) {
        if (p[1] === 80 && p[2] === 78 && p[3] === 71) {
          mimetype = "image/png";
        } else {
          if (p[0] === 255 && p[1] === 216 && p[2] === 255) {
            mimetype = "image/jpeg";
          } else {
            if (p[0] === 71 && p[1] === 73 && p[2] === 70) {
              mimetype = "image/gif";
            } else {
              mimetype = "";
            }
          }
        }
      }
      dataurl = "data:" + mimetype + ";base64,";
      while (i < data.length) {
        dataurl += base64.convertUTF8ArrayToBase64(p.subarray(i, Math.min(i + chunksize, p.length)));
        i += chunksize;
      }
      callback(null, dataurl);
    });
  }
  function loadAsDOM(filename, callback) {
    loadAsString(filename, function(err, xmldata) {
      if (err || xmldata === null) {
        callback(err, null);
        return;
      }
      var parser = new DOMParser, dom = parser.parseFromString(xmldata, "text/xml");
      callback(null, dom);
    });
  }
  function save(filename, data, compressed, date) {
    zip.file(filename, data, {date:date, compression:compressed ? "DEFLATE" : "STORE"});
  }
  function remove(filename) {
    var exists = zip.file(filename) !== null;
    zip.remove(filename);
    return exists;
  }
  function createByteArray(successCallback, errorCallback) {
    try {
      successCallback(zip.generate({type:"uint8array", compression:"STORE"}));
    } catch (e) {
      errorCallback(e.message);
    }
  }
  function writeAs(newurl, callback) {
    createByteArray(function(data) {
      runtime.writeFile(newurl, data, callback);
    }, callback);
  }
  function write(callback) {
    writeAs(url, callback);
  }
  this.load = load;
  this.save = save;
  this.remove = remove;
  this.write = write;
  this.writeAs = writeAs;
  this.createByteArray = createByteArray;
  this.loadContentXmlAsFragments = loadContentXmlAsFragments;
  this.loadAsString = loadAsString;
  this.loadAsDOM = loadAsDOM;
  this.loadAsDataURL = loadAsDataURL;
  this.getEntries = function() {
    return Object.keys(zip.files).map(function(filename) {
      var e = zip.files[filename];
      return {filename:filename, date:e.date};
    });
  };
  zip = new externs.JSZip;
  if (entriesReadCallback === null) {
    return;
  }
  runtime.readFile(url, "binary", function(err, result) {
    if (typeof result === "string") {
      err = "file was read as a string. Should be Uint8Array.";
    }
    if (err || !result || result.length === 0) {
      entriesReadCallback("File '" + url + "' cannot be read. Err: " + (err || "[none]"), self);
    } else {
      try {
        zip.load(result, {checkCRC32:false});
        entriesReadCallback(null, self);
      } catch (e) {
        entriesReadCallback(e.message, self);
      }
    }
  });
};
core.SimpleClientRect = null;
gui.CommonConstraints = {EDIT:{ANNOTATIONS:{ONLY_DELETE_OWN:"onlyDeleteOwn"}, REVIEW_MODE:"reviewMode"}};
gui.SessionConstraints = function SessionConstraints() {
  var constraints = {}, constraintNotifier = new core.EventNotifier;
  function registerConstraint(constraint) {
    if (!constraints.hasOwnProperty(constraint)) {
      constraints[constraint] = false;
      constraintNotifier.register(constraint);
    }
  }
  this.registerConstraint = registerConstraint;
  this.subscribe = function(constraint, callback) {
    registerConstraint(constraint);
    constraintNotifier.subscribe(constraint, callback);
  };
  this.unsubscribe = function(constraint, callback) {
    constraintNotifier.unsubscribe(constraint, callback);
  };
  this.setState = function(constraint, enabled) {
    runtime.assert(constraints.hasOwnProperty(constraint) === true, "No such constraint");
    if (constraints[constraint] !== enabled) {
      constraints[constraint] = enabled;
      constraintNotifier.emit(constraint, enabled);
    }
  };
  this.getState = function(constraint) {
    runtime.assert(constraints.hasOwnProperty(constraint) === true, "No such constraint");
    return constraints[constraint];
  };
};
gui.BlacklistNamespaceNodeFilter = function(excludedNamespaces) {
  var excludedNamespacesObj = {}, FILTER_REJECT = NodeFilter.FILTER_REJECT, FILTER_ACCEPT = NodeFilter.FILTER_ACCEPT;
  this.acceptNode = function(node) {
    if (!node || excludedNamespacesObj.hasOwnProperty(node.namespaceURI)) {
      return FILTER_REJECT;
    }
    return FILTER_ACCEPT;
  };
  function init() {
    excludedNamespaces.forEach(function(ns) {
      excludedNamespacesObj[ns] = true;
    });
  }
  init();
};
odf.Namespaces = {namespaceMap:{config:"urn:oasis:names:tc:opendocument:xmlns:config:1.0", db:"urn:oasis:names:tc:opendocument:xmlns:database:1.0", dc:"http://purl.org/dc/elements/1.1/", dr3d:"urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0", draw:"urn:oasis:names:tc:opendocument:xmlns:drawing:1.0", chart:"urn:oasis:names:tc:opendocument:xmlns:chart:1.0", fo:"urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0", form:"urn:oasis:names:tc:opendocument:xmlns:form:1.0", math:"http://www.w3.org/1998/Math/MathML", 
meta:"urn:oasis:names:tc:opendocument:xmlns:meta:1.0", number:"urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0", office:"urn:oasis:names:tc:opendocument:xmlns:office:1.0", presentation:"urn:oasis:names:tc:opendocument:xmlns:presentation:1.0", style:"urn:oasis:names:tc:opendocument:xmlns:style:1.0", svg:"urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0", table:"urn:oasis:names:tc:opendocument:xmlns:table:1.0", text:"urn:oasis:names:tc:opendocument:xmlns:text:1.0", xforms:"http://www.w3.org/2002/xforms", 
xlink:"http://www.w3.org/1999/xlink", xml:"http://www.w3.org/XML/1998/namespace"}, prefixMap:{}, configns:"urn:oasis:names:tc:opendocument:xmlns:config:1.0", dbns:"urn:oasis:names:tc:opendocument:xmlns:database:1.0", dcns:"http://purl.org/dc/elements/1.1/", dr3dns:"urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0", drawns:"urn:oasis:names:tc:opendocument:xmlns:drawing:1.0", chartns:"urn:oasis:names:tc:opendocument:xmlns:chart:1.0", fons:"urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0", 
formns:"urn:oasis:names:tc:opendocument:xmlns:form:1.0", mathns:"http://www.w3.org/1998/Math/MathML", metans:"urn:oasis:names:tc:opendocument:xmlns:meta:1.0", numberns:"urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0", officens:"urn:oasis:names:tc:opendocument:xmlns:office:1.0", presentationns:"urn:oasis:names:tc:opendocument:xmlns:presentation:1.0", stylens:"urn:oasis:names:tc:opendocument:xmlns:style:1.0", svgns:"urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0", tablens:"urn:oasis:names:tc:opendocument:xmlns:table:1.0", 
textns:"urn:oasis:names:tc:opendocument:xmlns:text:1.0", xformsns:"http://www.w3.org/2002/xforms", xlinkns:"http://www.w3.org/1999/xlink", xmlns:"http://www.w3.org/XML/1998/namespace"};
(function() {
  var map = odf.Namespaces.namespaceMap, pmap = odf.Namespaces.prefixMap, prefix;
  for (prefix in map) {
    if (map.hasOwnProperty(prefix)) {
      pmap[map[prefix]] = prefix;
    }
  }
})();
odf.Namespaces.forEachPrefix = function forEachPrefix(cb) {
  var ns = odf.Namespaces.namespaceMap, prefix;
  for (prefix in ns) {
    if (ns.hasOwnProperty(prefix)) {
      cb(prefix, ns[prefix]);
    }
  }
};
odf.Namespaces.lookupNamespaceURI = function lookupNamespaceURI(prefix) {
  var r = null;
  if (odf.Namespaces.namespaceMap.hasOwnProperty(prefix)) {
    r = odf.Namespaces.namespaceMap[prefix];
  }
  return r;
};
odf.Namespaces.lookupPrefix = function lookupPrefix(namespaceURI) {
  var map = odf.Namespaces.prefixMap;
  return map.hasOwnProperty(namespaceURI) ? map[namespaceURI] : null;
};
odf.Namespaces.lookupNamespaceURI.lookupNamespaceURI = odf.Namespaces.lookupNamespaceURI;
(function() {
  odf.OdfSchemaImpl = function() {
    var TEXT = "text", FIELD = "field", OBJECT = "object", STYLE = "style", DEPRECATED = "deprecated", UNKNOWN = "uncategorized", containers = [["config:config-item", UNKNOWN], ["form:item", OBJECT], ["form:option", UNKNOWN], ["math:math", FIELD], ["meta:user-defined", UNKNOWN], ["number:currency-symbol", UNKNOWN], ["number:embedded-text", UNKNOWN], ["number:text", UNKNOWN], ["presentation:date-time-decl", UNKNOWN], ["presentation:footer-decl", UNKNOWN], ["presentation:header-decl", UNKNOWN], ["svg:desc", 
    TEXT], ["svg:title", TEXT], ["table:desc", UNKNOWN], ["table:title", UNKNOWN], ["text:a", TEXT], ["text:author-initials", FIELD], ["text:author-name", FIELD], ["text:bibliography-mark", FIELD], ["text:bookmark-ref", FIELD], ["text:chapter", FIELD], ["text:character-count", FIELD], ["text:conditional-text", FIELD], ["text:creation-date", FIELD], ["text:creation-time", FIELD], ["text:creator", FIELD], ["text:database-display", FIELD], ["text:database-name", FIELD], ["text:database-row-number", 
    FIELD], ["text:date", FIELD], ["text:dde-connection", FIELD], ["text:description", FIELD], ["text:editing-cycles", FIELD], ["text:editing-duration", FIELD], ["text:execute-macro", UNKNOWN], ["text:expression", UNKNOWN], ["text:file-name", FIELD], ["text:h", TEXT], ["text:hidden-paragraph", TEXT], ["text:hidden-text", TEXT], ["text:image-count", FIELD], ["text:index-entry-span", UNKNOWN], ["text:index-title-template", UNKNOWN], ["text:initial-creator", FIELD], ["text:keywords", FIELD], ["text:linenumbering-separator", 
    STYLE], ["text:measure", UNKNOWN], ["text:meta", UNKNOWN], ["text:meta-field", UNKNOWN], ["text:modification-date", FIELD], ["text:modification-time", FIELD], ["text:note-citation", FIELD], ["text:note-continuation-notice-backward", STYLE], ["text:note-continuation-notice-forward", STYLE], ["text:note-ref", FIELD], ["text:object-count", FIELD], ["text:p", TEXT], ["text:page-continuation", UNKNOWN], ["text:page-count", FIELD], ["text:page-number", FIELD], ["text:page-variable-get", FIELD], ["text:page-variable-set", 
    FIELD], ["text:paragraph-count", FIELD], ["text:placeholder", FIELD], ["text:print-date", FIELD], ["text:print-time", FIELD], ["text:printed-by", FIELD], ["text:reference-ref", FIELD], ["text:ruby-base", TEXT], ["text:ruby-text", TEXT], ["text:script", TEXT], ["text:sender-city", FIELD], ["text:sender-company", FIELD], ["text:sender-country", FIELD], ["text:sender-email", FIELD], ["text:sender-fax", FIELD], ["text:sender-firstname", FIELD], ["text:sender-initials", FIELD], ["text:sender-lastname", 
    FIELD], ["text:sender-phone-private", FIELD], ["text:sender-phone-work", FIELD], ["text:sender-position", FIELD], ["text:sender-postal-code", FIELD], ["text:sender-state-or-province", FIELD], ["text:sender-street", FIELD], ["text:sender-title", FIELD], ["text:sequence", UNKNOWN], ["text:sequence-ref", UNKNOWN], ["text:sheet-name", UNKNOWN], ["text:span", TEXT], ["text:subject", FIELD], ["text:table-count", FIELD], ["text:table-formula", DEPRECATED], ["text:template-name", UNKNOWN], ["text:text-input", 
    FIELD], ["text:time", FIELD], ["text:title", FIELD], ["text:user-defined", FIELD], ["text:user-field-get", FIELD], ["text:user-field-input", FIELD], ["text:variable-get", FIELD], ["text:variable-input", FIELD], ["text:variable-set", FIELD], ["text:word-count", FIELD], ["xforms:model", UNKNOWN]], cache = {};
    this.isTextContainer = function(namespaceURI, localName) {
      return cache[namespaceURI + ":" + localName] === TEXT;
    };
    this.isField = function(namespaceURI, localName) {
      return cache[namespaceURI + ":" + localName] === FIELD;
    };
    this.getFields = function() {
      return containers.filter(function(containerInfo) {
        return containerInfo[1] === FIELD;
      }).map(function(containerInfo) {
        return containerInfo[0];
      });
    };
    function init() {
      containers.forEach(function(containerInfo) {
        var name = containerInfo[0], type = containerInfo[1], nameParts = name.split(":"), prefix = nameParts[0], localName = nameParts[1], namespaceURI = odf.Namespaces.lookupNamespaceURI(prefix);
        if (namespaceURI) {
          cache[namespaceURI + ":" + localName] = type;
        } else {
          runtime.log("DEBUG: OdfSchema - unknown prefix '" + prefix + "'");
        }
      });
    }
    init();
  };
  odf.OdfSchema = new odf.OdfSchemaImpl;
})();
odf.OdfUtilsImpl = function OdfUtilsImpl() {
  var textns = odf.Namespaces.textns, drawns = odf.Namespaces.drawns, xlinkns = odf.Namespaces.xlinkns, domUtils = core.DomUtils, odfNodeNamespaceMap = [odf.Namespaces.dbns, odf.Namespaces.dcns, odf.Namespaces.dr3dns, odf.Namespaces.drawns, odf.Namespaces.chartns, odf.Namespaces.formns, odf.Namespaces.numberns, odf.Namespaces.officens, odf.Namespaces.presentationns, odf.Namespaces.stylens, odf.Namespaces.svgns, odf.Namespaces.tablens, odf.Namespaces.textns], odfSchema = odf.OdfSchema;
  function isImage(e) {
    var name = e && e.localName;
    return name === "image" && e.namespaceURI === drawns;
  }
  this.isImage = isImage;
  function isCharacterFrame(e) {
    return e !== null && e.nodeType === Node.ELEMENT_NODE && e.localName === "frame" && e.namespaceURI === drawns && e.getAttributeNS(textns, "anchor-type") === "as-char";
  }
  this.isCharacterFrame = isCharacterFrame;
  function isAnnotation(e) {
    var name = e && e.localName;
    return name === "annotation" && e.namespaceURI === odf.Namespaces.officens;
  }
  function isAnnotationWrapper(e) {
    var name = e && e.localName;
    return name === "div" && e.className === "annotationWrapper";
  }
  function isInlineRoot(e) {
    return isAnnotation(e) || isAnnotationWrapper(e);
  }
  this.isInlineRoot = isInlineRoot;
  this.isTextSpan = function(e) {
    var name = e && e.localName;
    return name === "span" && e.namespaceURI === textns;
  };
  function isHyperlink(node) {
    var name = node && node.localName;
    return name === "a" && node.namespaceURI === textns;
  }
  this.isHyperlink = isHyperlink;
  this.getHyperlinkTarget = function(element) {
    return element.getAttributeNS(xlinkns, "href") || "";
  };
  function isParagraph(e) {
    var name = e && e.localName;
    return (name === "p" || name === "h") && e.namespaceURI === textns;
  }
  this.isParagraph = isParagraph;
  function getParagraphElement(node, offset) {
    if (node && offset !== undefined && !isParagraph(node) && node.childNodes.item(offset)) {
      node = node.childNodes.item(offset);
    }
    while (node && !isParagraph(node)) {
      node = node.parentNode;
    }
    return node;
  }
  this.getParagraphElement = getParagraphElement;
  function getParentAnnotation(node, container) {
    while (node && node !== container) {
      if (node.namespaceURI === odf.Namespaces.officens && node.localName === "annotation") {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }
  this.getParentAnnotation = getParentAnnotation;
  this.isWithinAnnotation = function(node, container) {
    return Boolean(getParentAnnotation(node, container));
  };
  this.getAnnotationCreator = function(annotationElement) {
    var creatorElement = annotationElement.getElementsByTagNameNS(odf.Namespaces.dcns, "creator")[0];
    return creatorElement.textContent;
  };
  this.isListItem = function(e) {
    var name = e && e.localName;
    return name === "list-item" && e.namespaceURI === textns;
  };
  this.isLineBreak = function(e) {
    var name = e && e.localName;
    return name === "line-break" && e.namespaceURI === textns;
  };
  function isODFWhitespace(text) {
    return /^[ \t\r\n]+$/.test(text);
  }
  this.isODFWhitespace = isODFWhitespace;
  function isGroupingElement(n) {
    if (n === null || n.nodeType !== Node.ELEMENT_NODE) {
      return false;
    }
    var e = n, localName = e.localName;
    return odfSchema.isTextContainer(e.namespaceURI, localName) || localName === "span" && e.className === "webodf-annotationHighlight";
  }
  this.isGroupingElement = isGroupingElement;
  function isFieldElement(n) {
    if (n === null || n.nodeType !== Node.ELEMENT_NODE) {
      return false;
    }
    var e = n, localName = e.localName;
    return odfSchema.isField(e.namespaceURI, localName);
  }
  this.isFieldElement = isFieldElement;
  function isCharacterElement(e) {
    var n = e && e.localName, ns, r = false;
    if (n) {
      ns = e.namespaceURI;
      if (ns === textns) {
        r = n === "s" || n === "tab" || n === "line-break";
      }
    }
    return r;
  }
  this.isCharacterElement = isCharacterElement;
  function isAnchoredAsCharacterElement(e) {
    return isCharacterElement(e) || isFieldElement(e) || isCharacterFrame(e) || isInlineRoot(e);
  }
  this.isAnchoredAsCharacterElement = isAnchoredAsCharacterElement;
  function isSpaceElement(e) {
    var n = e && e.localName, ns, r = false;
    if (n) {
      ns = e.namespaceURI;
      if (ns === textns) {
        r = n === "s";
      }
    }
    return r;
  }
  this.isSpaceElement = isSpaceElement;
  function isODFNode(node) {
    return odfNodeNamespaceMap.indexOf(node.namespaceURI) !== -1;
  }
  this.isODFNode = isODFNode;
  function hasNoODFContent(node) {
    var childNode;
    if (isCharacterElement(node) || isFieldElement(node)) {
      return false;
    }
    if (isGroupingElement(node.parentNode) && node.nodeType === Node.TEXT_NODE) {
      return node.textContent.length === 0;
    }
    childNode = node.firstChild;
    while (childNode) {
      if (isODFNode(childNode) || !hasNoODFContent(childNode)) {
        return false;
      }
      childNode = childNode.nextSibling;
    }
    return true;
  }
  this.hasNoODFContent = hasNoODFContent;
  function firstChild(node) {
    while (node.firstChild !== null && isGroupingElement(node)) {
      node = node.firstChild;
    }
    return node;
  }
  this.firstChild = firstChild;
  function lastChild(node) {
    while (node.lastChild !== null && isGroupingElement(node)) {
      node = node.lastChild;
    }
    return node;
  }
  this.lastChild = lastChild;
  function previousNode(node) {
    while (!isParagraph(node) && node.previousSibling === null) {
      node = node.parentNode;
    }
    return isParagraph(node) ? null : lastChild(node.previousSibling);
  }
  this.previousNode = previousNode;
  function nextNode(node) {
    while (!isParagraph(node) && node.nextSibling === null) {
      node = node.parentNode;
    }
    return isParagraph(node) ? null : firstChild(node.nextSibling);
  }
  this.nextNode = nextNode;
  function scanLeftForNonSpace(node) {
    var r = false, text;
    while (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        text = node;
        if (text.length === 0) {
          node = previousNode(text);
        } else {
          return !isODFWhitespace(text.data.substr(text.length - 1, 1));
        }
      } else {
        if (isAnchoredAsCharacterElement(node)) {
          r = isSpaceElement(node) === false;
          node = null;
        } else {
          node = previousNode(node);
        }
      }
    }
    return r;
  }
  this.scanLeftForNonSpace = scanLeftForNonSpace;
  function lookLeftForCharacter(node) {
    var text, r = 0, tl = 0;
    if (node.nodeType === Node.TEXT_NODE) {
      tl = node.length;
    }
    if (tl > 0) {
      text = node.data;
      if (!isODFWhitespace(text.substr(tl - 1, 1))) {
        r = 1;
      } else {
        if (tl === 1) {
          r = scanLeftForNonSpace(previousNode(node)) ? 2 : 0;
        } else {
          r = isODFWhitespace(text.substr(tl - 2, 1)) ? 0 : 2;
        }
      }
    } else {
      if (isAnchoredAsCharacterElement(node)) {
        r = 1;
      }
    }
    return r;
  }
  this.lookLeftForCharacter = lookLeftForCharacter;
  function lookRightForCharacter(node) {
    var r = false, l = 0;
    if (node && node.nodeType === Node.TEXT_NODE) {
      l = node.length;
    }
    if (l > 0) {
      r = !isODFWhitespace(node.data.substr(0, 1));
    } else {
      if (isAnchoredAsCharacterElement(node)) {
        r = true;
      }
    }
    return r;
  }
  this.lookRightForCharacter = lookRightForCharacter;
  function scanLeftForAnyCharacter(node) {
    var r = false, l;
    node = node && lastChild(node);
    while (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        l = node.length;
      } else {
        l = 0;
      }
      if (l > 0 && !isODFWhitespace(node.data)) {
        r = true;
        break;
      }
      if (isAnchoredAsCharacterElement(node)) {
        r = true;
        break;
      }
      node = previousNode(node);
    }
    return r;
  }
  this.scanLeftForAnyCharacter = scanLeftForAnyCharacter;
  function scanRightForAnyCharacter(node) {
    var r = false, l;
    node = node && firstChild(node);
    while (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        l = node.length;
      } else {
        l = 0;
      }
      if (l > 0 && !isODFWhitespace(node.data)) {
        r = true;
        break;
      }
      if (isAnchoredAsCharacterElement(node)) {
        r = true;
        break;
      }
      node = nextNode(node);
    }
    return r;
  }
  this.scanRightForAnyCharacter = scanRightForAnyCharacter;
  function isTrailingWhitespace(textnode, offset) {
    if (!isODFWhitespace(textnode.data.substr(offset))) {
      return false;
    }
    return !scanRightForAnyCharacter(nextNode(textnode));
  }
  this.isTrailingWhitespace = isTrailingWhitespace;
  function isSignificantWhitespace(textNode, offset) {
    var text = textNode.data, result;
    if (!isODFWhitespace(text[offset])) {
      return false;
    }
    if (isAnchoredAsCharacterElement(textNode.parentNode)) {
      return false;
    }
    if (offset > 0) {
      if (!isODFWhitespace(text[offset - 1])) {
        result = true;
      }
    } else {
      if (scanLeftForNonSpace(previousNode(textNode))) {
        result = true;
      }
    }
    if (result === true) {
      return isTrailingWhitespace(textNode, offset) ? false : true;
    }
    return false;
  }
  this.isSignificantWhitespace = isSignificantWhitespace;
  this.isDowngradableSpaceElement = function(node) {
    if (isSpaceElement(node)) {
      return scanLeftForNonSpace(previousNode(node)) && scanRightForAnyCharacter(nextNode(node));
    }
    return false;
  };
  function parseLength(length) {
    var re = /(-?[0-9]*[0-9][0-9]*(\.[0-9]*)?|0+\.[0-9]*[1-9][0-9]*|\.[0-9]*[1-9][0-9]*)((cm)|(mm)|(in)|(pt)|(pc)|(px)|(%))/, m = re.exec(length);
    if (!m) {
      return null;
    }
    return {value:parseFloat(m[1]), unit:m[3]};
  }
  this.parseLength = parseLength;
  function parsePositiveLength(length) {
    var result = parseLength(length);
    if (result && (result.value <= 0 || result.unit === "%")) {
      return null;
    }
    return result;
  }
  function parseNonNegativeLength(length) {
    var result = parseLength(length);
    if (result && (result.value < 0 || result.unit === "%")) {
      return null;
    }
    return result;
  }
  this.parseNonNegativeLength = parseNonNegativeLength;
  function parsePercentage(length) {
    var result = parseLength(length);
    if (result && result.unit !== "%") {
      return null;
    }
    return result;
  }
  function parseFoFontSize(fontSize) {
    return parsePositiveLength(fontSize) || parsePercentage(fontSize);
  }
  this.parseFoFontSize = parseFoFontSize;
  function parseFoLineHeight(lineHeight) {
    return parseNonNegativeLength(lineHeight) || parsePercentage(lineHeight);
  }
  this.parseFoLineHeight = parseFoLineHeight;
  function isTextContentContainingNode(node) {
    switch(node.namespaceURI) {
      case odf.Namespaces.drawns:
      ;
      case odf.Namespaces.svgns:
      ;
      case odf.Namespaces.dr3dns:
        return false;
      case odf.Namespaces.textns:
        switch(node.localName) {
          case "note-body":
          ;
          case "ruby-text":
            return false;
        }
        break;
      case odf.Namespaces.officens:
        switch(node.localName) {
          case "annotation":
          ;
          case "binary-data":
          ;
          case "event-listeners":
            return false;
        }
        break;
      default:
        switch(node.localName) {
          case "cursor":
          ;
          case "editinfo":
            return false;
        }
        break;
    }
    return true;
  }
  this.isTextContentContainingNode = isTextContentContainingNode;
  function isSignificantTextContent(textNode) {
    return Boolean(getParagraphElement(textNode) && (!isODFWhitespace(textNode.textContent) || isSignificantWhitespace(textNode, 0)));
  }
  function removePartiallyContainedNodes(range, nodes) {
    while (nodes.length > 0 && !domUtils.rangeContainsNode(range, nodes[0])) {
      nodes.shift();
    }
    while (nodes.length > 0 && !domUtils.rangeContainsNode(range, nodes[nodes.length - 1])) {
      nodes.pop();
    }
  }
  function getTextNodes(range, includePartial) {
    var textNodes;
    function nodeFilter(node) {
      var result = NodeFilter.FILTER_REJECT;
      if (node.nodeType === Node.TEXT_NODE) {
        if (isSignificantTextContent(node)) {
          result = NodeFilter.FILTER_ACCEPT;
        }
      } else {
        if (isTextContentContainingNode(node)) {
          result = NodeFilter.FILTER_SKIP;
        }
      }
      return result;
    }
    textNodes = domUtils.getNodesInRange(range, nodeFilter, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
    if (!includePartial) {
      removePartiallyContainedNodes(range, textNodes);
    }
    return textNodes;
  }
  this.getTextNodes = getTextNodes;
  function getTextElements(range, includePartial, includeInsignificantWhitespace) {
    var elements;
    function nodeFilter(node) {
      var result = NodeFilter.FILTER_REJECT;
      if (isCharacterElement(node.parentNode) || isFieldElement(node.parentNode) || isInlineRoot(node)) {
        result = NodeFilter.FILTER_REJECT;
      } else {
        if (node.nodeType === Node.TEXT_NODE) {
          if (includeInsignificantWhitespace || isSignificantTextContent(node)) {
            result = NodeFilter.FILTER_ACCEPT;
          }
        } else {
          if (isAnchoredAsCharacterElement(node)) {
            result = NodeFilter.FILTER_ACCEPT;
          } else {
            if (isTextContentContainingNode(node) || isGroupingElement(node)) {
              result = NodeFilter.FILTER_SKIP;
            }
          }
        }
      }
      return result;
    }
    elements = domUtils.getNodesInRange(range, nodeFilter, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
    if (!includePartial) {
      removePartiallyContainedNodes(range, elements);
    }
    return elements;
  }
  this.getTextElements = getTextElements;
  function prependParentContainers(startContainer, elements, filter) {
    var container = startContainer;
    while (container) {
      if (filter(container)) {
        if (elements[0] !== container) {
          elements.unshift(container);
        }
        break;
      }
      if (isInlineRoot(container)) {
        break;
      }
      container = container.parentNode;
    }
  }
  this.getParagraphElements = function(range) {
    var elements;
    function nodeFilter(node) {
      var result = NodeFilter.FILTER_REJECT;
      if (isParagraph(node)) {
        result = NodeFilter.FILTER_ACCEPT;
      } else {
        if (isTextContentContainingNode(node) || isGroupingElement(node)) {
          result = NodeFilter.FILTER_SKIP;
        }
      }
      return result;
    }
    elements = domUtils.getNodesInRange(range, nodeFilter, NodeFilter.SHOW_ELEMENT);
    prependParentContainers(range.startContainer, elements, isParagraph);
    return elements;
  };
  this.getImageElements = function(range) {
    var elements;
    function nodeFilter(node) {
      var result = NodeFilter.FILTER_SKIP;
      if (isImage(node)) {
        result = NodeFilter.FILTER_ACCEPT;
      }
      return result;
    }
    elements = domUtils.getNodesInRange(range, nodeFilter, NodeFilter.SHOW_ELEMENT);
    prependParentContainers(range.startContainer, elements, isImage);
    return elements;
  };
  function getRightNode(container, offset) {
    var node = container;
    if (offset < node.childNodes.length - 1) {
      node = node.childNodes[offset + 1];
    } else {
      while (!node.nextSibling) {
        node = node.parentNode;
      }
      node = node.nextSibling;
    }
    while (node.firstChild) {
      node = node.firstChild;
    }
    return node;
  }
  this.getHyperlinkElements = function(range) {
    var links = [], newRange = range.cloneRange(), node, textNodes;
    if (range.collapsed && range.endContainer.nodeType === Node.ELEMENT_NODE) {
      node = getRightNode(range.endContainer, range.endOffset);
      if (node.nodeType === Node.TEXT_NODE) {
        newRange.setEnd(node, 1);
      }
    }
    textNodes = getTextElements(newRange, true, false);
    textNodes.forEach(function(node) {
      var parent = node.parentNode;
      while (!isParagraph(parent)) {
        if (isHyperlink(parent) && links.indexOf(parent) === -1) {
          links.push(parent);
          break;
        }
        parent = parent.parentNode;
      }
    });
    newRange.detach();
    return links;
  };
  this.getNormalizedFontFamilyName = function(fontFamilyName) {
    if (!/^(["'])(?:.|[\n\r])*?\1$/.test(fontFamilyName)) {
      fontFamilyName = fontFamilyName.replace(/^[ \t\r\n\f]*((?:.|[\n\r])*?)[ \t\r\n\f]*$/, "$1");
      if (/[ \t\r\n\f]/.test(fontFamilyName)) {
        fontFamilyName = "'" + fontFamilyName.replace(/[ \t\r\n\f]+/g, " ") + "'";
      }
    }
    return fontFamilyName;
  };
};
odf.OdfUtils = new odf.OdfUtilsImpl;
gui.OdfTextBodyNodeFilter = function() {
  var odfUtils = odf.OdfUtils, TEXT_NODE = Node.TEXT_NODE, FILTER_REJECT = NodeFilter.FILTER_REJECT, FILTER_ACCEPT = NodeFilter.FILTER_ACCEPT, textns = odf.Namespaces.textns;
  this.acceptNode = function(node) {
    if (node.nodeType === TEXT_NODE) {
      if (!odfUtils.isGroupingElement(node.parentNode)) {
        return FILTER_REJECT;
      }
    } else {
      if (node.namespaceURI === textns && node.localName === "tracked-changes") {
        return FILTER_REJECT;
      }
    }
    return FILTER_ACCEPT;
  };
};
xmldom.LSSerializerFilter = function LSSerializerFilter() {
};
xmldom.LSSerializerFilter.prototype.acceptNode = function(node) {
};
odf.OdfNodeFilter = function OdfNodeFilter() {
  this.acceptNode = function(node) {
    var result;
    if (node.namespaceURI === "http://www.w3.org/1999/xhtml") {
      result = NodeFilter.FILTER_SKIP;
    } else {
      if (node.namespaceURI && node.namespaceURI.match(/^urn:webodf:/)) {
        result = NodeFilter.FILTER_REJECT;
      } else {
        result = NodeFilter.FILTER_ACCEPT;
      }
    }
    return result;
  };
};
xmldom.XPathIterator = function XPathIterator() {
};
xmldom.XPathIterator.prototype.next = function() {
};
xmldom.XPathIterator.prototype.reset = function() {
};
xmldom.XPathAtom;
function createXPathSingleton() {
  var createXPathPathIterator, parsePredicates;
  function isSmallestPositive(a, b, c) {
    return a !== -1 && (a < b || b === -1) && (a < c || c === -1);
  }
  function parseXPathStep(xpath, pos, end, steps) {
    var location = "", predicates = [], brapos = xpath.indexOf("[", pos), slapos = xpath.indexOf("/", pos), eqpos = xpath.indexOf("=", pos);
    if (isSmallestPositive(slapos, brapos, eqpos)) {
      location = xpath.substring(pos, slapos);
      pos = slapos + 1;
    } else {
      if (isSmallestPositive(brapos, slapos, eqpos)) {
        location = xpath.substring(pos, brapos);
        pos = parsePredicates(xpath, brapos, predicates);
      } else {
        if (isSmallestPositive(eqpos, slapos, brapos)) {
          location = xpath.substring(pos, eqpos);
          pos = eqpos;
        } else {
          location = xpath.substring(pos, end);
          pos = end;
        }
      }
    }
    steps.push({location:location, predicates:predicates});
    return pos;
  }
  function parseXPath(xpath) {
    var steps = [], p = 0, end = xpath.length, value;
    while (p < end) {
      p = parseXPathStep(xpath, p, end, steps);
      if (p < end && xpath[p] === "=") {
        value = xpath.substring(p + 1, end);
        if (value.length > 2 && (value[0] === "'" || value[0] === '"')) {
          value = value.slice(1, value.length - 1);
        } else {
          try {
            value = parseInt(value, 10);
          } catch (ignore) {
          }
        }
        p = end;
      }
    }
    return {steps:steps, value:value};
  }
  parsePredicates = function parsePredicates(xpath, start, predicates) {
    var pos = start, l = xpath.length, depth = 0;
    while (pos < l) {
      if (xpath[pos] === "]") {
        depth -= 1;
        if (depth <= 0) {
          predicates.push(parseXPath(xpath.substring(start, pos)));
        }
      } else {
        if (xpath[pos] === "[") {
          if (depth <= 0) {
            start = pos + 1;
          }
          depth += 1;
        }
      }
      pos += 1;
    }
    return pos;
  };
  function XPathNodeIterator() {
    var node = null, done = false;
    this.setNode = function setNode(n) {
      node = n;
    };
    this.reset = function() {
      done = false;
    };
    this.next = function next() {
      var val = done ? null : node;
      done = true;
      return val;
    };
  }
  function AttributeIterator(it, namespace, localName) {
    this.reset = function reset() {
      it.reset();
    };
    this.next = function next() {
      var node = it.next();
      while (node) {
        if (node.nodeType === Node.ELEMENT_NODE) {
          node = node.getAttributeNodeNS(namespace, localName);
        }
        if (node) {
          return node;
        }
        node = it.next();
      }
      return node;
    };
  }
  function AllChildElementIterator(it, recurse) {
    var root = it.next(), node = null;
    this.reset = function reset() {
      it.reset();
      root = it.next();
      node = null;
    };
    this.next = function next() {
      while (root) {
        if (node) {
          if (recurse && node.firstChild) {
            node = node.firstChild;
          } else {
            while (!node.nextSibling && node !== root) {
              node = node.parentNode;
            }
            if (node === root) {
              root = it.next();
            } else {
              node = node.nextSibling;
            }
          }
        } else {
          do {
            node = root.firstChild;
            if (!node) {
              root = it.next();
            }
          } while (root && !node);
        }
        if (node && node.nodeType === Node.ELEMENT_NODE) {
          return node;
        }
      }
      return null;
    };
  }
  function ConditionIterator(it, condition) {
    this.reset = function reset() {
      it.reset();
    };
    this.next = function next() {
      var n = it.next();
      while (n && !condition(n)) {
        n = it.next();
      }
      return n;
    };
  }
  function createNodenameFilter(it, name, namespaceResolver) {
    var s = name.split(":", 2), namespace = namespaceResolver(s[0]), localName = s[1];
    return new ConditionIterator(it, function(node) {
      return node.localName === localName && node.namespaceURI === namespace;
    });
  }
  function createPredicateFilteredIterator(it, p, namespaceResolver) {
    var nit = new XPathNodeIterator, pit = createXPathPathIterator(nit, p, namespaceResolver), value = p.value;
    if (value === undefined) {
      return new ConditionIterator(it, function(node) {
        nit.setNode(node);
        pit.reset();
        return pit.next() !== null;
      });
    }
    return new ConditionIterator(it, function(node) {
      nit.setNode(node);
      pit.reset();
      var n = pit.next();
      return n ? n.nodeValue === value : false;
    });
  }
  function item(p, i) {
    return p[i];
  }
  createXPathPathIterator = function createXPathPathIterator(it, xpath, namespaceResolver) {
    var i, j, step, location, s, p, ns;
    for (i = 0;i < xpath.steps.length;i += 1) {
      step = xpath.steps[i];
      location = step.location;
      if (location === "") {
        it = new AllChildElementIterator(it, false);
      } else {
        if (location[0] === "@") {
          s = location.substr(1).split(":", 2);
          ns = namespaceResolver(s[0]);
          if (!ns) {
            throw "No namespace associated with the prefix " + s[0];
          }
          it = new AttributeIterator(it, ns, s[1]);
        } else {
          if (location !== ".") {
            it = new AllChildElementIterator(it, false);
            if (location.indexOf(":") !== -1) {
              it = createNodenameFilter(it, location, namespaceResolver);
            }
          }
        }
      }
      for (j = 0;j < step.predicates.length;j += 1) {
        p = item(step.predicates, j);
        it = createPredicateFilteredIterator(it, p, namespaceResolver);
      }
    }
    return it;
  };
  function fallback(node, xpath, namespaceResolver) {
    var it = new XPathNodeIterator, i, nodelist, parsedXPath;
    it.setNode(node);
    parsedXPath = parseXPath(xpath);
    it = createXPathPathIterator(it, parsedXPath, namespaceResolver);
    nodelist = [];
    i = it.next();
    while (i) {
      nodelist.push(i);
      i = it.next();
    }
    return nodelist;
  }
  function getODFElementsWithXPath(node, xpath, namespaceResolver) {
    var doc = node.ownerDocument, nodes, elements = [], n = null;
    if (!doc || typeof doc.evaluate !== "function") {
      elements = fallback(node, xpath, namespaceResolver);
    } else {
      nodes = doc.evaluate(xpath, node, namespaceResolver, XPathResult.UNORDERED_NODE_ITERATOR_TYPE, null);
      n = nodes.iterateNext();
      while (n !== null) {
        if (n.nodeType === Node.ELEMENT_NODE) {
          elements.push(n);
        }
        n = nodes.iterateNext();
      }
    }
    return elements;
  }
  return {getODFElementsWithXPath:getODFElementsWithXPath};
}
xmldom.XPath = createXPathSingleton();
odf.StyleInfo = function StyleInfo() {
  var chartns = odf.Namespaces.chartns, dbns = odf.Namespaces.dbns, dr3dns = odf.Namespaces.dr3dns, drawns = odf.Namespaces.drawns, formns = odf.Namespaces.formns, numberns = odf.Namespaces.numberns, officens = odf.Namespaces.officens, presentationns = odf.Namespaces.presentationns, stylens = odf.Namespaces.stylens, tablens = odf.Namespaces.tablens, textns = odf.Namespaces.textns, nsprefixes = {"urn:oasis:names:tc:opendocument:xmlns:chart:1.0":"chart:", "urn:oasis:names:tc:opendocument:xmlns:database:1.0":"db:", 
  "urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0":"dr3d:", "urn:oasis:names:tc:opendocument:xmlns:drawing:1.0":"draw:", "urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0":"fo:", "urn:oasis:names:tc:opendocument:xmlns:form:1.0":"form:", "urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0":"number:", "urn:oasis:names:tc:opendocument:xmlns:office:1.0":"office:", "urn:oasis:names:tc:opendocument:xmlns:presentation:1.0":"presentation:", "urn:oasis:names:tc:opendocument:xmlns:style:1.0":"style:", 
  "urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0":"svg:", "urn:oasis:names:tc:opendocument:xmlns:table:1.0":"table:", "urn:oasis:names:tc:opendocument:xmlns:text:1.0":"chart:", "http://www.w3.org/XML/1998/namespace":"xml:"}, elementstyles = {"text":[{ens:stylens, en:"tab-stop", ans:stylens, a:"leader-text-style"}, {ens:stylens, en:"drop-cap", ans:stylens, a:"style-name"}, {ens:textns, en:"notes-configuration", ans:textns, a:"citation-body-style-name"}, {ens:textns, en:"notes-configuration", 
  ans:textns, a:"citation-style-name"}, {ens:textns, en:"a", ans:textns, a:"style-name"}, {ens:textns, en:"alphabetical-index", ans:textns, a:"style-name"}, {ens:textns, en:"linenumbering-configuration", ans:textns, a:"style-name"}, {ens:textns, en:"list-level-style-number", ans:textns, a:"style-name"}, {ens:textns, en:"ruby-text", ans:textns, a:"style-name"}, {ens:textns, en:"span", ans:textns, a:"style-name"}, {ens:textns, en:"a", ans:textns, a:"visited-style-name"}, {ens:stylens, en:"text-properties", 
  ans:stylens, a:"text-line-through-text-style"}, {ens:textns, en:"alphabetical-index-source", ans:textns, a:"main-entry-style-name"}, {ens:textns, en:"index-entry-bibliography", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-chapter", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-link-end", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-link-start", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-page-number", ans:textns, a:"style-name"}, {ens:textns, 
  en:"index-entry-span", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-tab-stop", ans:textns, a:"style-name"}, {ens:textns, en:"index-entry-text", ans:textns, a:"style-name"}, {ens:textns, en:"index-title-template", ans:textns, a:"style-name"}, {ens:textns, en:"list-level-style-bullet", ans:textns, a:"style-name"}, {ens:textns, en:"outline-level-style", ans:textns, a:"style-name"}], "paragraph":[{ens:drawns, en:"caption", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"circle", ans:drawns, 
  a:"text-style-name"}, {ens:drawns, en:"connector", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"control", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"custom-shape", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"ellipse", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"frame", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"line", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"measure", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"path", ans:drawns, a:"text-style-name"}, 
  {ens:drawns, en:"polygon", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"polyline", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"rect", ans:drawns, a:"text-style-name"}, {ens:drawns, en:"regular-polygon", ans:drawns, a:"text-style-name"}, {ens:officens, en:"annotation", ans:drawns, a:"text-style-name"}, {ens:formns, en:"column", ans:formns, a:"text-style-name"}, {ens:stylens, en:"style", ans:stylens, a:"next-style-name"}, {ens:tablens, en:"body", ans:tablens, a:"paragraph-style-name"}, 
  {ens:tablens, en:"even-columns", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"even-rows", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"first-column", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"first-row", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"last-column", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"last-row", ans:tablens, a:"paragraph-style-name"}, {ens:tablens, en:"odd-columns", ans:tablens, a:"paragraph-style-name"}, 
  {ens:tablens, en:"odd-rows", ans:tablens, a:"paragraph-style-name"}, {ens:textns, en:"notes-configuration", ans:textns, a:"default-style-name"}, {ens:textns, en:"alphabetical-index-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"bibliography-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"h", ans:textns, a:"style-name"}, {ens:textns, en:"illustration-index-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"index-source-style", ans:textns, a:"style-name"}, 
  {ens:textns, en:"object-index-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"p", ans:textns, a:"style-name"}, {ens:textns, en:"table-index-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"table-of-content-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"table-index-entry-template", ans:textns, a:"style-name"}, {ens:textns, en:"user-index-entry-template", ans:textns, a:"style-name"}, {ens:stylens, en:"page-layout-properties", ans:stylens, a:"register-truth-ref-style-name"}], 
  "chart":[{ens:chartns, en:"axis", ans:chartns, a:"style-name"}, {ens:chartns, en:"chart", ans:chartns, a:"style-name"}, {ens:chartns, en:"data-label", ans:chartns, a:"style-name"}, {ens:chartns, en:"data-point", ans:chartns, a:"style-name"}, {ens:chartns, en:"equation", ans:chartns, a:"style-name"}, {ens:chartns, en:"error-indicator", ans:chartns, a:"style-name"}, {ens:chartns, en:"floor", ans:chartns, a:"style-name"}, {ens:chartns, en:"footer", ans:chartns, a:"style-name"}, {ens:chartns, en:"grid", 
  ans:chartns, a:"style-name"}, {ens:chartns, en:"legend", ans:chartns, a:"style-name"}, {ens:chartns, en:"mean-value", ans:chartns, a:"style-name"}, {ens:chartns, en:"plot-area", ans:chartns, a:"style-name"}, {ens:chartns, en:"regression-curve", ans:chartns, a:"style-name"}, {ens:chartns, en:"series", ans:chartns, a:"style-name"}, {ens:chartns, en:"stock-gain-marker", ans:chartns, a:"style-name"}, {ens:chartns, en:"stock-loss-marker", ans:chartns, a:"style-name"}, {ens:chartns, en:"stock-range-line", 
  ans:chartns, a:"style-name"}, {ens:chartns, en:"subtitle", ans:chartns, a:"style-name"}, {ens:chartns, en:"title", ans:chartns, a:"style-name"}, {ens:chartns, en:"wall", ans:chartns, a:"style-name"}], "section":[{ens:textns, en:"alphabetical-index", ans:textns, a:"style-name"}, {ens:textns, en:"bibliography", ans:textns, a:"style-name"}, {ens:textns, en:"illustration-index", ans:textns, a:"style-name"}, {ens:textns, en:"index-title", ans:textns, a:"style-name"}, {ens:textns, en:"object-index", 
  ans:textns, a:"style-name"}, {ens:textns, en:"section", ans:textns, a:"style-name"}, {ens:textns, en:"table-of-content", ans:textns, a:"style-name"}, {ens:textns, en:"table-index", ans:textns, a:"style-name"}, {ens:textns, en:"user-index", ans:textns, a:"style-name"}], "ruby":[{ens:textns, en:"ruby", ans:textns, a:"style-name"}], "table":[{ens:dbns, en:"query", ans:dbns, a:"style-name"}, {ens:dbns, en:"table-representation", ans:dbns, a:"style-name"}, {ens:tablens, en:"background", ans:tablens, 
  a:"style-name"}, {ens:tablens, en:"table", ans:tablens, a:"style-name"}], "table-column":[{ens:dbns, en:"column", ans:dbns, a:"style-name"}, {ens:tablens, en:"table-column", ans:tablens, a:"style-name"}], "table-row":[{ens:dbns, en:"query", ans:dbns, a:"default-row-style-name"}, {ens:dbns, en:"table-representation", ans:dbns, a:"default-row-style-name"}, {ens:tablens, en:"table-row", ans:tablens, a:"style-name"}], "table-cell":[{ens:dbns, en:"column", ans:dbns, a:"default-cell-style-name"}, {ens:tablens, 
  en:"table-column", ans:tablens, a:"default-cell-style-name"}, {ens:tablens, en:"table-row", ans:tablens, a:"default-cell-style-name"}, {ens:tablens, en:"body", ans:tablens, a:"style-name"}, {ens:tablens, en:"covered-table-cell", ans:tablens, a:"style-name"}, {ens:tablens, en:"even-columns", ans:tablens, a:"style-name"}, {ens:tablens, en:"covered-table-cell", ans:tablens, a:"style-name"}, {ens:tablens, en:"even-columns", ans:tablens, a:"style-name"}, {ens:tablens, en:"even-rows", ans:tablens, a:"style-name"}, 
  {ens:tablens, en:"first-column", ans:tablens, a:"style-name"}, {ens:tablens, en:"first-row", ans:tablens, a:"style-name"}, {ens:tablens, en:"last-column", ans:tablens, a:"style-name"}, {ens:tablens, en:"last-row", ans:tablens, a:"style-name"}, {ens:tablens, en:"odd-columns", ans:tablens, a:"style-name"}, {ens:tablens, en:"odd-rows", ans:tablens, a:"style-name"}, {ens:tablens, en:"table-cell", ans:tablens, a:"style-name"}], "graphic":[{ens:dr3dns, en:"cube", ans:drawns, a:"style-name"}, {ens:dr3dns, 
  en:"extrude", ans:drawns, a:"style-name"}, {ens:dr3dns, en:"rotate", ans:drawns, a:"style-name"}, {ens:dr3dns, en:"scene", ans:drawns, a:"style-name"}, {ens:dr3dns, en:"sphere", ans:drawns, a:"style-name"}, {ens:drawns, en:"caption", ans:drawns, a:"style-name"}, {ens:drawns, en:"circle", ans:drawns, a:"style-name"}, {ens:drawns, en:"connector", ans:drawns, a:"style-name"}, {ens:drawns, en:"control", ans:drawns, a:"style-name"}, {ens:drawns, en:"custom-shape", ans:drawns, a:"style-name"}, {ens:drawns, 
  en:"ellipse", ans:drawns, a:"style-name"}, {ens:drawns, en:"frame", ans:drawns, a:"style-name"}, {ens:drawns, en:"g", ans:drawns, a:"style-name"}, {ens:drawns, en:"line", ans:drawns, a:"style-name"}, {ens:drawns, en:"measure", ans:drawns, a:"style-name"}, {ens:drawns, en:"page-thumbnail", ans:drawns, a:"style-name"}, {ens:drawns, en:"path", ans:drawns, a:"style-name"}, {ens:drawns, en:"polygon", ans:drawns, a:"style-name"}, {ens:drawns, en:"polyline", ans:drawns, a:"style-name"}, {ens:drawns, en:"rect", 
  ans:drawns, a:"style-name"}, {ens:drawns, en:"regular-polygon", ans:drawns, a:"style-name"}, {ens:officens, en:"annotation", ans:drawns, a:"style-name"}], "presentation":[{ens:dr3dns, en:"cube", ans:presentationns, a:"style-name"}, {ens:dr3dns, en:"extrude", ans:presentationns, a:"style-name"}, {ens:dr3dns, en:"rotate", ans:presentationns, a:"style-name"}, {ens:dr3dns, en:"scene", ans:presentationns, a:"style-name"}, {ens:dr3dns, en:"sphere", ans:presentationns, a:"style-name"}, {ens:drawns, en:"caption", 
  ans:presentationns, a:"style-name"}, {ens:drawns, en:"circle", ans:presentationns, a:"style-name"}, {ens:drawns, en:"connector", ans:presentationns, a:"style-name"}, {ens:drawns, en:"control", ans:presentationns, a:"style-name"}, {ens:drawns, en:"custom-shape", ans:presentationns, a:"style-name"}, {ens:drawns, en:"ellipse", ans:presentationns, a:"style-name"}, {ens:drawns, en:"frame", ans:presentationns, a:"style-name"}, {ens:drawns, en:"g", ans:presentationns, a:"style-name"}, {ens:drawns, en:"line", 
  ans:presentationns, a:"style-name"}, {ens:drawns, en:"measure", ans:presentationns, a:"style-name"}, {ens:drawns, en:"page-thumbnail", ans:presentationns, a:"style-name"}, {ens:drawns, en:"path", ans:presentationns, a:"style-name"}, {ens:drawns, en:"polygon", ans:presentationns, a:"style-name"}, {ens:drawns, en:"polyline", ans:presentationns, a:"style-name"}, {ens:drawns, en:"rect", ans:presentationns, a:"style-name"}, {ens:drawns, en:"regular-polygon", ans:presentationns, a:"style-name"}, {ens:officens, 
  en:"annotation", ans:presentationns, a:"style-name"}], "drawing-page":[{ens:drawns, en:"page", ans:drawns, a:"style-name"}, {ens:presentationns, en:"notes", ans:drawns, a:"style-name"}, {ens:stylens, en:"handout-master", ans:drawns, a:"style-name"}, {ens:stylens, en:"master-page", ans:drawns, a:"style-name"}], "list-style":[{ens:textns, en:"list", ans:textns, a:"style-name"}, {ens:textns, en:"numbered-paragraph", ans:textns, a:"style-name"}, {ens:textns, en:"list-item", ans:textns, a:"style-override"}, 
  {ens:stylens, en:"style", ans:stylens, a:"list-style-name"}], "data":[{ens:stylens, en:"style", ans:stylens, a:"data-style-name"}, {ens:stylens, en:"style", ans:stylens, a:"percentage-data-style-name"}, {ens:presentationns, en:"date-time-decl", ans:stylens, a:"data-style-name"}, {ens:textns, en:"creation-date", ans:stylens, a:"data-style-name"}, {ens:textns, en:"creation-time", ans:stylens, a:"data-style-name"}, {ens:textns, en:"database-display", ans:stylens, a:"data-style-name"}, {ens:textns, 
  en:"date", ans:stylens, a:"data-style-name"}, {ens:textns, en:"editing-duration", ans:stylens, a:"data-style-name"}, {ens:textns, en:"expression", ans:stylens, a:"data-style-name"}, {ens:textns, en:"meta-field", ans:stylens, a:"data-style-name"}, {ens:textns, en:"modification-date", ans:stylens, a:"data-style-name"}, {ens:textns, en:"modification-time", ans:stylens, a:"data-style-name"}, {ens:textns, en:"print-date", ans:stylens, a:"data-style-name"}, {ens:textns, en:"print-time", ans:stylens, 
  a:"data-style-name"}, {ens:textns, en:"table-formula", ans:stylens, a:"data-style-name"}, {ens:textns, en:"time", ans:stylens, a:"data-style-name"}, {ens:textns, en:"user-defined", ans:stylens, a:"data-style-name"}, {ens:textns, en:"user-field-get", ans:stylens, a:"data-style-name"}, {ens:textns, en:"user-field-input", ans:stylens, a:"data-style-name"}, {ens:textns, en:"variable-get", ans:stylens, a:"data-style-name"}, {ens:textns, en:"variable-input", ans:stylens, a:"data-style-name"}, {ens:textns, 
  en:"variable-set", ans:stylens, a:"data-style-name"}], "page-layout":[{ens:presentationns, en:"notes", ans:stylens, a:"page-layout-name"}, {ens:stylens, en:"handout-master", ans:stylens, a:"page-layout-name"}, {ens:stylens, en:"master-page", ans:stylens, a:"page-layout-name"}]}, elements, xpath = xmldom.XPath;
  function hasDerivedStyles(odfbody, nsResolver, styleElement) {
    var nodes, xp, styleName = styleElement.getAttributeNS(stylens, "name"), styleFamily = styleElement.getAttributeNS(stylens, "family");
    xp = '//style:*[@style:parent-style-name="' + styleName + '"][@style:family="' + styleFamily + '"]';
    nodes = xpath.getODFElementsWithXPath(odfbody, xp, nsResolver);
    if (nodes.length) {
      return true;
    }
    return false;
  }
  function prefixUsedStyleNames(element, prefix) {
    var i, stylename, a, e, ns, elname, elns, localName, length = 0;
    elname = elements[element.localName];
    if (elname) {
      elns = elname[element.namespaceURI];
      if (elns) {
        length = elns.length;
      }
    }
    for (i = 0;i < length;i += 1) {
      a = elns[i];
      ns = a.ns;
      localName = a.localname;
      stylename = element.getAttributeNS(ns, localName);
      if (stylename) {
        element.setAttributeNS(ns, nsprefixes[ns] + localName, prefix + stylename);
      }
    }
    e = element.firstElementChild;
    while (e) {
      prefixUsedStyleNames(e, prefix);
      e = e.nextElementSibling;
    }
  }
  function prefixStyleName(styleElement, prefix) {
    var stylename = styleElement.getAttributeNS(drawns, "name"), ns;
    if (stylename) {
      ns = drawns;
    } else {
      stylename = styleElement.getAttributeNS(stylens, "name");
      if (stylename) {
        ns = stylens;
      }
    }
    if (ns) {
      styleElement.setAttributeNS(ns, nsprefixes[ns] + "name", prefix + stylename);
    }
  }
  function prefixStyleNames(styleElementsRoot, prefix, styleUsingElementsRoot) {
    var s;
    if (styleElementsRoot) {
      s = styleElementsRoot.firstChild;
      while (s) {
        if (s.nodeType === Node.ELEMENT_NODE) {
          prefixStyleName(s, prefix);
        }
        s = s.nextSibling;
      }
      prefixUsedStyleNames(styleElementsRoot, prefix);
      if (styleUsingElementsRoot) {
        prefixUsedStyleNames(styleUsingElementsRoot, prefix);
      }
    }
  }
  function removeRegExpFromUsedStyleNames(element, regExp) {
    var i, stylename, e, elname, elns, a, ns, localName, length = 0;
    elname = elements[element.localName];
    if (elname) {
      elns = elname[element.namespaceURI];
      if (elns) {
        length = elns.length;
      }
    }
    for (i = 0;i < length;i += 1) {
      a = elns[i];
      ns = a.ns;
      localName = a.localname;
      stylename = element.getAttributeNS(ns, localName);
      if (stylename) {
        stylename = stylename.replace(regExp, "");
        element.setAttributeNS(ns, nsprefixes[ns] + localName, stylename);
      }
    }
    e = element.firstElementChild;
    while (e) {
      removeRegExpFromUsedStyleNames(e, regExp);
      e = e.nextElementSibling;
    }
  }
  function removeRegExpFromStyleName(styleElement, regExp) {
    var stylename = styleElement.getAttributeNS(drawns, "name"), ns;
    if (stylename) {
      ns = drawns;
    } else {
      stylename = styleElement.getAttributeNS(stylens, "name");
      if (stylename) {
        ns = stylens;
      }
    }
    if (ns) {
      stylename = stylename.replace(regExp, "");
      styleElement.setAttributeNS(ns, nsprefixes[ns] + "name", stylename);
    }
  }
  function removePrefixFromStyleNames(styleElementsRoot, prefix, styleUsingElementsRoot) {
    var s, regExp = new RegExp("^" + prefix);
    if (styleElementsRoot) {
      s = styleElementsRoot.firstChild;
      while (s) {
        if (s.nodeType === Node.ELEMENT_NODE) {
          removeRegExpFromStyleName(s, regExp);
        }
        s = s.nextSibling;
      }
      removeRegExpFromUsedStyleNames(styleElementsRoot, regExp);
      if (styleUsingElementsRoot) {
        removeRegExpFromUsedStyleNames(styleUsingElementsRoot, regExp);
      }
    }
  }
  function determineStylesForNode(element, usedStyles) {
    var i, stylename, elname, elns, a, ns, localName, keyname, length = 0, map;
    elname = elements[element.localName];
    if (elname) {
      elns = elname[element.namespaceURI];
      if (elns) {
        length = elns.length;
      }
    }
    for (i = 0;i < length;i += 1) {
      a = elns[i];
      ns = a.ns;
      localName = a.localname;
      stylename = element.getAttributeNS(ns, localName);
      if (stylename) {
        usedStyles = usedStyles || {};
        keyname = a.keyname;
        if (usedStyles.hasOwnProperty(keyname)) {
          usedStyles[keyname][stylename] = 1;
        } else {
          map = {};
          map[stylename] = 1;
          usedStyles[keyname] = map;
        }
      }
    }
    return usedStyles;
  }
  function determineUsedStyles(styleUsingElementsRoot, usedStyles) {
    var i, e;
    determineStylesForNode(styleUsingElementsRoot, usedStyles);
    i = styleUsingElementsRoot.firstChild;
    while (i) {
      if (i.nodeType === Node.ELEMENT_NODE) {
        e = i;
        determineUsedStyles(e, usedStyles);
      }
      i = i.nextSibling;
    }
  }
  function StyleDefinition(key, name, family) {
    this.key = key;
    this.name = name;
    this.family = family;
    this.requires = {};
  }
  function getStyleDefinition(stylename, stylefamily, knownStyles) {
    var styleKey = stylename + '"' + stylefamily, styleDefinition = knownStyles[styleKey];
    if (!styleDefinition) {
      styleDefinition = knownStyles[styleKey] = new StyleDefinition(styleKey, stylename, stylefamily);
    }
    return styleDefinition;
  }
  function determineDependentStyles(element, styleScope, knownStyles) {
    var i, stylename, elname, elns, a, ns, localName, e, referencedStyleFamily, referencedStyleDef, length = 0, newScopeName = element.getAttributeNS(stylens, "name"), newScopeFamily = element.getAttributeNS(stylens, "family");
    if (newScopeName && newScopeFamily) {
      styleScope = getStyleDefinition(newScopeName, newScopeFamily, knownStyles);
    }
    if (styleScope) {
      elname = elements[element.localName];
      if (elname) {
        elns = elname[element.namespaceURI];
        if (elns) {
          length = elns.length;
        }
      }
      for (i = 0;i < length;i += 1) {
        a = elns[i];
        ns = a.ns;
        localName = a.localname;
        stylename = element.getAttributeNS(ns, localName);
        if (stylename) {
          referencedStyleFamily = a.keyname;
          referencedStyleDef = getStyleDefinition(stylename, referencedStyleFamily, knownStyles);
          styleScope.requires[referencedStyleDef.key] = referencedStyleDef;
        }
      }
    }
    e = element.firstElementChild;
    while (e) {
      determineDependentStyles(e, styleScope, knownStyles);
      e = e.nextElementSibling;
    }
    return knownStyles;
  }
  function inverse() {
    var i, l, keyname, list, item, e = {}, map, array, en, ens;
    for (keyname in elementstyles) {
      if (elementstyles.hasOwnProperty(keyname)) {
        list = elementstyles[keyname];
        l = list.length;
        for (i = 0;i < l;i += 1) {
          item = list[i];
          en = item.en;
          ens = item.ens;
          if (e.hasOwnProperty(en)) {
            map = e[en];
          } else {
            e[en] = map = {};
          }
          if (map.hasOwnProperty(ens)) {
            array = map[ens];
          } else {
            map[ens] = array = [];
          }
          array.push({ns:item.ans, localname:item.a, keyname:keyname});
        }
      }
    }
    return e;
  }
  function mergeRequiredStyles(styleDependency, usedStyles) {
    var family = usedStyles[styleDependency.family];
    if (!family) {
      family = usedStyles[styleDependency.family] = {};
    }
    family[styleDependency.name] = 1;
    Object.keys(styleDependency.requires).forEach(function(requiredStyleKey) {
      mergeRequiredStyles(styleDependency.requires[requiredStyleKey], usedStyles);
    });
  }
  function mergeUsedAutomaticStyles(automaticStylesRoot, usedStyles) {
    var automaticStyles = determineDependentStyles(automaticStylesRoot, null, {});
    Object.keys(automaticStyles).forEach(function(styleKey) {
      var automaticStyleDefinition = automaticStyles[styleKey], usedFamily = usedStyles[automaticStyleDefinition.family];
      if (usedFamily && usedFamily.hasOwnProperty(automaticStyleDefinition.name)) {
        mergeRequiredStyles(automaticStyleDefinition, usedStyles);
      }
    });
  }
  function collectUsedFontFaces(usedFontFaceDeclMap, styleElement) {
    var localNames = ["font-name", "font-name-asian", "font-name-complex"], e, currentElement;
    function collectByAttribute(localName) {
      var fontFaceName = currentElement.getAttributeNS(stylens, localName);
      if (fontFaceName) {
        usedFontFaceDeclMap[fontFaceName] = true;
      }
    }
    e = styleElement && styleElement.firstElementChild;
    while (e) {
      currentElement = e;
      localNames.forEach(collectByAttribute);
      collectUsedFontFaces(usedFontFaceDeclMap, currentElement);
      e = e.nextElementSibling;
    }
  }
  this.collectUsedFontFaces = collectUsedFontFaces;
  function changeFontFaceNames(styleElement, fontFaceNameChangeMap) {
    var localNames = ["font-name", "font-name-asian", "font-name-complex"], e, currentElement;
    function changeFontFaceNameByAttribute(localName) {
      var fontFaceName = currentElement.getAttributeNS(stylens, localName);
      if (fontFaceName && fontFaceNameChangeMap.hasOwnProperty(fontFaceName)) {
        currentElement.setAttributeNS(stylens, "style:" + localName, fontFaceNameChangeMap[fontFaceName]);
      }
    }
    e = styleElement && styleElement.firstElementChild;
    while (e) {
      currentElement = e;
      localNames.forEach(changeFontFaceNameByAttribute);
      changeFontFaceNames(currentElement, fontFaceNameChangeMap);
      e = e.nextElementSibling;
    }
  }
  this.changeFontFaceNames = changeFontFaceNames;
  this.UsedStyleList = function(styleUsingElementsRoot, automaticStylesRoot) {
    var usedStyles = {};
    this.uses = function(element) {
      var localName = element.localName, name = element.getAttributeNS(drawns, "name") || element.getAttributeNS(stylens, "name"), keyName, map;
      if (localName === "style") {
        keyName = element.getAttributeNS(stylens, "family");
      } else {
        if (element.namespaceURI === numberns) {
          keyName = "data";
        } else {
          keyName = localName;
        }
      }
      map = usedStyles[keyName];
      return map ? map[name] > 0 : false;
    };
    determineUsedStyles(styleUsingElementsRoot, usedStyles);
    if (automaticStylesRoot) {
      mergeUsedAutomaticStyles(automaticStylesRoot, usedStyles);
    }
  };
  function getStyleName(family, element) {
    var stylename, i, map = elements[element.localName];
    if (map) {
      map = map[element.namespaceURI];
      if (map) {
        for (i = 0;i < map.length;i += 1) {
          if (map[i].keyname === family) {
            map = map[i];
            if (element.hasAttributeNS(map.ns, map.localname)) {
              stylename = element.getAttributeNS(map.ns, map.localname);
              break;
            }
          }
        }
      }
    }
    return stylename;
  }
  this.getStyleName = getStyleName;
  this.hasDerivedStyles = hasDerivedStyles;
  this.prefixStyleNames = prefixStyleNames;
  this.removePrefixFromStyleNames = removePrefixFromStyleNames;
  this.determineStylesForNode = determineStylesForNode;
  elements = inverse();
};
if (typeof Object.create !== "function") {
  Object["create"] = function(o) {
    var F = function() {
    };
    F.prototype = o;
    return new F;
  };
}
xmldom.LSSerializer = function LSSerializer() {
  var self = this;
  function Namespaces(nsmap) {
    function invertMap(map) {
      var m = {}, i;
      for (i in map) {
        if (map.hasOwnProperty(i)) {
          m[map[i]] = i;
        }
      }
      return m;
    }
    var current = nsmap || {}, currentrev = invertMap(nsmap), levels = [current], levelsrev = [currentrev], level = 0;
    this.push = function() {
      level += 1;
      current = levels[level] = Object.create(current);
      currentrev = levelsrev[level] = Object.create(currentrev);
    };
    this.pop = function() {
      levels.pop();
      levelsrev.pop();
      level -= 1;
      current = levels[level];
      currentrev = levelsrev[level];
    };
    this.getLocalNamespaceDefinitions = function() {
      return currentrev;
    };
    this.getQName = function(node) {
      var ns = node.namespaceURI, i = 0, p;
      if (!ns) {
        return node.localName;
      }
      p = currentrev[ns];
      if (p) {
        return p + ":" + node.localName;
      }
      do {
        if (p || !node.prefix) {
          p = "ns" + i;
          i += 1;
        } else {
          p = node.prefix;
        }
        if (current[p] === ns) {
          break;
        }
        if (!current[p]) {
          current[p] = ns;
          currentrev[ns] = p;
          break;
        }
        p = null;
      } while (p === null);
      return p + ":" + node.localName;
    };
  }
  function escapeContent(value) {
    return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&apos;").replace(/"/g, "&quot;");
  }
  function serializeAttribute(qname, attr) {
    var escapedValue = typeof attr.value === "string" ? escapeContent(attr.value) : attr.value, s = qname + '="' + escapedValue + '"';
    return s;
  }
  function startElement(ns, qname, element) {
    var s = "", atts = element.attributes, length, i, attr, attstr = "", accept, prefix, nsmap;
    s += "<" + qname;
    length = atts.length;
    for (i = 0;i < length;i += 1) {
      attr = atts.item(i);
      if (attr.namespaceURI !== "http://www.w3.org/2000/xmlns/") {
        accept = self.filter ? self.filter.acceptNode(attr) : NodeFilter.FILTER_ACCEPT;
        if (accept === NodeFilter.FILTER_ACCEPT) {
          attstr += " " + serializeAttribute(ns.getQName(attr), attr);
        }
      }
    }
    nsmap = ns.getLocalNamespaceDefinitions();
    for (i in nsmap) {
      if (nsmap.hasOwnProperty(i)) {
        prefix = nsmap[i];
        if (!prefix) {
          s += ' xmlns="' + i + '"';
        } else {
          if (prefix !== "xmlns") {
            s += " xmlns:" + nsmap[i] + '="' + i + '"';
          }
        }
      }
    }
    s += attstr + ">";
    return s;
  }
  function serializeNode(ns, node) {
    var s = "", accept = self.filter ? self.filter.acceptNode(node) : NodeFilter.FILTER_ACCEPT, child, qname;
    if (accept === NodeFilter.FILTER_ACCEPT && node.nodeType === Node.ELEMENT_NODE) {
      ns.push();
      qname = ns.getQName(node);
      s += startElement(ns, qname, node);
    }
    if (accept === NodeFilter.FILTER_ACCEPT || accept === NodeFilter.FILTER_SKIP) {
      child = node.firstChild;
      while (child) {
        s += serializeNode(ns, child);
        child = child.nextSibling;
      }
      if (node.nodeValue) {
        s += escapeContent(node.nodeValue);
      }
    }
    if (qname) {
      s += "</" + qname + ">";
      ns.pop();
    }
    return s;
  }
  this.filter = null;
  this.writeToString = function(node, nsmap) {
    if (!node) {
      return "";
    }
    var ns = new Namespaces(nsmap);
    return serializeNode(ns, node);
  };
};
(function() {
  var styleInfo = new odf.StyleInfo, domUtils = core.DomUtils, officens = "urn:oasis:names:tc:opendocument:xmlns:office:1.0", manifestns = "urn:oasis:names:tc:opendocument:xmlns:manifest:1.0", webodfns = "urn:webodf:names:scope", stylens = odf.Namespaces.stylens, nodeorder = ["meta", "settings", "scripts", "font-face-decls", "styles", "automatic-styles", "master-styles", "body"], automaticStylePrefix = Date.now() + "_webodf_", base64 = new core.Base64, documentStylesScope = "document-styles", documentContentScope = 
  "document-content";
  function getNodePosition(child) {
    var i, l = nodeorder.length;
    for (i = 0;i < l;i += 1) {
      if (child.namespaceURI === officens && child.localName === nodeorder[i]) {
        return i;
      }
    }
    return -1;
  }
  function OdfStylesFilter(styleUsingElementsRoot, automaticStyles) {
    var usedStyleList = new styleInfo.UsedStyleList(styleUsingElementsRoot, automaticStyles), odfNodeFilter = new odf.OdfNodeFilter;
    this.acceptNode = function(node) {
      var result = odfNodeFilter.acceptNode(node);
      if (result === NodeFilter.FILTER_ACCEPT && node.parentNode === automaticStyles && node.nodeType === Node.ELEMENT_NODE) {
        if (usedStyleList.uses(node)) {
          result = NodeFilter.FILTER_ACCEPT;
        } else {
          result = NodeFilter.FILTER_REJECT;
        }
      }
      return result;
    };
  }
  function OdfContentFilter(styleUsingElementsRoot, automaticStyles) {
    var odfStylesFilter = new OdfStylesFilter(styleUsingElementsRoot, automaticStyles);
    this.acceptNode = function(node) {
      var result = odfStylesFilter.acceptNode(node);
      if (result === NodeFilter.FILTER_ACCEPT && node.parentNode && node.parentNode.namespaceURI === odf.Namespaces.textns && (node.parentNode.localName === "s" || node.parentNode.localName === "tab")) {
        result = NodeFilter.FILTER_REJECT;
      }
      return result;
    };
  }
  function setChild(node, child) {
    if (!child) {
      return;
    }
    var childpos = getNodePosition(child), pos, c = node.firstChild;
    if (childpos === -1) {
      return;
    }
    while (c) {
      pos = getNodePosition(c);
      if (pos !== -1 && pos > childpos) {
        break;
      }
      c = c.nextSibling;
    }
    node.insertBefore(child, c);
  }
  odf.ODFElement = function ODFElement() {
  };
  odf.ODFDocumentElement = function ODFDocumentElement() {
  };
  odf.ODFDocumentElement.prototype = new odf.ODFElement;
  odf.ODFDocumentElement.prototype.constructor = odf.ODFDocumentElement;
  odf.ODFDocumentElement.prototype.automaticStyles;
  odf.ODFDocumentElement.prototype.body;
  odf.ODFDocumentElement.prototype.fontFaceDecls = null;
  odf.ODFDocumentElement.prototype.manifest = null;
  odf.ODFDocumentElement.prototype.masterStyles;
  odf.ODFDocumentElement.prototype.meta;
  odf.ODFDocumentElement.prototype.settings = null;
  odf.ODFDocumentElement.prototype.styles;
  odf.ODFDocumentElement.namespaceURI = officens;
  odf.ODFDocumentElement.localName = "document";
  odf.AnnotationElement = function AnnotationElement() {
  };
  odf.AnnotationElement.prototype.annotationEndElement;
  odf.OdfPart = function OdfPart(name, mimetype, container, zip) {
    var self = this;
    this.size = 0;
    this.type = null;
    this.name = name;
    this.container = container;
    this.url = null;
    this.mimetype = mimetype;
    this.document = null;
    this.onstatereadychange = null;
    this.onchange;
    this.EMPTY = 0;
    this.LOADING = 1;
    this.DONE = 2;
    this.state = this.EMPTY;
    this.data = "";
    this.load = function() {
      if (zip === null) {
        return;
      }
      this.mimetype = mimetype;
      zip.loadAsDataURL(name, mimetype, function(err, url) {
        if (err) {
          runtime.log(err);
        }
        self.url = url;
        if (self.onchange) {
          self.onchange(self);
        }
        if (self.onstatereadychange) {
          self.onstatereadychange(self);
        }
      });
    };
  };
  odf.OdfPart.prototype.load = function() {
  };
  odf.OdfPart.prototype.getUrl = function() {
    if (this.data) {
      return "data:;base64," + base64.toBase64(this.data);
    }
    return null;
  };
  odf.OdfContainer = function OdfContainer(urlOrType, onstatereadychange) {
    var self = this, zip, partMimetypes = {}, contentElement, url = "";
    this.onstatereadychange = onstatereadychange;
    this.onchange = null;
    this.state = null;
    this.rootElement;
    function removeProcessingInstructions(element) {
      var n = element.firstChild, next, e;
      while (n) {
        next = n.nextSibling;
        if (n.nodeType === Node.ELEMENT_NODE) {
          e = n;
          removeProcessingInstructions(e);
        } else {
          if (n.nodeType === Node.PROCESSING_INSTRUCTION_NODE) {
            element.removeChild(n);
          }
        }
        n = next;
      }
    }
    function linkAnnotationStartAndEndElements(rootElement) {
      var document = rootElement.ownerDocument, annotationStarts = {}, n, name, annotationStart, nodeIterator = document.createNodeIterator(rootElement, NodeFilter.SHOW_ELEMENT, null, false);
      n = nodeIterator.nextNode();
      while (n) {
        if (n.namespaceURI === officens) {
          if (n.localName === "annotation") {
            name = n.getAttributeNS(officens, "name");
            if (name) {
              if (annotationStarts.hasOwnProperty(name)) {
                runtime.log("Warning: annotation name used more than once with <office:annotation/>: '" + name + "'");
              } else {
                annotationStarts[name] = n;
              }
            }
          } else {
            if (n.localName === "annotation-end") {
              name = n.getAttributeNS(officens, "name");
              if (name) {
                if (annotationStarts.hasOwnProperty(name)) {
                  annotationStart = annotationStarts[name];
                  if (!annotationStart.annotationEndElement) {
                    annotationStart.annotationEndElement = n;
                  } else {
                    runtime.log("Warning: annotation name used more than once with <office:annotation-end/>: '" + name + "'");
                  }
                } else {
                  runtime.log("Warning: annotation end without an annotation start, name: '" + name + "'");
                }
              } else {
                runtime.log("Warning: annotation end without a name found");
              }
            }
          }
        }
        n = nodeIterator.nextNode();
      }
    }
    function setAutomaticStylesScope(stylesRootElement, scope) {
      var n = stylesRootElement && stylesRootElement.firstChild;
      while (n) {
        if (n.nodeType === Node.ELEMENT_NODE) {
          n.setAttributeNS(webodfns, "scope", scope);
        }
        n = n.nextSibling;
      }
    }
    function getEnsuredMetaElement() {
      var root = self.rootElement, meta = root.meta;
      if (!meta) {
        root.meta = meta = document.createElementNS(officens, "meta");
        setChild(root, meta);
      }
      return meta;
    }
    function getMetadata(metadataNs, metadataLocalName) {
      var node = self.rootElement.meta, textNode;
      node = node && node.firstChild;
      while (node && (node.namespaceURI !== metadataNs || node.localName !== metadataLocalName)) {
        node = node.nextSibling;
      }
      node = node && node.firstChild;
      while (node && node.nodeType !== Node.TEXT_NODE) {
        node = node.nextSibling;
      }
      if (node) {
        textNode = node;
        return textNode.data;
      }
      return null;
    }
    this.getMetadata = getMetadata;
    function unusedKey(key, map1, map2) {
      var i = 0, postFixedKey;
      key = key.replace(/\d+$/, "");
      postFixedKey = key;
      while (map1.hasOwnProperty(postFixedKey) || map2.hasOwnProperty(postFixedKey)) {
        i += 1;
        postFixedKey = key + i;
      }
      return postFixedKey;
    }
    function mapByFontFaceName(fontFaceDecls) {
      var fn, result = {}, fontname;
      fn = fontFaceDecls.firstChild;
      while (fn) {
        if (fn.nodeType === Node.ELEMENT_NODE && fn.namespaceURI === stylens && fn.localName === "font-face") {
          fontname = fn.getAttributeNS(stylens, "name");
          result[fontname] = fn;
        }
        fn = fn.nextSibling;
      }
      return result;
    }
    function mergeFontFaceDecls(targetFontFaceDeclsRootElement, sourceFontFaceDeclsRootElement) {
      var e, s, fontFaceName, newFontFaceName, targetFontFaceDeclsMap, sourceFontFaceDeclsMap, fontFaceNameChangeMap = {};
      targetFontFaceDeclsMap = mapByFontFaceName(targetFontFaceDeclsRootElement);
      sourceFontFaceDeclsMap = mapByFontFaceName(sourceFontFaceDeclsRootElement);
      e = sourceFontFaceDeclsRootElement.firstElementChild;
      while (e) {
        s = e.nextElementSibling;
        if (e.namespaceURI === stylens && e.localName === "font-face") {
          fontFaceName = e.getAttributeNS(stylens, "name");
          if (targetFontFaceDeclsMap.hasOwnProperty(fontFaceName)) {
            if (!e.isEqualNode(targetFontFaceDeclsMap[fontFaceName])) {
              newFontFaceName = unusedKey(fontFaceName, targetFontFaceDeclsMap, sourceFontFaceDeclsMap);
              e.setAttributeNS(stylens, "style:name", newFontFaceName);
              targetFontFaceDeclsRootElement.appendChild(e);
              targetFontFaceDeclsMap[newFontFaceName] = e;
              delete sourceFontFaceDeclsMap[fontFaceName];
              fontFaceNameChangeMap[fontFaceName] = newFontFaceName;
            }
          } else {
            targetFontFaceDeclsRootElement.appendChild(e);
            targetFontFaceDeclsMap[fontFaceName] = e;
            delete sourceFontFaceDeclsMap[fontFaceName];
          }
        }
        e = s;
      }
      return fontFaceNameChangeMap;
    }
    function cloneStylesInScope(stylesRootElement, scope) {
      var copy = null, e, s, scopeAttrValue;
      if (stylesRootElement) {
        copy = stylesRootElement.cloneNode(true);
        e = copy.firstElementChild;
        while (e) {
          s = e.nextElementSibling;
          scopeAttrValue = e.getAttributeNS(webodfns, "scope");
          if (scopeAttrValue && scopeAttrValue !== scope) {
            copy.removeChild(e);
          }
          e = s;
        }
      }
      return copy;
    }
    function cloneFontFaceDeclsUsedInStyles(fontFaceDeclsRootElement, stylesRootElementList) {
      var e, nextSibling, fontFaceName, copy = null, usedFontFaceDeclMap = {};
      if (fontFaceDeclsRootElement) {
        stylesRootElementList.forEach(function(stylesRootElement) {
          styleInfo.collectUsedFontFaces(usedFontFaceDeclMap, stylesRootElement);
        });
        copy = fontFaceDeclsRootElement.cloneNode(true);
        e = copy.firstElementChild;
        while (e) {
          nextSibling = e.nextElementSibling;
          fontFaceName = e.getAttributeNS(stylens, "name");
          if (!usedFontFaceDeclMap[fontFaceName]) {
            copy.removeChild(e);
          }
          e = nextSibling;
        }
      }
      return copy;
    }
    function importRootNode(xmldoc) {
      var doc = self.rootElement.ownerDocument, node;
      if (xmldoc) {
        removeProcessingInstructions(xmldoc.documentElement);
        try {
          node = doc.importNode(xmldoc.documentElement, true);
        } catch (ignore) {
        }
      }
      return node;
    }
    function setState(state) {
      self.state = state;
      if (self.onchange) {
        self.onchange(self);
      }
      if (self.onstatereadychange) {
        self.onstatereadychange(self);
      }
    }
    function setRootElement(root) {
      contentElement = null;
      self.rootElement = root;
      root.fontFaceDecls = domUtils.getDirectChild(root, officens, "font-face-decls");
      root.styles = domUtils.getDirectChild(root, officens, "styles");
      root.automaticStyles = domUtils.getDirectChild(root, officens, "automatic-styles");
      root.masterStyles = domUtils.getDirectChild(root, officens, "master-styles");
      root.body = domUtils.getDirectChild(root, officens, "body");
      root.meta = domUtils.getDirectChild(root, officens, "meta");
      root.settings = domUtils.getDirectChild(root, officens, "settings");
      root.scripts = domUtils.getDirectChild(root, officens, "scripts");
      linkAnnotationStartAndEndElements(root);
    }
    function handleFlatXml(xmldoc) {
      var root = importRootNode(xmldoc);
      if (!root || root.localName !== "document" || root.namespaceURI !== officens) {
        setState(OdfContainer.INVALID);
        return;
      }
      setRootElement(root);
      setState(OdfContainer.DONE);
    }
    function handleStylesXml(xmldoc) {
      var node = importRootNode(xmldoc), root = self.rootElement, n;
      if (!node || node.localName !== "document-styles" || node.namespaceURI !== officens) {
        setState(OdfContainer.INVALID);
        return;
      }
      root.fontFaceDecls = domUtils.getDirectChild(node, officens, "font-face-decls");
      setChild(root, root.fontFaceDecls);
      n = domUtils.getDirectChild(node, officens, "styles");
      root.styles = n || xmldoc.createElementNS(officens, "styles");
      setChild(root, root.styles);
      n = domUtils.getDirectChild(node, officens, "automatic-styles");
      root.automaticStyles = n || xmldoc.createElementNS(officens, "automatic-styles");
      setAutomaticStylesScope(root.automaticStyles, documentStylesScope);
      setChild(root, root.automaticStyles);
      node = domUtils.getDirectChild(node, officens, "master-styles");
      root.masterStyles = node || xmldoc.createElementNS(officens, "master-styles");
      setChild(root, root.masterStyles);
      styleInfo.prefixStyleNames(root.automaticStyles, automaticStylePrefix, root.masterStyles);
    }
    function handleContentXml(xmldoc) {
      var node = importRootNode(xmldoc), root, automaticStyles, fontFaceDecls, fontFaceNameChangeMap, c;
      if (!node || node.localName !== "document-content" || node.namespaceURI !== officens) {
        setState(OdfContainer.INVALID);
        return;
      }
      root = self.rootElement;
      fontFaceDecls = domUtils.getDirectChild(node, officens, "font-face-decls");
      if (root.fontFaceDecls && fontFaceDecls) {
        fontFaceNameChangeMap = mergeFontFaceDecls(root.fontFaceDecls, fontFaceDecls);
      } else {
        if (fontFaceDecls) {
          root.fontFaceDecls = fontFaceDecls;
          setChild(root, fontFaceDecls);
        }
      }
      automaticStyles = domUtils.getDirectChild(node, officens, "automatic-styles");
      setAutomaticStylesScope(automaticStyles, documentContentScope);
      if (fontFaceNameChangeMap) {
        styleInfo.changeFontFaceNames(automaticStyles, fontFaceNameChangeMap);
      }
      if (root.automaticStyles && automaticStyles) {
        c = automaticStyles.firstChild;
        while (c) {
          root.automaticStyles.appendChild(c);
          c = automaticStyles.firstChild;
        }
      } else {
        if (automaticStyles) {
          root.automaticStyles = automaticStyles;
          setChild(root, automaticStyles);
        }
      }
      node = domUtils.getDirectChild(node, officens, "body");
      if (node === null) {
        throw "<office:body/> tag is mising.";
      }
      root.body = node;
      setChild(root, root.body);
    }
    function handleMetaXml(xmldoc) {
      var node = importRootNode(xmldoc), root;
      if (!node || node.localName !== "document-meta" || node.namespaceURI !== officens) {
        return;
      }
      root = self.rootElement;
      root.meta = domUtils.getDirectChild(node, officens, "meta");
      setChild(root, root.meta);
    }
    function handleSettingsXml(xmldoc) {
      var node = importRootNode(xmldoc), root;
      if (!node || node.localName !== "document-settings" || node.namespaceURI !== officens) {
        return;
      }
      root = self.rootElement;
      root.settings = domUtils.getDirectChild(node, officens, "settings");
      setChild(root, root.settings);
    }
    function handleManifestXml(xmldoc) {
      var node = importRootNode(xmldoc), root, e;
      if (!node || node.localName !== "manifest" || node.namespaceURI !== manifestns) {
        return;
      }
      root = self.rootElement;
      root.manifest = node;
      e = root.manifest.firstElementChild;
      while (e) {
        if (e.localName === "file-entry" && e.namespaceURI === manifestns) {
          partMimetypes[e.getAttributeNS(manifestns, "full-path")] = e.getAttributeNS(manifestns, "media-type");
        }
        e = e.nextElementSibling;
      }
    }
    function removeElements(xmldoc, localName, allowedNamespaces) {
      var elements = domUtils.getElementsByTagName(xmldoc, localName), element, i;
      for (i = 0;i < elements.length;i += 1) {
        element = elements[i];
        if (!allowedNamespaces.hasOwnProperty(element.namespaceURI)) {
          element.parentNode.removeChild(element);
        }
      }
    }
    function removeDangerousElements(xmldoc) {
      removeElements(xmldoc, "script", {"urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:office:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:table:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:text:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0":true});
      removeElements(xmldoc, "style", {"urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:drawing:1.0":true, "urn:oasis:names:tc:opendocument:xmlns:style:1.0":true});
    }
    function removeDangerousAttributes(element) {
      var e = element.firstElementChild, as = [], i, n, a, atts = element.attributes, l = atts.length;
      for (i = 0;i < l;i += 1) {
        a = atts.item(i);
        n = a.localName.substr(0, 2).toLowerCase();
        if (a.namespaceURI === null && n === "on") {
          as.push(a);
        }
      }
      l = as.length;
      for (i = 0;i < l;i += 1) {
        element.removeAttributeNode(as[i]);
      }
      while (e) {
        removeDangerousAttributes(e);
        e = e.nextElementSibling;
      }
    }
    function loadNextComponent(remainingComponents) {
      var component = remainingComponents.shift();
      if (component) {
        zip.loadAsDOM(component.path, function(err, xmldoc) {
          if (xmldoc) {
            removeDangerousElements(xmldoc);
            removeDangerousAttributes(xmldoc.documentElement);
          }
          component.handler(xmldoc);
          if (self.state === OdfContainer.INVALID) {
            if (err) {
              runtime.log("ERROR: Unable to load " + component.path + " - " + err);
            } else {
              runtime.log("ERROR: Unable to load " + component.path);
            }
            return;
          }
          if (err) {
            runtime.log("DEBUG: Unable to load " + component.path + " - " + err);
          }
          loadNextComponent(remainingComponents);
        });
      } else {
        linkAnnotationStartAndEndElements(self.rootElement);
        setState(OdfContainer.DONE);
      }
    }
    function loadComponents() {
      var componentOrder = [{path:"styles.xml", handler:handleStylesXml}, {path:"content.xml", handler:handleContentXml}, {path:"meta.xml", handler:handleMetaXml}, {path:"settings.xml", handler:handleSettingsXml}, {path:"META-INF/manifest.xml", handler:handleManifestXml}];
      loadNextComponent(componentOrder);
    }
    function createDocumentElement(name) {
      var s = "";
      function defineNamespace(prefix, ns) {
        s += " xmlns:" + prefix + '="' + ns + '"';
      }
      odf.Namespaces.forEachPrefix(defineNamespace);
      return '<?xml version="1.0" encoding="UTF-8"?><office:' + name + " " + s + ' office:version="1.2">';
    }
    function serializeMetaXml() {
      var serializer = new xmldom.LSSerializer, s = createDocumentElement("document-meta");
      serializer.filter = new odf.OdfNodeFilter;
      s += serializer.writeToString(self.rootElement.meta, odf.Namespaces.namespaceMap);
      s += "</office:document-meta>";
      return s;
    }
    function createManifestEntry(fullPath, mediaType) {
      var element = document.createElementNS(manifestns, "manifest:file-entry");
      element.setAttributeNS(manifestns, "manifest:full-path", fullPath);
      element.setAttributeNS(manifestns, "manifest:media-type", mediaType);
      return element;
    }
    function serializeManifestXml() {
      var header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n', xml = '<manifest:manifest xmlns:manifest="' + manifestns + '" manifest:version="1.2"></manifest:manifest>', manifest = runtime.parseXML(xml), manifestRoot = manifest.documentElement, serializer = new xmldom.LSSerializer, fullPath;
      for (fullPath in partMimetypes) {
        if (partMimetypes.hasOwnProperty(fullPath)) {
          manifestRoot.appendChild(createManifestEntry(fullPath, partMimetypes[fullPath]));
        }
      }
      serializer.filter = new odf.OdfNodeFilter;
      return header + serializer.writeToString(manifest, odf.Namespaces.namespaceMap);
    }
    function serializeSettingsXml() {
      var serializer, s = "";
      if (self.rootElement.settings && self.rootElement.settings.firstElementChild) {
        serializer = new xmldom.LSSerializer;
        s = createDocumentElement("document-settings");
        serializer.filter = new odf.OdfNodeFilter;
        s += serializer.writeToString(self.rootElement.settings, odf.Namespaces.namespaceMap);
        s += "</office:document-settings>";
      }
      return s;
    }
    function serializeStylesXml() {
      var fontFaceDecls, automaticStyles, masterStyles, nsmap = odf.Namespaces.namespaceMap, serializer = new xmldom.LSSerializer, s = createDocumentElement("document-styles");
      automaticStyles = cloneStylesInScope(self.rootElement.automaticStyles, documentStylesScope);
      masterStyles = self.rootElement.masterStyles.cloneNode(true);
      fontFaceDecls = cloneFontFaceDeclsUsedInStyles(self.rootElement.fontFaceDecls, [masterStyles, self.rootElement.styles, automaticStyles]);
      styleInfo.removePrefixFromStyleNames(automaticStyles, automaticStylePrefix, masterStyles);
      serializer.filter = new OdfStylesFilter(masterStyles, automaticStyles);
      s += serializer.writeToString(fontFaceDecls, nsmap);
      s += serializer.writeToString(self.rootElement.styles, nsmap);
      s += serializer.writeToString(automaticStyles, nsmap);
      s += serializer.writeToString(masterStyles, nsmap);
      s += "</office:document-styles>";
      return s;
    }
    function serializeContentXml() {
      var fontFaceDecls, automaticStyles, nsmap = odf.Namespaces.namespaceMap, serializer = new xmldom.LSSerializer, s = createDocumentElement("document-content");
      automaticStyles = cloneStylesInScope(self.rootElement.automaticStyles, documentContentScope);
      fontFaceDecls = cloneFontFaceDeclsUsedInStyles(self.rootElement.fontFaceDecls, [automaticStyles]);
      serializer.filter = new OdfContentFilter(self.rootElement.body, automaticStyles);
      s += serializer.writeToString(fontFaceDecls, nsmap);
      s += serializer.writeToString(automaticStyles, nsmap);
      s += serializer.writeToString(self.rootElement.body, nsmap);
      s += "</office:document-content>";
      return s;
    }
    function createElement(type) {
      var original = document.createElementNS(type.namespaceURI, type.localName), method, iface = new type.Type;
      for (method in iface) {
        if (iface.hasOwnProperty(method)) {
          original[method] = iface[method];
        }
      }
      return original;
    }
    function loadFromXML(url, callback) {
      function handler(err, dom) {
        if (err) {
          callback(err);
        } else {
          if (!dom) {
            callback("No DOM was loaded.");
          } else {
            removeDangerousElements(dom);
            removeDangerousAttributes(dom.documentElement);
            handleFlatXml(dom);
          }
        }
      }
      runtime.loadXML(url, handler);
    }
    this.setRootElement = setRootElement;
    this.getContentElement = function() {
      var body;
      if (!contentElement) {
        body = self.rootElement.body;
        contentElement = domUtils.getDirectChild(body, officens, "text") || domUtils.getDirectChild(body, officens, "presentation") || domUtils.getDirectChild(body, officens, "spreadsheet");
      }
      if (!contentElement) {
        throw "Could not find content element in <office:body/>.";
      }
      return contentElement;
    };
    this.getDocumentType = function() {
      var content = self.getContentElement();
      return content && content.localName;
    };
    this.isTemplate = function() {
      var docMimetype = partMimetypes["/"];
      return docMimetype.substr(-9) === "-template";
    };
    this.setIsTemplate = function(isTemplate) {
      var docMimetype = partMimetypes["/"], oldIsTemplate = docMimetype.substr(-9) === "-template", data;
      if (isTemplate === oldIsTemplate) {
        return;
      }
      if (isTemplate) {
        docMimetype = docMimetype + "-template";
      } else {
        docMimetype = docMimetype.substr(0, docMimetype.length - 9);
      }
      partMimetypes["/"] = docMimetype;
      data = runtime.byteArrayFromString(docMimetype, "utf8");
      zip.save("mimetype", data, false, new Date);
    };
    this.getPart = function(partname) {
      return new odf.OdfPart(partname, partMimetypes[partname], self, zip);
    };
    this.getPartData = function(url, callback) {
      zip.load(url, callback);
    };
    function setMetadata(setProperties, removedPropertyNames) {
      var metaElement = getEnsuredMetaElement();
      if (setProperties) {
        domUtils.mapKeyValObjOntoNode(metaElement, setProperties, odf.Namespaces.lookupNamespaceURI);
      }
      if (removedPropertyNames) {
        domUtils.removeKeyElementsFromNode(metaElement, removedPropertyNames, odf.Namespaces.lookupNamespaceURI);
      }
    }
    this.setMetadata = setMetadata;
    this.incrementEditingCycles = function() {
      var currentValueString = getMetadata(odf.Namespaces.metans, "editing-cycles"), currentCycles = currentValueString ? parseInt(currentValueString, 10) : 0;
      if (isNaN(currentCycles)) {
        currentCycles = 0;
      }
      setMetadata({"meta:editing-cycles":currentCycles + 1}, null);
      return currentCycles + 1;
    };
    function updateMetadataForSaving() {
      var generatorString, window = runtime.getWindow();
      generatorString = "WebODF/" + webodf.Version;
      if (window) {
        generatorString = generatorString + " " + window.navigator.userAgent;
      }
      setMetadata({"meta:generator":generatorString}, null);
    }
    function createEmptyDocument(type, isTemplate) {
      var emptyzip = new core.Zip("", null), mimetype = "application/vnd.oasis.opendocument." + type + (isTemplate === true ? "-template" : ""), data = runtime.byteArrayFromString(mimetype, "utf8"), root = self.rootElement, content = document.createElementNS(officens, type);
      emptyzip.save("mimetype", data, false, new Date);
      function addToplevelElement(memberName, realLocalName) {
        var element;
        if (!realLocalName) {
          realLocalName = memberName;
        }
        element = document.createElementNS(officens, realLocalName);
        root[memberName] = element;
        root.appendChild(element);
      }
      addToplevelElement("meta");
      addToplevelElement("settings");
      addToplevelElement("scripts");
      addToplevelElement("fontFaceDecls", "font-face-decls");
      addToplevelElement("styles");
      addToplevelElement("automaticStyles", "automatic-styles");
      addToplevelElement("masterStyles", "master-styles");
      addToplevelElement("body");
      root.body.appendChild(content);
      partMimetypes["/"] = mimetype;
      partMimetypes["settings.xml"] = "text/xml";
      partMimetypes["meta.xml"] = "text/xml";
      partMimetypes["styles.xml"] = "text/xml";
      partMimetypes["content.xml"] = "text/xml";
      setState(OdfContainer.DONE);
      return emptyzip;
    }
    function fillZip() {
      var data, date = new Date, settings;
      settings = serializeSettingsXml();
      if (settings) {
        data = runtime.byteArrayFromString(settings, "utf8");
        zip.save("settings.xml", data, true, date);
      } else {
        zip.remove("settings.xml");
      }
      updateMetadataForSaving();
      data = runtime.byteArrayFromString(serializeMetaXml(), "utf8");
      zip.save("meta.xml", data, true, date);
      data = runtime.byteArrayFromString(serializeStylesXml(), "utf8");
      zip.save("styles.xml", data, true, date);
      data = runtime.byteArrayFromString(serializeContentXml(), "utf8");
      zip.save("content.xml", data, true, date);
      data = runtime.byteArrayFromString(serializeManifestXml(), "utf8");
      zip.save("META-INF/manifest.xml", data, true, date);
    }
    function createByteArray(successCallback, errorCallback) {
      fillZip();
      zip.createByteArray(successCallback, errorCallback);
    }
    this.createByteArray = createByteArray;
    function saveAs(newurl, callback) {
      fillZip();
      zip.writeAs(newurl, function(err) {
        callback(err);
      });
    }
    this.saveAs = saveAs;
    this.save = function(callback) {
      saveAs(url, callback);
    };
    this.getUrl = function() {
      return url;
    };
    this.setBlob = function(filename, mimetype, content) {
      var data = base64.convertBase64ToByteArray(content), date = new Date;
      zip.save(filename, data, false, date);
      if (partMimetypes.hasOwnProperty(filename)) {
        runtime.log(filename + " has been overwritten.");
      }
      partMimetypes[filename] = mimetype;
    };
    this.removeBlob = function(filename) {
      var foundAndRemoved = zip.remove(filename);
      runtime.assert(foundAndRemoved, "file is not found: " + filename);
      delete partMimetypes[filename];
    };
    this.state = OdfContainer.LOADING;
    this.rootElement = createElement({Type:odf.ODFDocumentElement, namespaceURI:odf.ODFDocumentElement.namespaceURI, localName:odf.ODFDocumentElement.localName});
    if (urlOrType === odf.OdfContainer.DocumentType.TEXT) {
      zip = createEmptyDocument("text");
    } else {
      if (urlOrType === odf.OdfContainer.DocumentType.TEXT_TEMPLATE) {
        zip = createEmptyDocument("text", true);
      } else {
        if (urlOrType === odf.OdfContainer.DocumentType.PRESENTATION) {
          zip = createEmptyDocument("presentation");
        } else {
          if (urlOrType === odf.OdfContainer.DocumentType.PRESENTATION_TEMPLATE) {
            zip = createEmptyDocument("presentation", true);
          } else {
            if (urlOrType === odf.OdfContainer.DocumentType.SPREADSHEET) {
              zip = createEmptyDocument("spreadsheet");
            } else {
              if (urlOrType === odf.OdfContainer.DocumentType.SPREADSHEET_TEMPLATE) {
                zip = createEmptyDocument("spreadsheet", true);
              } else {
                url = urlOrType;
                zip = new core.Zip(url, function(err, zipobject) {
                  zip = zipobject;
                  if (err) {
                    loadFromXML(url, function(xmlerr) {
                      if (err) {
                        zip.error = err + "\n" + xmlerr;
                        setState(OdfContainer.INVALID);
                      }
                    });
                  } else {
                    loadComponents();
                  }
                });
              }
            }
          }
        }
      }
    }
  };
  odf.OdfContainer.EMPTY = 0;
  odf.OdfContainer.LOADING = 1;
  odf.OdfContainer.DONE = 2;
  odf.OdfContainer.INVALID = 3;
  odf.OdfContainer.SAVING = 4;
  odf.OdfContainer.MODIFIED = 5;
  odf.OdfContainer.getContainer = function(url) {
    return new odf.OdfContainer(url, null);
  };
})();
odf.OdfContainer.DocumentType = {TEXT:1, TEXT_TEMPLATE:2, PRESENTATION:3, PRESENTATION_TEMPLATE:4, SPREADSHEET:5, SPREADSHEET_TEMPLATE:6};
gui.AnnotatableCanvas = function AnnotatableCanvas() {
};
gui.AnnotatableCanvas.prototype.refreshSize = function() {
};
gui.AnnotatableCanvas.prototype.getZoomLevel = function() {
};
gui.AnnotatableCanvas.prototype.getSizer = function() {
};
gui.AnnotationViewManager = function AnnotationViewManager(canvas, odfFragment, annotationsPane, showAnnotationRemoveButton) {
  var annotations = [], doc = odfFragment.ownerDocument, odfUtils = odf.OdfUtils, CONNECTOR_MARGIN = 30, NOTE_MARGIN = 20, window = runtime.getWindow(), htmlns = "http://www.w3.org/1999/xhtml";
  runtime.assert(Boolean(window), "Expected to be run in an environment which has a global window, like a browser.");
  function wrapAnnotation(annotation) {
    var annotationWrapper = doc.createElement("div"), annotationNote = doc.createElement("div"), connectorHorizontal = doc.createElement("div"), connectorAngular = doc.createElement("div"), removeButton;
    annotationWrapper.className = "annotationWrapper";
    annotationWrapper.setAttribute("creator", odfUtils.getAnnotationCreator(annotation));
    annotation.parentNode.insertBefore(annotationWrapper, annotation);
    annotationNote.className = "annotationNote";
    annotationNote.appendChild(annotation);
    if (showAnnotationRemoveButton) {
      removeButton = doc.createElement("div");
      removeButton.className = "annotationRemoveButton";
      annotationNote.appendChild(removeButton);
    }
    connectorHorizontal.className = "annotationConnector horizontal";
    connectorAngular.className = "annotationConnector angular";
    annotationWrapper.appendChild(annotationNote);
    annotationWrapper.appendChild(connectorHorizontal);
    annotationWrapper.appendChild(connectorAngular);
  }
  function unwrapAnnotation(annotation) {
    var annotationWrapper = annotation.parentNode.parentNode;
    if (annotationWrapper.localName === "div") {
      annotationWrapper.parentNode.insertBefore(annotation, annotationWrapper);
      annotationWrapper.parentNode.removeChild(annotationWrapper);
    }
  }
  function isNodeWithinAnnotationHighlight(node, annotationName) {
    var iteratingNode = node.parentNode;
    while (!(iteratingNode.namespaceURI === odf.Namespaces.officens && iteratingNode.localName === "body")) {
      if (iteratingNode.namespaceURI === htmlns && iteratingNode.className === "webodf-annotationHighlight" && iteratingNode.getAttribute("annotation") === annotationName) {
        return true;
      }
      iteratingNode = iteratingNode.parentNode;
    }
    return false;
  }
  function highlightAnnotation(annotation) {
    var annotationEnd = annotation.annotationEndElement, range = doc.createRange(), annotationName = annotation.getAttributeNS(odf.Namespaces.officens, "name"), textNodes;
    if (annotationEnd) {
      range.setStart(annotation, annotation.childNodes.length);
      range.setEnd(annotationEnd, 0);
      textNodes = odfUtils.getTextNodes(range, false);
      textNodes.forEach(function(n) {
        if (!isNodeWithinAnnotationHighlight(n, annotationName)) {
          var container = doc.createElement("span");
          container.className = "webodf-annotationHighlight";
          container.setAttribute("annotation", annotationName);
          n.parentNode.replaceChild(container, n);
          container.appendChild(n);
        }
      });
    }
    range.detach();
  }
  function unhighlightAnnotation(annotation) {
    var annotationName = annotation.getAttributeNS(odf.Namespaces.officens, "name"), highlightSpans = doc.querySelectorAll('span.webodf-annotationHighlight[annotation="' + annotationName + '"]'), i, container;
    for (i = 0;i < highlightSpans.length;i += 1) {
      container = highlightSpans.item(i);
      while (container.firstChild) {
        container.parentNode.insertBefore(container.firstChild, container);
      }
      container.parentNode.removeChild(container);
    }
  }
  function lineDistance(point1, point2) {
    var xs = 0, ys = 0;
    xs = point2.x - point1.x;
    xs = xs * xs;
    ys = point2.y - point1.y;
    ys = ys * ys;
    return Math.sqrt(xs + ys);
  }
  function renderAnnotation(annotation) {
    var annotationNote = annotation.parentNode, connectorHorizontal = annotationNote.nextElementSibling, connectorAngular = connectorHorizontal.nextElementSibling, annotationWrapper = annotationNote.parentNode, connectorAngle = 0, previousAnnotation = annotations[annotations.indexOf(annotation) - 1], previousRect, zoomLevel = canvas.getZoomLevel();
    annotationNote.style.left = (annotationsPane.getBoundingClientRect().left - annotationWrapper.getBoundingClientRect().left) / zoomLevel + "px";
    annotationNote.style.width = annotationsPane.getBoundingClientRect().width / zoomLevel + "px";
    connectorHorizontal.style.width = parseFloat(annotationNote.style.left) - CONNECTOR_MARGIN + "px";
    if (previousAnnotation) {
      previousRect = previousAnnotation.parentNode.getBoundingClientRect();
      if ((annotationWrapper.getBoundingClientRect().top - previousRect.bottom) / zoomLevel <= NOTE_MARGIN) {
        annotationNote.style.top = Math.abs(annotationWrapper.getBoundingClientRect().top - previousRect.bottom) / zoomLevel + NOTE_MARGIN + "px";
      } else {
        annotationNote.style.top = "0px";
      }
    } else {
      annotationNote.style.top = "0px";
    }
    connectorAngular.style.left = connectorHorizontal.getBoundingClientRect().width / zoomLevel + "px";
    connectorAngular.style.width = lineDistance({x:connectorAngular.getBoundingClientRect().left / zoomLevel, y:connectorAngular.getBoundingClientRect().top / zoomLevel}, {x:annotationNote.getBoundingClientRect().left / zoomLevel, y:annotationNote.getBoundingClientRect().top / zoomLevel}) + "px";
    connectorAngle = Math.asin((annotationNote.getBoundingClientRect().top - connectorAngular.getBoundingClientRect().top) / (zoomLevel * parseFloat(connectorAngular.style.width)));
    connectorAngular.style.transform = "rotate(" + connectorAngle + "rad)";
    connectorAngular.style.MozTransform = "rotate(" + connectorAngle + "rad)";
    connectorAngular.style.WebkitTransform = "rotate(" + connectorAngle + "rad)";
    connectorAngular.style.msTransform = "rotate(" + connectorAngle + "rad)";
  }
  function showAnnotationsPane(show) {
    var sizer = canvas.getSizer();
    if (show) {
      annotationsPane.style.display = "inline-block";
      sizer.style.paddingRight = window.getComputedStyle(annotationsPane).width;
    } else {
      annotationsPane.style.display = "none";
      sizer.style.paddingRight = 0;
    }
    canvas.refreshSize();
  }
  function sortAnnotations() {
    annotations.sort(function(a, b) {
      if ((a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0) {
        return -1;
      }
      return 1;
    });
  }
  function rerenderAnnotations() {
    var i;
    for (i = 0;i < annotations.length;i += 1) {
      renderAnnotation(annotations[i]);
    }
  }
  this.rerenderAnnotations = rerenderAnnotations;
  function rehighlightAnnotations() {
    annotations.forEach(function(annotation) {
      highlightAnnotation(annotation);
    });
  }
  this.rehighlightAnnotations = rehighlightAnnotations;
  function getMinimumHeightForAnnotationPane() {
    if (annotationsPane.style.display !== "none" && annotations.length > 0) {
      return (annotations[annotations.length - 1].parentNode.getBoundingClientRect().bottom - annotationsPane.getBoundingClientRect().top) / canvas.getZoomLevel() + "px";
    }
    return null;
  }
  this.getMinimumHeightForAnnotationPane = getMinimumHeightForAnnotationPane;
  function addAnnotations(annotationElements) {
    if (annotationElements.length === 0) {
      return;
    }
    showAnnotationsPane(true);
    annotationElements.forEach(function(annotation) {
      annotations.push(annotation);
      wrapAnnotation(annotation);
      if (annotation.annotationEndElement) {
        highlightAnnotation(annotation);
      }
    });
    sortAnnotations();
    rerenderAnnotations();
  }
  this.addAnnotations = addAnnotations;
  function forgetAnnotation(annotation) {
    var index = annotations.indexOf(annotation);
    unwrapAnnotation(annotation);
    unhighlightAnnotation(annotation);
    if (index !== -1) {
      annotations.splice(index, 1);
    }
    if (annotations.length === 0) {
      showAnnotationsPane(false);
    }
  }
  this.forgetAnnotation = forgetAnnotation;
  function forgetAnnotations() {
    while (annotations.length) {
      forgetAnnotation(annotations[0]);
    }
  }
  this.forgetAnnotations = forgetAnnotations;
};
gui.Viewport = function Viewport() {
};
gui.Viewport.prototype.scrollIntoView = function(clientRect, alignWithTop) {
};
gui.SingleScrollViewport = function(scrollPane) {
  var VIEW_PADDING_PX = 5;
  function shrinkClientRectByMargin(clientRect, margin) {
    return {left:clientRect.left + margin.left, top:clientRect.top + margin.top, right:clientRect.right - margin.right, bottom:clientRect.bottom - margin.bottom};
  }
  function height(clientRect) {
    return clientRect.bottom - clientRect.top;
  }
  function width(clientRect) {
    return clientRect.right - clientRect.left;
  }
  this.scrollIntoView = function(clientRect, alignWithTop) {
    var verticalScrollbarHeight = scrollPane.offsetHeight - scrollPane.clientHeight, horizontalScrollbarWidth = scrollPane.offsetWidth - scrollPane.clientWidth, nonNullClientRect, scrollPaneRect = scrollPane.getBoundingClientRect(), paneRect;
    if (!clientRect || !scrollPaneRect) {
      return;
    }
    nonNullClientRect = clientRect;
    paneRect = shrinkClientRectByMargin(scrollPaneRect, {top:VIEW_PADDING_PX, bottom:verticalScrollbarHeight + VIEW_PADDING_PX, left:VIEW_PADDING_PX, right:horizontalScrollbarWidth + VIEW_PADDING_PX});
    if (alignWithTop || nonNullClientRect.top < paneRect.top) {
      scrollPane.scrollTop -= paneRect.top - nonNullClientRect.top;
    } else {
      if (nonNullClientRect.top > paneRect.bottom || nonNullClientRect.bottom > paneRect.bottom) {
        if (height(nonNullClientRect) <= height(paneRect)) {
          scrollPane.scrollTop += nonNullClientRect.bottom - paneRect.bottom;
        } else {
          scrollPane.scrollTop += nonNullClientRect.top - paneRect.top;
        }
      }
    }
    if (nonNullClientRect.left < paneRect.left) {
      scrollPane.scrollLeft -= paneRect.left - nonNullClientRect.left;
    } else {
      if (nonNullClientRect.right > paneRect.right) {
        if (width(nonNullClientRect) <= width(paneRect)) {
          scrollPane.scrollLeft += nonNullClientRect.right - paneRect.right;
        } else {
          scrollPane.scrollLeft -= paneRect.left - nonNullClientRect.left;
        }
      }
    }
  };
};
(function() {
  var xpath = xmldom.XPath, odfUtils = odf.OdfUtils, base64 = new core.Base64;
  function getEmbeddedFontDeclarations(fontFaceDecls) {
    var decls = {}, fonts, i, font, name, uris, href, family;
    if (!fontFaceDecls) {
      return decls;
    }
    fonts = xpath.getODFElementsWithXPath(fontFaceDecls, "style:font-face[svg:font-face-src]", odf.Namespaces.lookupNamespaceURI);
    for (i = 0;i < fonts.length;i += 1) {
      font = fonts[i];
      name = font.getAttributeNS(odf.Namespaces.stylens, "name");
      family = odfUtils.getNormalizedFontFamilyName(font.getAttributeNS(odf.Namespaces.svgns, "font-family"));
      uris = xpath.getODFElementsWithXPath(font, "svg:font-face-src/svg:font-face-uri", odf.Namespaces.lookupNamespaceURI);
      if (uris.length > 0) {
        href = uris[0].getAttributeNS(odf.Namespaces.xlinkns, "href");
        decls[name] = {href:href, family:family};
      }
    }
    return decls;
  }
  function addFontToCSS(name, font, fontdata, stylesheet) {
    var cssFamily = font.family || name, rule = "@font-face { font-family: " + cssFamily + "; src: " + "url(data:application/x-font-ttf;charset=binary;base64," + base64.convertUTF8ArrayToBase64(fontdata) + ') format("truetype"); }';
    try {
      stylesheet.insertRule(rule, stylesheet.cssRules.length);
    } catch (e) {
      runtime.log("Problem inserting rule in CSS: " + runtime.toJson(e) + "\nRule: " + rule);
    }
  }
  function loadFontIntoCSS(embeddedFontDeclarations, odfContainer, pos, stylesheet, callback) {
    var name, i = 0, n;
    for (n in embeddedFontDeclarations) {
      if (embeddedFontDeclarations.hasOwnProperty(n)) {
        if (i === pos) {
          name = n;
          break;
        }
        i += 1;
      }
    }
    if (!name) {
      if (callback) {
        callback();
      }
      return;
    }
    odfContainer.getPartData(embeddedFontDeclarations[name].href, function(err, fontdata) {
      if (err) {
        runtime.log(err);
      } else {
        if (!fontdata) {
          runtime.log("missing font data for " + embeddedFontDeclarations[name].href);
        } else {
          addFontToCSS(name, embeddedFontDeclarations[name], fontdata, stylesheet);
        }
      }
      loadFontIntoCSS(embeddedFontDeclarations, odfContainer, pos + 1, stylesheet, callback);
    });
  }
  function loadFontsIntoCSS(embeddedFontDeclarations, odfContainer, stylesheet) {
    loadFontIntoCSS(embeddedFontDeclarations, odfContainer, 0, stylesheet);
  }
  odf.FontLoader = function FontLoader() {
    this.loadFonts = function(odfContainer, stylesheet) {
      var embeddedFontDeclarations, fontFaceDecls = odfContainer.rootElement.fontFaceDecls;
      while (stylesheet.cssRules.length) {
        stylesheet.deleteRule(stylesheet.cssRules.length - 1);
      }
      if (fontFaceDecls) {
        embeddedFontDeclarations = getEmbeddedFontDeclarations(fontFaceDecls);
        loadFontsIntoCSS(embeddedFontDeclarations, odfContainer, stylesheet);
      }
    };
  };
})();
odf.Formatting = function Formatting() {
  var odfContainer, styleInfo = new odf.StyleInfo, svgns = odf.Namespaces.svgns, stylens = odf.Namespaces.stylens, textns = odf.Namespaces.textns, numberns = odf.Namespaces.numberns, fons = odf.Namespaces.fons, odfUtils = odf.OdfUtils, domUtils = core.DomUtils, utils = new core.Utils, cssUnits = new core.CSSUnits, builtInDefaultStyleAttributesByFamily = {"paragraph":{"style:paragraph-properties":{"fo:text-align":"left"}}}, defaultPageFormatSettings = {width:"21.001cm", height:"29.7cm", margin:"2cm", 
  padding:"0cm"};
  function getSystemDefaultStyleAttributes(styleFamily) {
    var result, builtInDefaultStyleAttributes = builtInDefaultStyleAttributesByFamily[styleFamily];
    if (builtInDefaultStyleAttributes) {
      result = utils.mergeObjects({}, builtInDefaultStyleAttributes);
    } else {
      result = {};
    }
    return result;
  }
  this.getSystemDefaultStyleAttributes = getSystemDefaultStyleAttributes;
  this.setOdfContainer = function(odfcontainer) {
    odfContainer = odfcontainer;
  };
  function getFontMap() {
    var fontFaceDecls = odfContainer.rootElement.fontFaceDecls, fontFaceDeclsMap = {}, node, name, family;
    node = fontFaceDecls && fontFaceDecls.firstElementChild;
    while (node) {
      name = node.getAttributeNS(stylens, "name");
      if (name) {
        family = node.getAttributeNS(svgns, "font-family");
        if (family || node.getElementsByTagNameNS(svgns, "font-face-uri").length > 0) {
          fontFaceDeclsMap[name] = family;
        }
      }
      node = node.nextElementSibling;
    }
    return fontFaceDeclsMap;
  }
  this.getFontMap = getFontMap;
  this.getAvailableParagraphStyles = function() {
    var node = odfContainer.rootElement.styles, p_family, p_name, p_displayName, paragraphStyles = [];
    node = node && node.firstElementChild;
    while (node) {
      if (node.localName === "style" && node.namespaceURI === stylens) {
        p_family = node.getAttributeNS(stylens, "family");
        if (p_family === "paragraph") {
          p_name = node.getAttributeNS(stylens, "name");
          p_displayName = node.getAttributeNS(stylens, "display-name") || p_name;
          if (p_name && p_displayName) {
            paragraphStyles.push({name:p_name, displayName:p_displayName});
          }
        }
      }
      node = node.nextElementSibling;
    }
    return paragraphStyles;
  };
  this.isStyleUsed = function(styleElement) {
    var hasDerivedStyles, isUsed, root = odfContainer.rootElement;
    hasDerivedStyles = styleInfo.hasDerivedStyles(root, odf.Namespaces.lookupNamespaceURI, styleElement);
    isUsed = (new styleInfo.UsedStyleList(root.styles)).uses(styleElement) || (new styleInfo.UsedStyleList(root.automaticStyles)).uses(styleElement) || (new styleInfo.UsedStyleList(root.body)).uses(styleElement);
    return hasDerivedStyles || isUsed;
  };
  function getDefaultStyleElement(family) {
    var node = odfContainer.rootElement.styles.firstElementChild;
    while (node) {
      if (node.namespaceURI === stylens && node.localName === "default-style" && node.getAttributeNS(stylens, "family") === family) {
        return node;
      }
      node = node.nextElementSibling;
    }
    return null;
  }
  this.getDefaultStyleElement = getDefaultStyleElement;
  function getStyleElement(styleName, family, styleElements) {
    var node, nodeStyleName, styleListElement, i;
    styleElements = styleElements || [odfContainer.rootElement.automaticStyles, odfContainer.rootElement.styles];
    for (i = 0;i < styleElements.length;i += 1) {
      styleListElement = styleElements[i];
      node = styleListElement.firstElementChild;
      while (node) {
        nodeStyleName = node.getAttributeNS(stylens, "name");
        if (node.namespaceURI === stylens && node.localName === "style" && node.getAttributeNS(stylens, "family") === family && nodeStyleName === styleName) {
          return node;
        }
        if (family === "list-style" && node.namespaceURI === textns && node.localName === "list-style" && nodeStyleName === styleName) {
          return node;
        }
        if (family === "data" && node.namespaceURI === numberns && nodeStyleName === styleName) {
          return node;
        }
        node = node.nextElementSibling;
      }
    }
    return null;
  }
  this.getStyleElement = getStyleElement;
  function getStyleAttributes(styleNode) {
    var i, a, map, ai, propertiesMap = {}, propertiesNode = styleNode.firstElementChild;
    while (propertiesNode) {
      if (propertiesNode.namespaceURI === stylens) {
        map = propertiesMap[propertiesNode.nodeName] = {};
        a = propertiesNode.attributes;
        for (i = 0;i < a.length;i += 1) {
          ai = a.item(i);
          map[ai.name] = ai.value;
        }
      }
      propertiesNode = propertiesNode.nextElementSibling;
    }
    a = styleNode.attributes;
    for (i = 0;i < a.length;i += 1) {
      ai = a.item(i);
      propertiesMap[ai.name] = ai.value;
    }
    return propertiesMap;
  }
  this.getStyleAttributes = getStyleAttributes;
  function getInheritedStyleAttributes(styleNode, includeSystemDefault) {
    var styleListElement = odfContainer.rootElement.styles, parentStyleName, propertiesMap, inheritedPropertiesMap = {}, styleFamily = styleNode.getAttributeNS(stylens, "family"), node = styleNode;
    while (node) {
      propertiesMap = getStyleAttributes(node);
      inheritedPropertiesMap = utils.mergeObjects(propertiesMap, inheritedPropertiesMap);
      parentStyleName = node.getAttributeNS(stylens, "parent-style-name");
      if (parentStyleName) {
        node = getStyleElement(parentStyleName, styleFamily, [styleListElement]);
      } else {
        node = null;
      }
    }
    node = getDefaultStyleElement(styleFamily);
    if (node) {
      propertiesMap = getStyleAttributes(node);
      inheritedPropertiesMap = utils.mergeObjects(propertiesMap, inheritedPropertiesMap);
    }
    if (includeSystemDefault !== false) {
      propertiesMap = getSystemDefaultStyleAttributes(styleFamily);
      inheritedPropertiesMap = utils.mergeObjects(propertiesMap, inheritedPropertiesMap);
    }
    return inheritedPropertiesMap;
  }
  this.getInheritedStyleAttributes = getInheritedStyleAttributes;
  this.getFirstCommonParentStyleNameOrSelf = function(styleName) {
    var automaticStyleElementList = odfContainer.rootElement.automaticStyles, styleElementList = odfContainer.rootElement.styles, styleElement;
    styleElement = getStyleElement(styleName, "paragraph", [automaticStyleElementList]);
    if (styleElement) {
      styleName = styleElement.getAttributeNS(stylens, "parent-style-name");
      if (!styleName) {
        return null;
      }
    }
    styleElement = getStyleElement(styleName, "paragraph", [styleElementList]);
    if (!styleElement) {
      return null;
    }
    return styleName;
  };
  this.hasParagraphStyle = function(styleName) {
    return Boolean(getStyleElement(styleName, "paragraph"));
  };
  function buildStyleChain(node, collectedChains) {
    var parent = node.nodeType === Node.TEXT_NODE ? node.parentNode : node, nodeStyles, appliedStyles = [], chainKey = "", foundContainer = false;
    while (parent && !odfUtils.isInlineRoot(parent) && parent.parentNode !== odfContainer.rootElement) {
      if (!foundContainer && odfUtils.isGroupingElement(parent)) {
        foundContainer = true;
      }
      nodeStyles = styleInfo.determineStylesForNode(parent);
      if (nodeStyles) {
        appliedStyles.push(nodeStyles);
      }
      parent = parent.parentNode;
    }
    function chainStyles(usedStyleMap) {
      Object.keys(usedStyleMap).forEach(function(styleFamily) {
        Object.keys(usedStyleMap[styleFamily]).forEach(function(styleName) {
          chainKey += "|" + styleFamily + ":" + styleName + "|";
        });
      });
    }
    if (foundContainer) {
      appliedStyles.forEach(chainStyles);
      if (collectedChains) {
        collectedChains[chainKey] = appliedStyles;
      }
    }
    return foundContainer ? appliedStyles : undefined;
  }
  function isCommonStyleElement(styleNode) {
    return styleNode.parentNode === odfContainer.rootElement.styles;
  }
  function calculateAppliedStyle(styleChain) {
    var mergedChildStyle = {orderedStyles:[], styleProperties:{}};
    styleChain.forEach(function(elementStyleSet) {
      Object.keys(elementStyleSet).forEach(function(styleFamily) {
        var styleName = Object.keys(elementStyleSet[styleFamily])[0], styleSummary = {name:styleName, family:styleFamily, displayName:undefined, isCommonStyle:false}, styleElement, parentStyle;
        styleElement = getStyleElement(styleName, styleFamily);
        if (styleElement) {
          parentStyle = getInheritedStyleAttributes(styleElement);
          mergedChildStyle.styleProperties = utils.mergeObjects(parentStyle, mergedChildStyle.styleProperties);
          styleSummary.displayName = styleElement.getAttributeNS(stylens, "display-name") || undefined;
          styleSummary.isCommonStyle = isCommonStyleElement(styleElement);
        } else {
          runtime.log("No style element found for '" + styleName + "' of family '" + styleFamily + "'");
        }
        mergedChildStyle.orderedStyles.push(styleSummary);
      });
    });
    return mergedChildStyle;
  }
  function getAppliedStyles(nodes, calculatedStylesCache) {
    var styleChains = {}, styles = [];
    if (!calculatedStylesCache) {
      calculatedStylesCache = {};
    }
    nodes.forEach(function(n) {
      buildStyleChain(n, styleChains);
    });
    Object.keys(styleChains).forEach(function(key) {
      if (!calculatedStylesCache[key]) {
        calculatedStylesCache[key] = calculateAppliedStyle(styleChains[key]);
      }
      styles.push(calculatedStylesCache[key]);
    });
    return styles;
  }
  this.getAppliedStyles = getAppliedStyles;
  this.getAppliedStylesForElement = function(node, calculatedStylesCache) {
    return getAppliedStyles([node], calculatedStylesCache)[0];
  };
  this.updateStyle = function(styleNode, properties) {
    var fontName, fontFaceNode, textProperties;
    domUtils.mapObjOntoNode(styleNode, properties, odf.Namespaces.lookupNamespaceURI);
    textProperties = properties["style:text-properties"];
    fontName = textProperties && textProperties["style:font-name"];
    if (fontName && !getFontMap().hasOwnProperty(fontName)) {
      fontFaceNode = styleNode.ownerDocument.createElementNS(stylens, "style:font-face");
      fontFaceNode.setAttributeNS(stylens, "style:name", fontName);
      fontFaceNode.setAttributeNS(svgns, "svg:font-family", fontName);
      odfContainer.rootElement.fontFaceDecls.appendChild(fontFaceNode);
    }
  };
  this.createDerivedStyleObject = function(parentStyleName, family, overrides) {
    var originalStyleElement = getStyleElement(parentStyleName, family), newStyleObject;
    runtime.assert(Boolean(originalStyleElement), "No style element found for '" + parentStyleName + "' of family '" + family + "'");
    if (isCommonStyleElement(originalStyleElement)) {
      newStyleObject = {"style:parent-style-name":parentStyleName};
    } else {
      newStyleObject = getStyleAttributes(originalStyleElement);
    }
    newStyleObject["style:family"] = family;
    utils.mergeObjects(newStyleObject, overrides);
    return newStyleObject;
  };
  this.getDefaultTabStopDistance = function() {
    var defaultParagraph = getDefaultStyleElement("paragraph"), paragraphProperties = defaultParagraph && defaultParagraph.firstElementChild, tabStopDistance;
    while (paragraphProperties) {
      if (paragraphProperties.namespaceURI === stylens && paragraphProperties.localName === "paragraph-properties") {
        tabStopDistance = paragraphProperties.getAttributeNS(stylens, "tab-stop-distance");
      }
      paragraphProperties = paragraphProperties.nextElementSibling;
    }
    if (!tabStopDistance) {
      tabStopDistance = "1.25cm";
    }
    return odfUtils.parseNonNegativeLength(tabStopDistance);
  };
  function getMasterPageElement(pageName) {
    var node = odfContainer.rootElement.masterStyles.firstElementChild;
    while (node) {
      if (node.namespaceURI === stylens && node.localName === "master-page" && node.getAttributeNS(stylens, "name") === pageName) {
        break;
      }
      node = node.nextElementSibling;
    }
    return node;
  }
  this.getMasterPageElement = getMasterPageElement;
  function getPageLayoutStyleElement(styleName, styleFamily) {
    var masterPageName, layoutName, pageLayoutElements, node, i, styleElement = getStyleElement(styleName, styleFamily);
    runtime.assert(styleFamily === "paragraph" || styleFamily === "table", "styleFamily must be either paragraph or table");
    if (styleElement) {
      masterPageName = styleElement.getAttributeNS(stylens, "master-page-name");
      if (masterPageName) {
        node = getMasterPageElement(masterPageName);
        if (!node) {
          runtime.log("WARN: No master page definition found for " + masterPageName);
        }
      }
      if (!node) {
        node = getMasterPageElement("Standard");
      }
      if (!node) {
        node = odfContainer.rootElement.masterStyles.getElementsByTagNameNS(stylens, "master-page")[0];
        if (!node) {
          runtime.log("WARN: Document has no master pages defined");
        }
      }
      if (node) {
        layoutName = node.getAttributeNS(stylens, "page-layout-name");
        pageLayoutElements = odfContainer.rootElement.automaticStyles.getElementsByTagNameNS(stylens, "page-layout");
        for (i = 0;i < pageLayoutElements.length;i += 1) {
          node = pageLayoutElements.item(i);
          if (node.getAttributeNS(stylens, "name") === layoutName) {
            return node;
          }
        }
      }
    }
    return null;
  }
  function lengthInPx(length, defaultValue) {
    var measure;
    if (length) {
      measure = cssUnits.convertMeasure(length, "px");
    }
    if (measure === undefined && defaultValue) {
      measure = cssUnits.convertMeasure(defaultValue, "px");
    }
    return measure;
  }
  this.getContentSize = function(styleName, styleFamily) {
    var pageLayoutElement, props, defaultOrientedPageWidth, defaultOrientedPageHeight, pageWidth, pageHeight, margin, marginLeft, marginRight, marginTop, marginBottom, padding, paddingLeft, paddingRight, paddingTop, paddingBottom;
    pageLayoutElement = getPageLayoutStyleElement(styleName, styleFamily);
    if (!pageLayoutElement) {
      pageLayoutElement = domUtils.getDirectChild(odfContainer.rootElement.styles, stylens, "default-page-layout");
    }
    props = domUtils.getDirectChild(pageLayoutElement, stylens, "page-layout-properties");
    if (props) {
      if (props.getAttributeNS(stylens, "print-orientation") === "landscape") {
        defaultOrientedPageWidth = defaultPageFormatSettings.height;
        defaultOrientedPageHeight = defaultPageFormatSettings.width;
      } else {
        defaultOrientedPageWidth = defaultPageFormatSettings.width;
        defaultOrientedPageHeight = defaultPageFormatSettings.height;
      }
      pageWidth = lengthInPx(props.getAttributeNS(fons, "page-width"), defaultOrientedPageWidth);
      pageHeight = lengthInPx(props.getAttributeNS(fons, "page-height"), defaultOrientedPageHeight);
      margin = lengthInPx(props.getAttributeNS(fons, "margin"));
      if (margin === undefined) {
        marginLeft = lengthInPx(props.getAttributeNS(fons, "margin-left"), defaultPageFormatSettings.margin);
        marginRight = lengthInPx(props.getAttributeNS(fons, "margin-right"), defaultPageFormatSettings.margin);
        marginTop = lengthInPx(props.getAttributeNS(fons, "margin-top"), defaultPageFormatSettings.margin);
        marginBottom = lengthInPx(props.getAttributeNS(fons, "margin-bottom"), defaultPageFormatSettings.margin);
      } else {
        marginLeft = marginRight = marginTop = marginBottom = margin;
      }
      padding = lengthInPx(props.getAttributeNS(fons, "padding"));
      if (padding === undefined) {
        paddingLeft = lengthInPx(props.getAttributeNS(fons, "padding-left"), defaultPageFormatSettings.padding);
        paddingRight = lengthInPx(props.getAttributeNS(fons, "padding-right"), defaultPageFormatSettings.padding);
        paddingTop = lengthInPx(props.getAttributeNS(fons, "padding-top"), defaultPageFormatSettings.padding);
        paddingBottom = lengthInPx(props.getAttributeNS(fons, "padding-bottom"), defaultPageFormatSettings.padding);
      } else {
        paddingLeft = paddingRight = paddingTop = paddingBottom = padding;
      }
    } else {
      pageWidth = lengthInPx(defaultPageFormatSettings.width);
      pageHeight = lengthInPx(defaultPageFormatSettings.height);
      margin = lengthInPx(defaultPageFormatSettings.margin);
      marginLeft = marginRight = marginTop = marginBottom = margin;
      padding = lengthInPx(defaultPageFormatSettings.padding);
      paddingLeft = paddingRight = paddingTop = paddingBottom = padding;
    }
    return {width:pageWidth - marginLeft - marginRight - paddingLeft - paddingRight, height:pageHeight - marginTop - marginBottom - paddingTop - paddingBottom};
  };
};
odf.Formatting.StyleMetadata;
odf.Formatting.StyleData;
odf.Formatting.AppliedStyle;
(function() {
  var stylens = odf.Namespaces.stylens, textns = odf.Namespaces.textns, familyNamespacePrefixes = {"graphic":"draw", "drawing-page":"draw", "paragraph":"text", "presentation":"presentation", "ruby":"text", "section":"text", "table":"table", "table-cell":"table", "table-column":"table", "table-row":"table", "text":"text", "list":"text", "page":"office"};
  odf.StyleTreeNode = function StyleTreeNode(element) {
    this.derivedStyles = {};
    this.element = element;
  };
  odf.StyleTree = function StyleTree(styles, autoStyles) {
    var tree = {};
    function getStyleMap(stylesNode) {
      var node, name, family, style, styleMap = {};
      if (!stylesNode) {
        return styleMap;
      }
      node = stylesNode.firstElementChild;
      while (node) {
        if (node.namespaceURI === stylens && (node.localName === "style" || node.localName === "default-style")) {
          family = node.getAttributeNS(stylens, "family");
        } else {
          if (node.namespaceURI === textns && node.localName === "list-style") {
            family = "list";
          } else {
            if (node.namespaceURI === stylens && (node.localName === "page-layout" || node.localName === "default-page-layout")) {
              family = "page";
            } else {
              family = undefined;
            }
          }
        }
        if (family) {
          name = node.getAttributeNS(stylens, "name");
          if (!name) {
            name = "";
          }
          if (styleMap.hasOwnProperty(family)) {
            style = styleMap[family];
          } else {
            styleMap[family] = style = {};
          }
          style[name] = node;
        }
        node = node.nextElementSibling;
      }
      return styleMap;
    }
    function findStyleTreeNode(stylesTree, name) {
      if (stylesTree.hasOwnProperty(name)) {
        return stylesTree[name];
      }
      var style = null, styleNames = Object.keys(stylesTree), i;
      for (i = 0;i < styleNames.length;i += 1) {
        style = findStyleTreeNode(stylesTree[styleNames[i]].derivedStyles, name);
        if (style) {
          break;
        }
      }
      return style;
    }
    function createStyleTreeNode(styleName, stylesMap, stylesTree) {
      var style, parentname, parentstyle;
      if (!stylesMap.hasOwnProperty(styleName)) {
        return null;
      }
      style = new odf.StyleTreeNode(stylesMap[styleName]);
      parentname = style.element.getAttributeNS(stylens, "parent-style-name");
      parentstyle = null;
      if (parentname) {
        parentstyle = findStyleTreeNode(stylesTree, parentname) || createStyleTreeNode(parentname, stylesMap, stylesTree);
      }
      if (parentstyle) {
        parentstyle.derivedStyles[styleName] = style;
      } else {
        stylesTree[styleName] = style;
      }
      delete stylesMap[styleName];
      return style;
    }
    function addStyleMapToStyleTree(stylesMap, stylesTree) {
      if (stylesMap) {
        Object.keys(stylesMap).forEach(function(styleName) {
          createStyleTreeNode(styleName, stylesMap, stylesTree);
        });
      }
    }
    this.getStyleTree = function() {
      return tree;
    };
    function init() {
      var subTree, styleNodes, autoStyleNodes;
      styleNodes = getStyleMap(styles);
      autoStyleNodes = getStyleMap(autoStyles);
      Object.keys(familyNamespacePrefixes).forEach(function(family) {
        subTree = tree[family] = {};
        addStyleMapToStyleTree(styleNodes[family], subTree);
        addStyleMapToStyleTree(autoStyleNodes[family], subTree);
      });
    }
    init();
  };
})();
odf.StyleTree.Tree;
(function() {
  var fons = odf.Namespaces.fons, stylens = odf.Namespaces.stylens, textns = odf.Namespaces.textns, xmlns = odf.Namespaces.xmlns, helperns = "urn:webodf:names:helper", listCounterIdSuffix = "webodf-listLevel", stylemap = {1:"decimal", "a":"lower-latin", "A":"upper-latin", "i":"lower-roman", "I":"upper-roman"};
  function appendRule(styleSheet, rule) {
    try {
      styleSheet.insertRule(rule, styleSheet.cssRules.length);
    } catch (e) {
      runtime.log("cannot load rule: " + rule + " - " + e);
    }
  }
  function ParseState(contentRules, continuedCounterIdStack) {
    this.listCounterCount = 0;
    this.contentRules = contentRules;
    this.counterIdStack = [];
    this.continuedCounterIdStack = continuedCounterIdStack;
  }
  function UniqueListCounter(styleSheet) {
    var customListIdIndex = 0, globalCounterResetRule = "", counterIdStacks = {};
    function getCounterIdStack(list) {
      var counterId, stack = [];
      if (list) {
        counterId = list.getAttributeNS(helperns, "counter-id");
        stack = counterIdStacks[counterId].slice(0);
      }
      return stack;
    }
    function createCssRulesForList(topLevelListId, listElement, listLevel, parseState) {
      var newListSelectorId, newListCounterId, newRule, contentRule, i;
      parseState.listCounterCount += 1;
      newListSelectorId = topLevelListId + "-level" + listLevel + "-" + parseState.listCounterCount;
      listElement.setAttributeNS(helperns, "counter-id", newListSelectorId);
      newListCounterId = parseState.continuedCounterIdStack.shift();
      if (!newListCounterId) {
        newListCounterId = newListSelectorId;
        globalCounterResetRule += newListSelectorId + " 1 ";
        newRule = 'text|list[webodfhelper|counter-id="' + newListSelectorId + '"]';
        newRule += " > text|list-item:first-child > :not(text|list):first-child:before";
        newRule += "{";
        newRule += "counter-increment: " + newListCounterId + " 0;";
        newRule += "}";
        appendRule(styleSheet, newRule);
      }
      while (parseState.counterIdStack.length >= listLevel) {
        parseState.counterIdStack.pop();
      }
      parseState.counterIdStack.push(newListCounterId);
      contentRule = parseState.contentRules[listLevel.toString()] || "";
      for (i = 1;i <= listLevel;i += 1) {
        contentRule = contentRule.replace(i + listCounterIdSuffix, parseState.counterIdStack[i - 1]);
      }
      newRule = 'text|list[webodfhelper|counter-id="' + newListSelectorId + '"]';
      newRule += " > text|list-item > :not(text|list):first-child:before";
      newRule += "{";
      newRule += contentRule;
      newRule += "counter-increment: " + newListCounterId + ";";
      newRule += "}";
      appendRule(styleSheet, newRule);
    }
    function iterateOverChildListElements(topLevelListId, element, listLevel, parseState) {
      var isListElement = element.namespaceURI === textns && element.localName === "list", isListItemElement = element.namespaceURI === textns && element.localName === "list-item", childElement;
      if (!isListElement && !isListItemElement) {
        parseState.continuedCounterIdStack = [];
        return;
      }
      if (isListElement) {
        listLevel += 1;
        createCssRulesForList(topLevelListId, element, listLevel, parseState);
      }
      childElement = element.firstElementChild;
      while (childElement) {
        iterateOverChildListElements(topLevelListId, childElement, listLevel, parseState);
        childElement = childElement.nextElementSibling;
      }
    }
    this.createCounterRules = function(contentRules, list, continuedList) {
      var listId = list.getAttributeNS(xmlns, "id"), currentParseState = new ParseState(contentRules, getCounterIdStack(continuedList));
      if (!listId) {
        customListIdIndex += 1;
        listId = "X" + customListIdIndex;
      } else {
        listId = "Y" + listId;
      }
      iterateOverChildListElements(listId, list, 0, currentParseState);
      counterIdStacks[listId + "-level1-1"] = currentParseState.counterIdStack;
    };
    this.initialiseCreatedCounters = function() {
      var newRule;
      newRule = "office|document";
      newRule += "{";
      newRule += "counter-reset: " + globalCounterResetRule + ";";
      newRule += "}";
      appendRule(styleSheet, newRule);
    };
  }
  odf.ListStyleToCss = function ListStyleToCss() {
    var cssUnits = new core.CSSUnits, odfUtils = odf.OdfUtils;
    function convertToPxValue(value) {
      var parsedLength = odfUtils.parseLength(value);
      if (!parsedLength) {
        runtime.log("Could not parse value '" + value + "'.");
        return 0;
      }
      return cssUnits.convert(parsedLength.value, parsedLength.unit, "px");
    }
    function escapeCSSString(value) {
      return value.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
    }
    function isMatchingListStyle(list, matchingStyleName) {
      var styleName;
      if (list) {
        styleName = list.getAttributeNS(textns, "style-name");
      }
      return styleName === matchingStyleName;
    }
    function getNumberRule(node) {
      var style = node.getAttributeNS(stylens, "num-format"), suffix = node.getAttributeNS(stylens, "num-suffix") || "", prefix = node.getAttributeNS(stylens, "num-prefix") || "", content = "", textLevel = node.getAttributeNS(textns, "level"), displayLevels = node.getAttributeNS(textns, "display-levels");
      if (prefix) {
        content += '"' + escapeCSSString(prefix) + '"\n';
      }
      if (stylemap.hasOwnProperty(style)) {
        textLevel = textLevel ? parseInt(textLevel, 10) : 1;
        displayLevels = displayLevels ? parseInt(displayLevels, 10) : 1;
        while (displayLevels > 0) {
          content += " counter(" + (textLevel - displayLevels + 1) + listCounterIdSuffix + "," + stylemap[style] + ")";
          if (displayLevels > 1) {
            content += '"."';
          }
          displayLevels -= 1;
        }
      } else {
        if (style) {
          content += ' "' + style + '"';
        } else {
          content += ' ""';
        }
      }
      return "content:" + content + ' "' + escapeCSSString(suffix) + '"';
    }
    function getImageRule() {
      return "content: none";
    }
    function getBulletRule(node) {
      var bulletChar = node.getAttributeNS(textns, "bullet-char");
      return 'content: "' + escapeCSSString(bulletChar) + '"';
    }
    function getContentRule(node) {
      var contentRule = "", listLevelProps, listLevelPositionSpaceMode, listLevelLabelAlign, followedBy;
      if (node.localName === "list-level-style-number") {
        contentRule = getNumberRule(node);
      } else {
        if (node.localName === "list-level-style-image") {
          contentRule = getImageRule();
        } else {
          if (node.localName === "list-level-style-bullet") {
            contentRule = getBulletRule(node);
          }
        }
      }
      listLevelProps = node.getElementsByTagNameNS(stylens, "list-level-properties")[0];
      if (listLevelProps) {
        listLevelPositionSpaceMode = listLevelProps.getAttributeNS(textns, "list-level-position-and-space-mode");
        if (listLevelPositionSpaceMode === "label-alignment") {
          listLevelLabelAlign = listLevelProps.getElementsByTagNameNS(stylens, "list-level-label-alignment")[0];
          if (listLevelLabelAlign) {
            followedBy = listLevelLabelAlign.getAttributeNS(textns, "label-followed-by");
          }
          if (followedBy === "space") {
            contentRule += ' "\\a0"';
          }
        }
      }
      return "\n" + contentRule + ";\n";
    }
    function getAllContentRules(listStyleNode) {
      var childNode = listStyleNode.firstElementChild, level, rules = {};
      while (childNode) {
        level = childNode.getAttributeNS(textns, "level");
        level = level && parseInt(level, 10);
        rules[level] = getContentRule(childNode);
        childNode = childNode.nextElementSibling;
      }
      return rules;
    }
    function addListStyleRule(styleSheet, name, node) {
      var selector = 'text|list[text|style-name="' + name + '"]', level = node.getAttributeNS(textns, "level"), selectorLevel, listItemRule, listLevelProps, listLevelPositionSpaceMode, listLevelLabelAlign, listIndent, textAlign, bulletWidth, labelDistance, bulletIndent, followedBy, leftOffset;
      listLevelProps = node.getElementsByTagNameNS(stylens, "list-level-properties")[0];
      listLevelPositionSpaceMode = listLevelProps && listLevelProps.getAttributeNS(textns, "list-level-position-and-space-mode");
      listLevelLabelAlign = listLevelProps && listLevelProps.getElementsByTagNameNS(stylens, "list-level-label-alignment")[0];
      level = level && parseInt(level, 10);
      selectorLevel = level;
      while (selectorLevel > 1) {
        selector += " > text|list-item > text|list";
        selectorLevel -= 1;
      }
      textAlign = listLevelProps && listLevelProps.getAttributeNS(fons, "text-align") || "left";
      switch(textAlign) {
        case "end":
          textAlign = "right";
          break;
        case "start":
          textAlign = "left";
          break;
      }
      if (listLevelPositionSpaceMode === "label-alignment") {
        listIndent = listLevelLabelAlign && listLevelLabelAlign.getAttributeNS(fons, "margin-left") || "0px";
        bulletIndent = listLevelLabelAlign && listLevelLabelAlign.getAttributeNS(fons, "text-indent") || "0px";
        followedBy = listLevelLabelAlign && listLevelLabelAlign.getAttributeNS(textns, "label-followed-by");
        leftOffset = convertToPxValue(listIndent);
      } else {
        listIndent = listLevelProps && listLevelProps.getAttributeNS(textns, "space-before") || "0px";
        bulletWidth = listLevelProps && listLevelProps.getAttributeNS(textns, "min-label-width") || "0px";
        labelDistance = listLevelProps && listLevelProps.getAttributeNS(textns, "min-label-distance") || "0px";
        leftOffset = convertToPxValue(listIndent) + convertToPxValue(bulletWidth);
      }
      listItemRule = selector + " > text|list-item";
      listItemRule += "{";
      listItemRule += "margin-left: " + leftOffset + "px;";
      listItemRule += "}";
      appendRule(styleSheet, listItemRule);
      listItemRule = selector + " > text|list-item > text|list";
      listItemRule += "{";
      listItemRule += "margin-left: " + -leftOffset + "px;";
      listItemRule += "}";
      appendRule(styleSheet, listItemRule);
      listItemRule = selector + " > text|list-item > :not(text|list):first-child:before";
      listItemRule += "{";
      listItemRule += "text-align: " + textAlign + ";";
      listItemRule += "display: inline-block;";
      if (listLevelPositionSpaceMode === "label-alignment") {
        listItemRule += "margin-left: " + bulletIndent + ";";
        if (followedBy === "listtab") {
          listItemRule += "padding-right: 0.2cm;";
        }
      } else {
        listItemRule += "min-width: " + bulletWidth + ";";
        listItemRule += "margin-left: " + (parseFloat(bulletWidth) === 0 ? "" : "-") + bulletWidth + ";";
        listItemRule += "padding-right: " + labelDistance + ";";
      }
      listItemRule += "}";
      appendRule(styleSheet, listItemRule);
    }
    function addRule(styleSheet, name, node) {
      var n = node.firstElementChild;
      while (n) {
        if (n.namespaceURI === textns) {
          addListStyleRule(styleSheet, name, n);
        }
        n = n.nextElementSibling;
      }
    }
    function applyContentBasedStyles(styleSheet, odfBody, listStyles) {
      var lists = odfBody.getElementsByTagNameNS(textns, "list"), listCounter = new UniqueListCounter(styleSheet), list, previousList, continueNumbering, continueListXmlId, xmlId, styleName, contentRules, listsWithXmlId = {}, i;
      for (i = 0;i < lists.length;i += 1) {
        list = lists.item(i);
        styleName = list.getAttributeNS(textns, "style-name");
        if (styleName) {
          continueNumbering = list.getAttributeNS(textns, "continue-numbering");
          continueListXmlId = list.getAttributeNS(textns, "continue-list");
          xmlId = list.getAttributeNS(xmlns, "id");
          if (xmlId) {
            listsWithXmlId[xmlId] = list;
          }
          contentRules = getAllContentRules(listStyles[styleName].element);
          if (continueNumbering && !continueListXmlId && isMatchingListStyle(previousList, styleName)) {
            listCounter.createCounterRules(contentRules, list, previousList);
          } else {
            if (continueListXmlId && isMatchingListStyle(listsWithXmlId[continueListXmlId], styleName)) {
              listCounter.createCounterRules(contentRules, list, listsWithXmlId[continueListXmlId]);
            } else {
              listCounter.createCounterRules(contentRules, list);
            }
          }
          previousList = list;
        }
      }
      listCounter.initialiseCreatedCounters();
    }
    this.applyListStyles = function(styleSheet, styleTree, odfBody) {
      var styleFamilyTree, node;
      styleFamilyTree = styleTree["list"];
      if (styleFamilyTree) {
        Object.keys(styleFamilyTree).forEach(function(styleName) {
          node = styleFamilyTree[styleName];
          addRule(styleSheet, styleName, node.element);
        });
      }
      applyContentBasedStyles(styleSheet, odfBody, styleFamilyTree);
    };
  };
})();
odf.LazyStyleProperties = function(parent, getters) {
  var data = {};
  this.value = function(name) {
    var v;
    if (data.hasOwnProperty(name)) {
      v = data[name];
    } else {
      v = getters[name]();
      if (v === undefined && parent) {
        v = parent.value(name);
      }
      data[name] = v;
    }
    return v;
  };
  this.reset = function(p) {
    parent = p;
    data = {};
  };
};
odf.StyleParseUtils = function() {
  var stylens = odf.Namespaces.stylens;
  function splitLength(length) {
    var re = /(-?[0-9]*[0-9][0-9]*(\.[0-9]*)?|0+\.[0-9]*[1-9][0-9]*|\.[0-9]*[1-9][0-9]*)((cm)|(mm)|(in)|(pt)|(pc)|(px))/, m = re.exec(length);
    if (!m) {
      return null;
    }
    return {value:parseFloat(m[1]), unit:m[3]};
  }
  function parseLength(val) {
    var n, length, unit;
    length = splitLength(val);
    unit = length && length.unit;
    if (unit === "px") {
      n = length.value;
    } else {
      if (unit === "cm") {
        n = length.value / 2.54 * 96;
      } else {
        if (unit === "mm") {
          n = length.value / 25.4 * 96;
        } else {
          if (unit === "in") {
            n = length.value * 96;
          } else {
            if (unit === "pt") {
              n = length.value / .75;
            } else {
              if (unit === "pc") {
                n = length.value * 16;
              }
            }
          }
        }
      }
    }
    return n;
  }
  this.parseLength = parseLength;
  function parsePercent(value) {
    var v;
    if (value) {
      v = parseFloat(value.substr(0, value.indexOf("%")));
      if (isNaN(v)) {
        v = undefined;
      }
    }
    return v;
  }
  function parsePositiveLengthOrPercent(value, name, parent) {
    var v = parsePercent(value), parentValue;
    if (v !== undefined) {
      if (parent) {
        parentValue = parent.value(name);
      }
      if (parentValue === undefined) {
        v = undefined;
      } else {
        v *= parentValue / 100;
      }
    } else {
      v = parseLength(value);
    }
    return v;
  }
  this.parsePositiveLengthOrPercent = parsePositiveLengthOrPercent;
  function getPropertiesElement(name, styleElement, previousPropertyElement) {
    var e = previousPropertyElement ? previousPropertyElement.nextElementSibling : styleElement.firstElementChild;
    while (e !== null && (e.localName !== name || e.namespaceURI !== stylens)) {
      e = e.nextElementSibling;
    }
    return e;
  }
  this.getPropertiesElement = getPropertiesElement;
  function parseAttributeList(text) {
    if (text) {
      text = text.replace(/^\s*(.*?)\s*$/g, "$1");
    }
    return text && text.length > 0 ? text.split(/\s+/) : [];
  }
  this.parseAttributeList = parseAttributeList;
};
odf.Style2CSS = function Style2CSS() {
  var drawns = odf.Namespaces.drawns, fons = odf.Namespaces.fons, officens = odf.Namespaces.officens, stylens = odf.Namespaces.stylens, svgns = odf.Namespaces.svgns, tablens = odf.Namespaces.tablens, xlinkns = odf.Namespaces.xlinkns, presentationns = odf.Namespaces.presentationns, webodfhelperns = "urn:webodf:names:helper", domUtils = core.DomUtils, styleParseUtils = new odf.StyleParseUtils, familynamespaceprefixes = {"graphic":"draw", "drawing-page":"draw", "paragraph":"text", "presentation":"presentation", 
  "ruby":"text", "section":"text", "table":"table", "table-cell":"table", "table-column":"table", "table-row":"table", "text":"text", "list":"text", "page":"office"}, familytagnames = {"graphic":["circle", "connected", "control", "custom-shape", "ellipse", "frame", "g", "line", "measure", "page", "page-thumbnail", "path", "polygon", "polyline", "rect", "regular-polygon"], "paragraph":["alphabetical-index-entry-template", "h", "illustration-index-entry-template", "index-source-style", "object-index-entry-template", 
  "p", "table-index-entry-template", "table-of-content-entry-template", "user-index-entry-template"], "presentation":["caption", "circle", "connector", "control", "custom-shape", "ellipse", "frame", "g", "line", "measure", "page-thumbnail", "path", "polygon", "polyline", "rect", "regular-polygon"], "drawing-page":["caption", "circle", "connector", "control", "page", "custom-shape", "ellipse", "frame", "g", "line", "measure", "page-thumbnail", "path", "polygon", "polyline", "rect", "regular-polygon"], 
  "ruby":["ruby", "ruby-text"], "section":["alphabetical-index", "bibliography", "illustration-index", "index-title", "object-index", "section", "table-of-content", "table-index", "user-index"], "table":["background", "table"], "table-cell":["body", "covered-table-cell", "even-columns", "even-rows", "first-column", "first-row", "last-column", "last-row", "odd-columns", "odd-rows", "table-cell"], "table-column":["table-column"], "table-row":["table-row"], "text":["a", "index-entry-chapter", "index-entry-link-end", 
  "index-entry-link-start", "index-entry-page-number", "index-entry-span", "index-entry-tab-stop", "index-entry-text", "index-title-template", "linenumbering-configuration", "list-level-style-number", "list-level-style-bullet", "outline-level-style", "span"], "list":["list-item"]}, textPropertySimpleMapping = [[fons, "color", "color"], [fons, "background-color", "background-color"], [fons, "font-weight", "font-weight"], [fons, "font-style", "font-style"]], bgImageSimpleMapping = [[stylens, "repeat", 
  "background-repeat"]], paragraphPropertySimpleMapping = [[fons, "background-color", "background-color"], [fons, "text-align", "text-align"], [fons, "text-indent", "text-indent"], [fons, "padding", "padding"], [fons, "padding-left", "padding-left"], [fons, "padding-right", "padding-right"], [fons, "padding-top", "padding-top"], [fons, "padding-bottom", "padding-bottom"], [fons, "border-left", "border-left"], [fons, "border-right", "border-right"], [fons, "border-top", "border-top"], [fons, "border-bottom", 
  "border-bottom"], [fons, "margin", "margin"], [fons, "margin-left", "margin-left"], [fons, "margin-right", "margin-right"], [fons, "margin-top", "margin-top"], [fons, "margin-bottom", "margin-bottom"], [fons, "border", "border"]], graphicPropertySimpleMapping = [[fons, "background-color", "background-color"], [fons, "min-height", "min-height"], [drawns, "stroke", "border"], [svgns, "stroke-color", "border-color"], [svgns, "stroke-width", "border-width"], [fons, "border", "border"], [fons, "border-left", 
  "border-left"], [fons, "border-right", "border-right"], [fons, "border-top", "border-top"], [fons, "border-bottom", "border-bottom"]], tablecellPropertySimpleMapping = [[fons, "background-color", "background-color"], [fons, "border-left", "border-left"], [fons, "border-right", "border-right"], [fons, "border-top", "border-top"], [fons, "border-bottom", "border-bottom"], [fons, "border", "border"]], tablecolumnPropertySimpleMapping = [[stylens, "column-width", "width"]], tablerowPropertySimpleMapping = 
  [[stylens, "row-height", "height"], [fons, "keep-together", null]], tablePropertySimpleMapping = [[stylens, "width", "width"], [fons, "margin-left", "margin-left"], [fons, "margin-right", "margin-right"], [fons, "margin-top", "margin-top"], [fons, "margin-bottom", "margin-bottom"]], pageContentPropertySimpleMapping = [[fons, "background-color", "background-color"], [fons, "padding", "padding"], [fons, "padding-left", "padding-left"], [fons, "padding-right", "padding-right"], [fons, "padding-top", 
  "padding-top"], [fons, "padding-bottom", "padding-bottom"], [fons, "border", "border"], [fons, "border-left", "border-left"], [fons, "border-right", "border-right"], [fons, "border-top", "border-top"], [fons, "border-bottom", "border-bottom"], [fons, "margin", "margin"], [fons, "margin-left", "margin-left"], [fons, "margin-right", "margin-right"], [fons, "margin-top", "margin-top"], [fons, "margin-bottom", "margin-bottom"]], pageSizePropertySimpleMapping = [[fons, "page-width", "width"], [fons, 
  "page-height", "height"]], borderPropertyMap = {"border":true, "border-left":true, "border-right":true, "border-top":true, "border-bottom":true, "stroke-width":true}, marginPropertyMap = {"margin":true, "margin-left":true, "margin-right":true, "margin-top":true, "margin-bottom":true}, fontFaceDeclsMap = {}, utils = odf.OdfUtils, documentType, odfRoot, defaultFontSize, xpath = xmldom.XPath, cssUnits = new core.CSSUnits;
  function createSelector(family, name) {
    var prefix = familynamespaceprefixes[family], namepart, selector;
    if (prefix === undefined) {
      return null;
    }
    if (name) {
      namepart = "[" + prefix + '|style-name="' + name + '"]';
    } else {
      namepart = "";
    }
    if (prefix === "presentation") {
      prefix = "draw";
      if (name) {
        namepart = '[presentation|style-name="' + name + '"]';
      } else {
        namepart = "";
      }
    }
    selector = prefix + "|" + familytagnames[family].join(namepart + "," + prefix + "|") + namepart;
    return selector;
  }
  function getSelectors(family, name, node) {
    var selectors = [], ss, derivedStyles = node.derivedStyles, n;
    ss = createSelector(family, name);
    if (ss !== null) {
      selectors.push(ss);
    }
    for (n in derivedStyles) {
      if (derivedStyles.hasOwnProperty(n)) {
        ss = getSelectors(family, n, derivedStyles[n]);
        selectors = selectors.concat(ss);
      }
    }
    return selectors;
  }
  function fixBorderWidth(value) {
    var index = value.indexOf(" "), width, theRestOfBorderAttributes;
    if (index !== -1) {
      width = value.substring(0, index);
      theRestOfBorderAttributes = value.substring(index);
    } else {
      width = value;
      theRestOfBorderAttributes = "";
    }
    width = utils.parseLength(width);
    if (width && width.unit === "pt" && width.value < .75) {
      value = "0.75pt" + theRestOfBorderAttributes;
    }
    return value;
  }
  function getParentStyleNode(styleNode) {
    var parentStyleName = "", parentStyleFamily = "", parentStyleNode = null, xp;
    if (styleNode.localName === "default-style") {
      return null;
    }
    parentStyleName = styleNode.getAttributeNS(stylens, "parent-style-name");
    parentStyleFamily = styleNode.getAttributeNS(stylens, "family");
    if (parentStyleName) {
      xp = "//style:*[@style:name='" + parentStyleName + "'][@style:family='" + parentStyleFamily + "']";
    } else {
      xp = "//style:default-style[@style:family='" + parentStyleFamily + "']";
    }
    parentStyleNode = xpath.getODFElementsWithXPath(odfRoot, xp, odf.Namespaces.lookupNamespaceURI)[0];
    return parentStyleNode;
  }
  function fixMargin(props, namespace, name, value) {
    var length = utils.parseLength(value), multiplier, parentStyle, parentLength, result, properties;
    if (!length || length.unit !== "%") {
      return value;
    }
    multiplier = length.value / 100;
    parentStyle = getParentStyleNode(props.parentNode);
    result = "0";
    while (parentStyle) {
      properties = domUtils.getDirectChild(parentStyle, stylens, "paragraph-properties");
      if (properties) {
        parentLength = utils.parseLength(properties.getAttributeNS(namespace, name));
        if (parentLength) {
          if (parentLength.unit !== "%") {
            result = parentLength.value * multiplier + parentLength.unit;
            break;
          }
          multiplier *= parentLength.value / 100;
        }
      }
      parentStyle = getParentStyleNode(parentStyle);
    }
    return result;
  }
  function applySimpleMapping(props, mapping) {
    var rule = "", i, r, value;
    for (i = 0;i < mapping.length;i += 1) {
      r = mapping[i];
      value = props.getAttributeNS(r[0], r[1]);
      if (value) {
        value = value.trim();
        if (borderPropertyMap.hasOwnProperty(r[1])) {
          value = fixBorderWidth(value);
        } else {
          if (marginPropertyMap.hasOwnProperty(r[1])) {
            value = fixMargin(props, r[0], r[1], value);
          }
        }
        if (r[2]) {
          rule += r[2] + ":" + value + ";";
        }
      }
    }
    return rule;
  }
  function getFontSize(styleNode) {
    var props = domUtils.getDirectChild(styleNode, stylens, "text-properties");
    if (props) {
      return utils.parseFoFontSize(props.getAttributeNS(fons, "font-size"));
    }
    return null;
  }
  function parseTextPosition(position) {
    var parts = styleParseUtils.parseAttributeList(position);
    return {verticalTextPosition:parts[0], fontHeight:parts[1]};
  }
  function getTextProperties(props) {
    var rule = "", fontName, fontSize, value, textDecorationLine = "", textDecorationStyle = "", textPosition, fontSizeRule = "", sizeMultiplier = 1, textFamilyStyleNode;
    rule += applySimpleMapping(props, textPropertySimpleMapping);
    value = props.getAttributeNS(stylens, "text-underline-style");
    if (value === "solid") {
      textDecorationLine += " underline";
    }
    value = props.getAttributeNS(stylens, "text-line-through-style");
    if (value === "solid") {
      textDecorationLine += " line-through";
    }
    if (textDecorationLine.length) {
      rule += "text-decoration:" + textDecorationLine + ";\n";
      rule += "text-decoration-line:" + textDecorationLine + ";\n";
      rule += "-moz-text-decoration-line:" + textDecorationLine + ";\n";
    }
    value = props.getAttributeNS(stylens, "text-line-through-type");
    switch(value) {
      case "double":
        textDecorationStyle += " double";
        break;
      case "single":
        textDecorationStyle += " single";
        break;
    }
    if (textDecorationStyle) {
      rule += "text-decoration-style:" + textDecorationStyle + ";\n";
      rule += "-moz-text-decoration-style:" + textDecorationStyle + ";\n";
    }
    fontName = props.getAttributeNS(stylens, "font-name") || props.getAttributeNS(fons, "font-family");
    if (fontName) {
      value = fontFaceDeclsMap[fontName];
      rule += "font-family: " + (value || fontName) + ";";
    }
    value = props.getAttributeNS(stylens, "text-position");
    if (value) {
      textPosition = parseTextPosition(value);
      rule += "vertical-align: " + textPosition.verticalTextPosition + "\n; ";
      if (textPosition.fontHeight) {
        sizeMultiplier = parseFloat(textPosition.fontHeight) / 100;
      }
    }
    if (props.hasAttributeNS(fons, "font-size") || sizeMultiplier !== 1) {
      textFamilyStyleNode = props.parentNode;
      while (textFamilyStyleNode) {
        fontSize = getFontSize(textFamilyStyleNode);
        if (fontSize) {
          if (fontSize.unit !== "%") {
            fontSizeRule = "font-size: " + fontSize.value * sizeMultiplier + fontSize.unit + ";";
            break;
          }
          sizeMultiplier *= fontSize.value / 100;
        }
        textFamilyStyleNode = getParentStyleNode(textFamilyStyleNode);
      }
      if (!fontSizeRule) {
        fontSizeRule = "font-size: " + parseFloat(defaultFontSize) * sizeMultiplier + cssUnits.getUnits(defaultFontSize) + ";";
      }
    }
    rule += fontSizeRule;
    return rule;
  }
  function getParagraphProperties(props) {
    var rule = "", bgimage, url, lineHeight;
    rule += applySimpleMapping(props, paragraphPropertySimpleMapping);
    bgimage = domUtils.getDirectChild(props, stylens, "background-image");
    if (bgimage) {
      url = bgimage.getAttributeNS(xlinkns, "href");
      if (url) {
        rule += "background-image: url('odfkit:" + url + "');";
        rule += applySimpleMapping(bgimage, bgImageSimpleMapping);
      }
    }
    lineHeight = props.getAttributeNS(fons, "line-height");
    if (lineHeight && lineHeight !== "normal") {
      lineHeight = utils.parseFoLineHeight(lineHeight);
      if (lineHeight.unit !== "%") {
        rule += "line-height: " + lineHeight.value + lineHeight.unit + ";";
      } else {
        rule += "line-height: " + lineHeight.value / 100 + ";";
      }
    }
    return rule;
  }
  function matchToRgb(m, r, g, b) {
    return r + r + g + g + b + b;
  }
  function hexToRgb(hex) {
    var result, shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
    hex = hex.replace(shorthandRegex, matchToRgb);
    result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {r:parseInt(result[1], 16), g:parseInt(result[2], 16), b:parseInt(result[3], 16)} : null;
  }
  function isNumber(n) {
    return !isNaN(parseFloat(n));
  }
  function getGraphicProperties(props) {
    var rule = "", alpha, bgcolor, fill;
    rule += applySimpleMapping(props, graphicPropertySimpleMapping);
    alpha = props.getAttributeNS(drawns, "opacity");
    fill = props.getAttributeNS(drawns, "fill");
    bgcolor = props.getAttributeNS(drawns, "fill-color");
    if (fill === "solid" || fill === "hatch") {
      if (bgcolor && bgcolor !== "none") {
        alpha = isNumber(alpha) ? parseFloat(alpha) / 100 : 1;
        bgcolor = hexToRgb(bgcolor);
        if (bgcolor) {
          rule += "background-color: rgba(" + bgcolor.r + "," + bgcolor.g + "," + bgcolor.b + "," + alpha + ");";
        }
      } else {
        rule += "background: none;";
      }
    } else {
      if (fill === "none") {
        rule += "background: none;";
      }
    }
    return rule;
  }
  function getDrawingPageProperties(props) {
    var rule = "";
    rule += applySimpleMapping(props, graphicPropertySimpleMapping);
    if (props.getAttributeNS(presentationns, "background-visible") === "true") {
      rule += "background: none;";
    }
    return rule;
  }
  function getTableCellProperties(props) {
    var rule = "";
    rule += applySimpleMapping(props, tablecellPropertySimpleMapping);
    return rule;
  }
  function getTableRowProperties(props) {
    var rule = "";
    rule += applySimpleMapping(props, tablerowPropertySimpleMapping);
    return rule;
  }
  function getTableColumnProperties(props) {
    var rule = "";
    rule += applySimpleMapping(props, tablecolumnPropertySimpleMapping);
    return rule;
  }
  function getTableProperties(props) {
    var rule = "", borderModel;
    rule += applySimpleMapping(props, tablePropertySimpleMapping);
    borderModel = props.getAttributeNS(tablens, "border-model");
    if (borderModel === "collapsing") {
      rule += "border-collapse:collapse;";
    } else {
      if (borderModel === "separating") {
        rule += "border-collapse:separate;";
      }
    }
    return rule;
  }
  function getDerivedStyleNames(styleName, node) {
    var styleNames = [styleName], derivedStyles = node.derivedStyles;
    Object.keys(derivedStyles).forEach(function(styleName) {
      var dsn = getDerivedStyleNames(styleName, derivedStyles[styleName]);
      styleNames = styleNames.concat(dsn);
    });
    return styleNames;
  }
  function addDrawPageFrameDisplayRules(sheet, styleName, properties, node) {
    var frameClasses = ["page-number", "date-time", "header", "footer"], styleNames = getDerivedStyleNames(styleName, node), visibleFrameClasses = [], invisibleFrameClasses = [];
    function insertFrameVisibilityRule(controlledFrameClasses, visibility) {
      var selectors = [], rule;
      controlledFrameClasses.forEach(function(frameClass) {
        styleNames.forEach(function(styleName) {
          selectors.push('draw|page[webodfhelper|page-style-name="' + styleName + '"] draw|frame[presentation|class="' + frameClass + '"]');
        });
      });
      if (selectors.length > 0) {
        rule = selectors.join(",") + "{visibility:" + visibility + ";}";
        sheet.insertRule(rule, sheet.cssRules.length);
      }
    }
    frameClasses.forEach(function(frameClass) {
      var displayValue;
      displayValue = properties.getAttributeNS(presentationns, "display-" + frameClass);
      if (displayValue === "true") {
        visibleFrameClasses.push(frameClass);
      } else {
        if (displayValue === "false") {
          invisibleFrameClasses.push(frameClass);
        }
      }
    });
    insertFrameVisibilityRule(visibleFrameClasses, "visible");
    insertFrameVisibilityRule(invisibleFrameClasses, "hidden");
  }
  function addStyleRule(sheet, family, name, node) {
    var selectors = getSelectors(family, name, node), selector = selectors.join(","), rule = "", properties;
    properties = domUtils.getDirectChild(node.element, stylens, "text-properties");
    if (properties) {
      rule += getTextProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "paragraph-properties");
    if (properties) {
      rule += getParagraphProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "graphic-properties");
    if (properties) {
      rule += getGraphicProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "drawing-page-properties");
    if (properties) {
      rule += getDrawingPageProperties(properties);
      addDrawPageFrameDisplayRules(sheet, name, properties, node);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "table-cell-properties");
    if (properties) {
      rule += getTableCellProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "table-row-properties");
    if (properties) {
      rule += getTableRowProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "table-column-properties");
    if (properties) {
      rule += getTableColumnProperties(properties);
    }
    properties = domUtils.getDirectChild(node.element, stylens, "table-properties");
    if (properties) {
      rule += getTableProperties(properties);
    }
    if (rule.length === 0) {
      return;
    }
    rule = selector + "{" + rule + "}";
    sheet.insertRule(rule, sheet.cssRules.length);
  }
  function addPageStyleRules(sheet, node) {
    var rule = "", imageProps, url, contentLayoutRule = "", pageSizeRule = "", props = domUtils.getDirectChild(node, stylens, "page-layout-properties"), stylename, masterStyles, e, masterStyleName;
    if (!props) {
      return;
    }
    stylename = node.getAttributeNS(stylens, "name");
    rule += applySimpleMapping(props, pageContentPropertySimpleMapping);
    imageProps = domUtils.getDirectChild(props, stylens, "background-image");
    if (imageProps) {
      url = imageProps.getAttributeNS(xlinkns, "href");
      if (url) {
        rule += "background-image: url('odfkit:" + url + "');";
        rule += applySimpleMapping(imageProps, bgImageSimpleMapping);
      }
    }
    if (documentType === "presentation") {
      masterStyles = domUtils.getDirectChild(node.parentNode.parentNode, officens, "master-styles");
      e = masterStyles && masterStyles.firstElementChild;
      while (e) {
        if (e.namespaceURI === stylens && e.localName === "master-page" && e.getAttributeNS(stylens, "page-layout-name") === stylename) {
          masterStyleName = e.getAttributeNS(stylens, "name");
          contentLayoutRule = 'draw|page[draw|master-page-name="' + masterStyleName + '"] {' + rule + "}";
          pageSizeRule = 'office|body, draw|page[draw|master-page-name="' + masterStyleName + '"] {' + applySimpleMapping(props, pageSizePropertySimpleMapping) + " }";
          sheet.insertRule(contentLayoutRule, sheet.cssRules.length);
          sheet.insertRule(pageSizeRule, sheet.cssRules.length);
        }
        e = e.nextElementSibling;
      }
    } else {
      if (documentType === "text") {
        contentLayoutRule = "office|text {" + rule + "}";
        rule = "";
        pageSizeRule = "office|body {" + "width: " + props.getAttributeNS(fons, "page-width") + ";" + "}";
        sheet.insertRule(contentLayoutRule, sheet.cssRules.length);
        sheet.insertRule(pageSizeRule, sheet.cssRules.length);
      }
    }
  }
  function addRule(sheet, family, name, node) {
    if (family === "page") {
      addPageStyleRules(sheet, node.element);
    } else {
      addStyleRule(sheet, family, name, node);
    }
  }
  function addRules(sheet, family, name, node) {
    addRule(sheet, family, name, node);
    var n;
    for (n in node.derivedStyles) {
      if (node.derivedStyles.hasOwnProperty(n)) {
        addRules(sheet, family, n, node.derivedStyles[n]);
      }
    }
  }
  this.style2css = function(doctype, rootNode, stylesheet, fontFaceMap, styleTree) {
    var tree, rule, name, family;
    function insertCSSNamespace(prefix, ns) {
      rule = "@namespace " + prefix + " url(" + ns + ");";
      try {
        stylesheet.insertRule(rule, stylesheet.cssRules.length);
      } catch (ignore) {
      }
    }
    odfRoot = rootNode;
    while (stylesheet.cssRules.length) {
      stylesheet.deleteRule(stylesheet.cssRules.length - 1);
    }
    odf.Namespaces.forEachPrefix(insertCSSNamespace);
    insertCSSNamespace("webodfhelper", webodfhelperns);
    fontFaceDeclsMap = fontFaceMap;
    documentType = doctype;
    defaultFontSize = runtime.getWindow().getComputedStyle(document.body, null).getPropertyValue("font-size") || "12pt";
    for (family in familynamespaceprefixes) {
      if (familynamespaceprefixes.hasOwnProperty(family)) {
        tree = styleTree[family];
        for (name in tree) {
          if (tree.hasOwnProperty(name)) {
            addRules(stylesheet, family, name, tree[name]);
          }
        }
      }
    }
  };
};
(function() {
  function Point(x, y) {
    var self = this;
    this.getDistance = function(point) {
      var xOffset = self.x - point.x, yOffset = self.y - point.y;
      return Math.sqrt(xOffset * xOffset + yOffset * yOffset);
    };
    this.getCenter = function(point) {
      return new Point((self.x + point.x) / 2, (self.y + point.y) / 2);
    };
    this.x;
    this.y;
    function init() {
      self.x = x;
      self.y = y;
    }
    init();
  }
  gui.ZoomHelper = function() {
    var zoomableElement, panPoint, previousPanPoint, firstPinchDistance, zoom, previousZoom, maxZoom = 4, offsetParent, parentElement, events = new core.EventNotifier([gui.ZoomHelper.signalZoomChanged]), gestures = {NONE:0, SCROLL:1, PINCH:2}, currentGesture = gestures.NONE, requiresCustomScrollBars = runtime.getWindow().hasOwnProperty("ontouchstart"), parentOverflow = "";
    function applyCSSTransform(x, y, scale, is3D) {
      var transformCommand;
      if (is3D) {
        transformCommand = "translate3d(" + x + "px, " + y + "px, 0) scale3d(" + scale + ", " + scale + ", 1)";
      } else {
        transformCommand = "translate(" + x + "px, " + y + "px) scale(" + scale + ")";
      }
      zoomableElement.style.WebkitTransform = transformCommand;
      zoomableElement.style.MozTransform = transformCommand;
      zoomableElement.style.msTransform = transformCommand;
      zoomableElement.style.OTransform = transformCommand;
      zoomableElement.style.transform = transformCommand;
    }
    function applyTransform(is3D) {
      if (is3D) {
        applyCSSTransform(-panPoint.x, -panPoint.y, zoom, true);
      } else {
        applyCSSTransform(0, 0, zoom, true);
        applyCSSTransform(0, 0, zoom, false);
      }
    }
    function applyFastTransform() {
      applyTransform(true);
    }
    function applyDetailedTransform() {
      applyTransform(false);
    }
    function enableScrollBars(enable) {
      if (!offsetParent || !requiresCustomScrollBars) {
        return;
      }
      var initialOverflow = offsetParent.style.overflow, enabled = offsetParent.classList.contains("webodf-customScrollbars");
      if (enable && enabled || !enable && !enabled) {
        return;
      }
      if (enable) {
        offsetParent.classList.add("webodf-customScrollbars");
        offsetParent.style.overflow = "hidden";
        runtime.requestAnimationFrame(function() {
          offsetParent.style.overflow = initialOverflow;
        });
      } else {
        offsetParent.classList.remove("webodf-customScrollbars");
      }
    }
    function removeScroll() {
      applyCSSTransform(-panPoint.x, -panPoint.y, zoom, true);
      offsetParent.scrollLeft = 0;
      offsetParent.scrollTop = 0;
      parentOverflow = parentElement.style.overflow;
      parentElement.style.overflow = "visible";
      enableScrollBars(false);
    }
    function restoreScroll() {
      applyCSSTransform(0, 0, zoom, true);
      offsetParent.scrollLeft = panPoint.x;
      offsetParent.scrollTop = panPoint.y;
      parentElement.style.overflow = parentOverflow || "";
      enableScrollBars(true);
    }
    function getPoint(touch) {
      return new Point(touch.pageX - zoomableElement.offsetLeft, touch.pageY - zoomableElement.offsetTop);
    }
    function sanitizePointForPan(point) {
      return new Point(Math.min(Math.max(point.x, zoomableElement.offsetLeft), (zoomableElement.offsetLeft + zoomableElement.offsetWidth) * zoom - offsetParent.clientWidth), Math.min(Math.max(point.y, zoomableElement.offsetTop), (zoomableElement.offsetTop + zoomableElement.offsetHeight) * zoom - offsetParent.clientHeight));
    }
    function processPan(point) {
      if (previousPanPoint) {
        panPoint.x -= point.x - previousPanPoint.x;
        panPoint.y -= point.y - previousPanPoint.y;
        panPoint = sanitizePointForPan(panPoint);
      }
      previousPanPoint = point;
    }
    function processZoom(zoomPoint, incrementalZoom) {
      var originalZoom = zoom, actuallyIncrementedZoom, minZoom = Math.min(maxZoom, zoomableElement.offsetParent.clientWidth / zoomableElement.offsetWidth);
      zoom = previousZoom * incrementalZoom;
      zoom = Math.min(Math.max(zoom, minZoom), maxZoom);
      actuallyIncrementedZoom = zoom / originalZoom;
      panPoint.x += (actuallyIncrementedZoom - 1) * (zoomPoint.x + panPoint.x);
      panPoint.y += (actuallyIncrementedZoom - 1) * (zoomPoint.y + panPoint.y);
    }
    function processPinch(point1, point2) {
      var zoomPoint = point1.getCenter(point2), pinchDistance = point1.getDistance(point2), incrementalZoom = pinchDistance / firstPinchDistance;
      processPan(zoomPoint);
      processZoom(zoomPoint, incrementalZoom);
    }
    function prepareGesture(event) {
      var fingers = event.touches.length, point1 = fingers > 0 ? getPoint(event.touches[0]) : null, point2 = fingers > 1 ? getPoint(event.touches[1]) : null;
      if (point1 && point2) {
        firstPinchDistance = point1.getDistance(point2);
        previousZoom = zoom;
        previousPanPoint = point1.getCenter(point2);
        removeScroll();
        currentGesture = gestures.PINCH;
      } else {
        if (point1) {
          previousPanPoint = point1;
          currentGesture = gestures.SCROLL;
        }
      }
    }
    function processGesture(event) {
      var fingers = event.touches.length, point1 = fingers > 0 ? getPoint(event.touches[0]) : null, point2 = fingers > 1 ? getPoint(event.touches[1]) : null;
      if (point1 && point2) {
        event.preventDefault();
        if (currentGesture === gestures.SCROLL) {
          currentGesture = gestures.PINCH;
          removeScroll();
          firstPinchDistance = point1.getDistance(point2);
          return;
        }
        processPinch(point1, point2);
        applyFastTransform();
      } else {
        if (point1) {
          if (currentGesture === gestures.PINCH) {
            currentGesture = gestures.SCROLL;
            restoreScroll();
            return;
          }
          processPan(point1);
        }
      }
    }
    function sanitizeGesture() {
      if (currentGesture === gestures.PINCH) {
        events.emit(gui.ZoomHelper.signalZoomChanged, zoom);
        restoreScroll();
        applyDetailedTransform();
      }
      currentGesture = gestures.NONE;
    }
    this.subscribe = function(eventid, cb) {
      events.subscribe(eventid, cb);
    };
    this.unsubscribe = function(eventid, cb) {
      events.unsubscribe(eventid, cb);
    };
    this.getZoomLevel = function() {
      return zoom;
    };
    this.setZoomLevel = function(zoomLevel) {
      if (zoomableElement) {
        zoom = zoomLevel;
        applyDetailedTransform();
        events.emit(gui.ZoomHelper.signalZoomChanged, zoom);
      }
    };
    function registerGestureListeners() {
      if (offsetParent) {
        offsetParent.addEventListener("touchstart", prepareGesture, false);
        offsetParent.addEventListener("touchmove", processGesture, false);
        offsetParent.addEventListener("touchend", sanitizeGesture, false);
      }
    }
    function unregisterGestureListeners() {
      if (offsetParent) {
        offsetParent.removeEventListener("touchstart", prepareGesture, false);
        offsetParent.removeEventListener("touchmove", processGesture, false);
        offsetParent.removeEventListener("touchend", sanitizeGesture, false);
      }
    }
    this.destroy = function(callback) {
      unregisterGestureListeners();
      enableScrollBars(false);
      callback();
    };
    this.setZoomableElement = function(element) {
      unregisterGestureListeners();
      zoomableElement = element;
      offsetParent = zoomableElement.offsetParent;
      parentElement = zoomableElement.parentNode;
      applyDetailedTransform();
      registerGestureListeners();
      enableScrollBars(true);
    };
    function init() {
      zoom = 1;
      previousZoom = 1;
      panPoint = new Point(0, 0);
    }
    init();
  };
  gui.ZoomHelper.signalZoomChanged = "zoomChanged";
})();
ops.Canvas = function Canvas() {
};
ops.Canvas.prototype.getZoomLevel = function() {
};
ops.Canvas.prototype.getElement = function() {
};
ops.Canvas.prototype.getSizer = function() {
};
ops.Canvas.prototype.getZoomHelper = function() {
};
(function() {
  function LoadingQueue() {
    var queue = [], taskRunning = false;
    function run(task) {
      taskRunning = true;
      runtime.setTimeout(function() {
        try {
          task();
        } catch (e) {
          runtime.log(String(e) + "\n" + e.stack);
        }
        taskRunning = false;
        if (queue.length > 0) {
          run(queue.pop());
        }
      }, 10);
    }
    this.clearQueue = function() {
      queue.length = 0;
    };
    this.addToQueue = function(loadingTask) {
      if (queue.length === 0 && !taskRunning) {
        return run(loadingTask);
      }
      queue.push(loadingTask);
    };
  }
  function PageSwitcher(css) {
    var sheet = css.sheet, position = 1;
    function updateCSS() {
      while (sheet.cssRules.length > 0) {
        sheet.deleteRule(0);
      }
      sheet.insertRule("#shadowContent draw|page {display:none;}", 0);
      sheet.insertRule("office|presentation draw|page {display:none;}", 1);
      sheet.insertRule("#shadowContent draw|page:nth-of-type(" + position + ") {display:block;}", 2);
      sheet.insertRule("office|presentation draw|page:nth-of-type(" + position + ") {display:block;}", 3);
    }
    this.showFirstPage = function() {
      position = 1;
      updateCSS();
    };
    this.showNextPage = function() {
      position += 1;
      updateCSS();
    };
    this.showPreviousPage = function() {
      if (position > 1) {
        position -= 1;
        updateCSS();
      }
    };
    this.showPage = function(n) {
      if (n > 0) {
        position = n;
        updateCSS();
      }
    };
    this.css = css;
    this.destroy = function(callback) {
      css.parentNode.removeChild(css);
      callback();
    };
  }
  function listenEvent(eventTarget, eventType, eventHandler) {
    if (eventTarget.addEventListener) {
      eventTarget.addEventListener(eventType, eventHandler, false);
    } else {
      if (eventTarget.attachEvent) {
        eventType = "on" + eventType;
        eventTarget.attachEvent(eventType, eventHandler);
      } else {
        eventTarget["on" + eventType] = eventHandler;
      }
    }
  }
  var drawns = odf.Namespaces.drawns, fons = odf.Namespaces.fons, officens = odf.Namespaces.officens, stylens = odf.Namespaces.stylens, svgns = odf.Namespaces.svgns, tablens = odf.Namespaces.tablens, textns = odf.Namespaces.textns, xlinkns = odf.Namespaces.xlinkns, presentationns = odf.Namespaces.presentationns, webodfhelperns = "urn:webodf:names:helper", xpath = xmldom.XPath, domUtils = core.DomUtils;
  function clearCSSStyleSheet(style) {
    var stylesheet = style.sheet, cssRules = stylesheet.cssRules;
    while (cssRules.length) {
      stylesheet.deleteRule(cssRules.length - 1);
    }
  }
  function handleStyles(odfcontainer, formatting, stylesxmlcss) {
    var style2css = new odf.Style2CSS, list2css = new odf.ListStyleToCss, styleSheet = stylesxmlcss.sheet, styleTree = (new odf.StyleTree(odfcontainer.rootElement.styles, odfcontainer.rootElement.automaticStyles)).getStyleTree();
    style2css.style2css(odfcontainer.getDocumentType(), odfcontainer.rootElement, styleSheet, formatting.getFontMap(), styleTree);
    list2css.applyListStyles(styleSheet, styleTree, odfcontainer.rootElement.body);
  }
  function handleFonts(odfContainer, fontcss) {
    var fontLoader = new odf.FontLoader;
    fontLoader.loadFonts(odfContainer, fontcss.sheet);
  }
  function dropTemplateDrawFrames(clonedNode) {
    var i, element, presentationClass, clonedDrawFrameElements = domUtils.getElementsByTagNameNS(clonedNode, drawns, "frame");
    for (i = 0;i < clonedDrawFrameElements.length;i += 1) {
      element = clonedDrawFrameElements[i];
      presentationClass = element.getAttributeNS(presentationns, "class");
      if (presentationClass && !/^(date-time|footer|header|page-number)$/.test(presentationClass)) {
        element.parentNode.removeChild(element);
      }
    }
  }
  function getHeaderFooter(odfContainer, frame, headerFooterId) {
    var headerFooter = null, i, declElements = odfContainer.rootElement.body.getElementsByTagNameNS(presentationns, headerFooterId + "-decl"), headerFooterName = frame.getAttributeNS(presentationns, "use-" + headerFooterId + "-name"), element;
    if (headerFooterName && declElements.length > 0) {
      for (i = 0;i < declElements.length;i += 1) {
        element = declElements[i];
        if (element.getAttributeNS(presentationns, "name") === headerFooterName) {
          headerFooter = element.textContent;
          break;
        }
      }
    }
    return headerFooter;
  }
  function setContainerValue(rootElement, ns, localName, value) {
    var i, containerList, document = rootElement.ownerDocument, e;
    containerList = domUtils.getElementsByTagNameNS(rootElement, ns, localName);
    for (i = 0;i < containerList.length;i += 1) {
      domUtils.removeAllChildNodes(containerList[i]);
      if (value) {
        e = containerList[i];
        e.appendChild(document.createTextNode(value));
      }
    }
  }
  function setDrawElementPosition(styleid, frame, stylesheet) {
    frame.setAttributeNS(webodfhelperns, "styleid", styleid);
    var rule, anchor = frame.getAttributeNS(textns, "anchor-type"), x = frame.getAttributeNS(svgns, "x"), y = frame.getAttributeNS(svgns, "y"), width = frame.getAttributeNS(svgns, "width"), height = frame.getAttributeNS(svgns, "height"), minheight = frame.getAttributeNS(fons, "min-height"), minwidth = frame.getAttributeNS(fons, "min-width");
    if (anchor === "as-char") {
      rule = "display: inline-block;";
    } else {
      if (anchor || x || y) {
        rule = "position: absolute;";
      } else {
        if (width || height || minheight || minwidth) {
          rule = "display: block;";
        }
      }
    }
    if (x) {
      rule += "left: " + x + ";";
    }
    if (y) {
      rule += "top: " + y + ";";
    }
    if (width) {
      rule += "width: " + width + ";";
    }
    if (height) {
      rule += "height: " + height + ";";
    }
    if (minheight) {
      rule += "min-height: " + minheight + ";";
    }
    if (minwidth) {
      rule += "min-width: " + minwidth + ";";
    }
    if (rule) {
      rule = "draw|" + frame.localName + '[webodfhelper|styleid="' + styleid + '"] {' + rule + "}";
      stylesheet.insertRule(rule, stylesheet.cssRules.length);
    }
  }
  function getUrlFromBinaryDataElement(image) {
    var node = image.firstChild;
    while (node) {
      if (node.namespaceURI === officens && node.localName === "binary-data") {
        return "data:image/png;base64," + node.textContent.replace(/[\r\n\s]/g, "");
      }
      node = node.nextSibling;
    }
    return "";
  }
  function setImage(id, container, image, stylesheet) {
    image.setAttributeNS(webodfhelperns, "styleid", id);
    var url = image.getAttributeNS(xlinkns, "href"), part;
    function callback(url) {
      var rule;
      if (url) {
        rule = "background-image: url(" + url + ");";
        rule = 'draw|image[webodfhelper|styleid="' + id + '"] {' + rule + "}";
        stylesheet.insertRule(rule, stylesheet.cssRules.length);
      }
    }
    function onchange(p) {
      callback(p.url);
    }
    if (url) {
      try {
        part = container.getPart(url);
        part.onchange = onchange;
        part.load();
      } catch (e) {
        runtime.log("slight problem: " + String(e));
      }
    } else {
      url = getUrlFromBinaryDataElement(image);
      callback(url);
    }
  }
  function formatParagraphAnchors(odfbody) {
    var n, i, nodes = xpath.getODFElementsWithXPath(odfbody, ".//*[*[@text:anchor-type='paragraph']]", odf.Namespaces.lookupNamespaceURI);
    for (i = 0;i < nodes.length;i += 1) {
      n = nodes[i];
      if (n.setAttributeNS) {
        n.setAttributeNS(webodfhelperns, "containsparagraphanchor", true);
      }
    }
  }
  function modifyTables(odffragment, documentns) {
    var i, tableCells, node;
    function modifyTableCell(node) {
      if (node.hasAttributeNS(tablens, "number-columns-spanned")) {
        node.setAttributeNS(documentns, "colspan", node.getAttributeNS(tablens, "number-columns-spanned"));
      }
      if (node.hasAttributeNS(tablens, "number-rows-spanned")) {
        node.setAttributeNS(documentns, "rowspan", node.getAttributeNS(tablens, "number-rows-spanned"));
      }
    }
    tableCells = domUtils.getElementsByTagNameNS(odffragment, tablens, "table-cell");
    for (i = 0;i < tableCells.length;i += 1) {
      node = tableCells[i];
      modifyTableCell(node);
    }
  }
  function modifyLineBreakElements(odffragment) {
    var document = odffragment.ownerDocument, lineBreakElements = domUtils.getElementsByTagNameNS(odffragment, textns, "line-break");
    lineBreakElements.forEach(function(lineBreak) {
      if (!lineBreak.hasChildNodes()) {
        lineBreak.appendChild(document.createElement("br"));
      }
    });
  }
  function expandSpaceElements(odffragment) {
    var spaces, doc = odffragment.ownerDocument;
    function expandSpaceElement(space) {
      var j, count;
      domUtils.removeAllChildNodes(space);
      space.appendChild(doc.createTextNode(" "));
      count = parseInt(space.getAttributeNS(textns, "c"), 10);
      if (count > 1) {
        space.removeAttributeNS(textns, "c");
        for (j = 1;j < count;j += 1) {
          space.parentNode.insertBefore(space.cloneNode(true), space);
        }
      }
    }
    spaces = domUtils.getElementsByTagNameNS(odffragment, textns, "s");
    spaces.forEach(expandSpaceElement);
  }
  function expandTabElements(odffragment) {
    var tabs;
    tabs = domUtils.getElementsByTagNameNS(odffragment, textns, "tab");
    tabs.forEach(function(tab) {
      tab.textContent = "\t";
    });
  }
  function modifyDrawElements(odfbody, stylesheet) {
    var node, drawElements = [], i;
    node = odfbody.firstElementChild;
    while (node && node !== odfbody) {
      if (node.namespaceURI === drawns) {
        drawElements[drawElements.length] = node;
      }
      if (node.firstElementChild) {
        node = node.firstElementChild;
      } else {
        while (node && node !== odfbody && !node.nextElementSibling) {
          node = node.parentNode;
        }
        if (node && node.nextElementSibling) {
          node = node.nextElementSibling;
        }
      }
    }
    for (i = 0;i < drawElements.length;i += 1) {
      node = drawElements[i];
      setDrawElementPosition("frame" + String(i), node, stylesheet);
    }
    formatParagraphAnchors(odfbody);
  }
  function cloneMasterPages(formatting, odfContainer, shadowContent, odfbody, stylesheet) {
    var masterPageName, masterPageElement, styleId, clonedPageElement, clonedElement, clonedDrawElements, pageNumber = 0, i, element, elementToClone, document = odfContainer.rootElement.ownerDocument;
    element = odfbody.firstElementChild;
    if (!(element && element.namespaceURI === officens && (element.localName === "presentation" || element.localName === "drawing"))) {
      return;
    }
    element = element.firstElementChild;
    while (element) {
      masterPageName = element.getAttributeNS(drawns, "master-page-name");
      masterPageElement = masterPageName ? formatting.getMasterPageElement(masterPageName) : null;
      if (masterPageElement) {
        styleId = element.getAttributeNS(webodfhelperns, "styleid");
        clonedPageElement = document.createElementNS(drawns, "draw:page");
        elementToClone = masterPageElement.firstElementChild;
        i = 0;
        while (elementToClone) {
          if (elementToClone.getAttributeNS(presentationns, "placeholder") !== "true") {
            clonedElement = elementToClone.cloneNode(true);
            clonedPageElement.appendChild(clonedElement);
          }
          elementToClone = elementToClone.nextElementSibling;
          i += 1;
        }
        dropTemplateDrawFrames(clonedPageElement);
        clonedDrawElements = domUtils.getElementsByTagNameNS(clonedPageElement, drawns, "*");
        for (i = 0;i < clonedDrawElements.length;i += 1) {
          setDrawElementPosition(styleId + "_" + i, clonedDrawElements[i], stylesheet);
        }
        shadowContent.appendChild(clonedPageElement);
        pageNumber = String(shadowContent.getElementsByTagNameNS(drawns, "page").length);
        setContainerValue(clonedPageElement, textns, "page-number", pageNumber);
        setContainerValue(clonedPageElement, presentationns, "header", getHeaderFooter(odfContainer, element, "header"));
        setContainerValue(clonedPageElement, presentationns, "footer", getHeaderFooter(odfContainer, element, "footer"));
        setDrawElementPosition(styleId, clonedPageElement, stylesheet);
        clonedPageElement.setAttributeNS(webodfhelperns, "page-style-name", element.getAttributeNS(drawns, "style-name"));
        clonedPageElement.setAttributeNS(drawns, "draw:master-page-name", masterPageElement.getAttributeNS(stylens, "name"));
      }
      element = element.nextElementSibling;
    }
  }
  function setVideo(container, plugin) {
    var video, source, url, doc = plugin.ownerDocument, part;
    url = plugin.getAttributeNS(xlinkns, "href");
    function callback(url, mimetype) {
      var ns = doc.documentElement.namespaceURI;
      if (mimetype.substr(0, 6) === "video/") {
        video = doc.createElementNS(ns, "video");
        video.setAttribute("controls", "controls");
        source = doc.createElementNS(ns, "source");
        if (url) {
          source.setAttribute("src", url);
        }
        source.setAttribute("type", mimetype);
        video.appendChild(source);
        plugin.parentNode.appendChild(video);
      } else {
        plugin.innerHtml = "Unrecognised Plugin";
      }
    }
    function onchange(p) {
      callback(p.url, p.mimetype);
    }
    if (url) {
      try {
        part = container.getPart(url);
        part.onchange = onchange;
        part.load();
      } catch (e) {
        runtime.log("slight problem: " + String(e));
      }
    } else {
      runtime.log("using MP4 data fallback");
      url = getUrlFromBinaryDataElement(plugin);
      callback(url, "video/mp4");
    }
  }
  function findWebODFStyleSheet(head) {
    var style = head.firstElementChild;
    while (style && !(style.localName === "style" && style.hasAttribute("webodfcss"))) {
      style = style.nextElementSibling;
    }
    return style;
  }
  function addWebODFStyleSheet(document) {
    var head = document.getElementsByTagName("head")[0], css, style, href, count = document.styleSheets.length;
    style = findWebODFStyleSheet(head);
    if (style) {
      count = parseInt(style.getAttribute("webodfcss"), 10);
      style.setAttribute("webodfcss", count + 1);
      return style;
    }
    if (String(typeof webodf_css) === "string") {
      css = webodf_css;
    } else {
      href = "webodf.css";
      if (runtime.currentDirectory) {
        href = runtime.currentDirectory();
        if (href.length > 0 && href.substr(-1) !== "/") {
          href += "/";
        }
        href += "../webodf.css";
      }
      css = runtime.readFileSync(href, "utf-8");
    }
    style = document.createElementNS(head.namespaceURI, "style");
    style.setAttribute("media", "screen, print, handheld, projection");
    style.setAttribute("type", "text/css");
    style.setAttribute("webodfcss", "1");
    style.appendChild(document.createTextNode(css));
    head.appendChild(style);
    return style;
  }
  function removeWebODFStyleSheet(webodfcss) {
    var count = parseInt(webodfcss.getAttribute("webodfcss"), 10);
    if (count === 1) {
      webodfcss.parentNode.removeChild(webodfcss);
    } else {
      webodfcss.setAttribute("count", count - 1);
    }
  }
  function addStyleSheet(document) {
    var head = document.getElementsByTagName("head")[0], style = document.createElementNS(head.namespaceURI, "style"), text = "";
    style.setAttribute("type", "text/css");
    style.setAttribute("media", "screen, print, handheld, projection");
    odf.Namespaces.forEachPrefix(function(prefix, ns) {
      text += "@namespace " + prefix + " url(" + ns + ");\n";
    });
    text += "@namespace webodfhelper url(" + webodfhelperns + ");\n";
    style.appendChild(document.createTextNode(text));
    head.appendChild(style);
    return style;
  }
  odf.OdfCanvas = function OdfCanvas(element, viewport) {
    runtime.assert(element !== null && element !== undefined, "odf.OdfCanvas constructor needs DOM element");
    runtime.assert(element.ownerDocument !== null && element.ownerDocument !== undefined, "odf.OdfCanvas constructor needs DOM");
    var self = this, doc = element.ownerDocument, odfcontainer, formatting = new odf.Formatting, pageSwitcher, sizer = null, annotationsPane = null, allowAnnotations = false, showAnnotationRemoveButton = false, annotationViewManager = null, webodfcss, fontcss, stylesxmlcss, positioncss, shadowContent, eventHandlers = {}, waitingForDoneTimeoutId, redrawContainerTask, shouldRefreshCss = false, shouldRerenderAnnotations = false, loadingQueue = new LoadingQueue, zoomHelper = new gui.ZoomHelper, canvasViewport = 
    viewport || new gui.SingleScrollViewport(element.parentNode);
    function loadImages(container, odffragment, stylesheet) {
      var i, images, node;
      function loadImage(name, container, node, stylesheet) {
        loadingQueue.addToQueue(function() {
          setImage(name, container, node, stylesheet);
        });
      }
      images = odffragment.getElementsByTagNameNS(drawns, "image");
      for (i = 0;i < images.length;i += 1) {
        node = images.item(i);
        loadImage("image" + String(i), container, node, stylesheet);
      }
    }
    function loadVideos(container, odffragment) {
      var i, plugins, node;
      function loadVideo(container, node) {
        loadingQueue.addToQueue(function() {
          setVideo(container, node);
        });
      }
      plugins = odffragment.getElementsByTagNameNS(drawns, "plugin");
      for (i = 0;i < plugins.length;i += 1) {
        node = plugins.item(i);
        loadVideo(container, node);
      }
    }
    function addEventListener(eventType, eventHandler) {
      var handlers;
      if (eventHandlers.hasOwnProperty(eventType)) {
        handlers = eventHandlers[eventType];
      } else {
        handlers = eventHandlers[eventType] = [];
      }
      if (eventHandler && handlers.indexOf(eventHandler) === -1) {
        handlers.push(eventHandler);
      }
    }
    function fireEvent(eventType, args) {
      if (!eventHandlers.hasOwnProperty(eventType)) {
        return;
      }
      var handlers = eventHandlers[eventType], i;
      for (i = 0;i < handlers.length;i += 1) {
        handlers[i].apply(null, args);
      }
    }
    function fixContainerSize() {
      var minHeight, odfdoc = sizer.firstChild, zoomLevel = zoomHelper.getZoomLevel();
      if (!odfdoc) {
        return;
      }
      sizer.style.WebkitTransformOrigin = "0% 0%";
      sizer.style.MozTransformOrigin = "0% 0%";
      sizer.style.msTransformOrigin = "0% 0%";
      sizer.style.OTransformOrigin = "0% 0%";
      sizer.style.transformOrigin = "0% 0%";
      if (annotationViewManager) {
        minHeight = annotationViewManager.getMinimumHeightForAnnotationPane();
        if (minHeight) {
          sizer.style.minHeight = minHeight;
        } else {
          sizer.style.removeProperty("min-height");
        }
      }
      element.style.width = Math.round(zoomLevel * sizer.offsetWidth) + "px";
      element.style.height = Math.round(zoomLevel * sizer.offsetHeight) + "px";
      element.style.display = "inline-block";
    }
    function redrawContainer() {
      if (shouldRefreshCss) {
        handleStyles(odfcontainer, formatting, stylesxmlcss);
        shouldRefreshCss = false;
      }
      if (shouldRerenderAnnotations) {
        if (annotationViewManager) {
          annotationViewManager.rerenderAnnotations();
        }
        shouldRerenderAnnotations = false;
      }
      fixContainerSize();
    }
    function handleContent(container, odfnode) {
      var css = positioncss.sheet;
      domUtils.removeAllChildNodes(element);
      sizer = doc.createElementNS(element.namespaceURI, "div");
      sizer.style.display = "inline-block";
      sizer.style.background = "white";
      sizer.style.setProperty("float", "left", "important");
      sizer.appendChild(odfnode);
      element.appendChild(sizer);
      annotationsPane = doc.createElementNS(element.namespaceURI, "div");
      annotationsPane.id = "annotationsPane";
      shadowContent = doc.createElementNS(element.namespaceURI, "div");
      shadowContent.id = "shadowContent";
      shadowContent.style.position = "absolute";
      shadowContent.style.top = 0;
      shadowContent.style.left = 0;
      container.getContentElement().appendChild(shadowContent);
      modifyDrawElements(odfnode.body, css);
      cloneMasterPages(formatting, container, shadowContent, odfnode.body, css);
      modifyTables(odfnode.body, element.namespaceURI);
      modifyLineBreakElements(odfnode.body);
      expandSpaceElements(odfnode.body);
      expandTabElements(odfnode.body);
      loadImages(container, odfnode.body, css);
      loadVideos(container, odfnode.body);
      sizer.insertBefore(shadowContent, sizer.firstChild);
      zoomHelper.setZoomableElement(sizer);
    }
    function handleAnnotations(odfnode) {
      var annotationNodes;
      if (allowAnnotations) {
        if (!annotationsPane.parentNode) {
          sizer.appendChild(annotationsPane);
        }
        if (annotationViewManager) {
          annotationViewManager.forgetAnnotations();
        }
        annotationViewManager = new gui.AnnotationViewManager(self, odfnode.body, annotationsPane, showAnnotationRemoveButton);
        annotationNodes = domUtils.getElementsByTagNameNS(odfnode.body, officens, "annotation");
        annotationViewManager.addAnnotations(annotationNodes);
        fixContainerSize();
      } else {
        if (annotationsPane.parentNode) {
          sizer.removeChild(annotationsPane);
          annotationViewManager.forgetAnnotations();
          fixContainerSize();
        }
      }
    }
    function refreshOdf(suppressEvent) {
      function callback() {
        clearCSSStyleSheet(fontcss);
        clearCSSStyleSheet(stylesxmlcss);
        clearCSSStyleSheet(positioncss);
        domUtils.removeAllChildNodes(element);
        element.style.display = "inline-block";
        var odfnode = odfcontainer.rootElement;
        element.ownerDocument.importNode(odfnode, true);
        formatting.setOdfContainer(odfcontainer);
        handleFonts(odfcontainer, fontcss);
        handleStyles(odfcontainer, formatting, stylesxmlcss);
        handleContent(odfcontainer, odfnode);
        handleAnnotations(odfnode);
        if (!suppressEvent) {
          loadingQueue.addToQueue(function() {
            fireEvent("statereadychange", [odfcontainer]);
          });
        }
      }
      if (odfcontainer.state === odf.OdfContainer.DONE) {
        callback();
      } else {
        runtime.log("WARNING: refreshOdf called but ODF was not DONE.");
        waitingForDoneTimeoutId = runtime.setTimeout(function later_cb() {
          if (odfcontainer.state === odf.OdfContainer.DONE) {
            callback();
          } else {
            runtime.log("will be back later...");
            waitingForDoneTimeoutId = runtime.setTimeout(later_cb, 500);
          }
        }, 100);
      }
    }
    this.refreshCSS = function() {
      shouldRefreshCss = true;
      redrawContainerTask.trigger();
    };
    this.refreshSize = function() {
      redrawContainerTask.trigger();
    };
    this.odfContainer = function() {
      return odfcontainer;
    };
    this.setOdfContainer = function(container, suppressEvent) {
      odfcontainer = container;
      refreshOdf(suppressEvent === true);
    };
    function load(url) {
      loadingQueue.clearQueue();
      domUtils.removeAllChildNodes(element);
      element.appendChild(element.ownerDocument.createTextNode(runtime.tr("Loading") + url + "..."));
      element.removeAttribute("style");
      odfcontainer = new odf.OdfContainer(url, function(container) {
        odfcontainer = container;
        refreshOdf(false);
      });
    }
    this["load"] = load;
    this.load = load;
    this.save = function(callback) {
      odfcontainer.save(callback);
    };
    this.addListener = function(eventName, handler) {
      switch(eventName) {
        case "click":
          listenEvent(element, eventName, handler);
          break;
        default:
          addEventListener(eventName, handler);
          break;
      }
    };
    this.getFormatting = function() {
      return formatting;
    };
    this.getAnnotationViewManager = function() {
      return annotationViewManager;
    };
    this.refreshAnnotations = function() {
      handleAnnotations(odfcontainer.rootElement);
    };
    this.rerenderAnnotations = function() {
      if (annotationViewManager) {
        shouldRerenderAnnotations = true;
        redrawContainerTask.trigger();
      }
    };
    this.getSizer = function() {
      return sizer;
    };
    this.enableAnnotations = function(allow, showRemoveButton) {
      if (allow !== allowAnnotations) {
        allowAnnotations = allow;
        showAnnotationRemoveButton = showRemoveButton;
        if (odfcontainer) {
          handleAnnotations(odfcontainer.rootElement);
        }
      }
    };
    this.addAnnotation = function(annotation) {
      if (annotationViewManager) {
        annotationViewManager.addAnnotations([annotation]);
        fixContainerSize();
      }
    };
    this.forgetAnnotation = function(annotation) {
      if (annotationViewManager) {
        annotationViewManager.forgetAnnotation(annotation);
        fixContainerSize();
      }
    };
    this.getZoomHelper = function() {
      return zoomHelper;
    };
    this.setZoomLevel = function(zoom) {
      zoomHelper.setZoomLevel(zoom);
    };
    this.getZoomLevel = function() {
      return zoomHelper.getZoomLevel();
    };
    this.fitToContainingElement = function(width, height) {
      var zoomLevel = zoomHelper.getZoomLevel(), realWidth = element.offsetWidth / zoomLevel, realHeight = element.offsetHeight / zoomLevel, zoom;
      zoom = width / realWidth;
      if (height / realHeight < zoom) {
        zoom = height / realHeight;
      }
      zoomHelper.setZoomLevel(zoom);
    };
    this.fitToWidth = function(width) {
      var realWidth = element.offsetWidth / zoomHelper.getZoomLevel();
      zoomHelper.setZoomLevel(width / realWidth);
    };
    this.fitSmart = function(width, height) {
      var realWidth, realHeight, newScale, zoomLevel = zoomHelper.getZoomLevel();
      realWidth = element.offsetWidth / zoomLevel;
      realHeight = element.offsetHeight / zoomLevel;
      newScale = width / realWidth;
      if (height !== undefined) {
        if (height / realHeight < newScale) {
          newScale = height / realHeight;
        }
      }
      zoomHelper.setZoomLevel(Math.min(1, newScale));
    };
    this.fitToHeight = function(height) {
      var realHeight = element.offsetHeight / zoomHelper.getZoomLevel();
      zoomHelper.setZoomLevel(height / realHeight);
    };
    this.showFirstPage = function() {
      pageSwitcher.showFirstPage();
    };
    this.showNextPage = function() {
      pageSwitcher.showNextPage();
    };
    this.showPreviousPage = function() {
      pageSwitcher.showPreviousPage();
    };
    this.showPage = function(n) {
      pageSwitcher.showPage(n);
      fixContainerSize();
    };
    this.getElement = function() {
      return element;
    };
    this.getViewport = function() {
      return canvasViewport;
    };
    this.addCssForFrameWithImage = function(frame) {
      var frameName = frame.getAttributeNS(drawns, "name"), fc = frame.firstElementChild;
      setDrawElementPosition(frameName, frame, positioncss.sheet);
      if (fc) {
        setImage(frameName + "img", odfcontainer, fc, positioncss.sheet);
      }
    };
    this.destroy = function(callback) {
      var head = doc.getElementsByTagName("head")[0], cleanup = [pageSwitcher.destroy, redrawContainerTask.destroy];
      runtime.clearTimeout(waitingForDoneTimeoutId);
      if (annotationsPane && annotationsPane.parentNode) {
        annotationsPane.parentNode.removeChild(annotationsPane);
      }
      zoomHelper.destroy(function() {
        if (sizer) {
          element.removeChild(sizer);
          sizer = null;
        }
      });
      removeWebODFStyleSheet(webodfcss);
      head.removeChild(fontcss);
      head.removeChild(stylesxmlcss);
      head.removeChild(positioncss);
      core.Async.destroyAll(cleanup, callback);
    };
    function init() {
      webodfcss = addWebODFStyleSheet(doc);
      pageSwitcher = new PageSwitcher(addStyleSheet(doc));
      fontcss = addStyleSheet(doc);
      stylesxmlcss = addStyleSheet(doc);
      positioncss = addStyleSheet(doc);
      redrawContainerTask = core.Task.createRedrawTask(redrawContainer);
      zoomHelper.subscribe(gui.ZoomHelper.signalZoomChanged, fixContainerSize);
    }
    init();
  };
})();
odf.StepUtils = function StepUtils() {
  function getContentBounds(stepIterator) {
    var container = stepIterator.container(), offset, contentBounds;
    runtime.assert(stepIterator.isStep(), "Step iterator must be on a step");
    if (container.nodeType === Node.TEXT_NODE && stepIterator.offset() > 0) {
      offset = stepIterator.offset();
    } else {
      container = stepIterator.leftNode();
      if (container && container.nodeType === Node.TEXT_NODE) {
        offset = container.length;
      }
    }
    if (container) {
      if (container.nodeType === Node.TEXT_NODE) {
        runtime.assert(offset > 0, "Empty text node found");
        contentBounds = {container:container, startOffset:offset - 1, endOffset:offset};
      } else {
        contentBounds = {container:container, startOffset:0, endOffset:container.childNodes.length};
      }
    }
    return contentBounds;
  }
  this.getContentBounds = getContentBounds;
};
ops.MemberProperties = function() {
  this.fullName;
  this.color;
  this.imageUrl;
};
ops.Member = function Member(memberId, properties) {
  var props = new ops.MemberProperties;
  function getMemberId() {
    return memberId;
  }
  function getProperties() {
    return props;
  }
  function setProperties(newProperties) {
    Object.keys(newProperties).forEach(function(key) {
      props[key] = newProperties[key];
    });
  }
  function removeProperties(removedProperties) {
    Object.keys(removedProperties).forEach(function(key) {
      if (key !== "fullName" && key !== "color" && key !== "imageUrl" && props.hasOwnProperty(key)) {
        delete props[key];
      }
    });
  }
  this.getMemberId = getMemberId;
  this.getProperties = getProperties;
  this.setProperties = setProperties;
  this.removeProperties = removeProperties;
  function init() {
    runtime.assert(Boolean(memberId), "No memberId was supplied!");
    if (!properties.fullName) {
      properties.fullName = runtime.tr("Unknown Author");
    }
    if (!properties.color) {
      properties.color = "black";
    }
    if (!properties.imageUrl) {
      properties.imageUrl = "avatar-joe.png";
    }
    props = properties;
  }
  init();
};
ops.Document = function Document() {
};
ops.Document.prototype.getMemberIds = function() {
};
ops.Document.prototype.removeCursor = function(memberid) {
};
ops.Document.prototype.getDocumentElement = function() {
};
ops.Document.prototype.getRootNode = function() {
};
ops.Document.prototype.getDOMDocument = function() {
};
ops.Document.prototype.cloneDocumentElement = function() {
};
ops.Document.prototype.setDocumentElement = function(element) {
};
ops.Document.prototype.subscribe = function(eventid, cb) {
};
ops.Document.prototype.unsubscribe = function(eventid, cb) {
};
ops.Document.prototype.getCanvas = function() {
};
ops.Document.prototype.createRootFilter = function(inputMemberId) {
};
ops.Document.prototype.createPositionIterator = function(rootNode) {
};
ops.Document.prototype.hasCursor = function(memberid) {
};
ops.Document.signalCursorAdded = "cursor/added";
ops.Document.signalCursorRemoved = "cursor/removed";
ops.Document.signalCursorMoved = "cursor/moved";
ops.Document.signalMemberAdded = "member/added";
ops.Document.signalMemberUpdated = "member/updated";
ops.Document.signalMemberRemoved = "member/removed";
ops.OdtCursor = function OdtCursor(memberId, document) {
  var self = this, validSelectionTypes = {}, selectionType, cursor, events = new core.EventNotifier([ops.OdtCursor.signalCursorUpdated]);
  this.removeFromDocument = function() {
    cursor.remove();
  };
  this.subscribe = function(eventid, cb) {
    events.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    events.unsubscribe(eventid, cb);
  };
  this.getMemberId = function() {
    return memberId;
  };
  this.getNode = function() {
    return cursor.getNode();
  };
  this.getAnchorNode = function() {
    return cursor.getAnchorNode();
  };
  this.getSelectedRange = function() {
    return cursor.getSelectedRange();
  };
  this.setSelectedRange = function(range, isForwardSelection) {
    cursor.setSelectedRange(range, isForwardSelection);
    events.emit(ops.OdtCursor.signalCursorUpdated, self);
  };
  this.hasForwardSelection = function() {
    return cursor.hasForwardSelection();
  };
  this.getDocument = function() {
    return document;
  };
  this.getSelectionType = function() {
    return selectionType;
  };
  this.setSelectionType = function(value) {
    if (validSelectionTypes.hasOwnProperty(value)) {
      selectionType = value;
    } else {
      runtime.log("Invalid selection type: " + value);
    }
  };
  this.resetSelectionType = function() {
    self.setSelectionType(ops.OdtCursor.RangeSelection);
  };
  function init() {
    cursor = new core.Cursor(document.getDOMDocument(), memberId);
    validSelectionTypes[ops.OdtCursor.RangeSelection] = true;
    validSelectionTypes[ops.OdtCursor.RegionSelection] = true;
    self.resetSelectionType();
  }
  init();
};
ops.OdtCursor.RangeSelection = "Range";
ops.OdtCursor.RegionSelection = "Region";
ops.OdtCursor.signalCursorUpdated = "cursorUpdated";
(function() {
  var nextNodeId = 0;
  ops.StepsCache = function StepsCache(rootElement, bucketSize, restoreBookmarkPosition) {
    var coordinatens = "urn:webodf:names:steps", stepToDomPoint = {}, nodeToBookmark = {}, domUtils = core.DomUtils, basePoint, lastUndamagedCacheStep, DOCUMENT_POSITION_FOLLOWING = Node.DOCUMENT_POSITION_FOLLOWING, DOCUMENT_POSITION_PRECEDING = Node.DOCUMENT_POSITION_PRECEDING;
    function NodeBookmark(nodeId, bookmarkNode) {
      var self = this;
      this.nodeId = nodeId;
      this.steps = -1;
      this.node = bookmarkNode;
      this.nextBookmark = null;
      this.previousBookmark = null;
      this.setIteratorPosition = function(iterator) {
        iterator.setPositionBeforeElement(bookmarkNode);
        restoreBookmarkPosition(self.steps, iterator);
      };
    }
    function RootBookmark(nodeId, steps, rootNode) {
      var self = this;
      this.nodeId = nodeId;
      this.steps = steps;
      this.node = rootNode;
      this.nextBookmark = null;
      this.previousBookmark = null;
      this.setIteratorPosition = function(iterator) {
        iterator.setUnfilteredPosition(rootNode, 0);
        restoreBookmarkPosition(self.steps, iterator);
      };
    }
    function inspectBookmarks(bookmark1, bookmark2) {
      var parts = "[" + bookmark1.nodeId;
      if (bookmark2) {
        parts += " => " + bookmark2.nodeId;
      }
      return parts + "]";
    }
    function isUndamagedBookmark(bookmark) {
      return lastUndamagedCacheStep === undefined || bookmark === basePoint || bookmark.steps <= lastUndamagedCacheStep;
    }
    function verifyCache() {
      if (ops.StepsCache.ENABLE_CACHE_VERIFICATION !== true) {
        return;
      }
      var bookmark = basePoint, previousBookmark, nextBookmark, documentPosition, loopCheck = new core.LoopWatchDog(0, 1E5), stepToDomPointNodeIds = {};
      while (bookmark) {
        loopCheck.check();
        previousBookmark = bookmark.previousBookmark;
        if (previousBookmark) {
          runtime.assert(previousBookmark.nextBookmark === bookmark, "Broken bookmark link to previous @" + inspectBookmarks(previousBookmark, bookmark));
        } else {
          runtime.assert(bookmark === basePoint, "Broken bookmark link @" + inspectBookmarks(bookmark));
          runtime.assert(isUndamagedBookmark(basePoint), "Base point is damaged @" + inspectBookmarks(bookmark));
        }
        nextBookmark = bookmark.nextBookmark;
        if (nextBookmark) {
          runtime.assert(nextBookmark.previousBookmark === bookmark, "Broken bookmark link to next @" + inspectBookmarks(bookmark, nextBookmark));
        }
        if (isUndamagedBookmark(bookmark)) {
          runtime.assert(domUtils.containsNode(rootElement, bookmark.node), "Disconnected node is being reported as undamaged @" + inspectBookmarks(bookmark));
          if (previousBookmark) {
            documentPosition = bookmark.node.compareDocumentPosition(previousBookmark.node);
            runtime.assert(documentPosition === 0 || (documentPosition & DOCUMENT_POSITION_PRECEDING) !== 0, "Bookmark order with previous does not reflect DOM order @" + inspectBookmarks(previousBookmark, bookmark));
          }
          if (nextBookmark) {
            if (domUtils.containsNode(rootElement, nextBookmark.node)) {
              documentPosition = bookmark.node.compareDocumentPosition(nextBookmark.node);
              runtime.assert(documentPosition === 0 || (documentPosition & DOCUMENT_POSITION_FOLLOWING) !== 0, "Bookmark order with next does not reflect DOM order @" + inspectBookmarks(bookmark, nextBookmark));
            }
          }
        }
        bookmark = bookmark.nextBookmark;
      }
      Object.keys(stepToDomPoint).forEach(function(step) {
        var domPointBookmark = stepToDomPoint[step];
        if (lastUndamagedCacheStep === undefined || step <= lastUndamagedCacheStep) {
          runtime.assert(domPointBookmark.steps <= step, "Bookmark step of " + domPointBookmark.steps + " exceeds cached step lookup for " + step + " @" + inspectBookmarks(domPointBookmark));
        }
        runtime.assert(stepToDomPointNodeIds.hasOwnProperty(domPointBookmark.nodeId) === false, "Bookmark " + inspectBookmarks(domPointBookmark) + " appears twice in cached step lookup at steps " + stepToDomPointNodeIds[domPointBookmark.nodeId] + " and " + step);
        stepToDomPointNodeIds[domPointBookmark.nodeId] = step;
      });
    }
    function getBucket(steps) {
      return Math.floor(steps / bucketSize) * bucketSize;
    }
    function getDestinationBucket(steps) {
      return Math.ceil(steps / bucketSize) * bucketSize;
    }
    function clearNodeId(node) {
      node.removeAttributeNS(coordinatens, "nodeId");
    }
    function getNodeId(node) {
      var id = "";
      if (node.nodeType === Node.ELEMENT_NODE) {
        id = node.getAttributeNS(coordinatens, "nodeId") || "";
      }
      return id;
    }
    function setNodeId(node) {
      var nodeId = nextNodeId.toString();
      node.setAttributeNS(coordinatens, "nodeId", nodeId);
      nextNodeId += 1;
      return nodeId;
    }
    function isValidBookmarkForNode(node, bookmark) {
      return bookmark.node === node;
    }
    function getNodeBookmark(node) {
      var nodeId = getNodeId(node) || setNodeId(node), existingBookmark;
      existingBookmark = nodeToBookmark[nodeId];
      if (!existingBookmark) {
        existingBookmark = nodeToBookmark[nodeId] = new NodeBookmark(nodeId, node);
      } else {
        if (!isValidBookmarkForNode(node, existingBookmark)) {
          runtime.log("Cloned node detected. Creating new bookmark");
          nodeId = setNodeId(node);
          existingBookmark = nodeToBookmark[nodeId] = new NodeBookmark(nodeId, node);
        }
      }
      return existingBookmark;
    }
    function getClosestBookmark(steps) {
      var cacheBucket, cachePoint, loopGuard = new core.LoopWatchDog(0, 1E4);
      if (lastUndamagedCacheStep !== undefined && steps > lastUndamagedCacheStep) {
        steps = lastUndamagedCacheStep;
      }
      cacheBucket = getBucket(steps);
      while (!cachePoint && cacheBucket >= 0) {
        cachePoint = stepToDomPoint[cacheBucket];
        cacheBucket -= bucketSize;
      }
      cachePoint = cachePoint || basePoint;
      while (cachePoint.nextBookmark && cachePoint.nextBookmark.steps <= steps) {
        loopGuard.check();
        cachePoint = cachePoint.nextBookmark;
      }
      runtime.assert(steps === -1 || cachePoint.steps <= steps, "Bookmark @" + inspectBookmarks(cachePoint) + " at step " + cachePoint.steps + " exceeds requested step of " + steps);
      return cachePoint;
    }
    function getUndamagedBookmark(bookmark) {
      if (lastUndamagedCacheStep !== undefined && bookmark.steps > lastUndamagedCacheStep) {
        bookmark = getClosestBookmark(lastUndamagedCacheStep);
      }
      return bookmark;
    }
    function removeBookmark(currentBookmark) {
      if (currentBookmark.previousBookmark) {
        currentBookmark.previousBookmark.nextBookmark = currentBookmark.nextBookmark;
      }
      if (currentBookmark.nextBookmark) {
        currentBookmark.nextBookmark.previousBookmark = currentBookmark.previousBookmark;
      }
    }
    function isAlreadyInOrder(previousBookmark, newBookmark) {
      return previousBookmark === newBookmark || previousBookmark.nextBookmark === newBookmark;
    }
    function insertBookmark(previousBookmark, newBookmark) {
      var nextBookmark;
      if (!isAlreadyInOrder(previousBookmark, newBookmark)) {
        if (previousBookmark.steps === newBookmark.steps) {
          while ((newBookmark.node.compareDocumentPosition(previousBookmark.node) & DOCUMENT_POSITION_FOLLOWING) !== 0 && previousBookmark !== basePoint) {
            previousBookmark = previousBookmark.previousBookmark;
          }
        }
        if (!isAlreadyInOrder(previousBookmark, newBookmark)) {
          removeBookmark(newBookmark);
          nextBookmark = previousBookmark.nextBookmark;
          newBookmark.nextBookmark = previousBookmark.nextBookmark;
          newBookmark.previousBookmark = previousBookmark;
          previousBookmark.nextBookmark = newBookmark;
          if (nextBookmark) {
            nextBookmark.previousBookmark = newBookmark;
          }
        }
      }
    }
    function repairCacheUpToStep(currentIteratorStep) {
      var damagedBookmark, undamagedBookmark, nextBookmark, stepsBucket;
      if (lastUndamagedCacheStep !== undefined && lastUndamagedCacheStep < currentIteratorStep) {
        undamagedBookmark = getClosestBookmark(lastUndamagedCacheStep);
        damagedBookmark = undamagedBookmark.nextBookmark;
        while (damagedBookmark && damagedBookmark.steps <= currentIteratorStep) {
          nextBookmark = damagedBookmark.nextBookmark;
          stepsBucket = getDestinationBucket(damagedBookmark.steps);
          if (stepToDomPoint[stepsBucket] === damagedBookmark) {
            delete stepToDomPoint[stepsBucket];
          }
          if (!domUtils.containsNode(rootElement, damagedBookmark.node)) {
            removeBookmark(damagedBookmark);
            delete nodeToBookmark[damagedBookmark.nodeId];
          } else {
            damagedBookmark.steps = currentIteratorStep + 1;
          }
          damagedBookmark = nextBookmark;
        }
        lastUndamagedCacheStep = currentIteratorStep;
      } else {
        undamagedBookmark = getClosestBookmark(currentIteratorStep);
      }
      return undamagedBookmark;
    }
    this.updateBookmark = function(steps, node) {
      var previousCacheBucket, newCacheBucket = getDestinationBucket(steps), existingCachePoint, bookmark, closestPriorBookmark;
      closestPriorBookmark = repairCacheUpToStep(steps);
      bookmark = getNodeBookmark(node);
      if (bookmark.steps !== steps) {
        previousCacheBucket = getDestinationBucket(bookmark.steps);
        if (previousCacheBucket !== newCacheBucket && stepToDomPoint[previousCacheBucket] === bookmark) {
          delete stepToDomPoint[previousCacheBucket];
        }
        bookmark.steps = steps;
      }
      insertBookmark(closestPriorBookmark, bookmark);
      existingCachePoint = stepToDomPoint[newCacheBucket];
      if (!existingCachePoint || bookmark.steps > existingCachePoint.steps) {
        stepToDomPoint[newCacheBucket] = bookmark;
      }
      verifyCache();
    };
    this.setToClosestStep = function(steps, iterator) {
      var cachePoint;
      verifyCache();
      cachePoint = getClosestBookmark(steps);
      cachePoint.setIteratorPosition(iterator);
      return cachePoint.steps;
    };
    function findBookmarkedAncestor(node) {
      var currentNode = node, nodeId, bookmark = null;
      while (!bookmark && currentNode && currentNode !== rootElement) {
        nodeId = getNodeId(currentNode);
        if (nodeId) {
          bookmark = nodeToBookmark[nodeId];
          if (bookmark && !isValidBookmarkForNode(currentNode, bookmark)) {
            runtime.log("Cloned node detected. Creating new bookmark");
            bookmark = null;
            clearNodeId(currentNode);
          }
        }
        currentNode = currentNode.parentNode;
      }
      return bookmark;
    }
    this.setToClosestDomPoint = function(node, offset, iterator) {
      var bookmark, b, key;
      verifyCache();
      if (node === rootElement && offset === 0) {
        bookmark = basePoint;
      } else {
        if (node === rootElement && offset === rootElement.childNodes.length) {
          bookmark = basePoint;
          for (key in stepToDomPoint) {
            if (stepToDomPoint.hasOwnProperty(key)) {
              b = stepToDomPoint[key];
              if (b.steps > bookmark.steps) {
                bookmark = b;
              }
            }
          }
        } else {
          bookmark = findBookmarkedAncestor(node.childNodes.item(offset) || node);
          if (!bookmark) {
            iterator.setUnfilteredPosition(node, offset);
            while (!bookmark && iterator.previousNode()) {
              bookmark = findBookmarkedAncestor(iterator.getCurrentNode());
            }
          }
        }
      }
      bookmark = getUndamagedBookmark(bookmark || basePoint);
      bookmark.setIteratorPosition(iterator);
      return bookmark.steps;
    };
    this.damageCacheAfterStep = function(inflectionStep) {
      if (inflectionStep < 0) {
        inflectionStep = -1;
      }
      if (lastUndamagedCacheStep === undefined) {
        lastUndamagedCacheStep = inflectionStep;
      } else {
        if (inflectionStep < lastUndamagedCacheStep) {
          lastUndamagedCacheStep = inflectionStep;
        }
      }
      verifyCache();
    };
    function init() {
      var rootElementId = getNodeId(rootElement) || setNodeId(rootElement);
      basePoint = new RootBookmark(rootElementId, 0, rootElement);
    }
    init();
  };
  ops.StepsCache.ENABLE_CACHE_VERIFICATION = false;
  ops.StepsCache.Bookmark = function Bookmark() {
  };
  ops.StepsCache.Bookmark.prototype.nodeId;
  ops.StepsCache.Bookmark.prototype.node;
  ops.StepsCache.Bookmark.prototype.steps;
  ops.StepsCache.Bookmark.prototype.previousBookmark;
  ops.StepsCache.Bookmark.prototype.nextBookmark;
  ops.StepsCache.Bookmark.prototype.setIteratorPosition = function(iterator) {
  };
})();
(function() {
  ops.OdtStepsTranslator = function OdtStepsTranslator(rootNode, iterator, filter, bucketSize) {
    var stepsCache, odfUtils = odf.OdfUtils, domUtils = core.DomUtils, FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, PREVIOUS = core.StepDirection.PREVIOUS, NEXT = core.StepDirection.NEXT;
    function updateCache(steps, iterator, isStep) {
      var node = iterator.getCurrentNode();
      if (iterator.isBeforeNode() && odfUtils.isParagraph(node)) {
        if (!isStep) {
          steps += 1;
        }
        stepsCache.updateBookmark(steps, node);
      }
    }
    function roundUpToStep(steps, iterator) {
      do {
        if (filter.acceptPosition(iterator) === FILTER_ACCEPT) {
          updateCache(steps, iterator, true);
          break;
        }
        updateCache(steps - 1, iterator, false);
      } while (iterator.nextPosition());
    }
    this.convertStepsToDomPoint = function(steps) {
      var stepsFromRoot, isStep;
      if (isNaN(steps)) {
        throw new TypeError("Requested steps is not numeric (" + steps + ")");
      }
      if (steps < 0) {
        throw new RangeError("Requested steps is negative (" + steps + ")");
      }
      stepsFromRoot = stepsCache.setToClosestStep(steps, iterator);
      while (stepsFromRoot < steps && iterator.nextPosition()) {
        isStep = filter.acceptPosition(iterator) === FILTER_ACCEPT;
        if (isStep) {
          stepsFromRoot += 1;
        }
        updateCache(stepsFromRoot, iterator, isStep);
      }
      if (stepsFromRoot !== steps) {
        throw new RangeError("Requested steps (" + steps + ") exceeds available steps (" + stepsFromRoot + ")");
      }
      return {node:iterator.container(), offset:iterator.unfilteredDomOffset()};
    };
    function roundToPreferredStep(iterator, roundDirection) {
      if (!roundDirection || filter.acceptPosition(iterator) === FILTER_ACCEPT) {
        return true;
      }
      while (iterator.previousPosition()) {
        if (filter.acceptPosition(iterator) === FILTER_ACCEPT) {
          if (roundDirection(PREVIOUS, iterator.container(), iterator.unfilteredDomOffset())) {
            return true;
          }
          break;
        }
      }
      while (iterator.nextPosition()) {
        if (filter.acceptPosition(iterator) === FILTER_ACCEPT) {
          if (roundDirection(NEXT, iterator.container(), iterator.unfilteredDomOffset())) {
            return true;
          }
          break;
        }
      }
      return false;
    }
    this.convertDomPointToSteps = function(node, offset, roundDirection) {
      var stepsFromRoot, beforeRoot, destinationNode, destinationOffset, rounding = 0, isStep;
      if (!domUtils.containsNode(rootNode, node)) {
        beforeRoot = domUtils.comparePoints(rootNode, 0, node, offset) < 0;
        node = rootNode;
        offset = beforeRoot ? 0 : rootNode.childNodes.length;
      }
      iterator.setUnfilteredPosition(node, offset);
      if (!roundToPreferredStep(iterator, roundDirection)) {
        iterator.setUnfilteredPosition(node, offset);
      }
      destinationNode = iterator.container();
      destinationOffset = iterator.unfilteredDomOffset();
      stepsFromRoot = stepsCache.setToClosestDomPoint(destinationNode, destinationOffset, iterator);
      if (domUtils.comparePoints(iterator.container(), iterator.unfilteredDomOffset(), destinationNode, destinationOffset) < 0) {
        return stepsFromRoot > 0 ? stepsFromRoot - 1 : stepsFromRoot;
      }
      while (!(iterator.container() === destinationNode && iterator.unfilteredDomOffset() === destinationOffset) && iterator.nextPosition()) {
        isStep = filter.acceptPosition(iterator) === FILTER_ACCEPT;
        if (isStep) {
          stepsFromRoot += 1;
        }
        updateCache(stepsFromRoot, iterator, isStep);
      }
      return stepsFromRoot + rounding;
    };
    this.prime = function() {
      var stepsFromRoot, isStep;
      stepsFromRoot = stepsCache.setToClosestStep(0, iterator);
      while (iterator.nextPosition()) {
        isStep = filter.acceptPosition(iterator) === FILTER_ACCEPT;
        if (isStep) {
          stepsFromRoot += 1;
        }
        updateCache(stepsFromRoot, iterator, isStep);
      }
    };
    this.handleStepsInserted = function(eventArgs) {
      stepsCache.damageCacheAfterStep(eventArgs.position);
    };
    this.handleStepsRemoved = function(eventArgs) {
      stepsCache.damageCacheAfterStep(eventArgs.position - 1);
    };
    function init() {
      stepsCache = new ops.StepsCache(rootNode, bucketSize, roundUpToStep);
    }
    init();
  };
})();
ops.Operation = function Operation() {
};
ops.Operation.prototype.init = function(data) {
};
ops.Operation.prototype.isEdit;
ops.Operation.prototype.group;
ops.Operation.prototype.execute = function(document) {
};
ops.Operation.prototype.spec = function() {
};
ops.TextPositionFilter = function TextPositionFilter() {
  var odfUtils = odf.OdfUtils, ELEMENT_NODE = Node.ELEMENT_NODE, TEXT_NODE = Node.TEXT_NODE, FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, FILTER_REJECT = core.PositionFilter.FilterResult.FILTER_REJECT;
  function previousSibling(node, nodeFilter) {
    while (node && nodeFilter(node) !== FILTER_ACCEPT) {
      node = node.previousSibling;
    }
    return node;
  }
  function checkLeftRight(container, leftNode, rightNode, nodeFilter) {
    var r, firstPos, rightOfChar;
    if (leftNode) {
      if (odfUtils.isInlineRoot(leftNode) && odfUtils.isGroupingElement(rightNode)) {
        return FILTER_REJECT;
      }
      r = odfUtils.lookLeftForCharacter(leftNode);
      if (r === 1) {
        return FILTER_ACCEPT;
      }
      if (r === 2 && (odfUtils.scanRightForAnyCharacter(rightNode) || odfUtils.scanRightForAnyCharacter(odfUtils.nextNode(container)))) {
        return FILTER_ACCEPT;
      }
    } else {
      if (odfUtils.isGroupingElement(container) && odfUtils.isInlineRoot(previousSibling(container.previousSibling, nodeFilter))) {
        return FILTER_ACCEPT;
      }
    }
    firstPos = leftNode === null && odfUtils.isParagraph(container);
    rightOfChar = odfUtils.lookRightForCharacter(rightNode);
    if (firstPos) {
      if (rightOfChar) {
        return FILTER_ACCEPT;
      }
      return odfUtils.scanRightForAnyCharacter(rightNode) ? FILTER_REJECT : FILTER_ACCEPT;
    }
    if (!rightOfChar) {
      return FILTER_REJECT;
    }
    leftNode = leftNode || odfUtils.previousNode(container);
    return odfUtils.scanLeftForAnyCharacter(leftNode) ? FILTER_REJECT : FILTER_ACCEPT;
  }
  this.acceptPosition = function(iterator) {
    var container = iterator.container(), nodeType = container.nodeType, offset, text, leftChar, rightChar, leftNode, rightNode, r;
    if (nodeType !== ELEMENT_NODE && nodeType !== TEXT_NODE) {
      return FILTER_REJECT;
    }
    if (nodeType === TEXT_NODE) {
      offset = iterator.unfilteredDomOffset();
      text = container.data;
      runtime.assert(offset !== text.length, "Unexpected offset.");
      if (offset > 0) {
        leftChar = text[offset - 1];
        if (!odfUtils.isODFWhitespace(leftChar)) {
          return FILTER_ACCEPT;
        }
        if (offset > 1) {
          leftChar = text[offset - 2];
          if (!odfUtils.isODFWhitespace(leftChar)) {
            r = FILTER_ACCEPT;
          } else {
            if (!odfUtils.isODFWhitespace(text.substr(0, offset))) {
              return FILTER_REJECT;
            }
          }
        } else {
          leftNode = odfUtils.previousNode(container);
          if (odfUtils.scanLeftForNonSpace(leftNode)) {
            r = FILTER_ACCEPT;
          }
        }
        if (r === FILTER_ACCEPT) {
          return odfUtils.isTrailingWhitespace(container, offset) ? FILTER_REJECT : FILTER_ACCEPT;
        }
        rightChar = text[offset];
        if (odfUtils.isODFWhitespace(rightChar)) {
          return FILTER_REJECT;
        }
        return odfUtils.scanLeftForAnyCharacter(odfUtils.previousNode(container)) ? FILTER_REJECT : FILTER_ACCEPT;
      }
      leftNode = iterator.leftNode();
      rightNode = container;
      container = container.parentNode;
      r = checkLeftRight(container, leftNode, rightNode, iterator.getNodeFilter());
    } else {
      if (!odfUtils.isGroupingElement(container)) {
        r = FILTER_REJECT;
      } else {
        leftNode = iterator.leftNode();
        rightNode = iterator.rightNode();
        r = checkLeftRight(container, leftNode, rightNode, iterator.getNodeFilter());
      }
    }
    return r;
  };
};
function RootFilter(anchor, cursors, getRoot) {
  var FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, FILTER_REJECT = core.PositionFilter.FilterResult.FILTER_REJECT;
  this.acceptPosition = function(iterator) {
    var node = iterator.container(), anchorNode;
    if (typeof anchor === "string") {
      anchorNode = cursors[anchor].getNode();
    } else {
      anchorNode = anchor;
    }
    if (getRoot(node) === getRoot(anchorNode)) {
      return FILTER_ACCEPT;
    }
    return FILTER_REJECT;
  };
}
ops.OdtDocument = function OdtDocument(odfCanvas) {
  var self = this, stepUtils, odfUtils = odf.OdfUtils, domUtils = core.DomUtils, cursors = {}, members = {}, eventNotifier = new core.EventNotifier([ops.Document.signalMemberAdded, ops.Document.signalMemberUpdated, ops.Document.signalMemberRemoved, ops.Document.signalCursorAdded, ops.Document.signalCursorRemoved, ops.Document.signalCursorMoved, ops.OdtDocument.signalParagraphChanged, ops.OdtDocument.signalParagraphStyleModified, ops.OdtDocument.signalCommonStyleCreated, ops.OdtDocument.signalCommonStyleDeleted, 
  ops.OdtDocument.signalTableAdded, ops.OdtDocument.signalOperationStart, ops.OdtDocument.signalOperationEnd, ops.OdtDocument.signalProcessingBatchStart, ops.OdtDocument.signalProcessingBatchEnd, ops.OdtDocument.signalUndoStackChanged, ops.OdtDocument.signalStepsInserted, ops.OdtDocument.signalStepsRemoved, ops.OdtDocument.signalMetadataUpdated, ops.OdtDocument.signalAnnotationAdded]), NEXT = core.StepDirection.NEXT, filter, stepsTranslator, lastEditingOp, unsupportedMetadataRemoved = false, SHOW_ALL = 
  NodeFilter.SHOW_ALL, blacklistedNodes = new gui.BlacklistNamespaceNodeFilter(["urn:webodf:names:cursor", "urn:webodf:names:editinfo"]), odfTextBodyFilter = new gui.OdfTextBodyNodeFilter, defaultNodeFilter = new core.NodeFilterChain([blacklistedNodes, odfTextBodyFilter]);
  function createPositionIterator(rootNode) {
    return new core.PositionIterator(rootNode, SHOW_ALL, defaultNodeFilter, false);
  }
  this.createPositionIterator = createPositionIterator;
  function getRootNode() {
    var element = odfCanvas.odfContainer().getContentElement(), localName = element && element.localName;
    runtime.assert(localName === "text", "Unsupported content element type '" + localName + "' for OdtDocument");
    return element;
  }
  this.getDocumentElement = function() {
    return odfCanvas.odfContainer().rootElement;
  };
  this.cloneDocumentElement = function() {
    var rootElement = self.getDocumentElement(), annotationViewManager = odfCanvas.getAnnotationViewManager(), initialDoc;
    if (annotationViewManager) {
      annotationViewManager.forgetAnnotations();
    }
    initialDoc = rootElement.cloneNode(true);
    odfCanvas.refreshAnnotations();
    self.fixCursorPositions();
    return initialDoc;
  };
  this.setDocumentElement = function(documentElement) {
    var odfContainer = odfCanvas.odfContainer(), rootNode;
    eventNotifier.unsubscribe(ops.OdtDocument.signalStepsInserted, stepsTranslator.handleStepsInserted);
    eventNotifier.unsubscribe(ops.OdtDocument.signalStepsRemoved, stepsTranslator.handleStepsRemoved);
    odfContainer.setRootElement(documentElement);
    odfCanvas.setOdfContainer(odfContainer, true);
    odfCanvas.refreshCSS();
    rootNode = getRootNode();
    stepsTranslator = new ops.OdtStepsTranslator(rootNode, createPositionIterator(rootNode), filter, 500);
    eventNotifier.subscribe(ops.OdtDocument.signalStepsInserted, stepsTranslator.handleStepsInserted);
    eventNotifier.subscribe(ops.OdtDocument.signalStepsRemoved, stepsTranslator.handleStepsRemoved);
  };
  function getDOMDocument() {
    return self.getDocumentElement().ownerDocument;
  }
  this.getDOMDocument = getDOMDocument;
  function isRoot(node) {
    if (node.namespaceURI === odf.Namespaces.officens && node.localName === "text" || node.namespaceURI === odf.Namespaces.officens && node.localName === "annotation") {
      return true;
    }
    return false;
  }
  function getRoot(node) {
    while (node && !isRoot(node)) {
      node = node.parentNode;
    }
    return node;
  }
  this.getRootElement = getRoot;
  function createStepIterator(container, offset, filters, subTree) {
    var positionIterator = createPositionIterator(subTree), filterOrChain, stepIterator;
    if (filters.length === 1) {
      filterOrChain = filters[0];
    } else {
      filterOrChain = new core.PositionFilterChain;
      filters.forEach(filterOrChain.addFilter);
    }
    stepIterator = new core.StepIterator(filterOrChain, positionIterator);
    stepIterator.setPosition(container, offset);
    return stepIterator;
  }
  this.createStepIterator = createStepIterator;
  function getIteratorAtPosition(position) {
    var iterator = createPositionIterator(getRootNode()), point = stepsTranslator.convertStepsToDomPoint(position);
    iterator.setUnfilteredPosition(point.node, point.offset);
    return iterator;
  }
  this.getIteratorAtPosition = getIteratorAtPosition;
  this.convertCursorStepToDomPoint = function(step) {
    return stepsTranslator.convertStepsToDomPoint(step);
  };
  function roundUp(step) {
    return step === NEXT;
  }
  this.convertDomPointToCursorStep = function(node, offset, roundDirection) {
    var roundingFunc;
    if (roundDirection === NEXT) {
      roundingFunc = roundUp;
    }
    return stepsTranslator.convertDomPointToSteps(node, offset, roundingFunc);
  };
  this.convertDomToCursorRange = function(selection) {
    var point1, point2;
    point1 = stepsTranslator.convertDomPointToSteps(selection.anchorNode, selection.anchorOffset);
    if (selection.anchorNode === selection.focusNode && selection.anchorOffset === selection.focusOffset) {
      point2 = point1;
    } else {
      point2 = stepsTranslator.convertDomPointToSteps(selection.focusNode, selection.focusOffset);
    }
    return {position:point1, length:point2 - point1};
  };
  this.convertCursorToDomRange = function(position, length) {
    var range = getDOMDocument().createRange(), point1, point2;
    point1 = stepsTranslator.convertStepsToDomPoint(position);
    if (length) {
      point2 = stepsTranslator.convertStepsToDomPoint(position + length);
      if (length > 0) {
        range.setStart(point1.node, point1.offset);
        range.setEnd(point2.node, point2.offset);
      } else {
        range.setStart(point2.node, point2.offset);
        range.setEnd(point1.node, point1.offset);
      }
    } else {
      range.setStart(point1.node, point1.offset);
    }
    return range;
  };
  function getTextNodeAtStep(steps, memberid) {
    var iterator = getIteratorAtPosition(steps), node = iterator.container(), lastTextNode, nodeOffset = 0, cursorNode = null, text;
    if (node.nodeType === Node.TEXT_NODE) {
      lastTextNode = node;
      nodeOffset = iterator.unfilteredDomOffset();
      if (lastTextNode.length > 0) {
        if (nodeOffset > 0) {
          lastTextNode = lastTextNode.splitText(nodeOffset);
        }
        lastTextNode.parentNode.insertBefore(getDOMDocument().createTextNode(""), lastTextNode);
        lastTextNode = lastTextNode.previousSibling;
        nodeOffset = 0;
      }
    } else {
      lastTextNode = getDOMDocument().createTextNode("");
      nodeOffset = 0;
      node.insertBefore(lastTextNode, iterator.rightNode());
    }
    if (memberid) {
      if (cursors[memberid] && self.getCursorPosition(memberid) === steps) {
        cursorNode = cursors[memberid].getNode();
        while (cursorNode.nextSibling && cursorNode.nextSibling.localName === "cursor") {
          cursorNode.parentNode.insertBefore(cursorNode.nextSibling, cursorNode);
        }
        if (lastTextNode.length > 0 && lastTextNode.nextSibling !== cursorNode) {
          lastTextNode = getDOMDocument().createTextNode("");
          nodeOffset = 0;
        }
        cursorNode.parentNode.insertBefore(lastTextNode, cursorNode);
      }
    } else {
      while (lastTextNode.nextSibling && lastTextNode.nextSibling.localName === "cursor") {
        lastTextNode.parentNode.insertBefore(lastTextNode.nextSibling, lastTextNode);
      }
    }
    while (lastTextNode.previousSibling && lastTextNode.previousSibling.nodeType === Node.TEXT_NODE) {
      text = lastTextNode.previousSibling;
      text.appendData(lastTextNode.data);
      nodeOffset = text.length;
      lastTextNode = text;
      lastTextNode.parentNode.removeChild(lastTextNode.nextSibling);
    }
    while (lastTextNode.nextSibling && lastTextNode.nextSibling.nodeType === Node.TEXT_NODE) {
      text = lastTextNode.nextSibling;
      lastTextNode.appendData(text.data);
      lastTextNode.parentNode.removeChild(text);
    }
    return {textNode:lastTextNode, offset:nodeOffset};
  }
  function handleOperationExecuted(op) {
    var opspec = op.spec(), memberId = opspec.memberid, date = (new Date(opspec.timestamp)).toISOString(), odfContainer = odfCanvas.odfContainer(), changedMetadata, fullName;
    if (op.isEdit) {
      fullName = self.getMember(memberId).getProperties().fullName;
      odfContainer.setMetadata({"dc:creator":fullName, "dc:date":date}, null);
      changedMetadata = {setProperties:{"dc:creator":fullName, "dc:date":date}, removedProperties:[]};
      if (!lastEditingOp) {
        changedMetadata.setProperties["meta:editing-cycles"] = odfContainer.incrementEditingCycles();
        if (!unsupportedMetadataRemoved) {
          odfContainer.setMetadata(null, ["meta:editing-duration", "meta:document-statistic"]);
        }
      }
      lastEditingOp = op;
      self.emit(ops.OdtDocument.signalMetadataUpdated, changedMetadata);
    }
  }
  function upgradeWhitespaceToElement(textNode, offset) {
    runtime.assert(textNode.data[offset] === " ", "upgradeWhitespaceToElement: textNode.data[offset] should be a literal space");
    var space = textNode.ownerDocument.createElementNS(odf.Namespaces.textns, "text:s"), container = textNode.parentNode, adjacentNode = textNode;
    space.appendChild(textNode.ownerDocument.createTextNode(" "));
    if (textNode.length === 1) {
      container.replaceChild(space, textNode);
    } else {
      textNode.deleteData(offset, 1);
      if (offset > 0) {
        if (offset < textNode.length) {
          textNode.splitText(offset);
        }
        adjacentNode = textNode.nextSibling;
      }
      container.insertBefore(space, adjacentNode);
    }
    return space;
  }
  function upgradeWhitespacesAtPosition(step) {
    var positionIterator = getIteratorAtPosition(step), stepIterator = new core.StepIterator(filter, positionIterator), contentBounds, container, offset, stepsToUpgrade = 2;
    runtime.assert(stepIterator.isStep(), "positionIterator is not at a step (requested step: " + step + ")");
    do {
      contentBounds = stepUtils.getContentBounds(stepIterator);
      if (contentBounds) {
        container = contentBounds.container;
        offset = contentBounds.startOffset;
        if (container.nodeType === Node.TEXT_NODE && odfUtils.isSignificantWhitespace(container, offset)) {
          container = upgradeWhitespaceToElement(container, offset);
          stepIterator.setPosition(container, container.childNodes.length);
          stepIterator.roundToPreviousStep();
        }
      }
      stepsToUpgrade -= 1;
    } while (stepsToUpgrade > 0 && stepIterator.nextStep());
  }
  this.upgradeWhitespacesAtPosition = upgradeWhitespacesAtPosition;
  function maxOffset(node) {
    return node.nodeType === Node.TEXT_NODE ? node.length : node.childNodes.length;
  }
  function downgradeWhitespaces(stepIterator) {
    var contentBounds, container, modifiedNodes = [], lastChild, stepsToUpgrade = 2;
    runtime.assert(stepIterator.isStep(), "positionIterator is not at a step");
    do {
      contentBounds = stepUtils.getContentBounds(stepIterator);
      if (contentBounds) {
        container = contentBounds.container;
        if (odfUtils.isDowngradableSpaceElement(container)) {
          lastChild = container.lastChild;
          while (container.firstChild) {
            modifiedNodes.push(container.firstChild);
            container.parentNode.insertBefore(container.firstChild, container);
          }
          container.parentNode.removeChild(container);
          stepIterator.setPosition(lastChild, maxOffset(lastChild));
          stepIterator.roundToPreviousStep();
        }
      }
      stepsToUpgrade -= 1;
    } while (stepsToUpgrade > 0 && stepIterator.nextStep());
    modifiedNodes.forEach(domUtils.normalizeTextNodes);
  }
  this.downgradeWhitespaces = downgradeWhitespaces;
  this.downgradeWhitespacesAtPosition = function(step) {
    var positionIterator = getIteratorAtPosition(step), stepIterator = new core.StepIterator(filter, positionIterator);
    downgradeWhitespaces(stepIterator);
  };
  this.getTextNodeAtStep = getTextNodeAtStep;
  function paragraphOrRoot(container, offset, root) {
    var node = container.childNodes.item(offset) || container, paragraph = odfUtils.getParagraphElement(node);
    if (paragraph && domUtils.containsNode(root, paragraph)) {
      return paragraph;
    }
    return root;
  }
  this.fixCursorPositions = function() {
    Object.keys(cursors).forEach(function(memberId) {
      var cursor = cursors[memberId], root = getRoot(cursor.getNode()), rootFilter = self.createRootFilter(root), subTree, startPoint, endPoint, selectedRange, cursorMoved = false;
      selectedRange = cursor.getSelectedRange();
      subTree = paragraphOrRoot(selectedRange.startContainer, selectedRange.startOffset, root);
      startPoint = createStepIterator(selectedRange.startContainer, selectedRange.startOffset, [filter, rootFilter], subTree);
      if (!selectedRange.collapsed) {
        subTree = paragraphOrRoot(selectedRange.endContainer, selectedRange.endOffset, root);
        endPoint = createStepIterator(selectedRange.endContainer, selectedRange.endOffset, [filter, rootFilter], subTree);
      } else {
        endPoint = startPoint;
      }
      if (!startPoint.isStep() || !endPoint.isStep()) {
        cursorMoved = true;
        runtime.assert(startPoint.roundToClosestStep(), "No walkable step found for cursor owned by " + memberId);
        selectedRange.setStart(startPoint.container(), startPoint.offset());
        runtime.assert(endPoint.roundToClosestStep(), "No walkable step found for cursor owned by " + memberId);
        selectedRange.setEnd(endPoint.container(), endPoint.offset());
      } else {
        if (startPoint.container() === endPoint.container() && startPoint.offset() === endPoint.offset()) {
          if (!selectedRange.collapsed || cursor.getAnchorNode() !== cursor.getNode()) {
            cursorMoved = true;
            selectedRange.setStart(startPoint.container(), startPoint.offset());
            selectedRange.collapse(true);
          }
        }
      }
      if (cursorMoved) {
        cursor.setSelectedRange(selectedRange, cursor.hasForwardSelection());
        self.emit(ops.Document.signalCursorMoved, cursor);
      }
    });
  };
  this.getCursorPosition = function(memberid) {
    var cursor = cursors[memberid];
    return cursor ? stepsTranslator.convertDomPointToSteps(cursor.getNode(), 0) : 0;
  };
  this.getCursorSelection = function(memberid) {
    var cursor = cursors[memberid], focusPosition = 0, anchorPosition = 0;
    if (cursor) {
      focusPosition = stepsTranslator.convertDomPointToSteps(cursor.getNode(), 0);
      anchorPosition = stepsTranslator.convertDomPointToSteps(cursor.getAnchorNode(), 0);
    }
    return {position:anchorPosition, length:focusPosition - anchorPosition};
  };
  this.getPositionFilter = function() {
    return filter;
  };
  this.getOdfCanvas = function() {
    return odfCanvas;
  };
  this.getCanvas = function() {
    return odfCanvas;
  };
  this.getRootNode = getRootNode;
  this.addMember = function(member) {
    runtime.assert(members[member.getMemberId()] === undefined, "This member already exists");
    members[member.getMemberId()] = member;
  };
  this.getMember = function(memberId) {
    return members.hasOwnProperty(memberId) ? members[memberId] : null;
  };
  this.removeMember = function(memberId) {
    delete members[memberId];
  };
  this.getCursor = function(memberid) {
    return cursors[memberid];
  };
  this.hasCursor = function(memberid) {
    return cursors.hasOwnProperty(memberid);
  };
  this.getMemberIds = function() {
    return Object.keys(members);
  };
  this.addCursor = function(cursor) {
    runtime.assert(Boolean(cursor), "OdtDocument::addCursor without cursor");
    var memberid = cursor.getMemberId(), initialSelection = self.convertCursorToDomRange(0, 0);
    runtime.assert(typeof memberid === "string", "OdtDocument::addCursor has cursor without memberid");
    runtime.assert(!cursors[memberid], "OdtDocument::addCursor is adding a duplicate cursor with memberid " + memberid);
    cursor.setSelectedRange(initialSelection, true);
    cursors[memberid] = cursor;
  };
  this.removeCursor = function(memberid) {
    var cursor = cursors[memberid];
    if (cursor) {
      cursor.removeFromDocument();
      delete cursors[memberid];
      self.emit(ops.Document.signalCursorRemoved, memberid);
      return true;
    }
    return false;
  };
  this.moveCursor = function(memberid, position, length, selectionType) {
    var cursor = cursors[memberid], selectionRange = self.convertCursorToDomRange(position, length);
    if (cursor) {
      cursor.setSelectedRange(selectionRange, length >= 0);
      cursor.setSelectionType(selectionType || ops.OdtCursor.RangeSelection);
    }
  };
  this.getFormatting = function() {
    return odfCanvas.getFormatting();
  };
  this.emit = function(eventid, args) {
    eventNotifier.emit(eventid, args);
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  this.createRootFilter = function(inputMemberId) {
    return new RootFilter(inputMemberId, cursors, getRoot);
  };
  this.close = function(callback) {
    callback();
  };
  this.destroy = function(callback) {
    callback();
  };
  function init() {
    var rootNode = getRootNode();
    filter = new ops.TextPositionFilter;
    stepUtils = new odf.StepUtils;
    stepsTranslator = new ops.OdtStepsTranslator(rootNode, createPositionIterator(rootNode), filter, 500);
    eventNotifier.subscribe(ops.OdtDocument.signalStepsInserted, stepsTranslator.handleStepsInserted);
    eventNotifier.subscribe(ops.OdtDocument.signalStepsRemoved, stepsTranslator.handleStepsRemoved);
    eventNotifier.subscribe(ops.OdtDocument.signalOperationEnd, handleOperationExecuted);
    eventNotifier.subscribe(ops.OdtDocument.signalProcessingBatchEnd, core.Task.processTasks);
  }
  init();
};
ops.OdtDocument.signalParagraphChanged = "paragraph/changed";
ops.OdtDocument.signalTableAdded = "table/added";
ops.OdtDocument.signalCommonStyleCreated = "style/created";
ops.OdtDocument.signalCommonStyleDeleted = "style/deleted";
ops.OdtDocument.signalParagraphStyleModified = "paragraphstyle/modified";
ops.OdtDocument.signalOperationStart = "operation/start";
ops.OdtDocument.signalOperationEnd = "operation/end";
ops.OdtDocument.signalProcessingBatchStart = "router/batchstart";
ops.OdtDocument.signalProcessingBatchEnd = "router/batchend";
ops.OdtDocument.signalUndoStackChanged = "undo/changed";
ops.OdtDocument.signalStepsInserted = "steps/inserted";
ops.OdtDocument.signalStepsRemoved = "steps/removed";
ops.OdtDocument.signalMetadataUpdated = "metadata/updated";
ops.OdtDocument.signalAnnotationAdded = "annotation/added";
ops.OpAddAnnotation = function OpAddAnnotation() {
  var memberid, timestamp, position, length, name, doc;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = parseInt(data.timestamp, 10);
    position = parseInt(data.position, 10);
    length = data.length !== undefined ? parseInt(data.length, 10) || 0 : undefined;
    name = data.name;
  };
  this.isEdit = true;
  this.group = undefined;
  function createAnnotationNode(odtDocument, date) {
    var annotationNode, creatorNode, dateNode, listNode, listItemNode, paragraphNode;
    annotationNode = doc.createElementNS(odf.Namespaces.officens, "office:annotation");
    annotationNode.setAttributeNS(odf.Namespaces.officens, "office:name", name);
    creatorNode = doc.createElementNS(odf.Namespaces.dcns, "dc:creator");
    creatorNode.setAttributeNS("urn:webodf:names:editinfo", "editinfo:memberid", memberid);
    creatorNode.textContent = odtDocument.getMember(memberid).getProperties().fullName;
    dateNode = doc.createElementNS(odf.Namespaces.dcns, "dc:date");
    dateNode.appendChild(doc.createTextNode(date.toISOString()));
    listNode = doc.createElementNS(odf.Namespaces.textns, "text:list");
    listItemNode = doc.createElementNS(odf.Namespaces.textns, "text:list-item");
    paragraphNode = doc.createElementNS(odf.Namespaces.textns, "text:p");
    listItemNode.appendChild(paragraphNode);
    listNode.appendChild(listItemNode);
    annotationNode.appendChild(creatorNode);
    annotationNode.appendChild(dateNode);
    annotationNode.appendChild(listNode);
    return annotationNode;
  }
  function createAnnotationEnd() {
    var annotationEnd;
    annotationEnd = doc.createElementNS(odf.Namespaces.officens, "office:annotation-end");
    annotationEnd.setAttributeNS(odf.Namespaces.officens, "office:name", name);
    return annotationEnd;
  }
  function insertNodeAtPosition(odtDocument, node, insertPosition) {
    var previousNode, parentNode, domPosition = odtDocument.getTextNodeAtStep(insertPosition, memberid);
    if (domPosition) {
      previousNode = domPosition.textNode;
      parentNode = previousNode.parentNode;
      if (domPosition.offset !== previousNode.length) {
        previousNode.splitText(domPosition.offset);
      }
      parentNode.insertBefore(node, previousNode.nextSibling);
      if (previousNode.length === 0) {
        parentNode.removeChild(previousNode);
      }
    }
  }
  this.execute = function(document) {
    var odtDocument = document, annotation, annotationEnd, cursor = odtDocument.getCursor(memberid), selectedRange, paragraphElement;
    doc = odtDocument.getDOMDocument();
    annotation = createAnnotationNode(odtDocument, new Date(timestamp));
    if (length !== undefined) {
      annotationEnd = createAnnotationEnd();
      annotation.annotationEndElement = annotationEnd;
      insertNodeAtPosition(odtDocument, annotationEnd, position + length);
    }
    insertNodeAtPosition(odtDocument, annotation, position);
    odtDocument.emit(ops.OdtDocument.signalStepsInserted, {position:position});
    if (cursor) {
      selectedRange = doc.createRange();
      paragraphElement = annotation.getElementsByTagNameNS(odf.Namespaces.textns, "p")[0];
      selectedRange.selectNodeContents(paragraphElement);
      cursor.setSelectedRange(selectedRange, false);
      cursor.setSelectionType(ops.OdtCursor.RangeSelection);
      odtDocument.emit(ops.Document.signalCursorMoved, cursor);
    }
    odtDocument.getOdfCanvas().addAnnotation(annotation);
    odtDocument.fixCursorPositions();
    odtDocument.emit(ops.OdtDocument.signalAnnotationAdded, {memberId:memberid, annotation:annotation});
    return true;
  };
  this.spec = function() {
    return {optype:"AddAnnotation", memberid:memberid, timestamp:timestamp, position:position, length:length, name:name};
  };
};
ops.OpAddAnnotation.Spec;
ops.OpAddAnnotation.InitSpec;
ops.OpAddCursor = function OpAddCursor() {
  var memberid, timestamp;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
  };
  this.isEdit = false;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, cursor = odtDocument.getCursor(memberid);
    if (cursor) {
      return false;
    }
    cursor = new ops.OdtCursor(memberid, odtDocument);
    odtDocument.addCursor(cursor);
    odtDocument.emit(ops.Document.signalCursorAdded, cursor);
    return true;
  };
  this.spec = function() {
    return {optype:"AddCursor", memberid:memberid, timestamp:timestamp};
  };
};
ops.OpAddCursor.Spec;
ops.OpAddCursor.InitSpec;
ops.OpAddMember = function OpAddMember() {
  var memberid, timestamp, setProperties;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = parseInt(data.timestamp, 10);
    setProperties = data.setProperties;
  };
  this.isEdit = false;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, member;
    if (odtDocument.getMember(memberid)) {
      return false;
    }
    member = new ops.Member(memberid, setProperties);
    odtDocument.addMember(member);
    odtDocument.emit(ops.Document.signalMemberAdded, member);
    return true;
  };
  this.spec = function() {
    return {optype:"AddMember", memberid:memberid, timestamp:timestamp, setProperties:setProperties};
  };
};
ops.OpAddMember.Spec;
ops.OpAddMember.InitSpec;
ops.OpAddStyle = function OpAddStyle() {
  var memberid, timestamp, styleName, styleFamily, isAutomaticStyle, setProperties, stylens = odf.Namespaces.stylens;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    styleName = data.styleName;
    styleFamily = data.styleFamily;
    isAutomaticStyle = data.isAutomaticStyle === "true" || data.isAutomaticStyle === true;
    setProperties = data.setProperties;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, odfContainer = odtDocument.getOdfCanvas().odfContainer(), formatting = odtDocument.getFormatting(), dom = odtDocument.getDOMDocument(), styleNode = dom.createElementNS(stylens, "style:style");
    if (!styleNode) {
      return false;
    }
    if (setProperties) {
      formatting.updateStyle(styleNode, setProperties);
    }
    styleNode.setAttributeNS(stylens, "style:family", styleFamily);
    styleNode.setAttributeNS(stylens, "style:name", styleName);
    if (isAutomaticStyle) {
      odfContainer.rootElement.automaticStyles.appendChild(styleNode);
    } else {
      odfContainer.rootElement.styles.appendChild(styleNode);
    }
    odtDocument.getOdfCanvas().refreshCSS();
    if (!isAutomaticStyle) {
      odtDocument.emit(ops.OdtDocument.signalCommonStyleCreated, {name:styleName, family:styleFamily});
    }
    return true;
  };
  this.spec = function() {
    return {optype:"AddStyle", memberid:memberid, timestamp:timestamp, styleName:styleName, styleFamily:styleFamily, isAutomaticStyle:isAutomaticStyle, setProperties:setProperties};
  };
};
ops.OpAddStyle.Spec;
ops.OpAddStyle.InitSpec;
odf.ObjectNameGenerator = function ObjectNameGenerator(odfContainer, memberId) {
  var stylens = odf.Namespaces.stylens, drawns = odf.Namespaces.drawns, xlinkns = odf.Namespaces.xlinkns, utils = new core.Utils, memberIdHash = utils.hashString(memberId), styleNameGenerator = null, frameNameGenerator = null, imageNameGenerator = null, existingFrameNames = {}, existingImageNames = {};
  function NameGenerator(prefix, findExistingNames) {
    var reportedNames = {};
    this.generateName = function() {
      var existingNames = findExistingNames(), startIndex = 0, name;
      do {
        name = prefix + startIndex;
        startIndex += 1;
      } while (reportedNames[name] || existingNames[name]);
      reportedNames[name] = true;
      return name;
    };
  }
  function getAllStyleNames() {
    var styleElements = [odfContainer.rootElement.automaticStyles, odfContainer.rootElement.styles], styleNames = {};
    function getStyleNames(styleListElement) {
      var e = styleListElement.firstElementChild;
      while (e) {
        if (e.namespaceURI === stylens && e.localName === "style") {
          styleNames[e.getAttributeNS(stylens, "name")] = true;
        }
        e = e.nextElementSibling;
      }
    }
    styleElements.forEach(getStyleNames);
    return styleNames;
  }
  this.generateStyleName = function() {
    if (styleNameGenerator === null) {
      styleNameGenerator = new NameGenerator("auto" + memberIdHash + "_", function() {
        return getAllStyleNames();
      });
    }
    return styleNameGenerator.generateName();
  };
  this.generateFrameName = function() {
    var i, nodes, node;
    if (frameNameGenerator === null) {
      nodes = odfContainer.rootElement.body.getElementsByTagNameNS(drawns, "frame");
      for (i = 0;i < nodes.length;i += 1) {
        node = nodes.item(i);
        existingFrameNames[node.getAttributeNS(drawns, "name")] = true;
      }
      frameNameGenerator = new NameGenerator("fr" + memberIdHash + "_", function() {
        return existingFrameNames;
      });
    }
    return frameNameGenerator.generateName();
  };
  this.generateImageName = function() {
    var i, path, nodes, node;
    if (imageNameGenerator === null) {
      nodes = odfContainer.rootElement.body.getElementsByTagNameNS(drawns, "image");
      for (i = 0;i < nodes.length;i += 1) {
        node = nodes.item(i);
        path = node.getAttributeNS(xlinkns, "href");
        path = path.substring("Pictures/".length, path.lastIndexOf("."));
        existingImageNames[path] = true;
      }
      imageNameGenerator = new NameGenerator("img" + memberIdHash + "_", function() {
        return existingImageNames;
      });
    }
    return imageNameGenerator.generateName();
  };
};
odf.TextStyleApplicator = function TextStyleApplicator(objectNameGenerator, formatting, automaticStyles) {
  var domUtils = core.DomUtils, textns = odf.Namespaces.textns, stylens = odf.Namespaces.stylens, textProperties = "style:text-properties", webodfns = "urn:webodf:names:scope";
  function StyleLookup(info) {
    var cachedAppliedStyles = {};
    function compare(expected, actual) {
      if (typeof expected === "object" && typeof actual === "object") {
        return Object.keys(expected).every(function(key) {
          return compare(expected[key], actual[key]);
        });
      }
      return expected === actual;
    }
    this.isStyleApplied = function(textNode) {
      var appliedStyle = formatting.getAppliedStylesForElement(textNode, cachedAppliedStyles).styleProperties;
      return compare(info, appliedStyle);
    };
  }
  function StyleManager(info) {
    var createdStyles = {};
    function createDirectFormat(existingStyleName, document) {
      var derivedStyleInfo, derivedStyleNode;
      derivedStyleInfo = existingStyleName ? formatting.createDerivedStyleObject(existingStyleName, "text", info) : info;
      derivedStyleNode = document.createElementNS(stylens, "style:style");
      formatting.updateStyle(derivedStyleNode, derivedStyleInfo);
      derivedStyleNode.setAttributeNS(stylens, "style:name", objectNameGenerator.generateStyleName());
      derivedStyleNode.setAttributeNS(stylens, "style:family", "text");
      derivedStyleNode.setAttributeNS(webodfns, "scope", "document-content");
      automaticStyles.appendChild(derivedStyleNode);
      return derivedStyleNode;
    }
    function getDirectStyle(existingStyleName, document) {
      existingStyleName = existingStyleName || "";
      if (!createdStyles.hasOwnProperty(existingStyleName)) {
        createdStyles[existingStyleName] = createDirectFormat(existingStyleName, document);
      }
      return createdStyles[existingStyleName].getAttributeNS(stylens, "name");
    }
    this.applyStyleToContainer = function(container) {
      var name = getDirectStyle(container.getAttributeNS(textns, "style-name"), container.ownerDocument);
      container.setAttributeNS(textns, "text:style-name", name);
    };
  }
  function isTextSpan(node) {
    return node.localName === "span" && node.namespaceURI === textns;
  }
  function moveToNewSpan(startNode, range) {
    var document = startNode.ownerDocument, originalContainer = startNode.parentNode, styledContainer, trailingContainer, moveTrailing, node, nextNode, loopGuard = new core.LoopWatchDog(1E4), styledNodes = [];
    styledNodes.push(startNode);
    node = startNode.nextSibling;
    while (node && domUtils.rangeContainsNode(range, node)) {
      loopGuard.check();
      styledNodes.push(node);
      node = node.nextSibling;
    }
    if (!isTextSpan(originalContainer)) {
      styledContainer = document.createElementNS(textns, "text:span");
      originalContainer.insertBefore(styledContainer, startNode);
      moveTrailing = false;
    } else {
      if (startNode.previousSibling && !domUtils.rangeContainsNode(range, originalContainer.firstChild)) {
        styledContainer = originalContainer.cloneNode(false);
        originalContainer.parentNode.insertBefore(styledContainer, originalContainer.nextSibling);
        moveTrailing = true;
      } else {
        styledContainer = originalContainer;
        moveTrailing = true;
      }
    }
    styledNodes.forEach(function(n) {
      if (n.parentNode !== styledContainer) {
        styledContainer.appendChild(n);
      }
    });
    if (node && moveTrailing) {
      trailingContainer = styledContainer.cloneNode(false);
      styledContainer.parentNode.insertBefore(trailingContainer, styledContainer.nextSibling);
      while (node) {
        loopGuard.check();
        nextNode = node.nextSibling;
        trailingContainer.appendChild(node);
        node = nextNode;
      }
    }
    return styledContainer;
  }
  this.applyStyle = function(textNodes, range, info) {
    var textPropsOnly = {}, isStyled, container, styleCache, styleLookup;
    runtime.assert(info && info.hasOwnProperty(textProperties), "applyStyle without any text properties");
    textPropsOnly[textProperties] = info[textProperties];
    styleCache = new StyleManager(textPropsOnly);
    styleLookup = new StyleLookup(textPropsOnly);
    function apply(n) {
      isStyled = styleLookup.isStyleApplied(n);
      if (isStyled === false) {
        container = moveToNewSpan(n, range);
        styleCache.applyStyleToContainer(container);
      }
    }
    textNodes.forEach(apply);
  };
};
ops.OpApplyDirectStyling = function OpApplyDirectStyling() {
  var memberid, timestamp, position, length, setProperties, odfUtils = odf.OdfUtils, domUtils = core.DomUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = parseInt(data.position, 10);
    length = parseInt(data.length, 10);
    setProperties = data.setProperties;
  };
  this.isEdit = true;
  this.group = undefined;
  function applyStyle(odtDocument, range, info) {
    var odfCanvas = odtDocument.getOdfCanvas(), odfContainer = odfCanvas.odfContainer(), nextTextNodes = domUtils.splitBoundaries(range), textNodes = odfUtils.getTextNodes(range, false), textStyles;
    textStyles = new odf.TextStyleApplicator(new odf.ObjectNameGenerator(odfContainer, memberid), odtDocument.getFormatting(), odfContainer.rootElement.automaticStyles);
    textStyles.applyStyle(textNodes, range, info);
    nextTextNodes.forEach(domUtils.normalizeTextNodes);
  }
  this.execute = function(document) {
    var odtDocument = document, range = odtDocument.convertCursorToDomRange(position, length), impactedParagraphs = odfUtils.getParagraphElements(range);
    applyStyle(odtDocument, range, setProperties);
    range.detach();
    odtDocument.getOdfCanvas().refreshCSS();
    odtDocument.fixCursorPositions();
    impactedParagraphs.forEach(function(n) {
      odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:n, memberId:memberid, timeStamp:timestamp});
    });
    odtDocument.getOdfCanvas().rerenderAnnotations();
    return true;
  };
  this.spec = function() {
    return {optype:"ApplyDirectStyling", memberid:memberid, timestamp:timestamp, position:position, length:length, setProperties:setProperties};
  };
};
ops.OpApplyDirectStyling.Spec;
ops.OpApplyDirectStyling.InitSpec;
ops.OpApplyHyperlink = function OpApplyHyperlink() {
  var memberid, timestamp, position, length, hyperlink, domUtils = core.DomUtils, odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    length = data.length;
    hyperlink = data.hyperlink;
  };
  this.isEdit = true;
  this.group = undefined;
  function createHyperlink(document, hyperlink) {
    var node = document.createElementNS(odf.Namespaces.textns, "text:a");
    node.setAttributeNS(odf.Namespaces.xlinkns, "xlink:type", "simple");
    node.setAttributeNS(odf.Namespaces.xlinkns, "xlink:href", hyperlink);
    return node;
  }
  function isPartOfLink(node) {
    while (node) {
      if (odfUtils.isHyperlink(node)) {
        return true;
      }
      node = node.parentNode;
    }
    return false;
  }
  this.execute = function(document) {
    var odtDocument = document, ownerDocument = odtDocument.getDOMDocument(), range = odtDocument.convertCursorToDomRange(position, length), boundaryNodes = domUtils.splitBoundaries(range), modifiedParagraphs = [], textNodes = odfUtils.getTextNodes(range, false);
    if (textNodes.length === 0) {
      return false;
    }
    textNodes.forEach(function(node) {
      var linkNode, paragraph = odfUtils.getParagraphElement(node);
      runtime.assert(isPartOfLink(node) === false, "The given range should not contain any link.");
      linkNode = createHyperlink(ownerDocument, hyperlink);
      node.parentNode.insertBefore(linkNode, node);
      linkNode.appendChild(node);
      if (modifiedParagraphs.indexOf(paragraph) === -1) {
        modifiedParagraphs.push(paragraph);
      }
    });
    boundaryNodes.forEach(domUtils.normalizeTextNodes);
    range.detach();
    odtDocument.fixCursorPositions();
    odtDocument.getOdfCanvas().refreshSize();
    odtDocument.getOdfCanvas().rerenderAnnotations();
    modifiedParagraphs.forEach(function(paragraph) {
      odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraph, memberId:memberid, timeStamp:timestamp});
    });
    return true;
  };
  this.spec = function() {
    return {optype:"ApplyHyperlink", memberid:memberid, timestamp:timestamp, position:position, length:length, hyperlink:hyperlink};
  };
};
ops.OpApplyHyperlink.Spec;
ops.OpApplyHyperlink.InitSpec;
ops.OpInsertImage = function OpInsertImage() {
  var memberid, timestamp, position, filename, frameWidth, frameHeight, frameStyleName, frameName, drawns = odf.Namespaces.drawns, svgns = odf.Namespaces.svgns, textns = odf.Namespaces.textns, xlinkns = odf.Namespaces.xlinkns, odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    filename = data.filename;
    frameWidth = data.frameWidth;
    frameHeight = data.frameHeight;
    frameStyleName = data.frameStyleName;
    frameName = data.frameName;
  };
  this.isEdit = true;
  this.group = undefined;
  function createFrameElement(document) {
    var imageNode = document.createElementNS(drawns, "draw:image"), frameNode = document.createElementNS(drawns, "draw:frame");
    imageNode.setAttributeNS(xlinkns, "xlink:href", filename);
    imageNode.setAttributeNS(xlinkns, "xlink:type", "simple");
    imageNode.setAttributeNS(xlinkns, "xlink:show", "embed");
    imageNode.setAttributeNS(xlinkns, "xlink:actuate", "onLoad");
    frameNode.setAttributeNS(drawns, "draw:style-name", frameStyleName);
    frameNode.setAttributeNS(drawns, "draw:name", frameName);
    frameNode.setAttributeNS(textns, "text:anchor-type", "as-char");
    frameNode.setAttributeNS(svgns, "svg:width", frameWidth);
    frameNode.setAttributeNS(svgns, "svg:height", frameHeight);
    frameNode.appendChild(imageNode);
    return frameNode;
  }
  this.execute = function(document) {
    var odtDocument = document, odfCanvas = odtDocument.getOdfCanvas(), domPosition = odtDocument.getTextNodeAtStep(position, memberid), textNode, refNode, paragraphElement, frameElement;
    if (!domPosition) {
      return false;
    }
    textNode = domPosition.textNode;
    paragraphElement = odfUtils.getParagraphElement(textNode);
    refNode = domPosition.offset !== textNode.length ? textNode.splitText(domPosition.offset) : textNode.nextSibling;
    frameElement = createFrameElement(odtDocument.getDOMDocument());
    textNode.parentNode.insertBefore(frameElement, refNode);
    odtDocument.emit(ops.OdtDocument.signalStepsInserted, {position:position});
    if (textNode.length === 0) {
      textNode.parentNode.removeChild(textNode);
    }
    odfCanvas.addCssForFrameWithImage(frameElement);
    odfCanvas.refreshCSS();
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraphElement, memberId:memberid, timeStamp:timestamp});
    odfCanvas.rerenderAnnotations();
    return true;
  };
  this.spec = function() {
    return {optype:"InsertImage", memberid:memberid, timestamp:timestamp, filename:filename, position:position, frameWidth:frameWidth, frameHeight:frameHeight, frameStyleName:frameStyleName, frameName:frameName};
  };
};
ops.OpInsertImage.Spec;
ops.OpInsertImage.InitSpec;
ops.OpInsertTable = function OpInsertTable() {
  var memberid, timestamp, initialRows, initialColumns, position, tableName, tableStyleName, tableColumnStyleName, tableCellStyleMatrix, tablens = "urn:oasis:names:tc:opendocument:xmlns:table:1.0", textns = "urn:oasis:names:tc:opendocument:xmlns:text:1.0", odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    initialRows = data.initialRows;
    initialColumns = data.initialColumns;
    tableName = data.tableName;
    tableStyleName = data.tableStyleName;
    tableColumnStyleName = data.tableColumnStyleName;
    tableCellStyleMatrix = data.tableCellStyleMatrix;
  };
  this.isEdit = true;
  this.group = undefined;
  function getCellStyleName(row, column) {
    var rowStyles;
    if (tableCellStyleMatrix.length === 1) {
      rowStyles = tableCellStyleMatrix[0];
    } else {
      if (tableCellStyleMatrix.length === 3) {
        switch(row) {
          case 0:
            rowStyles = tableCellStyleMatrix[0];
            break;
          case initialRows - 1:
            rowStyles = tableCellStyleMatrix[2];
            break;
          default:
            rowStyles = tableCellStyleMatrix[1];
            break;
        }
      } else {
        rowStyles = tableCellStyleMatrix[row];
      }
    }
    if (rowStyles.length === 1) {
      return rowStyles[0];
    }
    if (rowStyles.length === 3) {
      switch(column) {
        case 0:
          return rowStyles[0];
        case initialColumns - 1:
          return rowStyles[2];
        default:
          return rowStyles[1];
      }
    }
    return rowStyles[column];
  }
  function createTableNode(document) {
    var tableNode = document.createElementNS(tablens, "table:table"), columns = document.createElementNS(tablens, "table:table-column"), row, cell, paragraph, rowCounter, columnCounter, cellStyleName;
    if (tableStyleName) {
      tableNode.setAttributeNS(tablens, "table:style-name", tableStyleName);
    }
    if (tableName) {
      tableNode.setAttributeNS(tablens, "table:name", tableName);
    }
    columns.setAttributeNS(tablens, "table:number-columns-repeated", initialColumns);
    if (tableColumnStyleName) {
      columns.setAttributeNS(tablens, "table:style-name", tableColumnStyleName);
    }
    tableNode.appendChild(columns);
    for (rowCounter = 0;rowCounter < initialRows;rowCounter += 1) {
      row = document.createElementNS(tablens, "table:table-row");
      for (columnCounter = 0;columnCounter < initialColumns;columnCounter += 1) {
        cell = document.createElementNS(tablens, "table:table-cell");
        cellStyleName = getCellStyleName(rowCounter, columnCounter);
        if (cellStyleName) {
          cell.setAttributeNS(tablens, "table:style-name", cellStyleName);
        }
        paragraph = document.createElementNS(textns, "text:p");
        cell.appendChild(paragraph);
        row.appendChild(cell);
      }
      tableNode.appendChild(row);
    }
    return tableNode;
  }
  this.execute = function(document) {
    var odtDocument = document, domPosition = odtDocument.getTextNodeAtStep(position), rootNode = odtDocument.getRootNode(), previousSibling, tableNode;
    if (domPosition) {
      tableNode = createTableNode(odtDocument.getDOMDocument());
      previousSibling = odfUtils.getParagraphElement(domPosition.textNode);
      rootNode.insertBefore(tableNode, previousSibling.nextSibling);
      odtDocument.emit(ops.OdtDocument.signalStepsInserted, {position:position});
      odtDocument.getOdfCanvas().refreshSize();
      odtDocument.emit(ops.OdtDocument.signalTableAdded, {tableElement:tableNode, memberId:memberid, timeStamp:timestamp});
      odtDocument.getOdfCanvas().rerenderAnnotations();
      return true;
    }
    return false;
  };
  this.spec = function() {
    return {optype:"InsertTable", memberid:memberid, timestamp:timestamp, position:position, initialRows:initialRows, initialColumns:initialColumns, tableName:tableName, tableStyleName:tableStyleName, tableColumnStyleName:tableColumnStyleName, tableCellStyleMatrix:tableCellStyleMatrix};
  };
};
ops.OpInsertTable.Spec;
ops.OpInsertTable.InitSpec;
ops.OpInsertText = function OpInsertText() {
  var tab = "\t", memberid, timestamp, position, moveCursor, text, odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    text = data.text;
    moveCursor = data.moveCursor === "true" || data.moveCursor === true;
  };
  this.isEdit = true;
  this.group = undefined;
  function triggerLayoutInWebkit(textNode) {
    var parent = textNode.parentNode, next = textNode.nextSibling;
    parent.removeChild(textNode);
    parent.insertBefore(textNode, next);
  }
  function isNonTabWhiteSpace(character) {
    return character !== tab && odfUtils.isODFWhitespace(character);
  }
  function requiresSpaceElement(text, index) {
    return isNonTabWhiteSpace(text[index]) && (index === 0 || index === text.length - 1 || isNonTabWhiteSpace(text[index - 1]));
  }
  this.execute = function(document) {
    var odtDocument = document, domPosition, previousNode, parentElement, nextNode = null, ownerDocument = odtDocument.getDOMDocument(), paragraphElement, textns = "urn:oasis:names:tc:opendocument:xmlns:text:1.0", toInsertIndex = 0, spaceElement, cursor = odtDocument.getCursor(memberid), i;
    function insertTextNode(toInsertText) {
      parentElement.insertBefore(ownerDocument.createTextNode(toInsertText), nextNode);
    }
    odtDocument.upgradeWhitespacesAtPosition(position);
    domPosition = odtDocument.getTextNodeAtStep(position);
    if (domPosition) {
      previousNode = domPosition.textNode;
      nextNode = previousNode.nextSibling;
      parentElement = previousNode.parentNode;
      paragraphElement = odfUtils.getParagraphElement(previousNode);
      for (i = 0;i < text.length;i += 1) {
        if (text[i] === tab || requiresSpaceElement(text, i)) {
          if (toInsertIndex === 0) {
            if (domPosition.offset !== previousNode.length) {
              nextNode = previousNode.splitText(domPosition.offset);
            }
            if (0 < i) {
              previousNode.appendData(text.substring(0, i));
            }
          } else {
            if (toInsertIndex < i) {
              insertTextNode(text.substring(toInsertIndex, i));
            }
          }
          toInsertIndex = i + 1;
          if (text[i] === tab) {
            spaceElement = ownerDocument.createElementNS(textns, "text:tab");
            spaceElement.appendChild(ownerDocument.createTextNode("\t"));
          } else {
            if (text[i] !== " ") {
              runtime.log("WARN: InsertText operation contains non-tab, non-space whitespace character (character code " + text.charCodeAt(i) + ")");
            }
            spaceElement = ownerDocument.createElementNS(textns, "text:s");
            spaceElement.appendChild(ownerDocument.createTextNode(" "));
          }
          parentElement.insertBefore(spaceElement, nextNode);
        }
      }
      if (toInsertIndex === 0) {
        previousNode.insertData(domPosition.offset, text);
      } else {
        if (toInsertIndex < text.length) {
          insertTextNode(text.substring(toInsertIndex));
        }
      }
      triggerLayoutInWebkit(previousNode);
      if (previousNode.length === 0) {
        previousNode.parentNode.removeChild(previousNode);
      }
      odtDocument.emit(ops.OdtDocument.signalStepsInserted, {position:position});
      if (cursor && moveCursor) {
        odtDocument.moveCursor(memberid, position + text.length, 0);
        odtDocument.emit(ops.Document.signalCursorMoved, cursor);
      }
      odtDocument.downgradeWhitespacesAtPosition(position);
      odtDocument.downgradeWhitespacesAtPosition(position + text.length);
      odtDocument.getOdfCanvas().refreshSize();
      odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraphElement, memberId:memberid, timeStamp:timestamp});
      odtDocument.getOdfCanvas().rerenderAnnotations();
      return true;
    }
    return false;
  };
  this.spec = function() {
    return {optype:"InsertText", memberid:memberid, timestamp:timestamp, position:position, text:text, moveCursor:moveCursor};
  };
};
ops.OpInsertText.Spec;
ops.OpInsertText.InitSpec;
odf.CollapsingRules = function CollapsingRules(rootNode) {
  var odfUtils = odf.OdfUtils, domUtils = core.DomUtils;
  function filterOdfNodesToRemove(node) {
    var isToRemove = odfUtils.isODFNode(node) || node.localName === "br" && odfUtils.isLineBreak(node.parentNode) || node.nodeType === Node.TEXT_NODE && odfUtils.isODFNode(node.parentNode);
    return isToRemove ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
  }
  function isCollapsibleContainer(node) {
    return !odfUtils.isParagraph(node) && node !== rootNode && odfUtils.hasNoODFContent(node);
  }
  function mergeChildrenIntoParent(targetNode) {
    var parent;
    if (targetNode.nodeType === Node.TEXT_NODE) {
      parent = targetNode.parentNode;
      parent.removeChild(targetNode);
    } else {
      parent = domUtils.removeUnwantedNodes(targetNode, filterOdfNodesToRemove);
    }
    if (parent && isCollapsibleContainer(parent)) {
      return mergeChildrenIntoParent(parent);
    }
    return parent;
  }
  this.mergeChildrenIntoParent = mergeChildrenIntoParent;
};
ops.OpMergeParagraph = function OpMergeParagraph() {
  var memberid, timestamp, moveCursor, paragraphStyleName, sourceStartPosition, destinationStartPosition, odfUtils = odf.OdfUtils, domUtils = core.DomUtils, textns = odf.Namespaces.textns;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    moveCursor = data.moveCursor;
    paragraphStyleName = data.paragraphStyleName;
    sourceStartPosition = parseInt(data.sourceStartPosition, 10);
    destinationStartPosition = parseInt(data.destinationStartPosition, 10);
  };
  this.isEdit = true;
  this.group = undefined;
  function filterEmptyGroupingElementToRemove(element) {
    if (odf.OdfUtils.isInlineRoot(element)) {
      return NodeFilter.FILTER_SKIP;
    }
    return odfUtils.isGroupingElement(element) && odfUtils.hasNoODFContent(element) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
  }
  function mergeParagraphs(destination, source) {
    var child;
    child = source.firstChild;
    while (child) {
      if (child.localName === "editinfo") {
        source.removeChild(child);
      } else {
        destination.appendChild(child);
        domUtils.removeUnwantedNodes(child, filterEmptyGroupingElementToRemove);
      }
      child = source.firstChild;
    }
  }
  function isInsignificantWhitespace(node) {
    var textNode, badNodeDescription;
    if (node.nodeType === Node.TEXT_NODE) {
      textNode = node;
      if (textNode.length === 0) {
        runtime.log("WARN: Empty text node found during merge operation");
        return true;
      }
      if (odfUtils.isODFWhitespace(textNode.data) && odfUtils.isSignificantWhitespace(textNode, 0) === false) {
        return true;
      }
      badNodeDescription = "#text";
    } else {
      badNodeDescription = (node.prefix ? node.prefix + ":" : "") + node.localName;
    }
    runtime.log("WARN: Unexpected text element found near paragraph boundary [" + badNodeDescription + "]");
    return false;
  }
  function removeTextNodes(range) {
    var emptyTextNodes;
    if (range.collapsed) {
      return;
    }
    domUtils.splitBoundaries(range);
    emptyTextNodes = odfUtils.getTextElements(range, false, true).filter(isInsignificantWhitespace);
    emptyTextNodes.forEach(function(node) {
      node.parentNode.removeChild(node);
    });
  }
  function trimLeadingInsignificantWhitespace(stepIterator, paragraphElement) {
    var range = paragraphElement.ownerDocument.createRange();
    stepIterator.setPosition(paragraphElement, 0);
    stepIterator.roundToNextStep();
    range.setStart(paragraphElement, 0);
    range.setEnd(stepIterator.container(), stepIterator.offset());
    removeTextNodes(range);
  }
  function trimTrailingInsignificantWhitespace(stepIterator, paragraphElement) {
    var range = paragraphElement.ownerDocument.createRange();
    stepIterator.setPosition(paragraphElement, paragraphElement.childNodes.length);
    stepIterator.roundToPreviousStep();
    range.setStart(stepIterator.container(), stepIterator.offset());
    range.setEnd(paragraphElement, paragraphElement.childNodes.length);
    removeTextNodes(range);
  }
  function getParagraphAtStep(odtDocument, steps, stepIterator) {
    var domPoint = odtDocument.convertCursorStepToDomPoint(steps), paragraph = odfUtils.getParagraphElement(domPoint.node, domPoint.offset);
    runtime.assert(Boolean(paragraph), "Paragraph not found at step " + steps);
    if (stepIterator) {
      stepIterator.setPosition(domPoint.node, domPoint.offset);
    }
    return paragraph;
  }
  this.execute = function(document) {
    var odtDocument = document, sourceParagraph, destinationParagraph, cursor = odtDocument.getCursor(memberid), rootNode = odtDocument.getRootNode(), collapseRules = new odf.CollapsingRules(rootNode), stepIterator = odtDocument.createStepIterator(rootNode, 0, [odtDocument.getPositionFilter()], rootNode), downgradeOffset;
    runtime.assert(destinationStartPosition < sourceStartPosition, "Destination paragraph (" + destinationStartPosition + ") must be " + "before source paragraph (" + sourceStartPosition + ")");
    destinationParagraph = getParagraphAtStep(odtDocument, destinationStartPosition);
    sourceParagraph = getParagraphAtStep(odtDocument, sourceStartPosition, stepIterator);
    stepIterator.previousStep();
    runtime.assert(domUtils.containsNode(destinationParagraph, stepIterator.container()), "Destination paragraph must be adjacent to the source paragraph");
    trimTrailingInsignificantWhitespace(stepIterator, destinationParagraph);
    downgradeOffset = destinationParagraph.childNodes.length;
    trimLeadingInsignificantWhitespace(stepIterator, sourceParagraph);
    mergeParagraphs(destinationParagraph, sourceParagraph);
    runtime.assert(sourceParagraph.childNodes.length === 0, "Source paragraph should be empty before it is removed");
    collapseRules.mergeChildrenIntoParent(sourceParagraph);
    odtDocument.emit(ops.OdtDocument.signalStepsRemoved, {position:sourceStartPosition - 1});
    stepIterator.setPosition(destinationParagraph, downgradeOffset);
    stepIterator.roundToClosestStep();
    if (!stepIterator.previousStep()) {
      stepIterator.roundToNextStep();
    }
    odtDocument.downgradeWhitespaces(stepIterator);
    if (paragraphStyleName) {
      destinationParagraph.setAttributeNS(textns, "text:style-name", paragraphStyleName);
    } else {
      destinationParagraph.removeAttributeNS(textns, "style-name");
    }
    if (cursor && moveCursor) {
      odtDocument.moveCursor(memberid, sourceStartPosition - 1, 0);
      odtDocument.emit(ops.Document.signalCursorMoved, cursor);
    }
    odtDocument.fixCursorPositions();
    odtDocument.getOdfCanvas().refreshSize();
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:destinationParagraph, memberId:memberid, timeStamp:timestamp});
    odtDocument.getOdfCanvas().rerenderAnnotations();
    return true;
  };
  this.spec = function() {
    return {optype:"MergeParagraph", memberid:memberid, timestamp:timestamp, moveCursor:moveCursor, paragraphStyleName:paragraphStyleName, sourceStartPosition:sourceStartPosition, destinationStartPosition:destinationStartPosition};
  };
};
ops.OpMergeParagraph.Spec;
ops.OpMergeParagraph.InitSpec;
ops.OpMoveCursor = function OpMoveCursor() {
  var memberid, timestamp, position, length, selectionType;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    length = data.length || 0;
    selectionType = data.selectionType || ops.OdtCursor.RangeSelection;
  };
  this.isEdit = false;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, cursor = odtDocument.getCursor(memberid), selectedRange;
    if (!cursor) {
      return false;
    }
    selectedRange = odtDocument.convertCursorToDomRange(position, length);
    cursor.setSelectedRange(selectedRange, length >= 0);
    cursor.setSelectionType(selectionType);
    odtDocument.emit(ops.Document.signalCursorMoved, cursor);
    return true;
  };
  this.spec = function() {
    return {optype:"MoveCursor", memberid:memberid, timestamp:timestamp, position:position, length:length, selectionType:selectionType};
  };
};
ops.OpMoveCursor.Spec;
ops.OpMoveCursor.InitSpec;
ops.OpRemoveAnnotation = function OpRemoveAnnotation() {
  var memberid, timestamp, position, length, domUtils = core.DomUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = parseInt(data.position, 10);
    length = parseInt(data.length, 10);
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, iterator = odtDocument.getIteratorAtPosition(position), container = iterator.container(), annotationNode, annotationEnd;
    while (!(container.namespaceURI === odf.Namespaces.officens && container.localName === "annotation")) {
      container = container.parentNode;
    }
    if (container === null) {
      return false;
    }
    annotationNode = container;
    annotationEnd = annotationNode.annotationEndElement;
    odtDocument.getOdfCanvas().forgetAnnotation(annotationNode);
    function insert(node) {
      annotationNode.parentNode.insertBefore(node, annotationNode);
    }
    domUtils.getElementsByTagNameNS(annotationNode, "urn:webodf:names:cursor", "cursor").forEach(insert);
    domUtils.getElementsByTagNameNS(annotationNode, "urn:webodf:names:cursor", "anchor").forEach(insert);
    annotationNode.parentNode.removeChild(annotationNode);
    if (annotationEnd) {
      annotationEnd.parentNode.removeChild(annotationEnd);
    }
    odtDocument.emit(ops.OdtDocument.signalStepsRemoved, {position:position > 0 ? position - 1 : position});
    odtDocument.getOdfCanvas().rerenderAnnotations();
    odtDocument.fixCursorPositions();
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveAnnotation", memberid:memberid, timestamp:timestamp, position:position, length:length};
  };
};
ops.OpRemoveAnnotation.Spec;
ops.OpRemoveAnnotation.InitSpec;
ops.OpRemoveBlob = function OpRemoveBlob() {
  var memberid, timestamp, filename;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    filename = data.filename;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document;
    odtDocument.getOdfCanvas().odfContainer().removeBlob(filename);
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveBlob", memberid:memberid, timestamp:timestamp, filename:filename};
  };
};
ops.OpRemoveBlob.Spec;
ops.OpRemoveBlob.InitSpec;
ops.OpRemoveCursor = function OpRemoveCursor() {
  var memberid, timestamp;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
  };
  this.isEdit = false;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document;
    if (!odtDocument.removeCursor(memberid)) {
      return false;
    }
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveCursor", memberid:memberid, timestamp:timestamp};
  };
};
ops.OpRemoveCursor.Spec;
ops.OpRemoveCursor.InitSpec;
ops.OpRemoveHyperlink = function OpRemoveHyperlink() {
  var memberid, timestamp, position, length, domUtils = core.DomUtils, odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    length = data.length;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, range = odtDocument.convertCursorToDomRange(position, length), links = odfUtils.getHyperlinkElements(range), node;
    runtime.assert(links.length === 1, "The given range should only contain a single link.");
    node = domUtils.mergeIntoParent(links[0]);
    range.detach();
    odtDocument.fixCursorPositions();
    odtDocument.getOdfCanvas().refreshSize();
    odtDocument.getOdfCanvas().rerenderAnnotations();
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:odfUtils.getParagraphElement(node), memberId:memberid, timeStamp:timestamp});
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveHyperlink", memberid:memberid, timestamp:timestamp, position:position, length:length};
  };
};
ops.OpRemoveHyperlink.Spec;
ops.OpRemoveHyperlink.InitSpec;
ops.OpRemoveMember = function OpRemoveMember() {
  var memberid, timestamp;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = parseInt(data.timestamp, 10);
  };
  this.isEdit = false;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document;
    if (!odtDocument.getMember(memberid)) {
      return false;
    }
    odtDocument.removeMember(memberid);
    odtDocument.emit(ops.Document.signalMemberRemoved, memberid);
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveMember", memberid:memberid, timestamp:timestamp};
  };
};
ops.OpRemoveMember.Spec;
ops.OpRemoveMember.InitSpec;
ops.OpRemoveStyle = function OpRemoveStyle() {
  var memberid, timestamp, styleName, styleFamily;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    styleName = data.styleName;
    styleFamily = data.styleFamily;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, styleNode = odtDocument.getFormatting().getStyleElement(styleName, styleFamily);
    if (!styleNode) {
      return false;
    }
    styleNode.parentNode.removeChild(styleNode);
    odtDocument.getOdfCanvas().refreshCSS();
    odtDocument.emit(ops.OdtDocument.signalCommonStyleDeleted, {name:styleName, family:styleFamily});
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveStyle", memberid:memberid, timestamp:timestamp, styleName:styleName, styleFamily:styleFamily};
  };
};
ops.OpRemoveStyle.Spec;
ops.OpRemoveStyle.InitSpec;
ops.OpRemoveText = function OpRemoveText() {
  var memberid, timestamp, position, length, odfUtils = odf.OdfUtils, domUtils = core.DomUtils;
  this.init = function(data) {
    runtime.assert(data.length >= 0, "OpRemoveText only supports positive lengths");
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = parseInt(data.position, 10);
    length = parseInt(data.length, 10);
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, range, textNodes, paragraph, cursor = odtDocument.getCursor(memberid), collapseRules = new odf.CollapsingRules(odtDocument.getRootNode());
    odtDocument.upgradeWhitespacesAtPosition(position);
    odtDocument.upgradeWhitespacesAtPosition(position + length);
    range = odtDocument.convertCursorToDomRange(position, length);
    domUtils.splitBoundaries(range);
    textNodes = odfUtils.getTextElements(range, false, true);
    paragraph = odfUtils.getParagraphElement(range.startContainer, range.startOffset);
    runtime.assert(paragraph !== undefined, "Attempting to remove text outside a paragraph element");
    range.detach();
    textNodes.forEach(function(element) {
      if (element.parentNode) {
        runtime.assert(domUtils.containsNode(paragraph, element), "RemoveText only supports removing elements within the same paragraph");
        collapseRules.mergeChildrenIntoParent(element);
      } else {
        runtime.log("WARN: text element has already been removed from it's container");
      }
    });
    odtDocument.emit(ops.OdtDocument.signalStepsRemoved, {position:position});
    odtDocument.downgradeWhitespacesAtPosition(position);
    odtDocument.fixCursorPositions();
    odtDocument.getOdfCanvas().refreshSize();
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraph, memberId:memberid, timeStamp:timestamp});
    if (cursor) {
      cursor.resetSelectionType();
      odtDocument.emit(ops.Document.signalCursorMoved, cursor);
    }
    odtDocument.getOdfCanvas().rerenderAnnotations();
    return true;
  };
  this.spec = function() {
    return {optype:"RemoveText", memberid:memberid, timestamp:timestamp, position:position, length:length};
  };
};
ops.OpRemoveText.Spec;
ops.OpRemoveText.InitSpec;
ops.OpSetBlob = function OpSetBlob() {
  var memberid, timestamp, filename, mimetype, content;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    filename = data.filename;
    mimetype = data.mimetype;
    content = data.content;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document;
    odtDocument.getOdfCanvas().odfContainer().setBlob(filename, mimetype, content);
    return true;
  };
  this.spec = function() {
    return {optype:"SetBlob", memberid:memberid, timestamp:timestamp, filename:filename, mimetype:mimetype, content:content};
  };
};
ops.OpSetBlob.Spec;
ops.OpSetBlob.InitSpec;
ops.OpSetParagraphStyle = function OpSetParagraphStyle() {
  var memberid, timestamp, position, styleName, textns = "urn:oasis:names:tc:opendocument:xmlns:text:1.0", odfUtils = odf.OdfUtils;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    styleName = data.styleName;
  };
  this.isEdit = true;
  this.group = undefined;
  function isFirstStep(odtDocument, paragraphNode, iterator) {
    var filters = [odtDocument.getPositionFilter()], container = iterator.container(), offset = iterator.unfilteredDomOffset(), stepIterator = odtDocument.createStepIterator(container, offset, filters, paragraphNode);
    return stepIterator.previousStep() === false;
  }
  this.execute = function(document) {
    var odtDocument = document, iterator, paragraphNode;
    iterator = odtDocument.getIteratorAtPosition(position);
    paragraphNode = odfUtils.getParagraphElement(iterator.container());
    if (paragraphNode) {
      runtime.assert(isFirstStep(odtDocument, paragraphNode, iterator), "SetParagraphStyle position should be the first position in the paragraph");
      if (styleName) {
        paragraphNode.setAttributeNS(textns, "text:style-name", styleName);
      } else {
        paragraphNode.removeAttributeNS(textns, "style-name");
      }
      odtDocument.getOdfCanvas().refreshSize();
      odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraphNode, timeStamp:timestamp, memberId:memberid});
      odtDocument.getOdfCanvas().rerenderAnnotations();
      return true;
    }
    return false;
  };
  this.spec = function() {
    return {optype:"SetParagraphStyle", memberid:memberid, timestamp:timestamp, position:position, styleName:styleName};
  };
};
ops.OpSetParagraphStyle.Spec;
ops.OpSetParagraphStyle.InitSpec;
ops.OpSplitParagraph = function OpSplitParagraph() {
  var memberid, timestamp, sourceParagraphPosition, position, moveCursor, paragraphStyleName, odfUtils = odf.OdfUtils, textns = odf.Namespaces.textns;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    position = data.position;
    sourceParagraphPosition = data.sourceParagraphPosition;
    paragraphStyleName = data.paragraphStyleName;
    moveCursor = data.moveCursor === "true" || data.moveCursor === true;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, domPosition, paragraphNode, targetNode, node, splitNode, splitChildNode, keptChildNode, cursor = odtDocument.getCursor(memberid);
    odtDocument.upgradeWhitespacesAtPosition(position);
    domPosition = odtDocument.getTextNodeAtStep(position);
    if (!domPosition) {
      return false;
    }
    paragraphNode = odfUtils.getParagraphElement(domPosition.textNode);
    if (!paragraphNode) {
      return false;
    }
    if (odfUtils.isListItem(paragraphNode.parentNode)) {
      targetNode = paragraphNode.parentNode;
    } else {
      targetNode = paragraphNode;
    }
    if (domPosition.offset === 0) {
      keptChildNode = domPosition.textNode.previousSibling;
      splitChildNode = null;
    } else {
      keptChildNode = domPosition.textNode;
      if (domPosition.offset >= domPosition.textNode.length) {
        splitChildNode = null;
      } else {
        splitChildNode = domPosition.textNode.splitText(domPosition.offset);
      }
    }
    node = domPosition.textNode;
    while (node !== targetNode) {
      node = node.parentNode;
      splitNode = node.cloneNode(false);
      if (splitChildNode) {
        splitNode.appendChild(splitChildNode);
      }
      if (keptChildNode) {
        while (keptChildNode && keptChildNode.nextSibling) {
          splitNode.appendChild(keptChildNode.nextSibling);
        }
      } else {
        while (node.firstChild) {
          splitNode.appendChild(node.firstChild);
        }
      }
      node.parentNode.insertBefore(splitNode, node.nextSibling);
      keptChildNode = node;
      splitChildNode = splitNode;
    }
    if (odfUtils.isListItem(splitChildNode)) {
      splitChildNode = splitChildNode.childNodes.item(0);
    }
    if (paragraphStyleName) {
      splitChildNode.setAttributeNS(textns, "text:style-name", paragraphStyleName);
    } else {
      splitChildNode.removeAttributeNS(textns, "style-name");
    }
    if (domPosition.textNode.length === 0) {
      domPosition.textNode.parentNode.removeChild(domPosition.textNode);
    }
    odtDocument.emit(ops.OdtDocument.signalStepsInserted, {position:position});
    if (cursor && moveCursor) {
      odtDocument.moveCursor(memberid, position + 1, 0);
      odtDocument.emit(ops.Document.signalCursorMoved, cursor);
    }
    odtDocument.fixCursorPositions();
    odtDocument.getOdfCanvas().refreshSize();
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:paragraphNode, memberId:memberid, timeStamp:timestamp});
    odtDocument.emit(ops.OdtDocument.signalParagraphChanged, {paragraphElement:splitChildNode, memberId:memberid, timeStamp:timestamp});
    odtDocument.getOdfCanvas().rerenderAnnotations();
    return true;
  };
  this.spec = function() {
    return {optype:"SplitParagraph", memberid:memberid, timestamp:timestamp, position:position, sourceParagraphPosition:sourceParagraphPosition, paragraphStyleName:paragraphStyleName, moveCursor:moveCursor};
  };
};
ops.OpSplitParagraph.Spec;
ops.OpSplitParagraph.InitSpec;
ops.OpUpdateMember = function OpUpdateMember() {
  var memberid, timestamp, setProperties, removedProperties;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = parseInt(data.timestamp, 10);
    setProperties = data.setProperties;
    removedProperties = data.removedProperties;
  };
  this.isEdit = false;
  this.group = undefined;
  function updateCreators(doc) {
    var xpath = xmldom.XPath, xp = "//dc:creator[@editinfo:memberid='" + memberid + "']", creators = xpath.getODFElementsWithXPath(doc.getRootNode(), xp, function(prefix) {
      if (prefix === "editinfo") {
        return "urn:webodf:names:editinfo";
      }
      return odf.Namespaces.lookupNamespaceURI(prefix);
    }), i;
    for (i = 0;i < creators.length;i += 1) {
      creators[i].textContent = setProperties.fullName;
    }
  }
  this.execute = function(document) {
    var odtDocument = document, member = odtDocument.getMember(memberid);
    if (!member) {
      return false;
    }
    if (removedProperties) {
      member.removeProperties(removedProperties);
    }
    if (setProperties) {
      member.setProperties(setProperties);
      if (setProperties.fullName) {
        updateCreators(odtDocument);
      }
    }
    odtDocument.emit(ops.Document.signalMemberUpdated, member);
    return true;
  };
  this.spec = function() {
    return {optype:"UpdateMember", memberid:memberid, timestamp:timestamp, setProperties:setProperties, removedProperties:removedProperties};
  };
};
ops.OpUpdateMember.Spec;
ops.OpUpdateMember.InitSpec;
ops.OpUpdateMetadata = function OpUpdateMetadata() {
  var memberid, timestamp, setProperties, removedProperties;
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = parseInt(data.timestamp, 10);
    setProperties = data.setProperties;
    removedProperties = data.removedProperties;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, odfContainer = odtDocument.getOdfCanvas().odfContainer(), removedPropertiesArray = null;
    if (removedProperties) {
      removedPropertiesArray = removedProperties.attributes.split(",");
    }
    odfContainer.setMetadata(setProperties, removedPropertiesArray);
    odtDocument.emit(ops.OdtDocument.signalMetadataUpdated, {setProperties:setProperties !== null ? setProperties : {}, removedProperties:removedPropertiesArray !== null ? removedPropertiesArray : []});
    return true;
  };
  this.spec = function() {
    return {optype:"UpdateMetadata", memberid:memberid, timestamp:timestamp, setProperties:setProperties, removedProperties:removedProperties};
  };
};
ops.OpUpdateMetadata.Spec;
ops.OpUpdateMetadata.InitSpec;
ops.OpUpdateParagraphStyle = function OpUpdateParagraphStyle() {
  var memberid, timestamp, styleName, setProperties, removedProperties, paragraphPropertiesName = "style:paragraph-properties", textPropertiesName = "style:text-properties", stylens = odf.Namespaces.stylens;
  function removedAttributesFromStyleNode(node, removedAttributeNames) {
    var i, attributeNameParts, attributeNameList = removedAttributeNames ? removedAttributeNames.split(",") : [];
    for (i = 0;i < attributeNameList.length;i += 1) {
      attributeNameParts = attributeNameList[i].split(":");
      node.removeAttributeNS(odf.Namespaces.lookupNamespaceURI(attributeNameParts[0]), attributeNameParts[1]);
    }
  }
  this.init = function(data) {
    memberid = data.memberid;
    timestamp = data.timestamp;
    styleName = data.styleName;
    setProperties = data.setProperties;
    removedProperties = data.removedProperties;
  };
  this.isEdit = true;
  this.group = undefined;
  this.execute = function(document) {
    var odtDocument = document, formatting = odtDocument.getFormatting(), styleNode, object, paragraphPropertiesNode, textPropertiesNode;
    if (styleName !== "") {
      styleNode = formatting.getStyleElement(styleName, "paragraph");
    } else {
      styleNode = formatting.getDefaultStyleElement("paragraph");
    }
    if (styleNode) {
      paragraphPropertiesNode = styleNode.getElementsByTagNameNS(stylens, "paragraph-properties").item(0);
      textPropertiesNode = styleNode.getElementsByTagNameNS(stylens, "text-properties").item(0);
      if (setProperties) {
        formatting.updateStyle(styleNode, setProperties);
      }
      if (removedProperties) {
        object = removedProperties[paragraphPropertiesName];
        if (paragraphPropertiesNode && object) {
          removedAttributesFromStyleNode(paragraphPropertiesNode, object.attributes);
          if (paragraphPropertiesNode.attributes.length === 0) {
            styleNode.removeChild(paragraphPropertiesNode);
          }
        }
        object = removedProperties[textPropertiesName];
        if (textPropertiesNode && object) {
          removedAttributesFromStyleNode(textPropertiesNode, object.attributes);
          if (textPropertiesNode.attributes.length === 0) {
            styleNode.removeChild(textPropertiesNode);
          }
        }
        removedAttributesFromStyleNode(styleNode, removedProperties.attributes);
      }
      odtDocument.getOdfCanvas().refreshCSS();
      odtDocument.emit(ops.OdtDocument.signalParagraphStyleModified, styleName);
      odtDocument.getOdfCanvas().rerenderAnnotations();
      return true;
    }
    return false;
  };
  this.spec = function() {
    return {optype:"UpdateParagraphStyle", memberid:memberid, timestamp:timestamp, styleName:styleName, setProperties:setProperties, removedProperties:removedProperties};
  };
};
ops.OpUpdateParagraphStyle.Spec;
ops.OpUpdateParagraphStyle.InitSpec;
ops.OperationFactory = function OperationFactory() {
  var specs;
  function construct(Constructor) {
    return function(spec) {
      return new Constructor;
    };
  }
  this.register = function(specName, specConstructor) {
    specs[specName] = specConstructor;
  };
  this.create = function(spec) {
    var op = null, constructor = specs[spec.optype];
    if (constructor) {
      op = constructor(spec);
      op.init(spec);
    }
    return op;
  };
  function init() {
    specs = {AddMember:construct(ops.OpAddMember), UpdateMember:construct(ops.OpUpdateMember), RemoveMember:construct(ops.OpRemoveMember), AddCursor:construct(ops.OpAddCursor), ApplyDirectStyling:construct(ops.OpApplyDirectStyling), SetBlob:construct(ops.OpSetBlob), RemoveBlob:construct(ops.OpRemoveBlob), InsertImage:construct(ops.OpInsertImage), InsertTable:construct(ops.OpInsertTable), InsertText:construct(ops.OpInsertText), RemoveText:construct(ops.OpRemoveText), MergeParagraph:construct(ops.OpMergeParagraph), 
    SplitParagraph:construct(ops.OpSplitParagraph), SetParagraphStyle:construct(ops.OpSetParagraphStyle), UpdateParagraphStyle:construct(ops.OpUpdateParagraphStyle), AddStyle:construct(ops.OpAddStyle), RemoveStyle:construct(ops.OpRemoveStyle), MoveCursor:construct(ops.OpMoveCursor), RemoveCursor:construct(ops.OpRemoveCursor), AddAnnotation:construct(ops.OpAddAnnotation), RemoveAnnotation:construct(ops.OpRemoveAnnotation), UpdateMetadata:construct(ops.OpUpdateMetadata), ApplyHyperlink:construct(ops.OpApplyHyperlink), 
    RemoveHyperlink:construct(ops.OpRemoveHyperlink)};
  }
  init();
};
ops.OperationFactory.SpecConstructor;
ops.OperationRouter = function OperationRouter() {
};
ops.OperationRouter.prototype.setOperationFactory = function(f) {
};
ops.OperationRouter.prototype.setPlaybackFunction = function(playback_func) {
};
ops.OperationRouter.prototype.push = function(operations) {
};
ops.OperationRouter.prototype.close = function(callback) {
};
ops.OperationRouter.prototype.subscribe = function(eventId, cb) {
};
ops.OperationRouter.prototype.unsubscribe = function(eventId, cb) {
};
ops.OperationRouter.prototype.hasLocalUnsyncedOps = function() {
};
ops.OperationRouter.prototype.hasSessionHostConnection = function() {
};
ops.OperationRouter.signalProcessingBatchStart = "router/batchstart";
ops.OperationRouter.signalProcessingBatchEnd = "router/batchend";
ops.TrivialOperationRouter = function TrivialOperationRouter() {
  var events = new core.EventNotifier([ops.OperationRouter.signalProcessingBatchStart, ops.OperationRouter.signalProcessingBatchEnd]), operationFactory, playbackFunction, groupIdentifier = 0;
  this.setOperationFactory = function(f) {
    operationFactory = f;
  };
  this.setPlaybackFunction = function(playback_func) {
    playbackFunction = playback_func;
  };
  this.push = function(operations) {
    groupIdentifier += 1;
    events.emit(ops.OperationRouter.signalProcessingBatchStart, {});
    operations.forEach(function(op) {
      var timedOp, opspec = op.spec();
      opspec.timestamp = Date.now();
      timedOp = operationFactory.create(opspec);
      timedOp.group = "g" + groupIdentifier;
      playbackFunction(timedOp);
    });
    events.emit(ops.OperationRouter.signalProcessingBatchEnd, {});
  };
  this.close = function(cb) {
    cb();
  };
  this.subscribe = function(eventId, cb) {
    events.subscribe(eventId, cb);
  };
  this.unsubscribe = function(eventId, cb) {
    events.unsubscribe(eventId, cb);
  };
  this.hasLocalUnsyncedOps = function() {
    return false;
  };
  this.hasSessionHostConnection = function() {
    return true;
  };
};
ops.Session = function Session(odfCanvas) {
  var self = this, operationFactory = new ops.OperationFactory, odtDocument = new ops.OdtDocument(odfCanvas), operationRouter = null;
  function forwardBatchStart(args) {
    odtDocument.emit(ops.OdtDocument.signalProcessingBatchStart, args);
  }
  function forwardBatchEnd(args) {
    odtDocument.emit(ops.OdtDocument.signalProcessingBatchEnd, args);
  }
  this.setOperationFactory = function(opFactory) {
    operationFactory = opFactory;
    if (operationRouter) {
      operationRouter.setOperationFactory(operationFactory);
    }
  };
  this.setOperationRouter = function(opRouter) {
    if (operationRouter) {
      operationRouter.unsubscribe(ops.OperationRouter.signalProcessingBatchStart, forwardBatchStart);
      operationRouter.unsubscribe(ops.OperationRouter.signalProcessingBatchEnd, forwardBatchEnd);
    }
    operationRouter = opRouter;
    operationRouter.subscribe(ops.OperationRouter.signalProcessingBatchStart, forwardBatchStart);
    operationRouter.subscribe(ops.OperationRouter.signalProcessingBatchEnd, forwardBatchEnd);
    opRouter.setPlaybackFunction(function(op) {
      odtDocument.emit(ops.OdtDocument.signalOperationStart, op);
      if (op.execute(odtDocument)) {
        odtDocument.emit(ops.OdtDocument.signalOperationEnd, op);
        return true;
      }
      return false;
    });
    opRouter.setOperationFactory(operationFactory);
  };
  this.getOperationFactory = function() {
    return operationFactory;
  };
  this.getOdtDocument = function() {
    return odtDocument;
  };
  this.enqueue = function(ops) {
    operationRouter.push(ops);
  };
  this.close = function(callback) {
    operationRouter.close(function(err) {
      if (err) {
        callback(err);
      } else {
        odtDocument.close(callback);
      }
    });
  };
  this.destroy = function(callback) {
    odtDocument.destroy(callback);
  };
  function init() {
    self.setOperationRouter(new ops.TrivialOperationRouter);
  }
  init();
};
gui.AnnotationController = function AnnotationController(session, sessionConstraints, inputMemberId) {
  var odtDocument = session.getOdtDocument(), isAnnotatable = false, eventNotifier = new core.EventNotifier([gui.AnnotationController.annotatableChanged]), odfUtils = odf.OdfUtils, NEXT = core.StepDirection.NEXT;
  function updatedCachedValues() {
    var cursor = odtDocument.getCursor(inputMemberId), cursorNode = cursor && cursor.getNode(), newIsAnnotatable = false;
    if (cursorNode) {
      newIsAnnotatable = !odfUtils.isWithinAnnotation(cursorNode, odtDocument.getRootNode());
    }
    if (newIsAnnotatable !== isAnnotatable) {
      isAnnotatable = newIsAnnotatable;
      eventNotifier.emit(gui.AnnotationController.annotatableChanged, isAnnotatable);
    }
  }
  function onCursorAdded(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updatedCachedValues();
    }
  }
  function onCursorRemoved(memberId) {
    if (memberId === inputMemberId) {
      updatedCachedValues();
    }
  }
  function onCursorMoved(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updatedCachedValues();
    }
  }
  this.isAnnotatable = function() {
    return isAnnotatable;
  };
  this.addAnnotation = function() {
    var op = new ops.OpAddAnnotation, selection = odtDocument.getCursorSelection(inputMemberId), length = selection.length, position = selection.position;
    if (!isAnnotatable) {
      return;
    }
    if (length === 0) {
      length = undefined;
    } else {
      position = length >= 0 ? position : position + length;
      length = Math.abs(length);
    }
    op.init({memberid:inputMemberId, position:position, length:length, name:inputMemberId + Date.now()});
    session.enqueue([op]);
  };
  this.removeAnnotation = function(annotationNode) {
    var startStep, endStep, op, moveCursor, currentUserName = odtDocument.getMember(inputMemberId).getProperties().fullName;
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.ANNOTATIONS.ONLY_DELETE_OWN) === true) {
      if (currentUserName !== odfUtils.getAnnotationCreator(annotationNode)) {
        return;
      }
    }
    startStep = odtDocument.convertDomPointToCursorStep(annotationNode, 0, NEXT);
    endStep = odtDocument.convertDomPointToCursorStep(annotationNode, annotationNode.childNodes.length);
    op = new ops.OpRemoveAnnotation;
    op.init({memberid:inputMemberId, position:startStep, length:endStep - startStep});
    moveCursor = new ops.OpMoveCursor;
    moveCursor.init({memberid:inputMemberId, position:startStep > 0 ? startStep - 1 : startStep, length:0});
    session.enqueue([op, moveCursor]);
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorAdded, onCursorAdded);
    odtDocument.unsubscribe(ops.Document.signalCursorRemoved, onCursorRemoved);
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorMoved);
    callback();
  };
  function init() {
    sessionConstraints.registerConstraint(gui.CommonConstraints.EDIT.ANNOTATIONS.ONLY_DELETE_OWN);
    odtDocument.subscribe(ops.Document.signalCursorAdded, onCursorAdded);
    odtDocument.subscribe(ops.Document.signalCursorRemoved, onCursorRemoved);
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorMoved);
    updatedCachedValues();
  }
  init();
};
gui.AnnotationController.annotatableChanged = "annotatable/changed";
gui.Avatar = function Avatar(parentElement, avatarInitiallyVisible) {
  var self = this, handle, image, pendingImageUrl, displayShown = "block", displayHidden = "none";
  this.setColor = function(color) {
    image.style.borderColor = color;
  };
  this.setImageUrl = function(url) {
    if (self.isVisible()) {
      image.src = url;
    } else {
      pendingImageUrl = url;
    }
  };
  this.isVisible = function() {
    return handle.style.display === displayShown;
  };
  this.show = function() {
    if (pendingImageUrl) {
      image.src = pendingImageUrl;
      pendingImageUrl = undefined;
    }
    handle.style.display = displayShown;
  };
  this.hide = function() {
    handle.style.display = displayHidden;
  };
  this.markAsFocussed = function(isFocussed) {
    if (isFocussed) {
      handle.classList.add("active");
    } else {
      handle.classList.remove("active");
    }
  };
  this.destroy = function(callback) {
    parentElement.removeChild(handle);
    callback();
  };
  function init() {
    var document = parentElement.ownerDocument;
    handle = document.createElement("div");
    image = document.createElement("img");
    handle.appendChild(image);
    handle.style.display = avatarInitiallyVisible ? displayShown : displayHidden;
    handle.className = "handle";
    parentElement.appendChild(handle);
  }
  init();
};
gui.StepInfo = function() {
};
gui.StepInfo.VisualDirection = {LEFT_TO_RIGHT:0, RIGHT_TO_LEFT:1};
gui.StepInfo.prototype.token;
gui.StepInfo.prototype.container = function() {
};
gui.StepInfo.prototype.offset = function() {
};
gui.StepInfo.prototype.direction;
gui.StepInfo.prototype.visualDirection;
gui.VisualStepScanner = function() {
};
gui.VisualStepScanner.prototype.token;
gui.VisualStepScanner.prototype.process = function(stepInfo, previousRect, nextRect) {
};
gui.GuiStepUtils = function GuiStepUtils() {
  var odfUtils = odf.OdfUtils, stepUtils = new odf.StepUtils, domUtils = core.DomUtils, NEXT = core.StepDirection.NEXT, LEFT_TO_RIGHT = gui.StepInfo.VisualDirection.LEFT_TO_RIGHT, RIGHT_TO_LEFT = gui.StepInfo.VisualDirection.RIGHT_TO_LEFT;
  function getContentRect(stepIterator) {
    var bounds = stepUtils.getContentBounds(stepIterator), range, rect = null;
    if (bounds) {
      if (bounds.container.nodeType === Node.TEXT_NODE) {
        range = bounds.container.ownerDocument.createRange();
        range.setStart(bounds.container, bounds.startOffset);
        range.setEnd(bounds.container, bounds.endOffset);
        rect = range.getClientRects().length > 0 ? range.getBoundingClientRect() : null;
        if (rect && bounds.container.data.substring(bounds.startOffset, bounds.endOffset) === " " && rect.width <= 1) {
          rect = null;
        }
        range.detach();
      } else {
        if (odfUtils.isCharacterElement(bounds.container) || odfUtils.isCharacterFrame(bounds.container)) {
          rect = domUtils.getBoundingClientRect(bounds.container);
        }
      }
    }
    return rect;
  }
  this.getContentRect = getContentRect;
  function moveToFilteredStep(stepIterator, direction, scanners) {
    var isForward = direction === NEXT, leftRect, rightRect, previousRect, nextRect, destinationToken, initialToken = stepIterator.snapshot(), wasTerminated = false, stepInfo;
    function process(terminated, scanner) {
      if (scanner.process(stepInfo, previousRect, nextRect)) {
        terminated = true;
        if (!destinationToken && scanner.token) {
          destinationToken = scanner.token;
        }
      }
      return terminated;
    }
    do {
      leftRect = getContentRect(stepIterator);
      stepInfo = {token:stepIterator.snapshot(), container:stepIterator.container, offset:stepIterator.offset, direction:direction, visualDirection:direction === NEXT ? LEFT_TO_RIGHT : RIGHT_TO_LEFT};
      if (stepIterator.nextStep()) {
        rightRect = getContentRect(stepIterator);
      } else {
        rightRect = null;
      }
      stepIterator.restore(stepInfo.token);
      if (isForward) {
        previousRect = leftRect;
        nextRect = rightRect;
      } else {
        previousRect = rightRect;
        nextRect = leftRect;
      }
      wasTerminated = scanners.reduce(process, false);
    } while (!wasTerminated && stepIterator.advanceStep(direction));
    if (!wasTerminated) {
      scanners.forEach(function(scanner) {
        if (!destinationToken && scanner.token) {
          destinationToken = scanner.token;
        }
      });
    }
    stepIterator.restore(destinationToken || initialToken);
    return Boolean(destinationToken);
  }
  this.moveToFilteredStep = moveToFilteredStep;
};
gui.Caret = function Caret(cursor, viewport, avatarInitiallyVisible, blinkOnRangeSelect) {
  var cursorns = "urn:webodf:names:cursor", MIN_OVERLAY_HEIGHT_PX = 8, BLINK_PERIOD_MS = 500, caretOverlay, caretElement, avatar, overlayElement, caretSizer, caretSizerRange, canvas = cursor.getDocument().getCanvas(), domUtils = core.DomUtils, guiStepUtils = new gui.GuiStepUtils, stepIterator, redrawTask, blinkTask, shouldResetBlink = false, shouldCheckCaretVisibility = false, shouldUpdateCaretSize = false, state = {isFocused:false, isShown:true, visibility:"hidden"}, lastState = {isFocused:!state.isFocused, 
  isShown:!state.isShown, visibility:"hidden"};
  function blinkCaret() {
    caretElement.style.opacity = caretElement.style.opacity === "0" ? "1" : "0";
    blinkTask.trigger();
  }
  function getCaretSizeFromCursor() {
    caretSizerRange.selectNodeContents(caretSizer);
    return caretSizerRange.getBoundingClientRect();
  }
  function getSelectionRect() {
    var node = cursor.getNode(), caretRectangle, nextRectangle, selectionRectangle, rootRect = domUtils.getBoundingClientRect(canvas.getSizer()), useLeftEdge = false, width = 0;
    node.removeAttributeNS(cursorns, "caret-sizer-active");
    if (node.getClientRects().length > 0) {
      selectionRectangle = getCaretSizeFromCursor();
      width = selectionRectangle.left - domUtils.getBoundingClientRect(node).left;
      useLeftEdge = true;
    } else {
      stepIterator.setPosition(node, 0);
      selectionRectangle = guiStepUtils.getContentRect(stepIterator);
      if (!selectionRectangle && stepIterator.nextStep()) {
        nextRectangle = guiStepUtils.getContentRect(stepIterator);
        if (nextRectangle) {
          selectionRectangle = nextRectangle;
          useLeftEdge = true;
        }
      }
      if (!selectionRectangle) {
        node.setAttributeNS(cursorns, "caret-sizer-active", "true");
        selectionRectangle = getCaretSizeFromCursor();
        useLeftEdge = true;
      }
      if (!selectionRectangle) {
        runtime.log("WARN: No suitable client rectangle found for visual caret for " + cursor.getMemberId());
        while (node) {
          if (node.getClientRects().length > 0) {
            selectionRectangle = domUtils.getBoundingClientRect(node);
            useLeftEdge = true;
            break;
          }
          node = node.parentNode;
        }
      }
    }
    selectionRectangle = domUtils.translateRect(selectionRectangle, rootRect, canvas.getZoomLevel());
    caretRectangle = {top:selectionRectangle.top, height:selectionRectangle.height, right:useLeftEdge ? selectionRectangle.left : selectionRectangle.right, width:domUtils.adaptRangeDifferenceToZoomLevel(width, canvas.getZoomLevel())};
    return caretRectangle;
  }
  function updateOverlayHeightAndPosition() {
    var selectionRect = getSelectionRect(), cursorStyle;
    if (selectionRect.height < MIN_OVERLAY_HEIGHT_PX) {
      selectionRect = {top:selectionRect.top - (MIN_OVERLAY_HEIGHT_PX - selectionRect.height) / 2, height:MIN_OVERLAY_HEIGHT_PX, right:selectionRect.right};
    }
    caretOverlay.style.height = selectionRect.height + "px";
    caretOverlay.style.top = selectionRect.top + "px";
    caretOverlay.style.left = selectionRect.right - selectionRect.width + "px";
    caretOverlay.style.width = selectionRect.width ? selectionRect.width + "px" : "";
    if (overlayElement) {
      cursorStyle = runtime.getWindow().getComputedStyle(cursor.getNode(), null);
      if (cursorStyle.font) {
        overlayElement.style.font = cursorStyle.font;
      } else {
        overlayElement.style.fontStyle = cursorStyle.fontStyle;
        overlayElement.style.fontVariant = cursorStyle.fontVariant;
        overlayElement.style.fontWeight = cursorStyle.fontWeight;
        overlayElement.style.fontSize = cursorStyle.fontSize;
        overlayElement.style.lineHeight = cursorStyle.lineHeight;
        overlayElement.style.fontFamily = cursorStyle.fontFamily;
      }
    }
  }
  function hasStateChanged(property) {
    return lastState[property] !== state[property];
  }
  function saveState() {
    Object.keys(state).forEach(function(key) {
      lastState[key] = state[key];
    });
  }
  function updateCaret() {
    if (state.isShown === false || cursor.getSelectionType() !== ops.OdtCursor.RangeSelection || !blinkOnRangeSelect && !cursor.getSelectedRange().collapsed) {
      state.visibility = "hidden";
      caretElement.style.visibility = "hidden";
      blinkTask.cancel();
    } else {
      state.visibility = "visible";
      caretElement.style.visibility = "visible";
      if (state.isFocused === false) {
        caretElement.style.opacity = "1";
        blinkTask.cancel();
      } else {
        if (shouldResetBlink || hasStateChanged("visibility")) {
          caretElement.style.opacity = "1";
          blinkTask.cancel();
        }
        blinkTask.trigger();
      }
    }
    if (shouldUpdateCaretSize || shouldCheckCaretVisibility) {
      updateOverlayHeightAndPosition();
    }
    if (state.isShown && shouldCheckCaretVisibility) {
      viewport.scrollIntoView(caretElement.getBoundingClientRect());
    }
    if (hasStateChanged("isFocused")) {
      avatar.markAsFocussed(state.isFocused);
    }
    saveState();
    shouldResetBlink = false;
    shouldCheckCaretVisibility = false;
    shouldUpdateCaretSize = false;
  }
  this.handleUpdate = function() {
    shouldUpdateCaretSize = true;
    redrawTask.trigger();
  };
  this.refreshCursorBlinking = function() {
    shouldResetBlink = true;
    redrawTask.trigger();
  };
  this.setFocus = function() {
    state.isFocused = true;
    redrawTask.trigger();
  };
  this.removeFocus = function() {
    state.isFocused = false;
    redrawTask.trigger();
  };
  this.show = function() {
    state.isShown = true;
    redrawTask.trigger();
  };
  this.hide = function() {
    state.isShown = false;
    redrawTask.trigger();
  };
  this.setAvatarImageUrl = function(url) {
    avatar.setImageUrl(url);
  };
  this.setColor = function(newColor) {
    caretElement.style.borderColor = newColor;
    avatar.setColor(newColor);
  };
  this.getCursor = function() {
    return cursor;
  };
  this.getFocusElement = function() {
    return caretElement;
  };
  this.toggleHandleVisibility = function() {
    if (avatar.isVisible()) {
      avatar.hide();
    } else {
      avatar.show();
    }
  };
  this.showHandle = function() {
    avatar.show();
  };
  this.hideHandle = function() {
    avatar.hide();
  };
  this.setOverlayElement = function(element) {
    overlayElement = element;
    caretOverlay.appendChild(element);
    shouldUpdateCaretSize = true;
    redrawTask.trigger();
  };
  this.ensureVisible = function() {
    shouldCheckCaretVisibility = true;
    redrawTask.trigger();
  };
  this.getBoundingClientRect = function() {
    return domUtils.getBoundingClientRect(caretOverlay);
  };
  function destroy(callback) {
    caretOverlay.parentNode.removeChild(caretOverlay);
    caretSizer.parentNode.removeChild(caretSizer);
    callback();
  }
  this.destroy = function(callback) {
    var cleanup = [redrawTask.destroy, blinkTask.destroy, avatar.destroy, destroy];
    core.Async.destroyAll(cleanup, callback);
  };
  function init() {
    var odtDocument = cursor.getDocument(), positionFilters = [odtDocument.createRootFilter(cursor.getMemberId()), odtDocument.getPositionFilter()], dom = odtDocument.getDOMDocument(), editinfons = "urn:webodf:names:editinfo";
    caretSizerRange = dom.createRange();
    caretSizer = dom.createElement("span");
    caretSizer.className = "webodf-caretSizer";
    caretSizer.textContent = "|";
    cursor.getNode().appendChild(caretSizer);
    caretOverlay = dom.createElement("div");
    caretOverlay.setAttributeNS(editinfons, "editinfo:memberid", cursor.getMemberId());
    caretOverlay.className = "webodf-caretOverlay";
    caretElement = dom.createElement("div");
    caretElement.className = "caret";
    caretOverlay.appendChild(caretElement);
    avatar = new gui.Avatar(caretOverlay, avatarInitiallyVisible);
    canvas.getSizer().appendChild(caretOverlay);
    stepIterator = odtDocument.createStepIterator(cursor.getNode(), 0, positionFilters, odtDocument.getRootNode());
    redrawTask = core.Task.createRedrawTask(updateCaret);
    blinkTask = core.Task.createTimeoutTask(blinkCaret, BLINK_PERIOD_MS);
    redrawTask.triggerImmediate();
  }
  init();
};
odf.TextSerializer = function TextSerializer() {
  var self = this, odfUtils = odf.OdfUtils;
  function serializeNode(node) {
    var s = "", accept = self.filter ? self.filter.acceptNode(node) : NodeFilter.FILTER_ACCEPT, nodeType = node.nodeType, child;
    if ((accept === NodeFilter.FILTER_ACCEPT || accept === NodeFilter.FILTER_SKIP) && odfUtils.isTextContentContainingNode(node)) {
      child = node.firstChild;
      while (child) {
        s += serializeNode(child);
        child = child.nextSibling;
      }
    }
    if (accept === NodeFilter.FILTER_ACCEPT) {
      if (nodeType === Node.ELEMENT_NODE && odfUtils.isParagraph(node)) {
        s += "\n";
      } else {
        if (nodeType === Node.TEXT_NODE && node.textContent) {
          s += node.textContent;
        }
      }
    }
    return s;
  }
  this.filter = null;
  this.writeToString = function(node) {
    var plainText;
    if (!node) {
      return "";
    }
    plainText = serializeNode(node);
    if (plainText[plainText.length - 1] === "\n") {
      plainText = plainText.substr(0, plainText.length - 1);
    }
    return plainText;
  };
};
gui.MimeDataExporter = function MimeDataExporter() {
  var textSerializer;
  this.exportRangeToDataTransfer = function(dataTransfer, range) {
    var document = range.startContainer.ownerDocument, serializedFragment, fragmentContainer;
    fragmentContainer = document.createElement("span");
    fragmentContainer.appendChild(range.cloneContents());
    serializedFragment = textSerializer.writeToString(fragmentContainer);
    try {
      dataTransfer.setData("text/plain", serializedFragment);
    } catch (e) {
      dataTransfer.setData("Text", serializedFragment);
    }
  };
  function init() {
    textSerializer = new odf.TextSerializer;
    textSerializer.filter = new odf.OdfNodeFilter;
  }
  init();
};
gui.Clipboard = function Clipboard(mimeDataExporter) {
  this.setDataFromRange = function(e, range) {
    var result, clipboard = e.clipboardData, window = runtime.getWindow();
    if (!clipboard && window) {
      clipboard = window.clipboardData;
    }
    if (clipboard) {
      result = true;
      mimeDataExporter.exportRangeToDataTransfer(clipboard, range);
      e.preventDefault();
    } else {
      result = false;
    }
    return result;
  };
};
gui.SessionContext = function(session, inputMemberId) {
  var odtDocument = session.getOdtDocument(), odfUtils = odf.OdfUtils;
  this.isLocalCursorWithinOwnAnnotation = function() {
    var cursor = odtDocument.getCursor(inputMemberId), cursorNode, currentUserName, parentAnnotation;
    if (!cursor) {
      return false;
    }
    cursorNode = cursor && cursor.getNode();
    currentUserName = odtDocument.getMember(inputMemberId).getProperties().fullName;
    parentAnnotation = odfUtils.getParentAnnotation(cursorNode, odtDocument.getRootNode());
    if (parentAnnotation && odfUtils.getAnnotationCreator(parentAnnotation) === currentUserName) {
      return true;
    }
    return false;
  };
};
gui.StyleSummary = function StyleSummary(styles) {
  var propertyValues = {};
  function getPropertyValues(section, propertyName) {
    var cacheKey = section + "|" + propertyName, values;
    if (!propertyValues.hasOwnProperty(cacheKey)) {
      values = [];
      styles.forEach(function(style) {
        var styleSection = style.styleProperties[section], value = styleSection && styleSection[propertyName];
        if (values.indexOf(value) === -1) {
          values.push(value);
        }
      });
      propertyValues[cacheKey] = values;
    }
    return propertyValues[cacheKey];
  }
  this.getPropertyValues = getPropertyValues;
  function lazilyLoaded(section, propertyName, acceptedPropertyValues) {
    return function() {
      var existingPropertyValues = getPropertyValues(section, propertyName);
      return acceptedPropertyValues.length >= existingPropertyValues.length && existingPropertyValues.every(function(v) {
        return acceptedPropertyValues.indexOf(v) !== -1;
      });
    };
  }
  function getCommonValue(section, propertyName) {
    var values = getPropertyValues(section, propertyName);
    return values.length === 1 ? values[0] : undefined;
  }
  this.getCommonValue = getCommonValue;
  this.isBold = lazilyLoaded("style:text-properties", "fo:font-weight", ["bold"]);
  this.isItalic = lazilyLoaded("style:text-properties", "fo:font-style", ["italic"]);
  this.hasUnderline = lazilyLoaded("style:text-properties", "style:text-underline-style", ["solid"]);
  this.hasStrikeThrough = lazilyLoaded("style:text-properties", "style:text-line-through-style", ["solid"]);
  this.fontSize = function() {
    var stringFontSize = getCommonValue("style:text-properties", "fo:font-size");
    return stringFontSize && parseFloat(stringFontSize);
  };
  this.fontName = function() {
    return getCommonValue("style:text-properties", "style:font-name");
  };
  this.isAlignedLeft = lazilyLoaded("style:paragraph-properties", "fo:text-align", ["left", "start"]);
  this.isAlignedCenter = lazilyLoaded("style:paragraph-properties", "fo:text-align", ["center"]);
  this.isAlignedRight = lazilyLoaded("style:paragraph-properties", "fo:text-align", ["right", "end"]);
  this.isAlignedJustified = lazilyLoaded("style:paragraph-properties", "fo:text-align", ["justify"]);
  this.text = {isBold:this.isBold, isItalic:this.isItalic, hasUnderline:this.hasUnderline, hasStrikeThrough:this.hasStrikeThrough, fontSize:this.fontSize, fontName:this.fontName};
  this.paragraph = {isAlignedLeft:this.isAlignedLeft, isAlignedCenter:this.isAlignedCenter, isAlignedRight:this.isAlignedRight, isAlignedJustified:this.isAlignedJustified};
};
gui.DirectFormattingController = function DirectFormattingController(session, sessionConstraints, sessionContext, inputMemberId, objectNameGenerator, directTextStylingEnabled, directParagraphStylingEnabled) {
  var self = this, odtDocument = session.getOdtDocument(), utils = new core.Utils, odfUtils = odf.OdfUtils, eventNotifier = new core.EventNotifier([gui.DirectFormattingController.enabledChanged, gui.DirectFormattingController.textStylingChanged, gui.DirectFormattingController.paragraphStylingChanged]), textns = odf.Namespaces.textns, NEXT = core.StepDirection.NEXT, directCursorStyleProperties = null, lastSignalledSelectionInfo, selectionInfoCache;
  function getCachedStyleSummary() {
    return selectionInfoCache.value().styleSummary;
  }
  function getCachedEnabledFeatures() {
    return selectionInfoCache.value().enabledFeatures;
  }
  this.enabledFeatures = getCachedEnabledFeatures;
  function getNodes(range) {
    var container, nodes;
    if (range.collapsed) {
      container = range.startContainer;
      if (container.hasChildNodes() && range.startOffset < container.childNodes.length) {
        container = container.childNodes.item(range.startOffset);
      }
      nodes = [container];
    } else {
      nodes = odfUtils.getTextElements(range, true, false);
    }
    return nodes;
  }
  function getSelectionInfo() {
    var cursor = odtDocument.getCursor(inputMemberId), range = cursor && cursor.getSelectedRange(), nodes = [], selectionStyles = [], selectionContainsText = true, enabledFeatures = {directTextStyling:true, directParagraphStyling:true};
    if (range) {
      nodes = getNodes(range);
      if (nodes.length === 0) {
        nodes = [range.startContainer, range.endContainer];
        selectionContainsText = false;
      }
      selectionStyles = odtDocument.getFormatting().getAppliedStyles(nodes);
    }
    if (selectionStyles[0] !== undefined && directCursorStyleProperties) {
      selectionStyles[0].styleProperties = utils.mergeObjects(selectionStyles[0].styleProperties, directCursorStyleProperties);
    }
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.REVIEW_MODE) === true) {
      enabledFeatures.directTextStyling = enabledFeatures.directParagraphStyling = sessionContext.isLocalCursorWithinOwnAnnotation();
    }
    if (enabledFeatures.directTextStyling) {
      enabledFeatures.directTextStyling = selectionContainsText && cursor !== undefined && cursor.getSelectionType() === ops.OdtCursor.RangeSelection;
    }
    return {enabledFeatures:enabledFeatures, appliedStyles:selectionStyles, styleSummary:new gui.StyleSummary(selectionStyles)};
  }
  function createDiff(oldSummary, newSummary) {
    var diffMap = {};
    Object.keys(oldSummary).forEach(function(funcName) {
      var oldValue = oldSummary[funcName](), newValue = newSummary[funcName]();
      if (oldValue !== newValue) {
        diffMap[funcName] = newValue;
      }
    });
    return diffMap;
  }
  function emitSelectionChanges() {
    var textStyleDiff, paragraphStyleDiff, lastStyleSummary = lastSignalledSelectionInfo.styleSummary, newSelectionInfo = selectionInfoCache.value(), newSelectionStylesSummary = newSelectionInfo.styleSummary, lastEnabledFeatures = lastSignalledSelectionInfo.enabledFeatures, newEnabledFeatures = newSelectionInfo.enabledFeatures, enabledFeaturesChanged;
    textStyleDiff = createDiff(lastStyleSummary.text, newSelectionStylesSummary.text);
    paragraphStyleDiff = createDiff(lastStyleSummary.paragraph, newSelectionStylesSummary.paragraph);
    enabledFeaturesChanged = !(newEnabledFeatures.directTextStyling === lastEnabledFeatures.directTextStyling && newEnabledFeatures.directParagraphStyling === lastEnabledFeatures.directParagraphStyling);
    lastSignalledSelectionInfo = newSelectionInfo;
    if (enabledFeaturesChanged) {
      eventNotifier.emit(gui.DirectFormattingController.enabledChanged, newEnabledFeatures);
    }
    if (Object.keys(textStyleDiff).length > 0) {
      eventNotifier.emit(gui.DirectFormattingController.textStylingChanged, textStyleDiff);
    }
    if (Object.keys(paragraphStyleDiff).length > 0) {
      eventNotifier.emit(gui.DirectFormattingController.paragraphStylingChanged, paragraphStyleDiff);
    }
  }
  function forceSelectionInfoRefresh() {
    selectionInfoCache.reset();
    emitSelectionChanges();
  }
  function onCursorEvent(cursorOrId) {
    var cursorMemberId = typeof cursorOrId === "string" ? cursorOrId : cursorOrId.getMemberId();
    if (cursorMemberId === inputMemberId) {
      selectionInfoCache.reset();
    }
  }
  function onParagraphStyleModified() {
    selectionInfoCache.reset();
  }
  function onParagraphChanged(args) {
    var cursor = odtDocument.getCursor(inputMemberId), p = args.paragraphElement;
    if (cursor && odfUtils.getParagraphElement(cursor.getNode()) === p) {
      selectionInfoCache.reset();
    }
  }
  function toggle(predicate, toggleMethod) {
    toggleMethod(!predicate());
    return true;
  }
  function formatTextSelection(textProperties) {
    if (!getCachedEnabledFeatures().directTextStyling) {
      return;
    }
    var selection = odtDocument.getCursorSelection(inputMemberId), op, properties = {"style:text-properties":textProperties};
    if (selection.length !== 0) {
      op = new ops.OpApplyDirectStyling;
      op.init({memberid:inputMemberId, position:selection.position, length:selection.length, setProperties:properties});
      session.enqueue([op]);
    } else {
      directCursorStyleProperties = utils.mergeObjects(directCursorStyleProperties || {}, properties);
      selectionInfoCache.reset();
    }
  }
  this.formatTextSelection = formatTextSelection;
  function applyTextPropertyToSelection(propertyName, propertyValue) {
    var textProperties = {};
    textProperties[propertyName] = propertyValue;
    formatTextSelection(textProperties);
  }
  this.createCursorStyleOp = function(position, length, useCachedStyle) {
    var styleOp = null, appliedStyles, properties = directCursorStyleProperties;
    if (useCachedStyle) {
      appliedStyles = selectionInfoCache.value().appliedStyles[0];
      properties = appliedStyles && appliedStyles.styleProperties;
    }
    if (properties && properties["style:text-properties"]) {
      styleOp = new ops.OpApplyDirectStyling;
      styleOp.init({memberid:inputMemberId, position:position, length:length, setProperties:{"style:text-properties":properties["style:text-properties"]}});
      directCursorStyleProperties = null;
      selectionInfoCache.reset();
    }
    return styleOp;
  };
  function clearCursorStyle(op) {
    var spec = op.spec();
    if (directCursorStyleProperties && spec.memberid === inputMemberId) {
      if (spec.optype !== "SplitParagraph") {
        directCursorStyleProperties = null;
        selectionInfoCache.reset();
      }
    }
  }
  function setBold(checked) {
    var value = checked ? "bold" : "normal";
    applyTextPropertyToSelection("fo:font-weight", value);
  }
  this.setBold = setBold;
  function setItalic(checked) {
    var value = checked ? "italic" : "normal";
    applyTextPropertyToSelection("fo:font-style", value);
  }
  this.setItalic = setItalic;
  function setHasUnderline(checked) {
    var value = checked ? "solid" : "none";
    applyTextPropertyToSelection("style:text-underline-style", value);
  }
  this.setHasUnderline = setHasUnderline;
  function setHasStrikethrough(checked) {
    var value = checked ? "solid" : "none";
    applyTextPropertyToSelection("style:text-line-through-style", value);
  }
  this.setHasStrikethrough = setHasStrikethrough;
  function setFontSize(value) {
    applyTextPropertyToSelection("fo:font-size", value + "pt");
  }
  this.setFontSize = setFontSize;
  function setFontName(value) {
    applyTextPropertyToSelection("style:font-name", value);
  }
  this.setFontName = setFontName;
  this.getAppliedStyles = function() {
    return selectionInfoCache.value().appliedStyles;
  };
  this.toggleBold = toggle.bind(self, function() {
    return getCachedStyleSummary().isBold();
  }, setBold);
  this.toggleItalic = toggle.bind(self, function() {
    return getCachedStyleSummary().isItalic();
  }, setItalic);
  this.toggleUnderline = toggle.bind(self, function() {
    return getCachedStyleSummary().hasUnderline();
  }, setHasUnderline);
  this.toggleStrikethrough = toggle.bind(self, function() {
    return getCachedStyleSummary().hasStrikeThrough();
  }, setHasStrikethrough);
  this.isBold = function() {
    return getCachedStyleSummary().isBold();
  };
  this.isItalic = function() {
    return getCachedStyleSummary().isItalic();
  };
  this.hasUnderline = function() {
    return getCachedStyleSummary().hasUnderline();
  };
  this.hasStrikeThrough = function() {
    return getCachedStyleSummary().hasStrikeThrough();
  };
  this.fontSize = function() {
    return getCachedStyleSummary().fontSize();
  };
  this.fontName = function() {
    return getCachedStyleSummary().fontName();
  };
  this.isAlignedLeft = function() {
    return getCachedStyleSummary().isAlignedLeft();
  };
  this.isAlignedCenter = function() {
    return getCachedStyleSummary().isAlignedCenter();
  };
  this.isAlignedRight = function() {
    return getCachedStyleSummary().isAlignedRight();
  };
  this.isAlignedJustified = function() {
    return getCachedStyleSummary().isAlignedJustified();
  };
  function getOwnProperty(obj, key) {
    return obj.hasOwnProperty(key) ? obj[key] : undefined;
  }
  function applyParagraphDirectStyling(applyDirectStyling) {
    if (!getCachedEnabledFeatures().directParagraphStyling) {
      return;
    }
    var range = odtDocument.getCursor(inputMemberId).getSelectedRange(), paragraphs = odfUtils.getParagraphElements(range), formatting = odtDocument.getFormatting(), operations = [], derivedStyleNames = {}, defaultStyleName;
    paragraphs.forEach(function(paragraph) {
      var paragraphStartPoint = odtDocument.convertDomPointToCursorStep(paragraph, 0, NEXT), paragraphStyleName = paragraph.getAttributeNS(odf.Namespaces.textns, "style-name"), newParagraphStyleName, opAddStyle, opSetParagraphStyle, paragraphProperties;
      if (paragraphStyleName) {
        newParagraphStyleName = getOwnProperty(derivedStyleNames, paragraphStyleName);
      } else {
        newParagraphStyleName = defaultStyleName;
      }
      if (!newParagraphStyleName) {
        newParagraphStyleName = objectNameGenerator.generateStyleName();
        if (paragraphStyleName) {
          derivedStyleNames[paragraphStyleName] = newParagraphStyleName;
          paragraphProperties = formatting.createDerivedStyleObject(paragraphStyleName, "paragraph", {});
        } else {
          defaultStyleName = newParagraphStyleName;
          paragraphProperties = {};
        }
        paragraphProperties = applyDirectStyling(paragraphProperties);
        opAddStyle = new ops.OpAddStyle;
        opAddStyle.init({memberid:inputMemberId, styleName:newParagraphStyleName.toString(), styleFamily:"paragraph", isAutomaticStyle:true, setProperties:paragraphProperties});
        operations.push(opAddStyle);
      }
      opSetParagraphStyle = new ops.OpSetParagraphStyle;
      opSetParagraphStyle.init({memberid:inputMemberId, styleName:newParagraphStyleName.toString(), position:paragraphStartPoint});
      operations.push(opSetParagraphStyle);
    });
    session.enqueue(operations);
  }
  function applySimpleParagraphDirectStyling(styleOverrides) {
    applyParagraphDirectStyling(function(paragraphStyle) {
      return utils.mergeObjects(paragraphStyle, styleOverrides);
    });
  }
  function alignParagraph(alignment) {
    applySimpleParagraphDirectStyling({"style:paragraph-properties":{"fo:text-align":alignment}});
  }
  this.alignParagraphLeft = function() {
    alignParagraph("left");
    return true;
  };
  this.alignParagraphCenter = function() {
    alignParagraph("center");
    return true;
  };
  this.alignParagraphRight = function() {
    alignParagraph("right");
    return true;
  };
  this.alignParagraphJustified = function() {
    alignParagraph("justify");
    return true;
  };
  function modifyParagraphIndent(direction, paragraphStyle) {
    var tabStopDistance = odtDocument.getFormatting().getDefaultTabStopDistance(), paragraphProperties = paragraphStyle["style:paragraph-properties"], indentValue, indent, newIndent;
    if (paragraphProperties) {
      indentValue = paragraphProperties["fo:margin-left"];
      indent = odfUtils.parseLength(indentValue);
    }
    if (indent && indent.unit === tabStopDistance.unit) {
      newIndent = indent.value + direction * tabStopDistance.value + indent.unit;
    } else {
      newIndent = direction * tabStopDistance.value + tabStopDistance.unit;
    }
    return utils.mergeObjects(paragraphStyle, {"style:paragraph-properties":{"fo:margin-left":newIndent}});
  }
  this.indent = function() {
    applyParagraphDirectStyling(modifyParagraphIndent.bind(null, 1));
    return true;
  };
  this.outdent = function() {
    applyParagraphDirectStyling(modifyParagraphIndent.bind(null, -1));
    return true;
  };
  function isSelectionAtTheEndOfLastParagraph(range, paragraphNode) {
    var stepIterator, filters = [odtDocument.getPositionFilter(), odtDocument.createRootFilter(inputMemberId)];
    stepIterator = odtDocument.createStepIterator(range.endContainer, range.endOffset, filters, paragraphNode);
    return stepIterator.nextStep() === false;
  }
  function isTextStyleDifferentFromFirstParagraph(range, paragraphNode) {
    var textNodes = getNodes(range), selectedNodes = textNodes.length === 0 ? [range.startContainer] : textNodes, appliedTextStyles = odtDocument.getFormatting().getAppliedStyles(selectedNodes), textStyle = appliedTextStyles.length > 0 ? appliedTextStyles[0].styleProperties : undefined, paragraphStyle = odtDocument.getFormatting().getAppliedStylesForElement(paragraphNode).styleProperties;
    if (!textStyle || textStyle["style:family"] !== "text" || !textStyle["style:text-properties"]) {
      return false;
    }
    if (!paragraphStyle || !paragraphStyle["style:text-properties"]) {
      return true;
    }
    textStyle = textStyle["style:text-properties"];
    paragraphStyle = paragraphStyle["style:text-properties"];
    return !Object.keys(textStyle).every(function(key) {
      return textStyle[key] === paragraphStyle[key];
    });
  }
  this.createParagraphStyleOps = function(position) {
    if (!getCachedEnabledFeatures().directParagraphStyling) {
      return [];
    }
    var cursor = odtDocument.getCursor(inputMemberId), range = cursor.getSelectedRange(), operations = [], op, startNode, endNode, paragraphNode, appliedStyles, properties, parentStyleName, styleName;
    if (cursor.hasForwardSelection()) {
      startNode = cursor.getAnchorNode();
      endNode = cursor.getNode();
    } else {
      startNode = cursor.getNode();
      endNode = cursor.getAnchorNode();
    }
    paragraphNode = odfUtils.getParagraphElement(endNode);
    runtime.assert(Boolean(paragraphNode), "DirectFormattingController: Cursor outside paragraph");
    if (!isSelectionAtTheEndOfLastParagraph(range, paragraphNode)) {
      return operations;
    }
    if (endNode !== startNode) {
      paragraphNode = odfUtils.getParagraphElement(startNode);
    }
    if (!directCursorStyleProperties && !isTextStyleDifferentFromFirstParagraph(range, paragraphNode)) {
      return operations;
    }
    appliedStyles = selectionInfoCache.value().appliedStyles[0];
    properties = appliedStyles && appliedStyles.styleProperties;
    if (!properties) {
      return operations;
    }
    parentStyleName = paragraphNode.getAttributeNS(textns, "style-name");
    if (parentStyleName) {
      properties = {"style:text-properties":properties["style:text-properties"]};
      properties = odtDocument.getFormatting().createDerivedStyleObject(parentStyleName, "paragraph", properties);
    }
    styleName = objectNameGenerator.generateStyleName();
    op = new ops.OpAddStyle;
    op.init({memberid:inputMemberId, styleName:styleName, styleFamily:"paragraph", isAutomaticStyle:true, setProperties:properties});
    operations.push(op);
    op = new ops.OpSetParagraphStyle;
    op.init({memberid:inputMemberId, styleName:styleName, position:position});
    operations.push(op);
    return operations;
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorAdded, onCursorEvent);
    odtDocument.unsubscribe(ops.Document.signalCursorRemoved, onCursorEvent);
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorEvent);
    odtDocument.unsubscribe(ops.OdtDocument.signalParagraphStyleModified, onParagraphStyleModified);
    odtDocument.unsubscribe(ops.OdtDocument.signalParagraphChanged, onParagraphChanged);
    odtDocument.unsubscribe(ops.OdtDocument.signalOperationEnd, clearCursorStyle);
    odtDocument.unsubscribe(ops.OdtDocument.signalProcessingBatchEnd, emitSelectionChanges);
    sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, forceSelectionInfoRefresh);
    callback();
  };
  function emptyFunction() {
  }
  function emptyBoolFunction() {
    return false;
  }
  function emptyFalseReturningFunction() {
    return false;
  }
  function getCachedSelectionInfo() {
    return selectionInfoCache.value();
  }
  function init() {
    odtDocument.subscribe(ops.Document.signalCursorAdded, onCursorEvent);
    odtDocument.subscribe(ops.Document.signalCursorRemoved, onCursorEvent);
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorEvent);
    odtDocument.subscribe(ops.OdtDocument.signalParagraphStyleModified, onParagraphStyleModified);
    odtDocument.subscribe(ops.OdtDocument.signalParagraphChanged, onParagraphChanged);
    odtDocument.subscribe(ops.OdtDocument.signalOperationEnd, clearCursorStyle);
    odtDocument.subscribe(ops.OdtDocument.signalProcessingBatchEnd, emitSelectionChanges);
    sessionConstraints.subscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, forceSelectionInfoRefresh);
    selectionInfoCache = new core.LazyProperty(getSelectionInfo);
    lastSignalledSelectionInfo = getCachedSelectionInfo();
    if (!directTextStylingEnabled) {
      self.formatTextSelection = emptyFunction;
      self.setBold = emptyFunction;
      self.setItalic = emptyFunction;
      self.setHasUnderline = emptyFunction;
      self.setHasStrikethrough = emptyFunction;
      self.setFontSize = emptyFunction;
      self.setFontName = emptyFunction;
      self.toggleBold = emptyFalseReturningFunction;
      self.toggleItalic = emptyFalseReturningFunction;
      self.toggleUnderline = emptyFalseReturningFunction;
      self.toggleStrikethrough = emptyFalseReturningFunction;
    }
    if (!directParagraphStylingEnabled) {
      self.alignParagraphCenter = emptyBoolFunction;
      self.alignParagraphJustified = emptyBoolFunction;
      self.alignParagraphLeft = emptyBoolFunction;
      self.alignParagraphRight = emptyBoolFunction;
      self.createParagraphStyleOps = function() {
        return [];
      };
      self.indent = emptyBoolFunction;
      self.outdent = emptyBoolFunction;
    }
  }
  init();
};
gui.DirectFormattingController.enabledChanged = "enabled/changed";
gui.DirectFormattingController.textStylingChanged = "textStyling/changed";
gui.DirectFormattingController.paragraphStylingChanged = "paragraphStyling/changed";
gui.DirectFormattingController.SelectionInfo = function() {
  this.enabledFeatures;
  this.appliedStyles;
  this.styleSummary;
};
gui.KeyboardHandler = function KeyboardHandler() {
  var modifier = gui.KeyboardHandler.Modifier, defaultBinding = null, bindings = {};
  function getModifiers(e) {
    var modifiers = modifier.None;
    if (e.metaKey) {
      modifiers |= modifier.Meta;
    }
    if (e.ctrlKey) {
      modifiers |= modifier.Ctrl;
    }
    if (e.altKey) {
      modifiers |= modifier.Alt;
    }
    if (e.shiftKey) {
      modifiers |= modifier.Shift;
    }
    return modifiers;
  }
  function getKeyCombo(keyCode, modifiers) {
    if (!modifiers) {
      modifiers = modifier.None;
    }
    switch(keyCode) {
      case gui.KeyboardHandler.KeyCode.LeftMeta:
      ;
      case gui.KeyboardHandler.KeyCode.RightMeta:
      ;
      case gui.KeyboardHandler.KeyCode.MetaInMozilla:
        modifiers |= modifier.Meta;
        break;
      case gui.KeyboardHandler.KeyCode.Ctrl:
        modifiers |= modifier.Ctrl;
        break;
      case gui.KeyboardHandler.KeyCode.Alt:
        modifiers |= modifier.Alt;
        break;
      case gui.KeyboardHandler.KeyCode.Shift:
        modifiers |= modifier.Shift;
        break;
    }
    return keyCode + ":" + modifiers;
  }
  this.setDefault = function(callback) {
    defaultBinding = callback;
  };
  this.bind = function(keyCode, modifiers, callback, overwrite) {
    var keyCombo = getKeyCombo(keyCode, modifiers);
    runtime.assert(overwrite || bindings.hasOwnProperty(keyCombo) === false, "tried to overwrite the callback handler of key combo: " + keyCombo);
    bindings[keyCombo] = callback;
  };
  this.unbind = function(keyCode, modifiers) {
    var keyCombo = getKeyCombo(keyCode, modifiers);
    delete bindings[keyCombo];
  };
  this.reset = function() {
    defaultBinding = null;
    bindings = {};
  };
  this.handleEvent = function(e) {
    var keyCombo = getKeyCombo(e.keyCode, getModifiers(e)), callback = bindings[keyCombo], handled = false;
    if (callback) {
      handled = callback();
    } else {
      if (defaultBinding !== null) {
        handled = defaultBinding(e);
      }
    }
    if (handled) {
      if (e.preventDefault) {
        e.preventDefault();
      } else {
        e.returnValue = false;
      }
    }
  };
};
gui.KeyboardHandler.Modifier = {None:0, Meta:1, Ctrl:2, Alt:4, CtrlAlt:6, Shift:8, MetaShift:9, CtrlShift:10, AltShift:12};
gui.KeyboardHandler.KeyCode = {Backspace:8, Tab:9, Clear:12, Enter:13, Shift:16, Ctrl:17, Alt:18, End:35, Home:36, Left:37, Up:38, Right:39, Down:40, Delete:46, A:65, B:66, C:67, D:68, E:69, F:70, G:71, H:72, I:73, J:74, K:75, L:76, M:77, N:78, O:79, P:80, Q:81, R:82, S:83, T:84, U:85, V:86, W:87, X:88, Y:89, Z:90, LeftMeta:91, RightMeta:93, MetaInMozilla:224};
gui.HyperlinkClickHandler = function HyperlinkClickHandler(getContainer, keyDownHandler, keyUpHandler) {
  var inactiveLinksCssClass = "webodf-inactiveLinks", modifier = gui.KeyboardHandler.Modifier, keyCode = gui.KeyboardHandler.KeyCode, xpath = xmldom.XPath, odfUtils = odf.OdfUtils, window = runtime.getWindow(), activeModifier = modifier.None, activeKeyBindings = [];
  runtime.assert(window !== null, "Expected to be run in an environment which has a global window, like a browser.");
  function getHyperlinkElement(node) {
    while (node !== null) {
      if (odfUtils.isHyperlink(node)) {
        return node;
      }
      if (odfUtils.isParagraph(node)) {
        break;
      }
      node = node.parentNode;
    }
    return null;
  }
  this.handleClick = function(e) {
    var target = e.target || e.srcElement, pressedModifier, linkElement, url, rootNode, bookmarks;
    if (e.ctrlKey) {
      pressedModifier = modifier.Ctrl;
    } else {
      if (e.metaKey) {
        pressedModifier = modifier.Meta;
      }
    }
    if (activeModifier !== modifier.None && activeModifier !== pressedModifier) {
      return;
    }
    linkElement = getHyperlinkElement(target);
    if (!linkElement) {
      return;
    }
    url = odfUtils.getHyperlinkTarget(linkElement);
    if (url === "") {
      return;
    }
    if (url[0] === "#") {
      url = url.substring(1);
      rootNode = getContainer();
      bookmarks = xpath.getODFElementsWithXPath(rootNode, "//text:bookmark-start[@text:name='" + url + "']", odf.Namespaces.lookupNamespaceURI);
      if (bookmarks.length === 0) {
        bookmarks = xpath.getODFElementsWithXPath(rootNode, "//text:bookmark[@text:name='" + url + "']", odf.Namespaces.lookupNamespaceURI);
      }
      if (bookmarks.length > 0) {
        bookmarks[0].scrollIntoView(true);
      }
    } else {
      if (/^\s*(javascript|data):/.test(url)) {
        runtime.log("WARN:", "potentially malicious URL ignored");
      } else {
        window.open(url);
      }
    }
    if (e.preventDefault) {
      e.preventDefault();
    } else {
      e.returnValue = false;
    }
  };
  function showPointerCursor() {
    var container = getContainer();
    runtime.assert(Boolean(container.classList), "Document container has no classList element");
    container.classList.remove(inactiveLinksCssClass);
  }
  function showTextCursor() {
    var container = getContainer();
    runtime.assert(Boolean(container.classList), "Document container has no classList element");
    container.classList.add(inactiveLinksCssClass);
  }
  function cleanupEventBindings() {
    window.removeEventListener("focus", showTextCursor, false);
    activeKeyBindings.forEach(function(boundShortcut) {
      keyDownHandler.unbind(boundShortcut.keyCode, boundShortcut.modifier);
      keyUpHandler.unbind(boundShortcut.keyCode, boundShortcut.modifier);
    });
    activeKeyBindings.length = 0;
  }
  function bindEvents(modifierKey) {
    cleanupEventBindings();
    if (modifierKey !== modifier.None) {
      window.addEventListener("focus", showTextCursor, false);
      switch(modifierKey) {
        case modifier.Ctrl:
          activeKeyBindings.push({keyCode:keyCode.Ctrl, modifier:modifier.None});
          break;
        case modifier.Meta:
          activeKeyBindings.push({keyCode:keyCode.LeftMeta, modifier:modifier.None});
          activeKeyBindings.push({keyCode:keyCode.RightMeta, modifier:modifier.None});
          activeKeyBindings.push({keyCode:keyCode.MetaInMozilla, modifier:modifier.None});
          break;
      }
      activeKeyBindings.forEach(function(boundShortcut) {
        keyDownHandler.bind(boundShortcut.keyCode, boundShortcut.modifier, showPointerCursor);
        keyUpHandler.bind(boundShortcut.keyCode, boundShortcut.modifier, showTextCursor);
      });
    }
  }
  this.setModifier = function(value) {
    if (activeModifier === value) {
      return;
    }
    runtime.assert(value === modifier.None || value === modifier.Ctrl || value === modifier.Meta, "Unsupported KeyboardHandler.Modifier value: " + value);
    activeModifier = value;
    if (activeModifier !== modifier.None) {
      showTextCursor();
    } else {
      showPointerCursor();
    }
    bindEvents(activeModifier);
  };
  this.getModifier = function() {
    return activeModifier;
  };
  this.destroy = function(callback) {
    showTextCursor();
    cleanupEventBindings();
    callback();
  };
};
gui.EventManager = function EventManager(odtDocument) {
  var window = runtime.getWindow(), bindToDirectHandler = {"beforecut":true, "beforepaste":true, "longpress":true, "drag":true, "dragstop":true}, bindToWindow = {"mousedown":true, "mouseup":true, "focus":true}, compoundEvents = {}, eventDelegates = {}, eventTrap, canvasElement = odtDocument.getCanvas().getElement(), eventManager = this, longPressTimers = {}, LONGPRESS_DURATION = 400;
  function EventDelegate(eventName) {
    var self = this, recentEvents = [], subscribers = new core.EventNotifier([eventName]);
    function listenEvent(eventTarget, eventType, eventHandler) {
      var onVariant, bound = false;
      onVariant = "on" + eventType;
      if (eventTarget.attachEvent) {
        eventTarget.attachEvent(onVariant, eventHandler);
        bound = true;
      }
      if (!bound && eventTarget.addEventListener) {
        eventTarget.addEventListener(eventType, eventHandler, false);
        bound = true;
      }
      if ((!bound || bindToDirectHandler[eventType]) && eventTarget.hasOwnProperty(onVariant)) {
        eventTarget[onVariant] = eventHandler;
      }
    }
    function removeEvent(eventTarget, eventType, eventHandler) {
      var onVariant = "on" + eventType;
      if (eventTarget.detachEvent) {
        eventTarget.detachEvent(onVariant, eventHandler);
      }
      if (eventTarget.removeEventListener) {
        eventTarget.removeEventListener(eventType, eventHandler, false);
      }
      if (eventTarget[onVariant] === eventHandler) {
        eventTarget[onVariant] = null;
      }
    }
    function handleEvent(e) {
      if (recentEvents.indexOf(e) === -1) {
        recentEvents.push(e);
        if (self.filters.every(function(filter) {
          return filter(e);
        })) {
          try {
            subscribers.emit(eventName, e);
          } catch (err) {
            runtime.log("Error occurred while processing " + eventName + ":\n" + err.message + "\n" + err.stack);
          }
        }
        runtime.setTimeout(function() {
          recentEvents.splice(recentEvents.indexOf(e), 1);
        }, 0);
      }
    }
    this.filters = [];
    this.subscribe = function(cb) {
      subscribers.subscribe(eventName, cb);
    };
    this.unsubscribe = function(cb) {
      subscribers.unsubscribe(eventName, cb);
    };
    this.destroy = function() {
      removeEvent(window, eventName, handleEvent);
      removeEvent(eventTrap, eventName, handleEvent);
      removeEvent(canvasElement, eventName, handleEvent);
    };
    function init() {
      if (bindToWindow[eventName]) {
        listenEvent(window, eventName, handleEvent);
      }
      listenEvent(eventTrap, eventName, handleEvent);
      listenEvent(canvasElement, eventName, handleEvent);
    }
    init();
  }
  function CompoundEvent(eventName, dependencies, eventProxy) {
    var cachedState = {}, subscribers = new core.EventNotifier([eventName]);
    function subscribedProxy(event) {
      eventProxy(event, cachedState, function(compoundEventInstance) {
        compoundEventInstance.type = eventName;
        subscribers.emit(eventName, compoundEventInstance);
      });
    }
    this.subscribe = function(cb) {
      subscribers.subscribe(eventName, cb);
    };
    this.unsubscribe = function(cb) {
      subscribers.unsubscribe(eventName, cb);
    };
    this.destroy = function() {
      dependencies.forEach(function(eventName) {
        eventManager.unsubscribe(eventName, subscribedProxy);
      });
    };
    function init() {
      dependencies.forEach(function(eventName) {
        eventManager.subscribe(eventName, subscribedProxy);
      });
    }
    init();
  }
  function clearTimeout(timer) {
    runtime.clearTimeout(timer);
    delete longPressTimers[timer];
  }
  function setTimeout(fn, duration) {
    var timer = runtime.setTimeout(function() {
      fn();
      clearTimeout(timer);
    }, duration);
    longPressTimers[timer] = true;
    return timer;
  }
  function getTarget(e) {
    return e.target || e.srcElement || null;
  }
  function emitLongPressEvent(event, cachedState, callback) {
    var touchEvent = event, fingers = touchEvent.touches.length, touch = touchEvent.touches[0], timer = cachedState.timer;
    if (event.type === "touchmove" || event.type === "touchend") {
      if (timer) {
        clearTimeout(timer);
      }
    } else {
      if (event.type === "touchstart") {
        if (fingers !== 1) {
          runtime.clearTimeout(timer);
        } else {
          timer = setTimeout(function() {
            callback({clientX:touch.clientX, clientY:touch.clientY, pageX:touch.pageX, pageY:touch.pageY, target:getTarget(event), detail:1});
          }, LONGPRESS_DURATION);
        }
      }
    }
    cachedState.timer = timer;
  }
  function emitDragEvent(event, cachedState, callback) {
    var touchEvent = event, fingers = touchEvent.touches.length, touch = touchEvent.touches[0], target = getTarget(event), cachedTarget = cachedState.target;
    if (fingers !== 1 || event.type === "touchend") {
      cachedTarget = null;
    } else {
      if (event.type === "touchstart" && target.getAttribute("class") === "webodf-draggable") {
        cachedTarget = target;
      } else {
        if (event.type === "touchmove" && cachedTarget) {
          event.preventDefault();
          event.stopPropagation();
          callback({clientX:touch.clientX, clientY:touch.clientY, pageX:touch.pageX, pageY:touch.pageY, target:cachedTarget, detail:1});
        }
      }
    }
    cachedState.target = cachedTarget;
  }
  function emitDragStopEvent(event, cachedState, callback) {
    var touchEvent = event, target = getTarget(event), touch, dragging = cachedState.dragging;
    if (event.type === "drag") {
      dragging = true;
    } else {
      if (event.type === "touchend" && dragging) {
        dragging = false;
        touch = touchEvent.changedTouches[0];
        callback({clientX:touch.clientX, clientY:touch.clientY, pageX:touch.pageX, pageY:touch.pageY, target:target, detail:1});
      }
    }
    cachedState.dragging = dragging;
  }
  function declareTouchEnabled() {
    canvasElement.classList.add("webodf-touchEnabled");
    eventManager.unsubscribe("touchstart", declareTouchEnabled);
  }
  function WindowScrollState(window) {
    var x = window.scrollX, y = window.scrollY;
    this.restore = function() {
      if (window.scrollX !== x || window.scrollY !== y) {
        window.scrollTo(x, y);
      }
    };
  }
  function ElementScrollState(element) {
    var top = element.scrollTop, left = element.scrollLeft;
    this.restore = function() {
      if (element.scrollTop !== top || element.scrollLeft !== left) {
        element.scrollTop = top;
        element.scrollLeft = left;
      }
    };
  }
  function getDelegateForEvent(eventName, shouldCreate) {
    var delegate = eventDelegates[eventName] || compoundEvents[eventName] || null;
    if (!delegate && shouldCreate) {
      delegate = eventDelegates[eventName] = new EventDelegate(eventName);
    }
    return delegate;
  }
  this.addFilter = function(eventName, filter) {
    var delegate = getDelegateForEvent(eventName, true);
    delegate.filters.push(filter);
  };
  this.removeFilter = function(eventName, filter) {
    var delegate = getDelegateForEvent(eventName, true), index = delegate.filters.indexOf(filter);
    if (index !== -1) {
      delegate.filters.splice(index, 1);
    }
  };
  function subscribe(eventName, handler) {
    var delegate = getDelegateForEvent(eventName, true);
    delegate.subscribe(handler);
  }
  this.subscribe = subscribe;
  function unsubscribe(eventName, handler) {
    var delegate = getDelegateForEvent(eventName, false);
    if (delegate) {
      delegate.unsubscribe(handler);
    }
  }
  this.unsubscribe = unsubscribe;
  function hasFocus() {
    return odtDocument.getDOMDocument().activeElement === eventTrap;
  }
  this.hasFocus = hasFocus;
  function disableTrapSelection() {
    if (hasFocus()) {
      eventTrap.blur();
    }
    eventTrap.setAttribute("disabled", "true");
  }
  function enableTrapSelection() {
    eventTrap.removeAttribute("disabled");
  }
  function findScrollableParents(element) {
    var scrollParents = [];
    while (element) {
      if (element.scrollWidth > element.clientWidth || element.scrollHeight > element.clientHeight) {
        scrollParents.push(new ElementScrollState(element));
      }
      element = element.parentNode;
    }
    scrollParents.push(new WindowScrollState(window));
    return scrollParents;
  }
  function focus() {
    var scrollParents;
    if (!hasFocus()) {
      scrollParents = findScrollableParents(eventTrap);
      enableTrapSelection();
      eventTrap.focus();
      scrollParents.forEach(function(scrollParent) {
        scrollParent.restore();
      });
    }
  }
  this.focus = focus;
  this.getEventTrap = function() {
    return eventTrap;
  };
  this.setEditing = function(editable) {
    var hadFocus = hasFocus();
    if (hadFocus) {
      eventTrap.blur();
    }
    if (editable) {
      eventTrap.removeAttribute("readOnly");
    } else {
      eventTrap.setAttribute("readOnly", "true");
    }
    if (hadFocus) {
      focus();
    }
  };
  this.destroy = function(callback) {
    unsubscribe("touchstart", declareTouchEnabled);
    Object.keys(longPressTimers).forEach(function(timer) {
      clearTimeout(parseInt(timer, 10));
    });
    longPressTimers.length = 0;
    Object.keys(compoundEvents).forEach(function(compoundEventName) {
      compoundEvents[compoundEventName].destroy();
    });
    compoundEvents = {};
    unsubscribe("mousedown", disableTrapSelection);
    unsubscribe("mouseup", enableTrapSelection);
    unsubscribe("contextmenu", enableTrapSelection);
    Object.keys(eventDelegates).forEach(function(eventName) {
      eventDelegates[eventName].destroy();
    });
    eventDelegates = {};
    eventTrap.parentNode.removeChild(eventTrap);
    callback();
  };
  function init() {
    var sizerElement = odtDocument.getOdfCanvas().getSizer(), doc = sizerElement.ownerDocument;
    runtime.assert(Boolean(window), "EventManager requires a window object to operate correctly");
    eventTrap = doc.createElement("textarea");
    eventTrap.id = "eventTrap";
    eventTrap.setAttribute("tabindex", "-1");
    eventTrap.setAttribute("readOnly", "true");
    eventTrap.setAttribute("rows", "1");
    sizerElement.appendChild(eventTrap);
    subscribe("mousedown", disableTrapSelection);
    subscribe("mouseup", enableTrapSelection);
    subscribe("contextmenu", enableTrapSelection);
    compoundEvents.longpress = new CompoundEvent("longpress", ["touchstart", "touchmove", "touchend"], emitLongPressEvent);
    compoundEvents.drag = new CompoundEvent("drag", ["touchstart", "touchmove", "touchend"], emitDragEvent);
    compoundEvents.dragstop = new CompoundEvent("dragstop", ["drag", "touchend"], emitDragStopEvent);
    subscribe("touchstart", declareTouchEnabled);
  }
  init();
};
gui.IOSSafariSupport = function(eventManager) {
  var window = runtime.getWindow(), eventTrap = eventManager.getEventTrap();
  function suppressFocusScrollIfKeyboardOpen() {
    if (window.innerHeight !== window.outerHeight) {
      eventTrap.style.display = "none";
      runtime.requestAnimationFrame(function() {
        eventTrap.style.display = "block";
      });
    }
  }
  this.destroy = function(callback) {
    eventManager.unsubscribe("focus", suppressFocusScrollIfKeyboardOpen);
    eventTrap.removeAttribute("autocapitalize");
    eventTrap.style.WebkitTransform = "";
    callback();
  };
  function init() {
    eventManager.subscribe("focus", suppressFocusScrollIfKeyboardOpen);
    eventTrap.setAttribute("autocapitalize", "off");
    eventTrap.style.WebkitTransform = "translateX(-10000px)";
  }
  init();
};
gui.HyperlinkController = function HyperlinkController(session, sessionConstraints, sessionContext, inputMemberId) {
  var odfUtils = odf.OdfUtils, odtDocument = session.getOdtDocument(), eventNotifier = new core.EventNotifier([gui.HyperlinkController.enabledChanged]), isEnabled = false;
  function updateEnabledState() {
    var newIsEnabled = true;
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.REVIEW_MODE) === true) {
      newIsEnabled = sessionContext.isLocalCursorWithinOwnAnnotation();
    }
    if (newIsEnabled !== isEnabled) {
      isEnabled = newIsEnabled;
      eventNotifier.emit(gui.HyperlinkController.enabledChanged, isEnabled);
    }
  }
  function onCursorEvent(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updateEnabledState();
    }
  }
  this.isEnabled = function() {
    return isEnabled;
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  function addHyperlink(hyperlink, insertionText) {
    if (!isEnabled) {
      return;
    }
    var selection = odtDocument.getCursorSelection(inputMemberId), op = new ops.OpApplyHyperlink, operations = [];
    if (selection.length === 0 || insertionText) {
      insertionText = insertionText || hyperlink;
      op = new ops.OpInsertText;
      op.init({memberid:inputMemberId, position:selection.position, text:insertionText});
      selection.length = insertionText.length;
      operations.push(op);
    }
    op = new ops.OpApplyHyperlink;
    op.init({memberid:inputMemberId, position:selection.position, length:selection.length, hyperlink:hyperlink});
    operations.push(op);
    session.enqueue(operations);
  }
  this.addHyperlink = addHyperlink;
  function removeHyperlinks() {
    if (!isEnabled) {
      return;
    }
    var iterator = odtDocument.createPositionIterator(odtDocument.getRootNode()), selectedRange = odtDocument.getCursor(inputMemberId).getSelectedRange(), links = odfUtils.getHyperlinkElements(selectedRange), removeEntireLink = selectedRange.collapsed && links.length === 1, domRange = odtDocument.getDOMDocument().createRange(), operations = [], cursorRange, firstLink, lastLink, offset, op;
    if (links.length === 0) {
      return;
    }
    links.forEach(function(link) {
      domRange.selectNodeContents(link);
      cursorRange = odtDocument.convertDomToCursorRange({anchorNode:domRange.startContainer, anchorOffset:domRange.startOffset, focusNode:domRange.endContainer, focusOffset:domRange.endOffset});
      op = new ops.OpRemoveHyperlink;
      op.init({memberid:inputMemberId, position:cursorRange.position, length:cursorRange.length});
      operations.push(op);
    });
    if (!removeEntireLink) {
      firstLink = links[0];
      if (selectedRange.comparePoint(firstLink, 0) === -1) {
        domRange.setStart(firstLink, 0);
        domRange.setEnd(selectedRange.startContainer, selectedRange.startOffset);
        cursorRange = odtDocument.convertDomToCursorRange({anchorNode:domRange.startContainer, anchorOffset:domRange.startOffset, focusNode:domRange.endContainer, focusOffset:domRange.endOffset});
        if (cursorRange.length > 0) {
          op = new ops.OpApplyHyperlink;
          op.init({memberid:inputMemberId, position:cursorRange.position, length:cursorRange.length, hyperlink:odfUtils.getHyperlinkTarget(firstLink)});
          operations.push(op);
        }
      }
      lastLink = links[links.length - 1];
      iterator.moveToEndOfNode(lastLink);
      offset = iterator.unfilteredDomOffset();
      if (selectedRange.comparePoint(lastLink, offset) === 1) {
        domRange.setStart(selectedRange.endContainer, selectedRange.endOffset);
        domRange.setEnd(lastLink, offset);
        cursorRange = odtDocument.convertDomToCursorRange({anchorNode:domRange.startContainer, anchorOffset:domRange.startOffset, focusNode:domRange.endContainer, focusOffset:domRange.endOffset});
        if (cursorRange.length > 0) {
          op = new ops.OpApplyHyperlink;
          op.init({memberid:inputMemberId, position:cursorRange.position, length:cursorRange.length, hyperlink:odfUtils.getHyperlinkTarget(lastLink)});
          operations.push(op);
        }
      }
    }
    session.enqueue(operations);
    domRange.detach();
  }
  this.removeHyperlinks = removeHyperlinks;
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    callback();
  };
  function init() {
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.subscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    updateEnabledState();
  }
  init();
};
gui.HyperlinkController.enabledChanged = "enabled/changed";
gui.ImageController = function ImageController(session, sessionConstraints, sessionContext, inputMemberId, objectNameGenerator) {
  var fileExtensionByMimetype = {"image/gif":".gif", "image/jpeg":".jpg", "image/png":".png"}, textns = odf.Namespaces.textns, odtDocument = session.getOdtDocument(), odfUtils = odf.OdfUtils, formatting = odtDocument.getFormatting(), eventNotifier = new core.EventNotifier([gui.HyperlinkController.enabledChanged]), isEnabled = false;
  function updateEnabledState() {
    var newIsEnabled = true;
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.REVIEW_MODE) === true) {
      newIsEnabled = sessionContext.isLocalCursorWithinOwnAnnotation();
    }
    if (newIsEnabled !== isEnabled) {
      isEnabled = newIsEnabled;
      eventNotifier.emit(gui.ImageController.enabledChanged, isEnabled);
    }
  }
  function onCursorEvent(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updateEnabledState();
    }
  }
  this.isEnabled = function() {
    return isEnabled;
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  function createAddGraphicsStyleOp(name) {
    var op = new ops.OpAddStyle;
    op.init({memberid:inputMemberId, styleName:name, styleFamily:"graphic", isAutomaticStyle:false, setProperties:{"style:graphic-properties":{"text:anchor-type":"paragraph", "svg:x":"0cm", "svg:y":"0cm", "style:wrap":"dynamic", "style:number-wrapped-paragraphs":"no-limit", "style:wrap-contour":"false", "style:vertical-pos":"top", "style:vertical-rel":"paragraph", "style:horizontal-pos":"center", "style:horizontal-rel":"paragraph"}}});
    return op;
  }
  function createAddFrameStyleOp(styleName, parentStyleName) {
    var op = new ops.OpAddStyle;
    op.init({memberid:inputMemberId, styleName:styleName, styleFamily:"graphic", isAutomaticStyle:true, setProperties:{"style:parent-style-name":parentStyleName, "style:graphic-properties":{"style:vertical-pos":"top", "style:vertical-rel":"baseline", "style:horizontal-pos":"center", "style:horizontal-rel":"paragraph", "fo:background-color":"transparent", "style:background-transparency":"100%", "style:shadow":"none", "style:mirror":"none", "fo:clip":"rect(0cm, 0cm, 0cm, 0cm)", "draw:luminance":"0%", 
    "draw:contrast":"0%", "draw:red":"0%", "draw:green":"0%", "draw:blue":"0%", "draw:gamma":"100%", "draw:color-inversion":"false", "draw:image-opacity":"100%", "draw:color-mode":"standard"}}});
    return op;
  }
  function getFileExtension(mimetype) {
    mimetype = mimetype.toLowerCase();
    return fileExtensionByMimetype.hasOwnProperty(mimetype) ? fileExtensionByMimetype[mimetype] : null;
  }
  function insertImageInternal(mimetype, content, widthMeasure, heightMeasure) {
    var graphicsStyleName = "Graphics", stylesElement = odtDocument.getOdfCanvas().odfContainer().rootElement.styles, fileExtension = getFileExtension(mimetype), fileName, graphicsStyleElement, frameStyleName, op, operations = [];
    runtime.assert(fileExtension !== null, "Image type is not supported: " + mimetype);
    fileName = "Pictures/" + objectNameGenerator.generateImageName() + fileExtension;
    op = new ops.OpSetBlob;
    op.init({memberid:inputMemberId, filename:fileName, mimetype:mimetype, content:content});
    operations.push(op);
    graphicsStyleElement = formatting.getStyleElement(graphicsStyleName, "graphic", [stylesElement]);
    if (!graphicsStyleElement) {
      op = createAddGraphicsStyleOp(graphicsStyleName);
      operations.push(op);
    }
    frameStyleName = objectNameGenerator.generateStyleName();
    op = createAddFrameStyleOp(frameStyleName, graphicsStyleName);
    operations.push(op);
    op = new ops.OpInsertImage;
    op.init({memberid:inputMemberId, position:odtDocument.getCursorPosition(inputMemberId), filename:fileName, frameWidth:widthMeasure, frameHeight:heightMeasure, frameStyleName:frameStyleName, frameName:objectNameGenerator.generateFrameName()});
    operations.push(op);
    session.enqueue(operations);
  }
  function scaleToAvailableContentSize(originalSize, pageContentSize) {
    var widthRatio = 1, heightRatio = 1, ratio;
    if (originalSize.width > pageContentSize.width) {
      widthRatio = pageContentSize.width / originalSize.width;
    }
    if (originalSize.height > pageContentSize.height) {
      heightRatio = pageContentSize.height / originalSize.height;
    }
    ratio = Math.min(widthRatio, heightRatio);
    return {width:originalSize.width * ratio, height:originalSize.height * ratio};
  }
  this.insertImage = function(mimetype, content, widthInPx, heightInPx) {
    if (!isEnabled) {
      return;
    }
    var paragraphElement, styleName, pageContentSize, imageSize, cssUnits = new core.CSSUnits;
    runtime.assert(widthInPx > 0 && heightInPx > 0, "Both width and height of the image should be greater than 0px.");
    imageSize = {width:widthInPx, height:heightInPx};
    paragraphElement = odfUtils.getParagraphElement(odtDocument.getCursor(inputMemberId).getNode());
    styleName = paragraphElement.getAttributeNS(textns, "style-name");
    if (styleName) {
      pageContentSize = formatting.getContentSize(styleName, "paragraph");
      imageSize = scaleToAvailableContentSize(imageSize, pageContentSize);
    }
    insertImageInternal(mimetype, content, cssUnits.convert(imageSize.width, "px", "cm") + "cm", cssUnits.convert(imageSize.height, "px", "cm") + "cm");
  };
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    callback();
  };
  function init() {
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.subscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    updateEnabledState();
  }
  init();
};
gui.ImageController.enabledChanged = "enabled/changed";
gui.ImageSelector = function ImageSelector(odfCanvas) {
  var svgns = odf.Namespaces.svgns, imageSelectorId = "imageSelector", selectorBorderWidth = 1, squareClassNames = ["topLeft", "topRight", "bottomRight", "bottomLeft", "topMiddle", "rightMiddle", "bottomMiddle", "leftMiddle"], document = odfCanvas.getElement().ownerDocument, hasSelection = false;
  function createSelectorElement() {
    var sizerElement = odfCanvas.getSizer(), selectorElement = document.createElement("div");
    selectorElement.id = "imageSelector";
    selectorElement.style.borderWidth = selectorBorderWidth + "px";
    sizerElement.appendChild(selectorElement);
    function createDiv(className) {
      var squareElement = document.createElement("div");
      squareElement.className = className;
      selectorElement.appendChild(squareElement);
    }
    squareClassNames.forEach(createDiv);
    return selectorElement;
  }
  function getPosition(element, referenceElement) {
    var rect = element.getBoundingClientRect(), refRect = referenceElement.getBoundingClientRect(), zoomLevel = odfCanvas.getZoomLevel();
    return {left:(rect.left - refRect.left) / zoomLevel - selectorBorderWidth, top:(rect.top - refRect.top) / zoomLevel - selectorBorderWidth};
  }
  this.select = function(frameElement) {
    var selectorElement = document.getElementById(imageSelectorId), position;
    if (!selectorElement) {
      selectorElement = createSelectorElement();
    }
    hasSelection = true;
    position = getPosition(frameElement, selectorElement.parentNode);
    selectorElement.style.display = "block";
    selectorElement.style.left = position.left + "px";
    selectorElement.style.top = position.top + "px";
    selectorElement.style.width = frameElement.getAttributeNS(svgns, "width");
    selectorElement.style.height = frameElement.getAttributeNS(svgns, "height");
  };
  this.clearSelection = function() {
    var selectorElement;
    if (hasSelection) {
      selectorElement = document.getElementById(imageSelectorId);
      if (selectorElement) {
        selectorElement.style.display = "none";
      }
    }
    hasSelection = false;
  };
  this.isSelectorElement = function(node) {
    var selectorElement = document.getElementById(imageSelectorId);
    if (!selectorElement) {
      return false;
    }
    return node === selectorElement || node.parentNode === selectorElement;
  };
};
(function() {
  function DetectSafariCompositionError(eventManager) {
    var lastCompositionValue, suppressedKeyPress = false;
    function suppressIncorrectKeyPress(e) {
      suppressedKeyPress = e.which && String.fromCharCode(e.which) === lastCompositionValue;
      lastCompositionValue = undefined;
      return suppressedKeyPress === false;
    }
    function clearSuppression() {
      suppressedKeyPress = false;
    }
    function trapComposedValue(e) {
      lastCompositionValue = e.data;
      suppressedKeyPress = false;
    }
    function init() {
      eventManager.subscribe("textInput", clearSuppression);
      eventManager.subscribe("compositionend", trapComposedValue);
      eventManager.addFilter("keypress", suppressIncorrectKeyPress);
    }
    this.destroy = function(callback) {
      eventManager.unsubscribe("textInput", clearSuppression);
      eventManager.unsubscribe("compositionend", trapComposedValue);
      eventManager.removeFilter("keypress", suppressIncorrectKeyPress);
      callback();
    };
    init();
  }
  gui.InputMethodEditor = function InputMethodEditor(inputMemberId, eventManager) {
    var cursorns = "urn:webodf:names:cursor", localCursor = null, eventTrap = eventManager.getEventTrap(), doc = eventTrap.ownerDocument, compositionElement, processUpdates, pendingEvent = false, pendingData = "", events = new core.EventNotifier([gui.InputMethodEditor.signalCompositionStart, gui.InputMethodEditor.signalCompositionEnd]), lastCompositionData, textSerializer, filters = [], cleanup, processingFocusEvent = false;
    this.subscribe = events.subscribe;
    this.unsubscribe = events.unsubscribe;
    function setCursorComposing(state) {
      if (localCursor) {
        if (state) {
          localCursor.getNode().setAttributeNS(cursorns, "composing", "true");
        } else {
          localCursor.getNode().removeAttributeNS(cursorns, "composing");
          compositionElement.textContent = "";
        }
      }
    }
    function flushEvent() {
      if (pendingEvent) {
        pendingEvent = false;
        setCursorComposing(false);
        events.emit(gui.InputMethodEditor.signalCompositionEnd, {data:pendingData});
        pendingData = "";
      }
    }
    function addCompositionData(data) {
      pendingEvent = true;
      pendingData += data;
      processUpdates.trigger();
    }
    function synchronizeWindowSelection() {
      if (processingFocusEvent) {
        return;
      }
      processingFocusEvent = true;
      flushEvent();
      if (localCursor && localCursor.getSelectedRange().collapsed) {
        eventTrap.value = "";
      } else {
        eventTrap.value = textSerializer.writeToString(localCursor.getSelectedRange().cloneContents());
      }
      eventTrap.setSelectionRange(0, eventTrap.value.length);
      processingFocusEvent = false;
    }
    function handleCursorUpdated() {
      if (eventManager.hasFocus()) {
        processUpdates.trigger();
      }
    }
    function compositionStart() {
      lastCompositionData = undefined;
      processUpdates.cancel();
      setCursorComposing(true);
      if (!pendingEvent) {
        events.emit(gui.InputMethodEditor.signalCompositionStart, {data:""});
      }
    }
    function compositionEnd(e) {
      lastCompositionData = e.data;
      addCompositionData(e.data);
    }
    function textInput(e) {
      if (e.data !== lastCompositionData) {
        addCompositionData(e.data);
      }
      lastCompositionData = undefined;
    }
    function synchronizeCompositionText() {
      compositionElement.textContent = eventTrap.value;
    }
    this.registerCursor = function(cursor) {
      if (cursor.getMemberId() === inputMemberId) {
        localCursor = cursor;
        localCursor.getNode().appendChild(compositionElement);
        cursor.subscribe(ops.OdtCursor.signalCursorUpdated, handleCursorUpdated);
        eventManager.subscribe("input", synchronizeCompositionText);
        eventManager.subscribe("compositionupdate", synchronizeCompositionText);
      }
    };
    this.removeCursor = function(memberid) {
      if (localCursor && memberid === inputMemberId) {
        localCursor.getNode().removeChild(compositionElement);
        localCursor.unsubscribe(ops.OdtCursor.signalCursorUpdated, handleCursorUpdated);
        eventManager.unsubscribe("input", synchronizeCompositionText);
        eventManager.unsubscribe("compositionupdate", synchronizeCompositionText);
        localCursor = null;
      }
    };
    this.destroy = function(callback) {
      eventManager.unsubscribe("compositionstart", compositionStart);
      eventManager.unsubscribe("compositionend", compositionEnd);
      eventManager.unsubscribe("textInput", textInput);
      eventManager.unsubscribe("keypress", flushEvent);
      eventManager.unsubscribe("focus", synchronizeWindowSelection);
      core.Async.destroyAll(cleanup, callback);
    };
    function init() {
      textSerializer = new odf.TextSerializer;
      textSerializer.filter = new odf.OdfNodeFilter;
      eventManager.subscribe("compositionstart", compositionStart);
      eventManager.subscribe("compositionend", compositionEnd);
      eventManager.subscribe("textInput", textInput);
      eventManager.subscribe("keypress", flushEvent);
      eventManager.subscribe("focus", synchronizeWindowSelection);
      filters.push(new DetectSafariCompositionError(eventManager));
      function getDestroy(filter) {
        return filter.destroy;
      }
      cleanup = filters.map(getDestroy);
      compositionElement = doc.createElement("span");
      compositionElement.setAttribute("id", "composer");
      processUpdates = core.Task.createTimeoutTask(synchronizeWindowSelection, 1);
      cleanup.push(processUpdates.destroy);
    }
    init();
  };
  gui.InputMethodEditor.signalCompositionStart = "input/compositionstart";
  gui.InputMethodEditor.signalCompositionEnd = "input/compositionend";
})();
gui.MetadataController = function MetadataController(session, inputMemberId) {
  var odtDocument = session.getOdtDocument(), eventNotifier = new core.EventNotifier([gui.MetadataController.signalMetadataChanged]), readonlyProperties = ["dc:creator", "dc:date", "meta:editing-cycles", "meta:editing-duration", "meta:document-statistic"];
  function onMetadataUpdated(changes) {
    eventNotifier.emit(gui.MetadataController.signalMetadataChanged, changes);
  }
  function isWriteableMetadata(property) {
    var isWriteable = readonlyProperties.indexOf(property) === -1;
    if (!isWriteable) {
      runtime.log("Setting " + property + " is restricted.");
    }
    return isWriteable;
  }
  this.setMetadata = function(setProperties, removedProperties) {
    var filteredSetProperties = {}, filteredRemovedProperties = "", op;
    if (setProperties) {
      Object.keys(setProperties).filter(isWriteableMetadata).forEach(function(property) {
        filteredSetProperties[property] = setProperties[property];
      });
    }
    if (removedProperties) {
      filteredRemovedProperties = removedProperties.filter(isWriteableMetadata).join(",");
    }
    if (filteredRemovedProperties.length > 0 || Object.keys(filteredSetProperties).length > 0) {
      op = new ops.OpUpdateMetadata;
      op.init({memberid:inputMemberId, setProperties:filteredSetProperties, removedProperties:filteredRemovedProperties.length > 0 ? {attributes:filteredRemovedProperties} : null});
      session.enqueue([op]);
    }
  };
  this.getMetadata = function(property) {
    var namespaceUri, parts;
    runtime.assert(typeof property === "string", "Property must be a string");
    parts = property.split(":");
    runtime.assert(parts.length === 2, "Property must be a namespace-prefixed string");
    namespaceUri = odf.Namespaces.lookupNamespaceURI(parts[0]);
    runtime.assert(Boolean(namespaceUri), "Prefix must be for an ODF namespace.");
    return odtDocument.getOdfCanvas().odfContainer().getMetadata(namespaceUri, parts[1]);
  };
  this.subscribe = function(eventid, cb) {
    eventNotifier.subscribe(eventid, cb);
  };
  this.unsubscribe = function(eventid, cb) {
    eventNotifier.unsubscribe(eventid, cb);
  };
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.OdtDocument.signalMetadataUpdated, onMetadataUpdated);
    callback();
  };
  function init() {
    odtDocument.subscribe(ops.OdtDocument.signalMetadataUpdated, onMetadataUpdated);
  }
  init();
};
gui.MetadataController.signalMetadataChanged = "metadata/changed";
gui.PasteController = function PasteController(session, sessionConstraints, sessionContext, inputMemberId) {
  var odtDocument = session.getOdtDocument(), isEnabled = false, textns = odf.Namespaces.textns, NEXT = core.StepDirection.NEXT, odfUtils = odf.OdfUtils;
  function updateEnabledState() {
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.REVIEW_MODE) === true) {
      isEnabled = sessionContext.isLocalCursorWithinOwnAnnotation();
    } else {
      isEnabled = true;
    }
  }
  function onCursorEvent(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updateEnabledState();
    }
  }
  this.isEnabled = function() {
    return isEnabled;
  };
  this.paste = function(data) {
    if (!isEnabled) {
      return;
    }
    var originalCursorPosition = odtDocument.getCursorPosition(inputMemberId), cursorNode = odtDocument.getCursor(inputMemberId).getNode(), originalParagraph = odfUtils.getParagraphElement(cursorNode), paragraphStyle = originalParagraph.getAttributeNS(textns, "style-name") || "", cursorPosition = originalCursorPosition, operations = [], currentParagraphStartPosition = odtDocument.convertDomPointToCursorStep(originalParagraph, 0, NEXT), paragraphs;
    paragraphs = data.replace(/\r/g, "").split("\n");
    paragraphs.forEach(function(text) {
      var insertTextOp = new ops.OpInsertText, splitParagraphOp = new ops.OpSplitParagraph;
      insertTextOp.init({memberid:inputMemberId, position:cursorPosition, text:text, moveCursor:true});
      operations.push(insertTextOp);
      cursorPosition += text.length;
      splitParagraphOp.init({memberid:inputMemberId, position:cursorPosition, paragraphStyleName:paragraphStyle, sourceParagraphPosition:currentParagraphStartPosition, moveCursor:true});
      operations.push(splitParagraphOp);
      cursorPosition += 1;
      currentParagraphStartPosition = cursorPosition;
    });
    operations.pop();
    session.enqueue(operations);
  };
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    callback();
  };
  function init() {
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.subscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    updateEnabledState();
  }
  init();
};
gui.ClosestXOffsetScanner = function(offset) {
  var self = this, closestDiff, LEFT_TO_RIGHT = gui.StepInfo.VisualDirection.LEFT_TO_RIGHT;
  this.token = undefined;
  function isFurtherFromOffset(edgeOffset) {
    if (edgeOffset !== null && closestDiff !== undefined) {
      return Math.abs(edgeOffset - offset) > closestDiff;
    }
    return false;
  }
  function updateDiffIfSmaller(edge) {
    if (edge !== null && isFurtherFromOffset(edge) === false) {
      closestDiff = Math.abs(edge - offset);
    }
  }
  this.process = function(stepInfo, previousRect, nextRect) {
    var edge1, edge2;
    if (stepInfo.visualDirection === LEFT_TO_RIGHT) {
      edge1 = previousRect && previousRect.right;
      edge2 = nextRect && nextRect.left;
    } else {
      edge1 = previousRect && previousRect.left;
      edge2 = nextRect && nextRect.right;
    }
    if (isFurtherFromOffset(edge1) || isFurtherFromOffset(edge2)) {
      return true;
    }
    if (previousRect || nextRect) {
      updateDiffIfSmaller(edge1);
      updateDiffIfSmaller(edge2);
      self.token = stepInfo.token;
    }
    return false;
  };
};
gui.LineBoundaryScanner = function() {
  var self = this, lineRect = null, MIN_OVERLAP_THRESHOLD = .4;
  function verticalOverlapPercent(rect1, rect2) {
    var rect1Height = rect1.bottom - rect1.top, rect2Height = rect2.bottom - rect2.top, minRectHeight = Math.min(rect1Height, rect2Height), intersectTop = Math.max(rect1.top, rect2.top), intersectBottom = Math.min(rect1.bottom, rect2.bottom), overlapHeight = intersectBottom - intersectTop;
    return minRectHeight > 0 ? overlapHeight / minRectHeight : 0;
  }
  function isLineBoundary(nextRect) {
    if (lineRect) {
      return verticalOverlapPercent(lineRect, nextRect) <= MIN_OVERLAP_THRESHOLD;
    }
    return false;
  }
  function combineRects(rect1, rect2) {
    return {left:Math.min(rect1.left, rect2.left), right:Math.max(rect1.right, rect2.right), top:Math.min(rect1.top, rect2.top), bottom:Math.min(rect1.bottom, rect2.bottom)};
  }
  function growRect(originalRect, newRect) {
    if (originalRect && newRect) {
      return combineRects(originalRect, newRect);
    }
    return originalRect || newRect;
  }
  this.token = undefined;
  this.process = function(stepInfo, previousRect, nextRect) {
    var isOverLineBoundary = nextRect && isLineBoundary(nextRect);
    if (previousRect && (!nextRect || isOverLineBoundary)) {
      self.token = stepInfo.token;
    }
    if (isOverLineBoundary) {
      return true;
    }
    lineRect = growRect(lineRect, previousRect);
    return false;
  };
};
gui.ParagraphBoundaryScanner = function() {
  var self = this, isInitialised = false, lastParagraph, odfUtils = odf.OdfUtils;
  this.token = undefined;
  this.process = function(stepInfo) {
    var currentParagraph = odfUtils.getParagraphElement(stepInfo.container());
    if (!isInitialised) {
      lastParagraph = currentParagraph;
      isInitialised = true;
    }
    if (lastParagraph !== currentParagraph) {
      return true;
    }
    self.token = stepInfo.token;
    return false;
  };
};
odf.WordBoundaryFilter = function WordBoundaryFilter(odtDocument, includeWhitespace) {
  var TEXT_NODE = Node.TEXT_NODE, ELEMENT_NODE = Node.ELEMENT_NODE, odfUtils = odf.OdfUtils, punctuation = /[!-#%-*,-\/:-;?-@\[-\]_{}\u00a1\u00ab\u00b7\u00bb\u00bf;\u00b7\u055a-\u055f\u0589-\u058a\u05be\u05c0\u05c3\u05c6\u05f3-\u05f4\u0609-\u060a\u060c-\u060d\u061b\u061e-\u061f\u066a-\u066d\u06d4\u0700-\u070d\u07f7-\u07f9\u0964-\u0965\u0970\u0df4\u0e4f\u0e5a-\u0e5b\u0f04-\u0f12\u0f3a-\u0f3d\u0f85\u0fd0-\u0fd4\u104a-\u104f\u10fb\u1361-\u1368\u166d-\u166e\u169b-\u169c\u16eb-\u16ed\u1735-\u1736\u17d4-\u17d6\u17d8-\u17da\u1800-\u180a\u1944-\u1945\u19de-\u19df\u1a1e-\u1a1f\u1b5a-\u1b60\u1c3b-\u1c3f\u1c7e-\u1c7f\u2000-\u206e\u207d-\u207e\u208d-\u208e\u3008-\u3009\u2768-\u2775\u27c5-\u27c6\u27e6-\u27ef\u2983-\u2998\u29d8-\u29db\u29fc-\u29fd\u2cf9-\u2cfc\u2cfe-\u2cff\u2e00-\u2e7e\u3000-\u303f\u30a0\u30fb\ua60d-\ua60f\ua673\ua67e\ua874-\ua877\ua8ce-\ua8cf\ua92e-\ua92f\ua95f\uaa5c-\uaa5f\ufd3e-\ufd3f\ufe10-\ufe19\ufe30-\ufe52\ufe54-\ufe61\ufe63\ufe68\ufe6a-\ufe6b\uff01-\uff03\uff05-\uff0a\uff0c-\uff0f\uff1a-\uff1b\uff1f-\uff20\uff3b-\uff3d\uff3f\uff5b\uff5d\uff5f-\uff65]|\ud800[\udd00-\udd01\udf9f\udfd0]|\ud802[\udd1f\udd3f\ude50-\ude58]|\ud809[\udc00-\udc7e]/, 
  spacing = /\s/, FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT, FILTER_REJECT = core.PositionFilter.FilterResult.FILTER_REJECT, TRAILING = odf.WordBoundaryFilter.IncludeWhitespace.TRAILING, LEADING = odf.WordBoundaryFilter.IncludeWhitespace.LEADING, NeighborType = {NO_NEIGHBOUR:0, SPACE_CHAR:1, PUNCTUATION_CHAR:2, WORD_CHAR:3, OTHER:4};
  function findHigherNeighborNode(node, direction, nodeFilter) {
    var neighboringNode = null, rootNode = odtDocument.getRootNode(), unfilteredCandidate;
    while (node !== rootNode && node !== null && neighboringNode === null) {
      unfilteredCandidate = direction < 0 ? node.previousSibling : node.nextSibling;
      if (nodeFilter(unfilteredCandidate) === NodeFilter.FILTER_ACCEPT) {
        neighboringNode = unfilteredCandidate;
      }
      node = node.parentNode;
    }
    return neighboringNode;
  }
  function typeOfNeighbor(node, getOffset) {
    var neighboringChar;
    if (node === null) {
      return NeighborType.NO_NEIGHBOUR;
    }
    if (odfUtils.isCharacterElement(node)) {
      return NeighborType.SPACE_CHAR;
    }
    if (node.nodeType === TEXT_NODE || odfUtils.isTextSpan(node) || odfUtils.isHyperlink(node)) {
      neighboringChar = node.textContent.charAt(getOffset());
      if (spacing.test(neighboringChar)) {
        return NeighborType.SPACE_CHAR;
      }
      if (punctuation.test(neighboringChar)) {
        return NeighborType.PUNCTUATION_CHAR;
      }
      return NeighborType.WORD_CHAR;
    }
    return NeighborType.OTHER;
  }
  this.acceptPosition = function(iterator) {
    var container = iterator.container(), leftNode = iterator.leftNode(), rightNode = iterator.rightNode(), getRightCharOffset = iterator.unfilteredDomOffset, getLeftCharOffset = function() {
      return iterator.unfilteredDomOffset() - 1;
    }, leftNeighborType, rightNeighborType;
    if (container.nodeType === ELEMENT_NODE) {
      if (rightNode === null) {
        rightNode = findHigherNeighborNode(container, 1, iterator.getNodeFilter());
      }
      if (leftNode === null) {
        leftNode = findHigherNeighborNode(container, -1, iterator.getNodeFilter());
      }
    }
    if (container !== rightNode) {
      getRightCharOffset = function() {
        return 0;
      };
    }
    if (container !== leftNode && leftNode !== null) {
      getLeftCharOffset = function() {
        return leftNode.textContent.length - 1;
      };
    }
    leftNeighborType = typeOfNeighbor(leftNode, getLeftCharOffset);
    rightNeighborType = typeOfNeighbor(rightNode, getRightCharOffset);
    if (leftNeighborType === NeighborType.WORD_CHAR && rightNeighborType === NeighborType.WORD_CHAR || leftNeighborType === NeighborType.PUNCTUATION_CHAR && rightNeighborType === NeighborType.PUNCTUATION_CHAR || includeWhitespace === TRAILING && leftNeighborType !== NeighborType.NO_NEIGHBOUR && rightNeighborType === NeighborType.SPACE_CHAR || includeWhitespace === LEADING && leftNeighborType === NeighborType.SPACE_CHAR && rightNeighborType !== NeighborType.NO_NEIGHBOUR) {
      return FILTER_REJECT;
    }
    return FILTER_ACCEPT;
  };
};
odf.WordBoundaryFilter.IncludeWhitespace = {None:0, TRAILING:1, LEADING:2};
gui.SelectionController = function SelectionController(session, inputMemberId) {
  var odtDocument = session.getOdtDocument(), domUtils = core.DomUtils, odfUtils = odf.OdfUtils, baseFilter = odtDocument.getPositionFilter(), guiStepUtils = new gui.GuiStepUtils, rootFilter = odtDocument.createRootFilter(inputMemberId), caretXPositionLocator = null, lastXPosition, resetLastXPositionTask, TRAILING_SPACE = odf.WordBoundaryFilter.IncludeWhitespace.TRAILING, LEADING_SPACE = odf.WordBoundaryFilter.IncludeWhitespace.LEADING, PREVIOUS = core.StepDirection.PREVIOUS, NEXT = core.StepDirection.NEXT, 
  UPDOWN_NAVIGATION_RESET_DELAY_MS = 2E3;
  function resetLastXPosition(op) {
    var opspec = op.spec();
    if (op.isEdit || opspec.memberid === inputMemberId) {
      lastXPosition = undefined;
      resetLastXPositionTask.cancel();
    }
  }
  function createKeyboardStepIterator() {
    var cursor = odtDocument.getCursor(inputMemberId), node = cursor.getNode();
    return odtDocument.createStepIterator(node, 0, [baseFilter, rootFilter], odtDocument.getRootElement(node));
  }
  function createWordBoundaryStepIterator(node, offset, includeWhitespace) {
    var wordBoundaryFilter = new odf.WordBoundaryFilter(odtDocument, includeWhitespace), nodeRoot = odtDocument.getRootElement(node) || odtDocument.getRootNode(), nodeRootFilter = odtDocument.createRootFilter(nodeRoot);
    return odtDocument.createStepIterator(node, offset, [baseFilter, nodeRootFilter, wordBoundaryFilter], nodeRoot);
  }
  function selectionToRange(selection) {
    var hasForwardSelection = domUtils.comparePoints(selection.anchorNode, selection.anchorOffset, selection.focusNode, selection.focusOffset) >= 0, range = selection.focusNode.ownerDocument.createRange();
    if (hasForwardSelection) {
      range.setStart(selection.anchorNode, selection.anchorOffset);
      range.setEnd(selection.focusNode, selection.focusOffset);
    } else {
      range.setStart(selection.focusNode, selection.focusOffset);
      range.setEnd(selection.anchorNode, selection.anchorOffset);
    }
    return {range:range, hasForwardSelection:hasForwardSelection};
  }
  this.selectionToRange = selectionToRange;
  function rangeToSelection(range, hasForwardSelection) {
    if (hasForwardSelection) {
      return {anchorNode:range.startContainer, anchorOffset:range.startOffset, focusNode:range.endContainer, focusOffset:range.endOffset};
    }
    return {anchorNode:range.endContainer, anchorOffset:range.endOffset, focusNode:range.startContainer, focusOffset:range.startOffset};
  }
  this.rangeToSelection = rangeToSelection;
  function createOpMoveCursor(position, length, selectionType) {
    var op = new ops.OpMoveCursor;
    op.init({memberid:inputMemberId, position:position, length:length || 0, selectionType:selectionType});
    return op;
  }
  function moveCursorFocusPoint(focusNode, focusOffset, extend) {
    var cursor, newSelection, newCursorSelection;
    cursor = odtDocument.getCursor(inputMemberId);
    newSelection = rangeToSelection(cursor.getSelectedRange(), cursor.hasForwardSelection());
    newSelection.focusNode = focusNode;
    newSelection.focusOffset = focusOffset;
    if (!extend) {
      newSelection.anchorNode = newSelection.focusNode;
      newSelection.anchorOffset = newSelection.focusOffset;
    }
    newCursorSelection = odtDocument.convertDomToCursorRange(newSelection);
    session.enqueue([createOpMoveCursor(newCursorSelection.position, newCursorSelection.length)]);
  }
  function selectImage(frameNode) {
    var frameRoot = odtDocument.getRootElement(frameNode), frameRootFilter = odtDocument.createRootFilter(frameRoot), stepIterator = odtDocument.createStepIterator(frameNode, 0, [frameRootFilter, odtDocument.getPositionFilter()], frameRoot), anchorNode, anchorOffset, newSelection, op;
    if (!stepIterator.roundToPreviousStep()) {
      runtime.assert(false, "No walkable position before frame");
    }
    anchorNode = stepIterator.container();
    anchorOffset = stepIterator.offset();
    stepIterator.setPosition(frameNode, frameNode.childNodes.length);
    if (!stepIterator.roundToNextStep()) {
      runtime.assert(false, "No walkable position after frame");
    }
    newSelection = odtDocument.convertDomToCursorRange({anchorNode:anchorNode, anchorOffset:anchorOffset, focusNode:stepIterator.container(), focusOffset:stepIterator.offset()});
    op = createOpMoveCursor(newSelection.position, newSelection.length, ops.OdtCursor.RegionSelection);
    session.enqueue([op]);
  }
  this.selectImage = selectImage;
  function expandToWordBoundaries(range) {
    var stepIterator;
    stepIterator = createWordBoundaryStepIterator(range.startContainer, range.startOffset, TRAILING_SPACE);
    if (stepIterator.roundToPreviousStep()) {
      range.setStart(stepIterator.container(), stepIterator.offset());
    }
    stepIterator = createWordBoundaryStepIterator(range.endContainer, range.endOffset, LEADING_SPACE);
    if (stepIterator.roundToNextStep()) {
      range.setEnd(stepIterator.container(), stepIterator.offset());
    }
  }
  this.expandToWordBoundaries = expandToWordBoundaries;
  function expandToParagraphBoundaries(range) {
    var paragraphs = odfUtils.getParagraphElements(range), startParagraph = paragraphs[0], endParagraph = paragraphs[paragraphs.length - 1];
    if (startParagraph) {
      range.setStart(startParagraph, 0);
    }
    if (endParagraph) {
      if (odfUtils.isParagraph(range.endContainer) && range.endOffset === 0) {
        range.setEndBefore(endParagraph);
      } else {
        range.setEnd(endParagraph, endParagraph.childNodes.length);
      }
    }
  }
  this.expandToParagraphBoundaries = expandToParagraphBoundaries;
  function roundToClosestStep(root, filters, range, modifyStart) {
    var stepIterator, node, offset;
    if (modifyStart) {
      node = range.startContainer;
      offset = range.startOffset;
    } else {
      node = range.endContainer;
      offset = range.endOffset;
    }
    if (!domUtils.containsNode(root, node)) {
      if (domUtils.comparePoints(root, 0, node, offset) < 0) {
        offset = 0;
      } else {
        offset = root.childNodes.length;
      }
      node = root;
    }
    stepIterator = odtDocument.createStepIterator(node, offset, filters, odfUtils.getParagraphElement(node) || root);
    if (!stepIterator.roundToClosestStep()) {
      runtime.assert(false, "No step found in requested range");
    }
    if (modifyStart) {
      range.setStart(stepIterator.container(), stepIterator.offset());
    } else {
      range.setEnd(stepIterator.container(), stepIterator.offset());
    }
  }
  function selectRange(range, hasForwardSelection, clickCount) {
    var canvasElement = odtDocument.getOdfCanvas().getElement(), validSelection, startInsideCanvas, endInsideCanvas, existingSelection, newSelection, anchorRoot, filters = [baseFilter], op;
    startInsideCanvas = domUtils.containsNode(canvasElement, range.startContainer);
    endInsideCanvas = domUtils.containsNode(canvasElement, range.endContainer);
    if (!startInsideCanvas && !endInsideCanvas) {
      return;
    }
    if (startInsideCanvas && endInsideCanvas) {
      if (clickCount === 2) {
        expandToWordBoundaries(range);
      } else {
        if (clickCount >= 3) {
          expandToParagraphBoundaries(range);
        }
      }
    }
    if (hasForwardSelection) {
      anchorRoot = odtDocument.getRootElement(range.startContainer);
    } else {
      anchorRoot = odtDocument.getRootElement(range.endContainer);
    }
    if (!anchorRoot) {
      anchorRoot = odtDocument.getRootNode();
    }
    filters.push(odtDocument.createRootFilter(anchorRoot));
    roundToClosestStep(anchorRoot, filters, range, true);
    roundToClosestStep(anchorRoot, filters, range, false);
    validSelection = rangeToSelection(range, hasForwardSelection);
    newSelection = odtDocument.convertDomToCursorRange(validSelection);
    existingSelection = odtDocument.getCursorSelection(inputMemberId);
    if (newSelection.position !== existingSelection.position || newSelection.length !== existingSelection.length) {
      op = createOpMoveCursor(newSelection.position, newSelection.length, ops.OdtCursor.RangeSelection);
      session.enqueue([op]);
    }
  }
  this.selectRange = selectRange;
  function moveCursor(direction, extend) {
    var stepIterator = createKeyboardStepIterator();
    if (stepIterator.advanceStep(direction)) {
      moveCursorFocusPoint(stepIterator.container(), stepIterator.offset(), extend);
    }
  }
  function moveCursorToLeft() {
    moveCursor(PREVIOUS, false);
    return true;
  }
  this.moveCursorToLeft = moveCursorToLeft;
  function moveCursorToRight() {
    moveCursor(NEXT, false);
    return true;
  }
  this.moveCursorToRight = moveCursorToRight;
  function extendSelectionToLeft() {
    moveCursor(PREVIOUS, true);
    return true;
  }
  this.extendSelectionToLeft = extendSelectionToLeft;
  function extendSelectionToRight() {
    moveCursor(NEXT, true);
    return true;
  }
  this.extendSelectionToRight = extendSelectionToRight;
  this.setCaretXPositionLocator = function(locator) {
    caretXPositionLocator = locator;
  };
  function moveCursorByLine(direction, extend) {
    var stepIterator, currentX = lastXPosition, stepScanners = [new gui.LineBoundaryScanner, new gui.ParagraphBoundaryScanner];
    if (currentX === undefined && caretXPositionLocator) {
      currentX = caretXPositionLocator();
    }
    if (isNaN(currentX)) {
      return;
    }
    stepIterator = createKeyboardStepIterator();
    if (!guiStepUtils.moveToFilteredStep(stepIterator, direction, stepScanners)) {
      return;
    }
    if (!stepIterator.advanceStep(direction)) {
      return;
    }
    stepScanners = [new gui.ClosestXOffsetScanner(currentX), new gui.LineBoundaryScanner, new gui.ParagraphBoundaryScanner];
    if (guiStepUtils.moveToFilteredStep(stepIterator, direction, stepScanners)) {
      moveCursorFocusPoint(stepIterator.container(), stepIterator.offset(), extend);
      lastXPosition = currentX;
      resetLastXPositionTask.restart();
    }
  }
  function moveCursorUp() {
    moveCursorByLine(PREVIOUS, false);
    return true;
  }
  this.moveCursorUp = moveCursorUp;
  function moveCursorDown() {
    moveCursorByLine(NEXT, false);
    return true;
  }
  this.moveCursorDown = moveCursorDown;
  function extendSelectionUp() {
    moveCursorByLine(PREVIOUS, true);
    return true;
  }
  this.extendSelectionUp = extendSelectionUp;
  function extendSelectionDown() {
    moveCursorByLine(NEXT, true);
    return true;
  }
  this.extendSelectionDown = extendSelectionDown;
  function moveCursorToLineBoundary(direction, extend) {
    var stepIterator = createKeyboardStepIterator(), stepScanners = [new gui.LineBoundaryScanner, new gui.ParagraphBoundaryScanner];
    if (guiStepUtils.moveToFilteredStep(stepIterator, direction, stepScanners)) {
      moveCursorFocusPoint(stepIterator.container(), stepIterator.offset(), extend);
    }
  }
  function moveCursorByWord(direction, extend) {
    var cursor = odtDocument.getCursor(inputMemberId), newSelection = rangeToSelection(cursor.getSelectedRange(), cursor.hasForwardSelection()), stepIterator = createWordBoundaryStepIterator(newSelection.focusNode, newSelection.focusOffset, TRAILING_SPACE);
    if (stepIterator.advanceStep(direction)) {
      moveCursorFocusPoint(stepIterator.container(), stepIterator.offset(), extend);
    }
  }
  function moveCursorBeforeWord() {
    moveCursorByWord(PREVIOUS, false);
    return true;
  }
  this.moveCursorBeforeWord = moveCursorBeforeWord;
  function moveCursorPastWord() {
    moveCursorByWord(NEXT, false);
    return true;
  }
  this.moveCursorPastWord = moveCursorPastWord;
  function extendSelectionBeforeWord() {
    moveCursorByWord(PREVIOUS, true);
    return true;
  }
  this.extendSelectionBeforeWord = extendSelectionBeforeWord;
  function extendSelectionPastWord() {
    moveCursorByWord(NEXT, true);
    return true;
  }
  this.extendSelectionPastWord = extendSelectionPastWord;
  function moveCursorToLineStart() {
    moveCursorToLineBoundary(PREVIOUS, false);
    return true;
  }
  this.moveCursorToLineStart = moveCursorToLineStart;
  function moveCursorToLineEnd() {
    moveCursorToLineBoundary(NEXT, false);
    return true;
  }
  this.moveCursorToLineEnd = moveCursorToLineEnd;
  function extendSelectionToLineStart() {
    moveCursorToLineBoundary(PREVIOUS, true);
    return true;
  }
  this.extendSelectionToLineStart = extendSelectionToLineStart;
  function extendSelectionToLineEnd() {
    moveCursorToLineBoundary(NEXT, true);
    return true;
  }
  this.extendSelectionToLineEnd = extendSelectionToLineEnd;
  function adjustSelectionByNode(direction, extend, getContainmentNode) {
    var validStepFound = false, cursor = odtDocument.getCursor(inputMemberId), containmentNode, selection = rangeToSelection(cursor.getSelectedRange(), cursor.hasForwardSelection()), rootElement = odtDocument.getRootElement(selection.focusNode), stepIterator;
    runtime.assert(Boolean(rootElement), "SelectionController: Cursor outside root");
    stepIterator = odtDocument.createStepIterator(selection.focusNode, selection.focusOffset, [baseFilter, rootFilter], rootElement);
    stepIterator.roundToClosestStep();
    if (!stepIterator.advanceStep(direction)) {
      return;
    }
    containmentNode = getContainmentNode(stepIterator.container());
    if (!containmentNode) {
      return;
    }
    if (direction === PREVIOUS) {
      stepIterator.setPosition(containmentNode, 0);
      validStepFound = stepIterator.roundToNextStep();
    } else {
      stepIterator.setPosition(containmentNode, containmentNode.childNodes.length);
      validStepFound = stepIterator.roundToPreviousStep();
    }
    if (validStepFound) {
      moveCursorFocusPoint(stepIterator.container(), stepIterator.offset(), extend);
    }
  }
  this.extendSelectionToParagraphStart = function() {
    adjustSelectionByNode(PREVIOUS, true, odfUtils.getParagraphElement);
    return true;
  };
  this.extendSelectionToParagraphEnd = function() {
    adjustSelectionByNode(NEXT, true, odfUtils.getParagraphElement);
    return true;
  };
  this.moveCursorToParagraphStart = function() {
    adjustSelectionByNode(PREVIOUS, false, odfUtils.getParagraphElement);
    return true;
  };
  this.moveCursorToParagraphEnd = function() {
    adjustSelectionByNode(NEXT, false, odfUtils.getParagraphElement);
    return true;
  };
  this.moveCursorToDocumentStart = function() {
    adjustSelectionByNode(PREVIOUS, false, odtDocument.getRootElement);
    return true;
  };
  this.moveCursorToDocumentEnd = function() {
    adjustSelectionByNode(NEXT, false, odtDocument.getRootElement);
    return true;
  };
  this.extendSelectionToDocumentStart = function() {
    adjustSelectionByNode(PREVIOUS, true, odtDocument.getRootElement);
    return true;
  };
  this.extendSelectionToDocumentEnd = function() {
    adjustSelectionByNode(NEXT, true, odtDocument.getRootElement);
    return true;
  };
  function extendSelectionToEntireDocument() {
    var cursor = odtDocument.getCursor(inputMemberId), rootElement = odtDocument.getRootElement(cursor.getNode()), anchorNode, anchorOffset, stepIterator, newCursorSelection;
    runtime.assert(Boolean(rootElement), "SelectionController: Cursor outside root");
    stepIterator = odtDocument.createStepIterator(rootElement, 0, [baseFilter, rootFilter], rootElement);
    stepIterator.roundToClosestStep();
    anchorNode = stepIterator.container();
    anchorOffset = stepIterator.offset();
    stepIterator.setPosition(rootElement, rootElement.childNodes.length);
    stepIterator.roundToClosestStep();
    newCursorSelection = odtDocument.convertDomToCursorRange({anchorNode:anchorNode, anchorOffset:anchorOffset, focusNode:stepIterator.container(), focusOffset:stepIterator.offset()});
    session.enqueue([createOpMoveCursor(newCursorSelection.position, newCursorSelection.length)]);
    return true;
  }
  this.extendSelectionToEntireDocument = extendSelectionToEntireDocument;
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.OdtDocument.signalOperationStart, resetLastXPosition);
    core.Async.destroyAll([resetLastXPositionTask.destroy], callback);
  };
  function init() {
    resetLastXPositionTask = core.Task.createTimeoutTask(function() {
      lastXPosition = undefined;
    }, UPDOWN_NAVIGATION_RESET_DELAY_MS);
    odtDocument.subscribe(ops.OdtDocument.signalOperationStart, resetLastXPosition);
  }
  init();
};
gui.TextController = function TextController(session, sessionConstraints, sessionContext, inputMemberId, directStyleOp, paragraphStyleOps) {
  var odtDocument = session.getOdtDocument(), odfUtils = odf.OdfUtils, domUtils = core.DomUtils, BACKWARD = false, FORWARD = true, isEnabled = false, textns = odf.Namespaces.textns, NEXT = core.StepDirection.NEXT;
  function updateEnabledState() {
    if (sessionConstraints.getState(gui.CommonConstraints.EDIT.REVIEW_MODE) === true) {
      isEnabled = sessionContext.isLocalCursorWithinOwnAnnotation();
    } else {
      isEnabled = true;
    }
  }
  function onCursorEvent(cursor) {
    if (cursor.getMemberId() === inputMemberId) {
      updateEnabledState();
    }
  }
  this.isEnabled = function() {
    return isEnabled;
  };
  function domToCursorRange(range, subTree, withRootFilter) {
    var filters = [odtDocument.getPositionFilter()], startStep, endStep, stepIterator;
    if (withRootFilter) {
      filters.push(odtDocument.createRootFilter(range.startContainer));
    }
    stepIterator = odtDocument.createStepIterator(range.startContainer, range.startOffset, filters, subTree);
    if (!stepIterator.roundToClosestStep()) {
      runtime.assert(false, "No walkable step found in paragraph element at range start");
    }
    startStep = odtDocument.convertDomPointToCursorStep(stepIterator.container(), stepIterator.offset());
    if (range.collapsed) {
      endStep = startStep;
    } else {
      stepIterator.setPosition(range.endContainer, range.endOffset);
      if (!stepIterator.roundToClosestStep()) {
        runtime.assert(false, "No walkable step found in paragraph element at range end");
      }
      endStep = odtDocument.convertDomPointToCursorStep(stepIterator.container(), stepIterator.offset());
    }
    return {position:startStep, length:endStep - startStep};
  }
  function createRemoveSelectionOps(range) {
    var firstParagraph, lastParagraph, mergedParagraphStyleName, previousParagraphStart, paragraphs = odfUtils.getParagraphElements(range), paragraphRange = range.cloneRange(), operations = [];
    firstParagraph = paragraphs[0];
    if (paragraphs.length > 1) {
      if (odfUtils.hasNoODFContent(firstParagraph)) {
        lastParagraph = paragraphs[paragraphs.length - 1];
        mergedParagraphStyleName = lastParagraph.getAttributeNS(odf.Namespaces.textns, "style-name") || "";
      } else {
        mergedParagraphStyleName = firstParagraph.getAttributeNS(odf.Namespaces.textns, "style-name") || "";
      }
    }
    paragraphs.forEach(function(paragraph, index) {
      var paragraphStart, removeLimits, intersectionRange, removeOp, mergeOp;
      paragraphRange.setStart(paragraph, 0);
      paragraphRange.collapse(true);
      paragraphStart = domToCursorRange(paragraphRange, paragraph, false).position;
      if (index > 0) {
        mergeOp = new ops.OpMergeParagraph;
        mergeOp.init({memberid:inputMemberId, paragraphStyleName:mergedParagraphStyleName, destinationStartPosition:previousParagraphStart, sourceStartPosition:paragraphStart, moveCursor:index === 1});
        operations.unshift(mergeOp);
      }
      previousParagraphStart = paragraphStart;
      paragraphRange.selectNodeContents(paragraph);
      intersectionRange = domUtils.rangeIntersection(paragraphRange, range);
      if (intersectionRange) {
        removeLimits = domToCursorRange(intersectionRange, paragraph, true);
        if (removeLimits.length > 0) {
          removeOp = new ops.OpRemoveText;
          removeOp.init({memberid:inputMemberId, position:removeLimits.position, length:removeLimits.length});
          operations.unshift(removeOp);
        }
      }
    });
    return operations;
  }
  function toForwardSelection(selection) {
    if (selection.length < 0) {
      selection.position += selection.length;
      selection.length = -selection.length;
    }
    return selection;
  }
  this.enqueueParagraphSplittingOps = function() {
    if (!isEnabled) {
      return false;
    }
    var cursor = odtDocument.getCursor(inputMemberId), range = cursor.getSelectedRange(), selection = toForwardSelection(odtDocument.getCursorSelection(inputMemberId)), op, operations = [], styleOps, originalParagraph = odfUtils.getParagraphElement(cursor.getNode()), paragraphStyle = originalParagraph.getAttributeNS(textns, "style-name") || "";
    if (selection.length > 0) {
      operations = operations.concat(createRemoveSelectionOps(range));
    }
    op = new ops.OpSplitParagraph;
    op.init({memberid:inputMemberId, position:selection.position, paragraphStyleName:paragraphStyle, sourceParagraphPosition:odtDocument.convertDomPointToCursorStep(originalParagraph, 0, NEXT), moveCursor:true});
    operations.push(op);
    if (paragraphStyleOps) {
      styleOps = paragraphStyleOps(selection.position + 1);
      operations = operations.concat(styleOps);
    }
    session.enqueue(operations);
    return true;
  };
  function createStepIterator(cursorNode) {
    var cursorRoot = odtDocument.getRootElement(cursorNode), filters = [odtDocument.getPositionFilter(), odtDocument.createRootFilter(cursorRoot)];
    return odtDocument.createStepIterator(cursorNode, 0, filters, cursorRoot);
  }
  function removeTextInDirection(isForward) {
    if (!isEnabled) {
      return false;
    }
    var cursorNode, range = odtDocument.getCursor(inputMemberId).getSelectedRange().cloneRange(), selection = toForwardSelection(odtDocument.getCursorSelection(inputMemberId)), stepIterator;
    if (selection.length === 0) {
      selection = undefined;
      cursorNode = odtDocument.getCursor(inputMemberId).getNode();
      stepIterator = createStepIterator(cursorNode);
      if (stepIterator.roundToClosestStep() && (isForward ? stepIterator.nextStep() : stepIterator.previousStep())) {
        selection = toForwardSelection(odtDocument.convertDomToCursorRange({anchorNode:cursorNode, anchorOffset:0, focusNode:stepIterator.container(), focusOffset:stepIterator.offset()}));
        if (isForward) {
          range.setStart(cursorNode, 0);
          range.setEnd(stepIterator.container(), stepIterator.offset());
        } else {
          range.setStart(stepIterator.container(), stepIterator.offset());
          range.setEnd(cursorNode, 0);
        }
      }
    }
    if (selection) {
      session.enqueue(createRemoveSelectionOps(range));
    }
    return selection !== undefined;
  }
  this.removeTextByBackspaceKey = function() {
    return removeTextInDirection(BACKWARD);
  };
  this.removeTextByDeleteKey = function() {
    return removeTextInDirection(FORWARD);
  };
  this.removeCurrentSelection = function() {
    if (!isEnabled) {
      return false;
    }
    var range = odtDocument.getCursor(inputMemberId).getSelectedRange();
    session.enqueue(createRemoveSelectionOps(range));
    return true;
  };
  function insertText(text) {
    if (!isEnabled) {
      return;
    }
    var range = odtDocument.getCursor(inputMemberId).getSelectedRange(), selection = toForwardSelection(odtDocument.getCursorSelection(inputMemberId)), op, stylingOp, operations = [], useCachedStyle = false;
    if (selection.length > 0) {
      operations = operations.concat(createRemoveSelectionOps(range));
      useCachedStyle = true;
    }
    op = new ops.OpInsertText;
    op.init({memberid:inputMemberId, position:selection.position, text:text, moveCursor:true});
    operations.push(op);
    if (directStyleOp) {
      stylingOp = directStyleOp(selection.position, text.length, useCachedStyle);
      if (stylingOp) {
        operations.push(stylingOp);
      }
    }
    session.enqueue(operations);
  }
  this.insertText = insertText;
  this.destroy = function(callback) {
    odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    callback();
  };
  function init() {
    odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorEvent);
    sessionConstraints.subscribe(gui.CommonConstraints.EDIT.REVIEW_MODE, updateEnabledState);
    updateEnabledState();
  }
  init();
};
gui.UndoManager = function UndoManager() {
};
gui.UndoManager.prototype.subscribe = function(signal, callback) {
};
gui.UndoManager.prototype.unsubscribe = function(signal, callback) {
};
gui.UndoManager.prototype.setDocument = function(newDocument) {
};
gui.UndoManager.prototype.setInitialState = function() {
};
gui.UndoManager.prototype.initialize = function() {
};
gui.UndoManager.prototype.purgeInitialState = function() {
};
gui.UndoManager.prototype.setPlaybackFunction = function(playback_func) {
};
gui.UndoManager.prototype.hasUndoStates = function() {
};
gui.UndoManager.prototype.hasRedoStates = function() {
};
gui.UndoManager.prototype.moveForward = function(states) {
};
gui.UndoManager.prototype.moveBackward = function(states) {
};
gui.UndoManager.prototype.onOperationExecuted = function(op) {
};
gui.UndoManager.prototype.isDocumentModified = function() {
};
gui.UndoManager.prototype.setDocumentModified = function(modified) {
};
gui.UndoManager.signalUndoStackChanged = "undoStackChanged";
gui.UndoManager.signalUndoStateCreated = "undoStateCreated";
gui.UndoManager.signalUndoStateModified = "undoStateModified";
gui.UndoManager.signalDocumentModifiedChanged = "documentModifiedChanged";
gui.SessionControllerOptions = function() {
  this.directTextStylingEnabled = false;
  this.directParagraphStylingEnabled = false;
  this.annotationsEnabled = false;
};
(function() {
  var FILTER_ACCEPT = core.PositionFilter.FilterResult.FILTER_ACCEPT;
  gui.SessionController = function SessionController(session, inputMemberId, shadowCursor, args) {
    var window = runtime.getWindow(), odtDocument = session.getOdtDocument(), sessionConstraints = new gui.SessionConstraints, sessionContext = new gui.SessionContext(session, inputMemberId), domUtils = core.DomUtils, odfUtils = odf.OdfUtils, mimeDataExporter = new gui.MimeDataExporter, clipboard = new gui.Clipboard(mimeDataExporter), keyDownHandler = new gui.KeyboardHandler, keyPressHandler = new gui.KeyboardHandler, keyUpHandler = new gui.KeyboardHandler, clickStartedWithinCanvas = false, objectNameGenerator = 
    new odf.ObjectNameGenerator(odtDocument.getOdfCanvas().odfContainer(), inputMemberId), isMouseMoved = false, mouseDownRootFilter = null, handleMouseClickTimeoutId, undoManager = null, eventManager = new gui.EventManager(odtDocument), annotationsEnabled = args.annotationsEnabled, annotationController = new gui.AnnotationController(session, sessionConstraints, inputMemberId), directFormattingController = new gui.DirectFormattingController(session, sessionConstraints, sessionContext, inputMemberId, 
    objectNameGenerator, args.directTextStylingEnabled, args.directParagraphStylingEnabled), createCursorStyleOp = directFormattingController.createCursorStyleOp, createParagraphStyleOps = directFormattingController.createParagraphStyleOps, textController = new gui.TextController(session, sessionConstraints, sessionContext, inputMemberId, createCursorStyleOp, createParagraphStyleOps), imageController = new gui.ImageController(session, sessionConstraints, sessionContext, inputMemberId, objectNameGenerator), 
    imageSelector = new gui.ImageSelector(odtDocument.getOdfCanvas()), shadowCursorIterator = odtDocument.createPositionIterator(odtDocument.getRootNode()), drawShadowCursorTask, redrawRegionSelectionTask, pasteController = new gui.PasteController(session, sessionConstraints, sessionContext, inputMemberId), inputMethodEditor = new gui.InputMethodEditor(inputMemberId, eventManager), clickCount = 0, hyperlinkClickHandler = new gui.HyperlinkClickHandler(odtDocument.getOdfCanvas().getElement, keyDownHandler, 
    keyUpHandler), hyperlinkController = new gui.HyperlinkController(session, sessionConstraints, sessionContext, inputMemberId), selectionController = new gui.SelectionController(session, inputMemberId), metadataController = new gui.MetadataController(session, inputMemberId), modifier = gui.KeyboardHandler.Modifier, keyCode = gui.KeyboardHandler.KeyCode, isMacOS = window.navigator.appVersion.toLowerCase().indexOf("mac") !== -1, isIOS = ["iPad", "iPod", "iPhone"].indexOf(window.navigator.platform) !== 
    -1, iOSSafariSupport;
    runtime.assert(window !== null, "Expected to be run in an environment which has a global window, like a browser.");
    function getTarget(e) {
      return e.target || e.srcElement || null;
    }
    function cancelEvent(event) {
      if (event.preventDefault) {
        event.preventDefault();
      } else {
        event.returnValue = false;
      }
    }
    function caretPositionFromPoint(x, y) {
      var doc = odtDocument.getDOMDocument(), c, result = null;
      if (doc.caretRangeFromPoint) {
        c = doc.caretRangeFromPoint(x, y);
        result = {container:c.startContainer, offset:c.startOffset};
      } else {
        if (doc.caretPositionFromPoint) {
          c = doc.caretPositionFromPoint(x, y);
          if (c && c.offsetNode) {
            result = {container:c.offsetNode, offset:c.offset};
          }
        }
      }
      return result;
    }
    function redrawRegionSelection() {
      var cursor = odtDocument.getCursor(inputMemberId), imageElement;
      if (cursor && cursor.getSelectionType() === ops.OdtCursor.RegionSelection) {
        imageElement = odfUtils.getImageElements(cursor.getSelectedRange())[0];
        if (imageElement) {
          imageSelector.select(imageElement.parentNode);
          return;
        }
      }
      imageSelector.clearSelection();
    }
    function stringFromKeyPress(event) {
      if (event.which === null || event.which === undefined) {
        return String.fromCharCode(event.keyCode);
      }
      if (event.which !== 0 && event.charCode !== 0) {
        return String.fromCharCode(event.which);
      }
      return null;
    }
    function handleCut(e) {
      var cursor = odtDocument.getCursor(inputMemberId), selectedRange = cursor.getSelectedRange();
      if (selectedRange.collapsed) {
        e.preventDefault();
        return;
      }
      if (clipboard.setDataFromRange(e, selectedRange)) {
        textController.removeCurrentSelection();
      } else {
        runtime.log("Cut operation failed");
      }
    }
    function handleBeforeCut() {
      var cursor = odtDocument.getCursor(inputMemberId), selectedRange = cursor.getSelectedRange();
      return selectedRange.collapsed !== false;
    }
    function handleCopy(e) {
      var cursor = odtDocument.getCursor(inputMemberId), selectedRange = cursor.getSelectedRange();
      if (selectedRange.collapsed) {
        e.preventDefault();
        return;
      }
      if (!clipboard.setDataFromRange(e, selectedRange)) {
        runtime.log("Copy operation failed");
      }
    }
    function handlePaste(e) {
      var plainText;
      if (window.clipboardData && window.clipboardData.getData) {
        plainText = window.clipboardData.getData("Text");
      } else {
        if (e.clipboardData && e.clipboardData.getData) {
          plainText = e.clipboardData.getData("text/plain");
        }
      }
      if (plainText) {
        textController.removeCurrentSelection();
        pasteController.paste(plainText);
      }
      cancelEvent(e);
    }
    function handleBeforePaste() {
      return false;
    }
    function updateUndoStack(op) {
      if (undoManager) {
        undoManager.onOperationExecuted(op);
      }
    }
    function forwardUndoStackChange(e) {
      odtDocument.emit(ops.OdtDocument.signalUndoStackChanged, e);
    }
    function undo() {
      var hadFocusBefore;
      if (undoManager) {
        hadFocusBefore = eventManager.hasFocus();
        undoManager.moveBackward(1);
        if (hadFocusBefore) {
          eventManager.focus();
        }
        return true;
      }
      return false;
    }
    this.undo = undo;
    function redo() {
      var hadFocusBefore;
      if (undoManager) {
        hadFocusBefore = eventManager.hasFocus();
        undoManager.moveForward(1);
        if (hadFocusBefore) {
          eventManager.focus();
        }
        return true;
      }
      return false;
    }
    this.redo = redo;
    function extendSelectionByDrag(event) {
      var position, cursor = odtDocument.getCursor(inputMemberId), selectedRange = cursor.getSelectedRange(), newSelectionRange, handleEnd = getTarget(event).getAttribute("end");
      if (selectedRange && handleEnd) {
        position = caretPositionFromPoint(event.clientX, event.clientY);
        if (position) {
          shadowCursorIterator.setUnfilteredPosition(position.container, position.offset);
          if (mouseDownRootFilter.acceptPosition(shadowCursorIterator) === FILTER_ACCEPT) {
            newSelectionRange = selectedRange.cloneRange();
            if (handleEnd === "left") {
              newSelectionRange.setStart(shadowCursorIterator.container(), shadowCursorIterator.unfilteredDomOffset());
            } else {
              newSelectionRange.setEnd(shadowCursorIterator.container(), shadowCursorIterator.unfilteredDomOffset());
            }
            shadowCursor.setSelectedRange(newSelectionRange, handleEnd === "right");
            odtDocument.emit(ops.Document.signalCursorMoved, shadowCursor);
          }
        }
      }
    }
    function updateCursorSelection() {
      selectionController.selectRange(shadowCursor.getSelectedRange(), shadowCursor.hasForwardSelection(), 1);
    }
    function updateShadowCursor() {
      var selection = window.getSelection(), selectionRange = selection.rangeCount > 0 && selectionController.selectionToRange(selection);
      if (clickStartedWithinCanvas && selectionRange) {
        isMouseMoved = true;
        imageSelector.clearSelection();
        shadowCursorIterator.setUnfilteredPosition(selection.focusNode, selection.focusOffset);
        if (mouseDownRootFilter.acceptPosition(shadowCursorIterator) === FILTER_ACCEPT) {
          if (clickCount === 2) {
            selectionController.expandToWordBoundaries(selectionRange.range);
          } else {
            if (clickCount >= 3) {
              selectionController.expandToParagraphBoundaries(selectionRange.range);
            }
          }
          shadowCursor.setSelectedRange(selectionRange.range, selectionRange.hasForwardSelection);
          odtDocument.emit(ops.Document.signalCursorMoved, shadowCursor);
        }
      }
    }
    function synchronizeWindowSelection(cursor) {
      var selection = window.getSelection(), range = cursor.getSelectedRange();
      if (selection.extend) {
        if (cursor.hasForwardSelection()) {
          selection.collapse(range.startContainer, range.startOffset);
          selection.extend(range.endContainer, range.endOffset);
        } else {
          selection.collapse(range.endContainer, range.endOffset);
          selection.extend(range.startContainer, range.startOffset);
        }
      } else {
        selection.removeAllRanges();
        selection.addRange(range.cloneRange());
      }
    }
    function computeClickCount(event) {
      return event.button === 0 ? event.detail : 0;
    }
    function handleMouseDown(e) {
      var target = getTarget(e), cursor = odtDocument.getCursor(inputMemberId), rootNode;
      clickStartedWithinCanvas = target !== null && domUtils.containsNode(odtDocument.getOdfCanvas().getElement(), target);
      if (clickStartedWithinCanvas) {
        isMouseMoved = false;
        rootNode = odtDocument.getRootElement(target) || odtDocument.getRootNode();
        mouseDownRootFilter = odtDocument.createRootFilter(rootNode);
        clickCount = computeClickCount(e);
        if (cursor && e.shiftKey) {
          window.getSelection().collapse(cursor.getAnchorNode(), 0);
        } else {
          synchronizeWindowSelection(cursor);
        }
        if (clickCount > 1) {
          updateShadowCursor();
        }
      }
    }
    function mutableSelection(selection) {
      if (selection) {
        return {anchorNode:selection.anchorNode, anchorOffset:selection.anchorOffset, focusNode:selection.focusNode, focusOffset:selection.focusOffset};
      }
      return null;
    }
    function getNextWalkablePosition(node) {
      var root = odtDocument.getRootElement(node), rootFilter = odtDocument.createRootFilter(root), stepIterator = odtDocument.createStepIterator(node, 0, [rootFilter, odtDocument.getPositionFilter()], root);
      stepIterator.setPosition(node, node.childNodes.length);
      if (!stepIterator.roundToNextStep()) {
        return null;
      }
      return {container:stepIterator.container(), offset:stepIterator.offset()};
    }
    function moveByMouseClickEvent(event) {
      var selection = mutableSelection(window.getSelection()), isCollapsed = window.getSelection().isCollapsed, position, selectionRange, rect, frameNode;
      if (!selection.anchorNode && !selection.focusNode) {
        position = caretPositionFromPoint(event.clientX, event.clientY);
        if (position) {
          selection.anchorNode = position.container;
          selection.anchorOffset = position.offset;
          selection.focusNode = selection.anchorNode;
          selection.focusOffset = selection.anchorOffset;
        }
      }
      if (odfUtils.isImage(selection.focusNode) && selection.focusOffset === 0 && odfUtils.isCharacterFrame(selection.focusNode.parentNode)) {
        frameNode = selection.focusNode.parentNode;
        rect = frameNode.getBoundingClientRect();
        if (event.clientX > rect.left) {
          position = getNextWalkablePosition(frameNode);
          if (position) {
            selection.focusNode = position.container;
            selection.focusOffset = position.offset;
            if (isCollapsed) {
              selection.anchorNode = selection.focusNode;
              selection.anchorOffset = selection.focusOffset;
            }
          }
        }
      } else {
        if (odfUtils.isImage(selection.focusNode.firstChild) && selection.focusOffset === 1 && odfUtils.isCharacterFrame(selection.focusNode)) {
          position = getNextWalkablePosition(selection.focusNode);
          if (position) {
            selection.anchorNode = selection.focusNode = position.container;
            selection.anchorOffset = selection.focusOffset = position.offset;
          }
        }
      }
      if (selection.anchorNode && selection.focusNode) {
        selectionRange = selectionController.selectionToRange(selection);
        selectionController.selectRange(selectionRange.range, selectionRange.hasForwardSelection, computeClickCount(event));
      }
      eventManager.focus();
    }
    function selectWordByLongPress(event) {
      var selection, position, selectionRange, container, offset;
      position = caretPositionFromPoint(event.clientX, event.clientY);
      if (position) {
        container = position.container;
        offset = position.offset;
        selection = {anchorNode:container, anchorOffset:offset, focusNode:container, focusOffset:offset};
        selectionRange = selectionController.selectionToRange(selection);
        selectionController.selectRange(selectionRange.range, selectionRange.hasForwardSelection, 2);
        eventManager.focus();
      }
    }
    function handleMouseClickEvent(event) {
      var target = getTarget(event), clickEvent, range, wasCollapsed, frameNode, pos;
      drawShadowCursorTask.processRequests();
      if (clickStartedWithinCanvas) {
        if (odfUtils.isImage(target) && odfUtils.isCharacterFrame(target.parentNode) && window.getSelection().isCollapsed) {
          selectionController.selectImage(target.parentNode);
          eventManager.focus();
        } else {
          if (imageSelector.isSelectorElement(target)) {
            eventManager.focus();
          } else {
            if (isMouseMoved) {
              range = shadowCursor.getSelectedRange();
              wasCollapsed = range.collapsed;
              if (odfUtils.isImage(range.endContainer) && range.endOffset === 0 && odfUtils.isCharacterFrame(range.endContainer.parentNode)) {
                frameNode = range.endContainer.parentNode;
                pos = getNextWalkablePosition(frameNode);
                if (pos) {
                  range.setEnd(pos.container, pos.offset);
                  if (wasCollapsed) {
                    range.collapse(false);
                  }
                }
              }
              selectionController.selectRange(range, shadowCursor.hasForwardSelection(), computeClickCount(event));
              eventManager.focus();
            } else {
              if (isIOS) {
                moveByMouseClickEvent(event);
              } else {
                clickEvent = domUtils.cloneEvent(event);
                handleMouseClickTimeoutId = runtime.setTimeout(function() {
                  moveByMouseClickEvent(clickEvent);
                }, 0);
              }
            }
          }
        }
        clickCount = 0;
        clickStartedWithinCanvas = false;
        isMouseMoved = false;
      }
    }
    function handleDragStart(e) {
      var cursor = odtDocument.getCursor(inputMemberId), selectedRange = cursor.getSelectedRange();
      if (selectedRange.collapsed) {
        return;
      }
      mimeDataExporter.exportRangeToDataTransfer(e.dataTransfer, selectedRange);
    }
    function handleDragEnd() {
      if (clickStartedWithinCanvas) {
        eventManager.focus();
      }
      clickCount = 0;
      clickStartedWithinCanvas = false;
      isMouseMoved = false;
    }
    function handleContextMenu(e) {
      handleMouseClickEvent(e);
    }
    function handleMouseUp(event) {
      var target = getTarget(event), annotationNode = null;
      if (target.className === "annotationRemoveButton") {
        runtime.assert(annotationsEnabled, "Remove buttons are displayed on annotations while annotation editing is disabled in the controller.");
        annotationNode = target.parentNode.getElementsByTagNameNS(odf.Namespaces.officens, "annotation").item(0);
        annotationController.removeAnnotation(annotationNode);
        eventManager.focus();
      } else {
        if (target.getAttribute("class") !== "webodf-draggable") {
          handleMouseClickEvent(event);
        }
      }
    }
    function insertNonEmptyData(e) {
      var input = e.data;
      if (input) {
        if (input.indexOf("\n") === -1) {
          textController.insertText(input);
        } else {
          pasteController.paste(input);
        }
      }
    }
    function returnTrue(fn) {
      return function() {
        fn();
        return true;
      };
    }
    function rangeSelectionOnly(fn) {
      function f(e) {
        var selectionType = odtDocument.getCursor(inputMemberId).getSelectionType();
        if (selectionType === ops.OdtCursor.RangeSelection) {
          return fn(e);
        }
        return true;
      }
      return f;
    }
    function insertLocalCursor() {
      runtime.assert(session.getOdtDocument().getCursor(inputMemberId) === undefined, "Inserting local cursor a second time.");
      var op = new ops.OpAddCursor;
      op.init({memberid:inputMemberId});
      session.enqueue([op]);
      eventManager.focus();
    }
    this.insertLocalCursor = insertLocalCursor;
    function removeLocalCursor() {
      runtime.assert(session.getOdtDocument().getCursor(inputMemberId) !== undefined, "Removing local cursor without inserting before.");
      var op = new ops.OpRemoveCursor;
      op.init({memberid:inputMemberId});
      session.enqueue([op]);
    }
    this.removeLocalCursor = removeLocalCursor;
    this.startEditing = function() {
      inputMethodEditor.subscribe(gui.InputMethodEditor.signalCompositionStart, textController.removeCurrentSelection);
      inputMethodEditor.subscribe(gui.InputMethodEditor.signalCompositionEnd, insertNonEmptyData);
      eventManager.subscribe("beforecut", handleBeforeCut);
      eventManager.subscribe("cut", handleCut);
      eventManager.subscribe("beforepaste", handleBeforePaste);
      eventManager.subscribe("paste", handlePaste);
      if (undoManager) {
        undoManager.initialize();
      }
      eventManager.setEditing(true);
      hyperlinkClickHandler.setModifier(isMacOS ? modifier.Meta : modifier.Ctrl);
      keyDownHandler.bind(keyCode.Backspace, modifier.None, returnTrue(textController.removeTextByBackspaceKey), true);
      keyDownHandler.bind(keyCode.Delete, modifier.None, textController.removeTextByDeleteKey);
      keyDownHandler.bind(keyCode.Tab, modifier.None, rangeSelectionOnly(function() {
        textController.insertText("\t");
        return true;
      }));
      if (isMacOS) {
        keyDownHandler.bind(keyCode.Clear, modifier.None, textController.removeCurrentSelection);
        keyDownHandler.bind(keyCode.B, modifier.Meta, rangeSelectionOnly(directFormattingController.toggleBold));
        keyDownHandler.bind(keyCode.I, modifier.Meta, rangeSelectionOnly(directFormattingController.toggleItalic));
        keyDownHandler.bind(keyCode.U, modifier.Meta, rangeSelectionOnly(directFormattingController.toggleUnderline));
        keyDownHandler.bind(keyCode.L, modifier.MetaShift, rangeSelectionOnly(directFormattingController.alignParagraphLeft));
        keyDownHandler.bind(keyCode.E, modifier.MetaShift, rangeSelectionOnly(directFormattingController.alignParagraphCenter));
        keyDownHandler.bind(keyCode.R, modifier.MetaShift, rangeSelectionOnly(directFormattingController.alignParagraphRight));
        keyDownHandler.bind(keyCode.J, modifier.MetaShift, rangeSelectionOnly(directFormattingController.alignParagraphJustified));
        if (annotationsEnabled) {
          keyDownHandler.bind(keyCode.C, modifier.MetaShift, annotationController.addAnnotation);
        }
        keyDownHandler.bind(keyCode.Z, modifier.Meta, undo);
        keyDownHandler.bind(keyCode.Z, modifier.MetaShift, redo);
      } else {
        keyDownHandler.bind(keyCode.B, modifier.Ctrl, rangeSelectionOnly(directFormattingController.toggleBold));
        keyDownHandler.bind(keyCode.I, modifier.Ctrl, rangeSelectionOnly(directFormattingController.toggleItalic));
        keyDownHandler.bind(keyCode.U, modifier.Ctrl, rangeSelectionOnly(directFormattingController.toggleUnderline));
        keyDownHandler.bind(keyCode.L, modifier.CtrlShift, rangeSelectionOnly(directFormattingController.alignParagraphLeft));
        keyDownHandler.bind(keyCode.E, modifier.CtrlShift, rangeSelectionOnly(directFormattingController.alignParagraphCenter));
        keyDownHandler.bind(keyCode.R, modifier.CtrlShift, rangeSelectionOnly(directFormattingController.alignParagraphRight));
        keyDownHandler.bind(keyCode.J, modifier.CtrlShift, rangeSelectionOnly(directFormattingController.alignParagraphJustified));
        if (annotationsEnabled) {
          keyDownHandler.bind(keyCode.C, modifier.CtrlAlt, annotationController.addAnnotation);
        }
        keyDownHandler.bind(keyCode.Z, modifier.Ctrl, undo);
        keyDownHandler.bind(keyCode.Z, modifier.CtrlShift, redo);
      }
      function handler(e) {
        var text = stringFromKeyPress(e);
        if (text && !(e.altKey || e.ctrlKey || e.metaKey)) {
          textController.insertText(text);
          return true;
        }
        return false;
      }
      keyPressHandler.setDefault(rangeSelectionOnly(handler));
      keyPressHandler.bind(keyCode.Enter, modifier.None, rangeSelectionOnly(textController.enqueueParagraphSplittingOps));
    };
    this.endEditing = function() {
      inputMethodEditor.unsubscribe(gui.InputMethodEditor.signalCompositionStart, textController.removeCurrentSelection);
      inputMethodEditor.unsubscribe(gui.InputMethodEditor.signalCompositionEnd, insertNonEmptyData);
      eventManager.unsubscribe("cut", handleCut);
      eventManager.unsubscribe("beforecut", handleBeforeCut);
      eventManager.unsubscribe("paste", handlePaste);
      eventManager.unsubscribe("beforepaste", handleBeforePaste);
      eventManager.setEditing(false);
      hyperlinkClickHandler.setModifier(modifier.None);
      keyDownHandler.bind(keyCode.Backspace, modifier.None, function() {
        return true;
      }, true);
      keyDownHandler.unbind(keyCode.Delete, modifier.None);
      keyDownHandler.unbind(keyCode.Tab, modifier.None);
      if (isMacOS) {
        keyDownHandler.unbind(keyCode.Clear, modifier.None);
        keyDownHandler.unbind(keyCode.B, modifier.Meta);
        keyDownHandler.unbind(keyCode.I, modifier.Meta);
        keyDownHandler.unbind(keyCode.U, modifier.Meta);
        keyDownHandler.unbind(keyCode.L, modifier.MetaShift);
        keyDownHandler.unbind(keyCode.E, modifier.MetaShift);
        keyDownHandler.unbind(keyCode.R, modifier.MetaShift);
        keyDownHandler.unbind(keyCode.J, modifier.MetaShift);
        if (annotationsEnabled) {
          keyDownHandler.unbind(keyCode.C, modifier.MetaShift);
        }
        keyDownHandler.unbind(keyCode.Z, modifier.Meta);
        keyDownHandler.unbind(keyCode.Z, modifier.MetaShift);
      } else {
        keyDownHandler.unbind(keyCode.B, modifier.Ctrl);
        keyDownHandler.unbind(keyCode.I, modifier.Ctrl);
        keyDownHandler.unbind(keyCode.U, modifier.Ctrl);
        keyDownHandler.unbind(keyCode.L, modifier.CtrlShift);
        keyDownHandler.unbind(keyCode.E, modifier.CtrlShift);
        keyDownHandler.unbind(keyCode.R, modifier.CtrlShift);
        keyDownHandler.unbind(keyCode.J, modifier.CtrlShift);
        if (annotationsEnabled) {
          keyDownHandler.unbind(keyCode.C, modifier.CtrlAlt);
        }
        keyDownHandler.unbind(keyCode.Z, modifier.Ctrl);
        keyDownHandler.unbind(keyCode.Z, modifier.CtrlShift);
      }
      keyPressHandler.setDefault(null);
      keyPressHandler.unbind(keyCode.Enter, modifier.None);
    };
    this.getInputMemberId = function() {
      return inputMemberId;
    };
    this.getSession = function() {
      return session;
    };
    this.getSessionConstraints = function() {
      return sessionConstraints;
    };
    this.setUndoManager = function(manager) {
      if (undoManager) {
        undoManager.unsubscribe(gui.UndoManager.signalUndoStackChanged, forwardUndoStackChange);
      }
      undoManager = manager;
      if (undoManager) {
        undoManager.setDocument(odtDocument);
        undoManager.setPlaybackFunction(session.enqueue);
        undoManager.subscribe(gui.UndoManager.signalUndoStackChanged, forwardUndoStackChange);
      }
    };
    this.getUndoManager = function() {
      return undoManager;
    };
    this.getMetadataController = function() {
      return metadataController;
    };
    this.getAnnotationController = function() {
      return annotationController;
    };
    this.getDirectFormattingController = function() {
      return directFormattingController;
    };
    this.getHyperlinkClickHandler = function() {
      return hyperlinkClickHandler;
    };
    this.getHyperlinkController = function() {
      return hyperlinkController;
    };
    this.getImageController = function() {
      return imageController;
    };
    this.getSelectionController = function() {
      return selectionController;
    };
    this.getTextController = function() {
      return textController;
    };
    this.getEventManager = function() {
      return eventManager;
    };
    this.getKeyboardHandlers = function() {
      return {keydown:keyDownHandler, keypress:keyPressHandler};
    };
    function destroy(callback) {
      eventManager.unsubscribe("keydown", keyDownHandler.handleEvent);
      eventManager.unsubscribe("keypress", keyPressHandler.handleEvent);
      eventManager.unsubscribe("keyup", keyUpHandler.handleEvent);
      eventManager.unsubscribe("copy", handleCopy);
      eventManager.unsubscribe("mousedown", handleMouseDown);
      eventManager.unsubscribe("mousemove", drawShadowCursorTask.trigger);
      eventManager.unsubscribe("mouseup", handleMouseUp);
      eventManager.unsubscribe("contextmenu", handleContextMenu);
      eventManager.unsubscribe("dragstart", handleDragStart);
      eventManager.unsubscribe("dragend", handleDragEnd);
      eventManager.unsubscribe("click", hyperlinkClickHandler.handleClick);
      eventManager.unsubscribe("longpress", selectWordByLongPress);
      eventManager.unsubscribe("drag", extendSelectionByDrag);
      eventManager.unsubscribe("dragstop", updateCursorSelection);
      odtDocument.unsubscribe(ops.OdtDocument.signalOperationEnd, redrawRegionSelectionTask.trigger);
      odtDocument.unsubscribe(ops.Document.signalCursorAdded, inputMethodEditor.registerCursor);
      odtDocument.unsubscribe(ops.Document.signalCursorRemoved, inputMethodEditor.removeCursor);
      odtDocument.unsubscribe(ops.OdtDocument.signalOperationEnd, updateUndoStack);
      callback();
    }
    this.destroy = function(callback) {
      var destroyCallbacks = [drawShadowCursorTask.destroy, redrawRegionSelectionTask.destroy, directFormattingController.destroy, inputMethodEditor.destroy, eventManager.destroy, hyperlinkClickHandler.destroy, hyperlinkController.destroy, metadataController.destroy, selectionController.destroy, textController.destroy, destroy];
      if (iOSSafariSupport) {
        destroyCallbacks.unshift(iOSSafariSupport.destroy);
      }
      runtime.clearTimeout(handleMouseClickTimeoutId);
      core.Async.destroyAll(destroyCallbacks, callback);
    };
    function init() {
      drawShadowCursorTask = core.Task.createRedrawTask(updateShadowCursor);
      redrawRegionSelectionTask = core.Task.createRedrawTask(redrawRegionSelection);
      keyDownHandler.bind(keyCode.Left, modifier.None, rangeSelectionOnly(selectionController.moveCursorToLeft));
      keyDownHandler.bind(keyCode.Right, modifier.None, rangeSelectionOnly(selectionController.moveCursorToRight));
      keyDownHandler.bind(keyCode.Up, modifier.None, rangeSelectionOnly(selectionController.moveCursorUp));
      keyDownHandler.bind(keyCode.Down, modifier.None, rangeSelectionOnly(selectionController.moveCursorDown));
      keyDownHandler.bind(keyCode.Left, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionToLeft));
      keyDownHandler.bind(keyCode.Right, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionToRight));
      keyDownHandler.bind(keyCode.Up, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionUp));
      keyDownHandler.bind(keyCode.Down, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionDown));
      keyDownHandler.bind(keyCode.Home, modifier.None, rangeSelectionOnly(selectionController.moveCursorToLineStart));
      keyDownHandler.bind(keyCode.End, modifier.None, rangeSelectionOnly(selectionController.moveCursorToLineEnd));
      keyDownHandler.bind(keyCode.Home, modifier.Ctrl, rangeSelectionOnly(selectionController.moveCursorToDocumentStart));
      keyDownHandler.bind(keyCode.End, modifier.Ctrl, rangeSelectionOnly(selectionController.moveCursorToDocumentEnd));
      keyDownHandler.bind(keyCode.Home, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionToLineStart));
      keyDownHandler.bind(keyCode.End, modifier.Shift, rangeSelectionOnly(selectionController.extendSelectionToLineEnd));
      keyDownHandler.bind(keyCode.Up, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionToParagraphStart));
      keyDownHandler.bind(keyCode.Down, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionToParagraphEnd));
      keyDownHandler.bind(keyCode.Home, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionToDocumentStart));
      keyDownHandler.bind(keyCode.End, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionToDocumentEnd));
      if (isMacOS) {
        keyDownHandler.bind(keyCode.Left, modifier.Alt, rangeSelectionOnly(selectionController.moveCursorBeforeWord));
        keyDownHandler.bind(keyCode.Right, modifier.Alt, rangeSelectionOnly(selectionController.moveCursorPastWord));
        keyDownHandler.bind(keyCode.Left, modifier.Meta, rangeSelectionOnly(selectionController.moveCursorToLineStart));
        keyDownHandler.bind(keyCode.Right, modifier.Meta, rangeSelectionOnly(selectionController.moveCursorToLineEnd));
        keyDownHandler.bind(keyCode.Home, modifier.Meta, rangeSelectionOnly(selectionController.moveCursorToDocumentStart));
        keyDownHandler.bind(keyCode.End, modifier.Meta, rangeSelectionOnly(selectionController.moveCursorToDocumentEnd));
        keyDownHandler.bind(keyCode.Left, modifier.AltShift, rangeSelectionOnly(selectionController.extendSelectionBeforeWord));
        keyDownHandler.bind(keyCode.Right, modifier.AltShift, rangeSelectionOnly(selectionController.extendSelectionPastWord));
        keyDownHandler.bind(keyCode.Left, modifier.MetaShift, rangeSelectionOnly(selectionController.extendSelectionToLineStart));
        keyDownHandler.bind(keyCode.Right, modifier.MetaShift, rangeSelectionOnly(selectionController.extendSelectionToLineEnd));
        keyDownHandler.bind(keyCode.Up, modifier.AltShift, rangeSelectionOnly(selectionController.extendSelectionToParagraphStart));
        keyDownHandler.bind(keyCode.Down, modifier.AltShift, rangeSelectionOnly(selectionController.extendSelectionToParagraphEnd));
        keyDownHandler.bind(keyCode.Up, modifier.MetaShift, rangeSelectionOnly(selectionController.extendSelectionToDocumentStart));
        keyDownHandler.bind(keyCode.Down, modifier.MetaShift, rangeSelectionOnly(selectionController.extendSelectionToDocumentEnd));
        keyDownHandler.bind(keyCode.A, modifier.Meta, rangeSelectionOnly(selectionController.extendSelectionToEntireDocument));
      } else {
        keyDownHandler.bind(keyCode.Left, modifier.Ctrl, rangeSelectionOnly(selectionController.moveCursorBeforeWord));
        keyDownHandler.bind(keyCode.Right, modifier.Ctrl, rangeSelectionOnly(selectionController.moveCursorPastWord));
        keyDownHandler.bind(keyCode.Left, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionBeforeWord));
        keyDownHandler.bind(keyCode.Right, modifier.CtrlShift, rangeSelectionOnly(selectionController.extendSelectionPastWord));
        keyDownHandler.bind(keyCode.A, modifier.Ctrl, rangeSelectionOnly(selectionController.extendSelectionToEntireDocument));
      }
      if (isIOS) {
        iOSSafariSupport = new gui.IOSSafariSupport(eventManager);
      }
      eventManager.subscribe("keydown", keyDownHandler.handleEvent);
      eventManager.subscribe("keypress", keyPressHandler.handleEvent);
      eventManager.subscribe("keyup", keyUpHandler.handleEvent);
      eventManager.subscribe("copy", handleCopy);
      eventManager.subscribe("mousedown", handleMouseDown);
      eventManager.subscribe("mousemove", drawShadowCursorTask.trigger);
      eventManager.subscribe("mouseup", handleMouseUp);
      eventManager.subscribe("contextmenu", handleContextMenu);
      eventManager.subscribe("dragstart", handleDragStart);
      eventManager.subscribe("dragend", handleDragEnd);
      eventManager.subscribe("click", hyperlinkClickHandler.handleClick);
      eventManager.subscribe("longpress", selectWordByLongPress);
      eventManager.subscribe("drag", extendSelectionByDrag);
      eventManager.subscribe("dragstop", updateCursorSelection);
      odtDocument.subscribe(ops.OdtDocument.signalOperationEnd, redrawRegionSelectionTask.trigger);
      odtDocument.subscribe(ops.Document.signalCursorAdded, inputMethodEditor.registerCursor);
      odtDocument.subscribe(ops.Document.signalCursorRemoved, inputMethodEditor.removeCursor);
      odtDocument.subscribe(ops.OdtDocument.signalOperationEnd, updateUndoStack);
    }
    init();
  };
})();
gui.CaretManager = function CaretManager(sessionController, viewport) {
  var carets = {}, window = runtime.getWindow(), odtDocument = sessionController.getSession().getOdtDocument(), eventManager = sessionController.getEventManager();
  function getCaret(memberId) {
    return carets.hasOwnProperty(memberId) ? carets[memberId] : null;
  }
  function getLocalCaretXOffsetPx() {
    var localCaret = getCaret(sessionController.getInputMemberId()), lastRect;
    if (localCaret) {
      lastRect = localCaret.getBoundingClientRect();
    }
    return lastRect ? lastRect.right : undefined;
  }
  function getCarets() {
    return Object.keys(carets).map(function(memberid) {
      return carets[memberid];
    });
  }
  function removeCaret(memberId) {
    var caret = carets[memberId];
    if (caret) {
      delete carets[memberId];
      if (memberId === sessionController.getInputMemberId()) {
        odtDocument.unsubscribe(ops.OdtDocument.signalProcessingBatchEnd, caret.ensureVisible);
        odtDocument.unsubscribe(ops.Document.signalCursorMoved, caret.refreshCursorBlinking);
        eventManager.unsubscribe("compositionupdate", caret.handleUpdate);
        eventManager.unsubscribe("compositionend", caret.handleUpdate);
        eventManager.unsubscribe("focus", caret.setFocus);
        eventManager.unsubscribe("blur", caret.removeFocus);
        window.removeEventListener("focus", caret.show, false);
        window.removeEventListener("blur", caret.hide, false);
      } else {
        odtDocument.unsubscribe(ops.OdtDocument.signalProcessingBatchEnd, caret.handleUpdate);
      }
      caret.destroy(function() {
      });
    }
  }
  this.registerCursor = function(cursor, caretAvatarInitiallyVisible, blinkOnRangeSelect) {
    var memberid = cursor.getMemberId(), caret = new gui.Caret(cursor, viewport, caretAvatarInitiallyVisible, blinkOnRangeSelect);
    carets[memberid] = caret;
    if (memberid === sessionController.getInputMemberId()) {
      runtime.log("Starting to track input on new cursor of " + memberid);
      odtDocument.subscribe(ops.OdtDocument.signalProcessingBatchEnd, caret.ensureVisible);
      odtDocument.subscribe(ops.Document.signalCursorMoved, caret.refreshCursorBlinking);
      eventManager.subscribe("compositionupdate", caret.handleUpdate);
      eventManager.subscribe("compositionend", caret.handleUpdate);
      eventManager.subscribe("focus", caret.setFocus);
      eventManager.subscribe("blur", caret.removeFocus);
      window.addEventListener("focus", caret.show, false);
      window.addEventListener("blur", caret.hide, false);
      caret.setOverlayElement(eventManager.getEventTrap());
    } else {
      odtDocument.subscribe(ops.OdtDocument.signalProcessingBatchEnd, caret.handleUpdate);
    }
    return caret;
  };
  this.getCaret = getCaret;
  this.getCarets = getCarets;
  this.destroy = function(callback) {
    var caretCleanup = getCarets().map(function(caret) {
      return caret.destroy;
    });
    sessionController.getSelectionController().setCaretXPositionLocator(null);
    odtDocument.unsubscribe(ops.Document.signalCursorRemoved, removeCaret);
    carets = {};
    core.Async.destroyAll(caretCleanup, callback);
  };
  function init() {
    sessionController.getSelectionController().setCaretXPositionLocator(getLocalCaretXOffsetPx);
    odtDocument.subscribe(ops.Document.signalCursorRemoved, removeCaret);
  }
  init();
};
gui.EditInfoHandle = function EditInfoHandle(parentElement) {
  var edits = [], handle, document = parentElement.ownerDocument, htmlns = document.documentElement.namespaceURI, editinfons = "urn:webodf:names:editinfo";
  function renderEdits() {
    var i, infoDiv, colorSpan, authorSpan, timeSpan;
    core.DomUtils.removeAllChildNodes(handle);
    for (i = 0;i < edits.length;i += 1) {
      infoDiv = document.createElementNS(htmlns, "div");
      infoDiv.className = "editInfo";
      colorSpan = document.createElementNS(htmlns, "span");
      colorSpan.className = "editInfoColor";
      colorSpan.setAttributeNS(editinfons, "editinfo:memberid", edits[i].memberid);
      authorSpan = document.createElementNS(htmlns, "span");
      authorSpan.className = "editInfoAuthor";
      authorSpan.setAttributeNS(editinfons, "editinfo:memberid", edits[i].memberid);
      timeSpan = document.createElementNS(htmlns, "span");
      timeSpan.className = "editInfoTime";
      timeSpan.setAttributeNS(editinfons, "editinfo:memberid", edits[i].memberid);
      timeSpan.appendChild(document.createTextNode(edits[i].time.toString()));
      infoDiv.appendChild(colorSpan);
      infoDiv.appendChild(authorSpan);
      infoDiv.appendChild(timeSpan);
      handle.appendChild(infoDiv);
    }
  }
  this.setEdits = function(editArray) {
    edits = editArray;
    renderEdits();
  };
  this.show = function() {
    handle.style.display = "block";
  };
  this.hide = function() {
    handle.style.display = "none";
  };
  this.destroy = function(callback) {
    parentElement.removeChild(handle);
    callback();
  };
  function init() {
    handle = document.createElementNS(htmlns, "div");
    handle.setAttribute("class", "editInfoHandle");
    handle.style.display = "none";
    parentElement.appendChild(handle);
  }
  init();
};
ops.EditInfo = function EditInfo(container, odtDocument) {
  var editInfoNode, editHistory = {};
  function sortEdits() {
    var arr = [], memberid;
    for (memberid in editHistory) {
      if (editHistory.hasOwnProperty(memberid)) {
        arr.push({"memberid":memberid, "time":editHistory[memberid].time});
      }
    }
    arr.sort(function(a, b) {
      return a.time - b.time;
    });
    return arr;
  }
  this.getNode = function() {
    return editInfoNode;
  };
  this.getOdtDocument = function() {
    return odtDocument;
  };
  this.getEdits = function() {
    return editHistory;
  };
  this.getSortedEdits = function() {
    return sortEdits();
  };
  this.addEdit = function(memberid, timestamp) {
    editHistory[memberid] = {time:timestamp};
  };
  this.clearEdits = function() {
    editHistory = {};
  };
  this.destroy = function(callback) {
    if (container.parentNode) {
      container.removeChild(editInfoNode);
    }
    callback();
  };
  function init() {
    var editInfons = "urn:webodf:names:editinfo", dom = odtDocument.getDOMDocument();
    editInfoNode = dom.createElementNS(editInfons, "editinfo");
    container.insertBefore(editInfoNode, container.firstChild);
  }
  init();
};
gui.EditInfoMarker = function EditInfoMarker(editInfo, initialVisibility) {
  var self = this, editInfoNode, handle, marker, editinfons = "urn:webodf:names:editinfo", decayTimer0, decayTimer1, decayTimer2, decayTimeStep = 1E4;
  function applyDecay(opacity, delay) {
    return runtime.setTimeout(function() {
      marker.style.opacity = opacity;
    }, delay);
  }
  function deleteDecay(timerId) {
    runtime.clearTimeout(timerId);
  }
  function setLastAuthor(memberid) {
    marker.setAttributeNS(editinfons, "editinfo:memberid", memberid);
  }
  this.addEdit = function(memberid, timestamp) {
    var age = Date.now() - timestamp;
    editInfo.addEdit(memberid, timestamp);
    handle.setEdits(editInfo.getSortedEdits());
    setLastAuthor(memberid);
    deleteDecay(decayTimer1);
    deleteDecay(decayTimer2);
    if (age < decayTimeStep) {
      decayTimer0 = applyDecay(1, 0);
      decayTimer1 = applyDecay(.5, decayTimeStep - age);
      decayTimer2 = applyDecay(.2, decayTimeStep * 2 - age);
    } else {
      if (age >= decayTimeStep && age < decayTimeStep * 2) {
        decayTimer0 = applyDecay(.5, 0);
        decayTimer2 = applyDecay(.2, decayTimeStep * 2 - age);
      } else {
        decayTimer0 = applyDecay(.2, 0);
      }
    }
  };
  this.getEdits = function() {
    return editInfo.getEdits();
  };
  this.clearEdits = function() {
    editInfo.clearEdits();
    handle.setEdits([]);
    if (marker.hasAttributeNS(editinfons, "editinfo:memberid")) {
      marker.removeAttributeNS(editinfons, "editinfo:memberid");
    }
  };
  this.getEditInfo = function() {
    return editInfo;
  };
  this.show = function() {
    marker.style.display = "block";
  };
  this.hide = function() {
    self.hideHandle();
    marker.style.display = "none";
  };
  this.showHandle = function() {
    handle.show();
  };
  this.hideHandle = function() {
    handle.hide();
  };
  this.destroy = function(callback) {
    deleteDecay(decayTimer0);
    deleteDecay(decayTimer1);
    deleteDecay(decayTimer2);
    editInfoNode.removeChild(marker);
    handle.destroy(function(err) {
      if (err) {
        callback(err);
      } else {
        editInfo.destroy(callback);
      }
    });
  };
  function init() {
    var dom = editInfo.getOdtDocument().getDOMDocument(), htmlns = dom.documentElement.namespaceURI;
    marker = dom.createElementNS(htmlns, "div");
    marker.setAttribute("class", "editInfoMarker");
    marker.onmouseover = function() {
      self.showHandle();
    };
    marker.onmouseout = function() {
      self.hideHandle();
    };
    editInfoNode = editInfo.getNode();
    editInfoNode.appendChild(marker);
    handle = new gui.EditInfoHandle(editInfoNode);
    if (!initialVisibility) {
      self.hide();
    }
  }
  init();
};
gui.HyperlinkTooltipView = function HyperlinkTooltipView(odfCanvas, getActiveModifier) {
  var domUtils = core.DomUtils, odfUtils = odf.OdfUtils, window = runtime.getWindow(), linkSpan, textSpan, tooltipElement, offsetXPx = 15, offsetYPx = 10;
  runtime.assert(window !== null, "Expected to be run in an environment which has a global window, like a browser.");
  function getHyperlinkElement(node) {
    while (node) {
      if (odfUtils.isHyperlink(node)) {
        return node;
      }
      if (odfUtils.isParagraph(node) || odfUtils.isInlineRoot(node)) {
        break;
      }
      node = node.parentNode;
    }
    return null;
  }
  function getHint() {
    var modifierKey = getActiveModifier(), hint;
    switch(modifierKey) {
      case gui.KeyboardHandler.Modifier.Ctrl:
        hint = runtime.tr("Ctrl-click to follow link");
        break;
      case gui.KeyboardHandler.Modifier.Meta:
        hint = runtime.tr("\u2318-click to follow link");
        break;
      default:
        hint = "";
        break;
    }
    return hint;
  }
  this.showTooltip = function(e) {
    var target = e.target || e.srcElement, sizerElement = odfCanvas.getSizer(), zoomLevel = odfCanvas.getZoomLevel(), referenceRect, linkElement, left, top, max;
    linkElement = getHyperlinkElement(target);
    if (!linkElement) {
      return;
    }
    if (!domUtils.containsNode(sizerElement, tooltipElement)) {
      sizerElement.appendChild(tooltipElement);
    }
    textSpan.textContent = getHint();
    linkSpan.textContent = odfUtils.getHyperlinkTarget(linkElement);
    tooltipElement.style.display = "block";
    max = window.innerWidth - tooltipElement.offsetWidth - offsetXPx;
    left = e.clientX > max ? max : e.clientX + offsetXPx;
    max = window.innerHeight - tooltipElement.offsetHeight - offsetYPx;
    top = e.clientY > max ? max : e.clientY + offsetYPx;
    referenceRect = sizerElement.getBoundingClientRect();
    left = (left - referenceRect.left) / zoomLevel;
    top = (top - referenceRect.top) / zoomLevel;
    tooltipElement.style.left = left + "px";
    tooltipElement.style.top = top + "px";
  };
  this.hideTooltip = function() {
    tooltipElement.style.display = "none";
  };
  this.destroy = function(callback) {
    if (tooltipElement.parentNode) {
      tooltipElement.parentNode.removeChild(tooltipElement);
    }
    callback();
  };
  function init() {
    var document = odfCanvas.getElement().ownerDocument;
    linkSpan = document.createElement("span");
    textSpan = document.createElement("span");
    linkSpan.className = "webodf-hyperlinkTooltipLink";
    textSpan.className = "webodf-hyperlinkTooltipText";
    tooltipElement = document.createElement("div");
    tooltipElement.className = "webodf-hyperlinkTooltip";
    tooltipElement.appendChild(linkSpan);
    tooltipElement.appendChild(textSpan);
    odfCanvas.getElement().appendChild(tooltipElement);
  }
  init();
};
gui.OdfFieldView = function(odfCanvas) {
  var style, document = odfCanvas.getElement().ownerDocument;
  function newStyleSheet() {
    var head = document.getElementsByTagName("head").item(0), sheet = document.createElement("style"), text = "";
    sheet.type = "text/css";
    sheet.media = "screen, print, handheld, projection";
    odf.Namespaces.forEachPrefix(function(prefix, ns) {
      text += "@namespace " + prefix + " url(" + ns + ");\n";
    });
    sheet.appendChild(document.createTextNode(text));
    head.appendChild(sheet);
    return sheet;
  }
  function clearCSSStyleSheet(style) {
    var stylesheet = style.sheet, cssRules = stylesheet.cssRules;
    while (cssRules.length) {
      stylesheet.deleteRule(cssRules.length - 1);
    }
  }
  function createRule(selectors, css) {
    return selectors.join(",\n") + "\n" + css + "\n";
  }
  function generateFieldCSS() {
    var cssSelectors = odf.OdfSchema.getFields().map(function(prefixedName) {
      return prefixedName.replace(":", "|");
    }), highlightFields = createRule(cssSelectors, "{ background-color: #D0D0D0; }"), emptyCssSelectors = cssSelectors.map(function(selector) {
      return selector + ":empty::after";
    }), highlightEmptyFields = createRule(emptyCssSelectors, "{ content:' '; white-space: pre; }");
    return highlightFields + "\n" + highlightEmptyFields;
  }
  this.showFieldHighlight = function() {
    style.appendChild(document.createTextNode(generateFieldCSS()));
  };
  this.hideFieldHighlight = function() {
    clearCSSStyleSheet(style);
  };
  this.destroy = function(callback) {
    if (style.parentNode) {
      style.parentNode.removeChild(style);
    }
    callback();
  };
  function init() {
    style = newStyleSheet();
  }
  init();
};
gui.ShadowCursor = function ShadowCursor(document) {
  var selectedRange = document.getDOMDocument().createRange(), forwardSelection = true;
  this.removeFromDocument = function() {
  };
  this.getMemberId = function() {
    return gui.ShadowCursor.ShadowCursorMemberId;
  };
  this.getSelectedRange = function() {
    return selectedRange;
  };
  this.setSelectedRange = function(range, isForwardSelection) {
    selectedRange = range;
    forwardSelection = isForwardSelection !== false;
  };
  this.hasForwardSelection = function() {
    return forwardSelection;
  };
  this.getDocument = function() {
    return document;
  };
  this.getSelectionType = function() {
    return ops.OdtCursor.RangeSelection;
  };
  function init() {
    selectedRange.setStart(document.getRootNode(), 0);
  }
  init();
};
gui.ShadowCursor.ShadowCursorMemberId = "";
gui.SelectionView = function SelectionView(cursor) {
};
gui.SelectionView.prototype.rerender = function() {
};
gui.SelectionView.prototype.show = function() {
};
gui.SelectionView.prototype.hide = function() {
};
gui.SelectionView.prototype.destroy = function(callback) {
};
gui.SelectionViewManager = function SelectionViewManager(SelectionView) {
  var selectionViews = {};
  function getSelectionView(memberId) {
    return selectionViews.hasOwnProperty(memberId) ? selectionViews[memberId] : null;
  }
  this.getSelectionView = getSelectionView;
  function getSelectionViews() {
    return Object.keys(selectionViews).map(function(memberid) {
      return selectionViews[memberid];
    });
  }
  this.getSelectionViews = getSelectionViews;
  function removeSelectionView(memberId) {
    if (selectionViews.hasOwnProperty(memberId)) {
      selectionViews[memberId].destroy(function() {
      });
      delete selectionViews[memberId];
    }
  }
  this.removeSelectionView = removeSelectionView;
  function hideSelectionView(memberId) {
    if (selectionViews.hasOwnProperty(memberId)) {
      selectionViews[memberId].hide();
    }
  }
  this.hideSelectionView = hideSelectionView;
  function showSelectionView(memberId) {
    if (selectionViews.hasOwnProperty(memberId)) {
      selectionViews[memberId].show();
    }
  }
  this.showSelectionView = showSelectionView;
  this.rerenderSelectionViews = function() {
    Object.keys(selectionViews).forEach(function(memberId) {
      selectionViews[memberId].rerender();
    });
  };
  this.registerCursor = function(cursor, virtualSelectionsInitiallyVisible) {
    var memberId = cursor.getMemberId(), selectionView = new SelectionView(cursor);
    if (virtualSelectionsInitiallyVisible) {
      selectionView.show();
    } else {
      selectionView.hide();
    }
    selectionViews[memberId] = selectionView;
    return selectionView;
  };
  this.destroy = function(callback) {
    var selectionViewArray = getSelectionViews();
    function destroySelectionView(i, err) {
      if (err) {
        callback(err);
      } else {
        if (i < selectionViewArray.length) {
          selectionViewArray[i].destroy(function(err) {
            destroySelectionView(i + 1, err);
          });
        } else {
          callback();
        }
      }
    }
    destroySelectionView(0, undefined);
  };
};
gui.SessionViewOptions = function() {
  this.editInfoMarkersInitiallyVisible = true;
  this.caretAvatarsInitiallyVisible = true;
  this.caretBlinksOnRangeSelect = true;
};
(function() {
  function configOption(userValue, defaultValue) {
    return userValue !== undefined ? Boolean(userValue) : defaultValue;
  }
  gui.SessionView = function SessionView(viewOptions, localMemberId, session, sessionConstraints, caretManager, selectionViewManager) {
    var avatarInfoStyles, annotationConstraintStyles, editInfons = "urn:webodf:names:editinfo", editInfoMap = {}, odtDocument, odfCanvas, highlightRefreshTask, showEditInfoMarkers = configOption(viewOptions.editInfoMarkersInitiallyVisible, true), showCaretAvatars = configOption(viewOptions.caretAvatarsInitiallyVisible, true), blinkOnRangeSelect = configOption(viewOptions.caretBlinksOnRangeSelect, true);
    function onAnnotationAdded(info) {
      if (info.memberId === localMemberId) {
        odfCanvas.getViewport().scrollIntoView(info.annotation.getBoundingClientRect());
      }
    }
    function newStyleSheet() {
      var head = document.getElementsByTagName("head").item(0), sheet = document.createElement("style");
      sheet.type = "text/css";
      sheet.media = "screen, print, handheld, projection";
      head.appendChild(sheet);
      return sheet;
    }
    function createAvatarInfoNodeMatch(nodeName, memberId, pseudoClass) {
      return nodeName + '[editinfo|memberid="' + memberId + '"]' + pseudoClass;
    }
    function getAvatarInfoStyle(nodeName, memberId, pseudoClass) {
      var node = avatarInfoStyles.firstChild, nodeMatch = createAvatarInfoNodeMatch(nodeName, memberId, pseudoClass) + "{";
      while (node) {
        if (node.nodeType === Node.TEXT_NODE && node.data.indexOf(nodeMatch) === 0) {
          return node;
        }
        node = node.nextSibling;
      }
      return null;
    }
    function setAvatarInfoStyle(memberId, name, color) {
      function setStyle(nodeName, rule, pseudoClass) {
        var styleRule = createAvatarInfoNodeMatch(nodeName, memberId, pseudoClass) + rule, styleNode = getAvatarInfoStyle(nodeName, memberId, pseudoClass);
        if (styleNode) {
          styleNode.data = styleRule;
        } else {
          avatarInfoStyles.appendChild(document.createTextNode(styleRule));
        }
      }
      setStyle("div.editInfoMarker", "{ background-color: " + color + "; }", "");
      setStyle("span.editInfoColor", "{ background-color: " + color + "; }", "");
      setStyle("span.editInfoAuthor", '{ content: "' + name + '"; }', ":before");
      setStyle("dc|creator", "{ background-color: " + color + "; }", "");
      setStyle(".webodf-selectionOverlay", "{ fill: " + color + "; stroke: " + color + ";}", "");
      if (memberId === localMemberId) {
        setStyle(".webodf-touchEnabled .webodf-selectionOverlay", "{ display: block; }", " > .webodf-draggable");
        memberId = gui.ShadowCursor.ShadowCursorMemberId;
        setStyle(".webodf-selectionOverlay", "{ fill: " + color + "; stroke: " + color + ";}", "");
        setStyle(".webodf-touchEnabled .webodf-selectionOverlay", "{ display: block; }", " > .webodf-draggable");
      }
    }
    function highlightEdit(element, memberId, timestamp) {
      var editInfo, editInfoMarker, id = "", editInfoNode = element.getElementsByTagNameNS(editInfons, "editinfo").item(0);
      if (editInfoNode) {
        id = editInfoNode.getAttributeNS(editInfons, "id");
        editInfoMarker = editInfoMap[id];
      } else {
        id = Math.random().toString();
        editInfo = new ops.EditInfo(element, session.getOdtDocument());
        editInfoMarker = new gui.EditInfoMarker(editInfo, showEditInfoMarkers);
        editInfoNode = element.getElementsByTagNameNS(editInfons, "editinfo").item(0);
        editInfoNode.setAttributeNS(editInfons, "id", id);
        editInfoMap[id] = editInfoMarker;
      }
      editInfoMarker.addEdit(memberId, new Date(timestamp));
    }
    function setEditInfoMarkerVisibility(visible) {
      var editInfoMarker, keyname;
      for (keyname in editInfoMap) {
        if (editInfoMap.hasOwnProperty(keyname)) {
          editInfoMarker = editInfoMap[keyname];
          if (visible) {
            editInfoMarker.show();
          } else {
            editInfoMarker.hide();
          }
        }
      }
    }
    function setCaretAvatarVisibility(visible) {
      caretManager.getCarets().forEach(function(caret) {
        if (visible) {
          caret.showHandle();
        } else {
          caret.hideHandle();
        }
      });
    }
    this.showEditInfoMarkers = function() {
      if (showEditInfoMarkers) {
        return;
      }
      showEditInfoMarkers = true;
      setEditInfoMarkerVisibility(showEditInfoMarkers);
    };
    this.hideEditInfoMarkers = function() {
      if (!showEditInfoMarkers) {
        return;
      }
      showEditInfoMarkers = false;
      setEditInfoMarkerVisibility(showEditInfoMarkers);
    };
    this.showCaretAvatars = function() {
      if (showCaretAvatars) {
        return;
      }
      showCaretAvatars = true;
      setCaretAvatarVisibility(showCaretAvatars);
    };
    this.hideCaretAvatars = function() {
      if (!showCaretAvatars) {
        return;
      }
      showCaretAvatars = false;
      setCaretAvatarVisibility(showCaretAvatars);
    };
    this.getSession = function() {
      return session;
    };
    this.getCaret = function(memberid) {
      return caretManager.getCaret(memberid);
    };
    function renderMemberData(member) {
      var memberId = member.getMemberId(), properties = member.getProperties();
      setAvatarInfoStyle(memberId, properties.fullName, properties.color);
    }
    function onCursorAdded(cursor) {
      var memberId = cursor.getMemberId(), properties = session.getOdtDocument().getMember(memberId).getProperties(), caret;
      caretManager.registerCursor(cursor, showCaretAvatars, blinkOnRangeSelect);
      selectionViewManager.registerCursor(cursor, true);
      caret = caretManager.getCaret(memberId);
      if (caret) {
        caret.setAvatarImageUrl(properties.imageUrl);
        caret.setColor(properties.color);
      }
      runtime.log("+++ View here +++ eagerly created an Caret for '" + memberId + "'! +++");
    }
    function onCursorMoved(cursor) {
      var memberId = cursor.getMemberId(), localSelectionView = selectionViewManager.getSelectionView(localMemberId), shadowSelectionView = selectionViewManager.getSelectionView(gui.ShadowCursor.ShadowCursorMemberId), localCaret = caretManager.getCaret(localMemberId);
      if (memberId === localMemberId) {
        shadowSelectionView.hide();
        if (localSelectionView) {
          localSelectionView.show();
        }
        if (localCaret) {
          localCaret.show();
        }
      } else {
        if (memberId === gui.ShadowCursor.ShadowCursorMemberId) {
          shadowSelectionView.show();
          if (localSelectionView) {
            localSelectionView.hide();
          }
          if (localCaret) {
            localCaret.hide();
          }
        }
      }
    }
    function onCursorRemoved(memberid) {
      selectionViewManager.removeSelectionView(memberid);
    }
    function onParagraphChanged(info) {
      highlightEdit(info.paragraphElement, info.memberId, info.timeStamp);
      highlightRefreshTask.trigger();
    }
    function refreshHighlights() {
      var annotationViewManager = odfCanvas.getAnnotationViewManager();
      if (annotationViewManager) {
        annotationViewManager.rehighlightAnnotations();
        odtDocument.fixCursorPositions();
      }
    }
    function processConstraints() {
      var localMemberName, cssString, localMember;
      if (annotationConstraintStyles.hasChildNodes()) {
        core.DomUtils.removeAllChildNodes(annotationConstraintStyles);
      }
      if (sessionConstraints.getState(gui.CommonConstraints.EDIT.ANNOTATIONS.ONLY_DELETE_OWN) === true) {
        localMember = session.getOdtDocument().getMember(localMemberId);
        if (localMember) {
          localMemberName = localMember.getProperties().fullName;
          cssString = ".annotationWrapper:not([creator = '" + localMemberName + "']) .annotationRemoveButton { display: none; }";
          annotationConstraintStyles.appendChild(document.createTextNode(cssString));
        }
      }
    }
    function destroy(callback) {
      var editInfoArray = Object.keys(editInfoMap).map(function(keyname) {
        return editInfoMap[keyname];
      });
      odtDocument.unsubscribe(ops.Document.signalMemberAdded, renderMemberData);
      odtDocument.unsubscribe(ops.Document.signalMemberUpdated, renderMemberData);
      odtDocument.unsubscribe(ops.Document.signalCursorAdded, onCursorAdded);
      odtDocument.unsubscribe(ops.Document.signalCursorRemoved, onCursorRemoved);
      odtDocument.unsubscribe(ops.OdtDocument.signalParagraphChanged, onParagraphChanged);
      odtDocument.unsubscribe(ops.Document.signalCursorMoved, onCursorMoved);
      odtDocument.unsubscribe(ops.OdtDocument.signalParagraphChanged, selectionViewManager.rerenderSelectionViews);
      odtDocument.unsubscribe(ops.OdtDocument.signalTableAdded, selectionViewManager.rerenderSelectionViews);
      odtDocument.unsubscribe(ops.OdtDocument.signalParagraphStyleModified, selectionViewManager.rerenderSelectionViews);
      sessionConstraints.unsubscribe(gui.CommonConstraints.EDIT.ANNOTATIONS.ONLY_DELETE_OWN, processConstraints);
      odtDocument.unsubscribe(ops.Document.signalMemberAdded, processConstraints);
      odtDocument.unsubscribe(ops.Document.signalMemberUpdated, processConstraints);
      avatarInfoStyles.parentNode.removeChild(avatarInfoStyles);
      annotationConstraintStyles.parentNode.removeChild(annotationConstraintStyles);
      (function destroyEditInfo(i, err) {
        if (err) {
          callback(err);
        } else {
          if (i < editInfoArray.length) {
            editInfoArray[i].destroy(function(err) {
              destroyEditInfo(i + 1, err);
            });
          } else {
            callback();
          }
        }
      })(0, undefined);
    }
    this.destroy = function(callback) {
      var cleanup = [highlightRefreshTask.destroy, destroy];
      odtDocument.unsubscribe(ops.OdtDocument.signalAnnotationAdded, onAnnotationAdded);
      core.Async.destroyAll(cleanup, callback);
    };
    function init() {
      odtDocument = session.getOdtDocument();
      odfCanvas = odtDocument.getOdfCanvas();
      odtDocument.subscribe(ops.OdtDocument.signalAnnotationAdded, onAnnotationAdded);
      odtDocument.subscribe(ops.Document.signalMemberAdded, renderMemberData);
      odtDocument.subscribe(ops.Document.signalMemberUpdated, renderMemberData);
      odtDocument.subscribe(ops.Document.signalCursorAdded, onCursorAdded);
      odtDocument.subscribe(ops.Document.signalCursorRemoved, onCursorRemoved);
      odtDocument.subscribe(ops.OdtDocument.signalParagraphChanged, onParagraphChanged);
      odtDocument.subscribe(ops.Document.signalCursorMoved, onCursorMoved);
      odtDocument.subscribe(ops.OdtDocument.signalParagraphChanged, selectionViewManager.rerenderSelectionViews);
      odtDocument.subscribe(ops.OdtDocument.signalTableAdded, selectionViewManager.rerenderSelectionViews);
      odtDocument.subscribe(ops.OdtDocument.signalParagraphStyleModified, selectionViewManager.rerenderSelectionViews);
      sessionConstraints.subscribe(gui.CommonConstraints.EDIT.ANNOTATIONS.ONLY_DELETE_OWN, processConstraints);
      odtDocument.subscribe(ops.Document.signalMemberAdded, processConstraints);
      odtDocument.subscribe(ops.Document.signalMemberUpdated, processConstraints);
      avatarInfoStyles = newStyleSheet();
      avatarInfoStyles.appendChild(document.createTextNode("@namespace editinfo url(urn:webodf:names:editinfo);"));
      avatarInfoStyles.appendChild(document.createTextNode("@namespace dc url(http://purl.org/dc/elements/1.1/);"));
      annotationConstraintStyles = newStyleSheet();
      processConstraints();
      highlightRefreshTask = core.Task.createRedrawTask(refreshHighlights);
    }
    init();
  };
})();
gui.SvgSelectionView = function SvgSelectionView(cursor) {
  var document = cursor.getDocument(), documentRoot, sizer, doc = document.getDOMDocument(), svgns = "http://www.w3.org/2000/svg", overlay = doc.createElementNS(svgns, "svg"), polygon = doc.createElementNS(svgns, "polygon"), handle1 = doc.createElementNS(svgns, "circle"), handle2 = doc.createElementNS(svgns, "circle"), odfUtils = odf.OdfUtils, domUtils = core.DomUtils, zoomHelper = document.getCanvas().getZoomHelper(), isVisible = true, positionIterator = cursor.getDocument().createPositionIterator(document.getRootNode()), 
  FILTER_ACCEPT = NodeFilter.FILTER_ACCEPT, FILTER_REJECT = NodeFilter.FILTER_REJECT, HANDLE_RADIUS = 8, renderTask;
  function addOverlay() {
    var newDocumentRoot = document.getRootNode();
    if (documentRoot !== newDocumentRoot) {
      documentRoot = newDocumentRoot;
      sizer = document.getCanvas().getSizer();
      sizer.appendChild(overlay);
      overlay.setAttribute("class", "webodf-selectionOverlay");
      handle1.setAttribute("class", "webodf-draggable");
      handle2.setAttribute("class", "webodf-draggable");
      handle1.setAttribute("end", "left");
      handle2.setAttribute("end", "right");
      handle1.setAttribute("r", HANDLE_RADIUS);
      handle2.setAttribute("r", HANDLE_RADIUS);
      overlay.appendChild(polygon);
      overlay.appendChild(handle1);
      overlay.appendChild(handle2);
    }
  }
  function isRangeVisible(range) {
    var bcr = range.getBoundingClientRect();
    return Boolean(bcr && bcr.height !== 0);
  }
  function lastVisibleRect(range, nodes) {
    var nextNodeIndex = nodes.length - 1, node = nodes[nextNodeIndex], startOffset, endOffset;
    if (range.endContainer === node) {
      startOffset = range.endOffset;
    } else {
      if (node.nodeType === Node.TEXT_NODE) {
        startOffset = node.length;
      } else {
        startOffset = node.childNodes.length;
      }
    }
    endOffset = startOffset;
    range.setStart(node, startOffset);
    range.setEnd(node, endOffset);
    while (!isRangeVisible(range)) {
      if (node.nodeType === Node.ELEMENT_NODE && startOffset > 0) {
        startOffset = 0;
      } else {
        if (node.nodeType === Node.TEXT_NODE && startOffset > 0) {
          startOffset -= 1;
        } else {
          if (nodes[nextNodeIndex]) {
            node = nodes[nextNodeIndex];
            nextNodeIndex -= 1;
            startOffset = endOffset = node.length || node.childNodes.length;
          } else {
            return false;
          }
        }
      }
      range.setStart(node, startOffset);
      range.setEnd(node, endOffset);
    }
    return true;
  }
  function firstVisibleRect(range, nodes) {
    var nextNodeIndex = 0, node = nodes[nextNodeIndex], startOffset = range.startContainer === node ? range.startOffset : 0, endOffset = startOffset;
    range.setStart(node, startOffset);
    range.setEnd(node, endOffset);
    while (!isRangeVisible(range)) {
      if (node.nodeType === Node.ELEMENT_NODE && endOffset < node.childNodes.length) {
        endOffset = node.childNodes.length;
      } else {
        if (node.nodeType === Node.TEXT_NODE && endOffset < node.length) {
          endOffset += 1;
        } else {
          if (nodes[nextNodeIndex]) {
            node = nodes[nextNodeIndex];
            nextNodeIndex += 1;
            startOffset = endOffset = 0;
          } else {
            return false;
          }
        }
      }
      range.setStart(node, startOffset);
      range.setEnd(node, endOffset);
    }
    return true;
  }
  function getExtremeRanges(range) {
    var nodes = odfUtils.getTextElements(range, true, false), firstRange = range.cloneRange(), lastRange = range.cloneRange(), fillerRange = range.cloneRange();
    if (!nodes.length) {
      return null;
    }
    if (!firstVisibleRect(firstRange, nodes)) {
      return null;
    }
    if (!lastVisibleRect(lastRange, nodes)) {
      return null;
    }
    fillerRange.setStart(firstRange.startContainer, firstRange.startOffset);
    fillerRange.setEnd(lastRange.endContainer, lastRange.endOffset);
    return {firstRange:firstRange, lastRange:lastRange, fillerRange:fillerRange};
  }
  function getBoundingRect(rect1, rect2) {
    var resultRect = {};
    resultRect.top = Math.min(rect1.top, rect2.top);
    resultRect.left = Math.min(rect1.left, rect2.left);
    resultRect.right = Math.max(rect1.right, rect2.right);
    resultRect.bottom = Math.max(rect1.bottom, rect2.bottom);
    resultRect.width = resultRect.right - resultRect.left;
    resultRect.height = resultRect.bottom - resultRect.top;
    return resultRect;
  }
  function checkAndGrowOrCreateRect(originalRect, newRect) {
    if (newRect && newRect.width > 0 && newRect.height > 0) {
      if (!originalRect) {
        originalRect = newRect;
      } else {
        originalRect = getBoundingRect(originalRect, newRect);
      }
    }
    return originalRect;
  }
  function getFillerRect(fillerRange) {
    var containerNode = fillerRange.commonAncestorContainer, firstNode = fillerRange.startContainer, lastNode = fillerRange.endContainer, firstOffset = fillerRange.startOffset, lastOffset = fillerRange.endOffset, currentNode, lastMeasuredNode, firstSibling, lastSibling, grownRect = null, currentRect, range = doc.createRange(), rootFilter, odfNodeFilter = new odf.OdfNodeFilter, treeWalker;
    function acceptNode(node) {
      positionIterator.setUnfilteredPosition(node, 0);
      if (odfNodeFilter.acceptNode(node) === FILTER_ACCEPT && rootFilter.acceptPosition(positionIterator) === FILTER_ACCEPT) {
        return FILTER_ACCEPT;
      }
      return FILTER_REJECT;
    }
    function getRectFromNodeAfterFiltering(node) {
      var rect = null;
      if (acceptNode(node) === FILTER_ACCEPT) {
        rect = domUtils.getBoundingClientRect(node);
      }
      return rect;
    }
    if (firstNode === containerNode || lastNode === containerNode) {
      range = fillerRange.cloneRange();
      grownRect = range.getBoundingClientRect();
      range.detach();
      return grownRect;
    }
    firstSibling = firstNode;
    while (firstSibling.parentNode !== containerNode) {
      firstSibling = firstSibling.parentNode;
    }
    lastSibling = lastNode;
    while (lastSibling.parentNode !== containerNode) {
      lastSibling = lastSibling.parentNode;
    }
    rootFilter = document.createRootFilter(firstNode);
    currentNode = firstSibling.nextSibling;
    while (currentNode && currentNode !== lastSibling) {
      currentRect = getRectFromNodeAfterFiltering(currentNode);
      grownRect = checkAndGrowOrCreateRect(grownRect, currentRect);
      currentNode = currentNode.nextSibling;
    }
    if (odfUtils.isParagraph(firstSibling)) {
      grownRect = checkAndGrowOrCreateRect(grownRect, domUtils.getBoundingClientRect(firstSibling));
    } else {
      if (firstSibling.nodeType === Node.TEXT_NODE) {
        currentNode = firstSibling;
        range.setStart(currentNode, firstOffset);
        range.setEnd(currentNode, currentNode === lastSibling ? lastOffset : currentNode.length);
        currentRect = range.getBoundingClientRect();
        grownRect = checkAndGrowOrCreateRect(grownRect, currentRect);
      } else {
        treeWalker = doc.createTreeWalker(firstSibling, NodeFilter.SHOW_TEXT, acceptNode, false);
        currentNode = treeWalker.currentNode = firstNode;
        while (currentNode && currentNode !== lastNode) {
          range.setStart(currentNode, firstOffset);
          range.setEnd(currentNode, currentNode.length);
          currentRect = range.getBoundingClientRect();
          grownRect = checkAndGrowOrCreateRect(grownRect, currentRect);
          lastMeasuredNode = currentNode;
          firstOffset = 0;
          currentNode = treeWalker.nextNode();
        }
      }
    }
    if (!lastMeasuredNode) {
      lastMeasuredNode = firstNode;
    }
    if (odfUtils.isParagraph(lastSibling)) {
      grownRect = checkAndGrowOrCreateRect(grownRect, domUtils.getBoundingClientRect(lastSibling));
    } else {
      if (lastSibling.nodeType === Node.TEXT_NODE) {
        currentNode = lastSibling;
        range.setStart(currentNode, currentNode === firstSibling ? firstOffset : 0);
        range.setEnd(currentNode, lastOffset);
        currentRect = range.getBoundingClientRect();
        grownRect = checkAndGrowOrCreateRect(grownRect, currentRect);
      } else {
        treeWalker = doc.createTreeWalker(lastSibling, NodeFilter.SHOW_TEXT, acceptNode, false);
        currentNode = treeWalker.currentNode = lastNode;
        while (currentNode && currentNode !== lastMeasuredNode) {
          range.setStart(currentNode, 0);
          range.setEnd(currentNode, lastOffset);
          currentRect = range.getBoundingClientRect();
          grownRect = checkAndGrowOrCreateRect(grownRect, currentRect);
          currentNode = treeWalker.previousNode();
          if (currentNode) {
            lastOffset = currentNode.length;
          }
        }
      }
    }
    return grownRect;
  }
  function getCollapsedRectOfTextRange(range, useRightEdge) {
    var clientRect = range.getBoundingClientRect(), collapsedRect = {};
    collapsedRect.width = 0;
    collapsedRect.top = clientRect.top;
    collapsedRect.bottom = clientRect.bottom;
    collapsedRect.height = clientRect.height;
    collapsedRect.left = collapsedRect.right = useRightEdge ? clientRect.right : clientRect.left;
    return collapsedRect;
  }
  function setPoints(points) {
    var pointsString = "", i;
    for (i = 0;i < points.length;i += 1) {
      pointsString += points[i].x + "," + points[i].y + " ";
    }
    polygon.setAttribute("points", pointsString);
  }
  function repositionOverlays(selectedRange) {
    var rootRect = domUtils.getBoundingClientRect(sizer), zoomLevel = zoomHelper.getZoomLevel(), extremes = getExtremeRanges(selectedRange), firstRange, lastRange, fillerRange, firstRect, fillerRect, lastRect, left, right, top, bottom;
    if (extremes) {
      firstRange = extremes.firstRange;
      lastRange = extremes.lastRange;
      fillerRange = extremes.fillerRange;
      firstRect = domUtils.translateRect(getCollapsedRectOfTextRange(firstRange, false), rootRect, zoomLevel);
      lastRect = domUtils.translateRect(getCollapsedRectOfTextRange(lastRange, true), rootRect, zoomLevel);
      fillerRect = getFillerRect(fillerRange);
      if (!fillerRect) {
        fillerRect = getBoundingRect(firstRect, lastRect);
      } else {
        fillerRect = domUtils.translateRect(fillerRect, rootRect, zoomLevel);
      }
      left = fillerRect.left;
      right = firstRect.left + Math.max(0, fillerRect.width - (firstRect.left - fillerRect.left));
      top = Math.min(firstRect.top, lastRect.top);
      bottom = lastRect.top + lastRect.height;
      setPoints([{x:firstRect.left, y:top + firstRect.height}, {x:firstRect.left, y:top}, {x:right, y:top}, {x:right, y:bottom - lastRect.height}, {x:lastRect.right, y:bottom - lastRect.height}, {x:lastRect.right, y:bottom}, {x:left, y:bottom}, {x:left, y:top + firstRect.height}, {x:firstRect.left, y:top + firstRect.height}]);
      handle1.setAttribute("cx", firstRect.left);
      handle1.setAttribute("cy", top + firstRect.height / 2);
      handle2.setAttribute("cx", lastRect.right);
      handle2.setAttribute("cy", bottom - lastRect.height / 2);
      firstRange.detach();
      lastRange.detach();
      fillerRange.detach();
    }
    return Boolean(extremes);
  }
  function rerender() {
    var range = cursor.getSelectedRange(), shouldShow;
    shouldShow = isVisible && cursor.getSelectionType() === ops.OdtCursor.RangeSelection && !range.collapsed;
    if (shouldShow) {
      addOverlay();
      shouldShow = repositionOverlays(range);
    }
    if (shouldShow) {
      overlay.style.display = "block";
    } else {
      overlay.style.display = "none";
    }
  }
  this.rerender = function() {
    if (isVisible) {
      renderTask.trigger();
    }
  };
  this.show = function() {
    isVisible = true;
    renderTask.trigger();
  };
  this.hide = function() {
    isVisible = false;
    renderTask.trigger();
  };
  function handleCursorMove(movedCursor) {
    if (isVisible && movedCursor === cursor) {
      renderTask.trigger();
    }
  }
  function scaleHandles(zoomLevel) {
    var radius = HANDLE_RADIUS / zoomLevel;
    handle1.setAttribute("r", radius);
    handle2.setAttribute("r", radius);
  }
  function destroy(callback) {
    sizer.removeChild(overlay);
    sizer.classList.remove("webodf-virtualSelections");
    cursor.getDocument().unsubscribe(ops.Document.signalCursorMoved, handleCursorMove);
    zoomHelper.unsubscribe(gui.ZoomHelper.signalZoomChanged, scaleHandles);
    callback();
  }
  this.destroy = function(callback) {
    core.Async.destroyAll([renderTask.destroy, destroy], callback);
  };
  function init() {
    var editinfons = "urn:webodf:names:editinfo", memberid = cursor.getMemberId();
    renderTask = core.Task.createRedrawTask(rerender);
    addOverlay();
    overlay.setAttributeNS(editinfons, "editinfo:memberid", memberid);
    sizer.classList.add("webodf-virtualSelections");
    cursor.getDocument().subscribe(ops.Document.signalCursorMoved, handleCursorMove);
    zoomHelper.subscribe(gui.ZoomHelper.signalZoomChanged, scaleHandles);
    scaleHandles(zoomHelper.getZoomLevel());
  }
  init();
};
gui.UndoStateRules = function UndoStateRules() {
  function ReverseIterator(array, predicate) {
    var index = array.length;
    this.previous = function() {
      for (index = index - 1;index >= 0;index -= 1) {
        if (predicate(array[index])) {
          return array[index];
        }
      }
      return null;
    };
  }
  function getOpType(op) {
    return op.spec().optype;
  }
  function getOpPosition(op) {
    var key = "position", spec = op.spec(), value;
    if (spec.hasOwnProperty(key)) {
      value = spec[key];
    }
    return value;
  }
  function isEditOperation(op) {
    return op.isEdit;
  }
  this.isEditOperation = isEditOperation;
  function canAggregateOperation(op) {
    switch(getOpType(op)) {
      case "RemoveText":
      ;
      case "InsertText":
        return true;
      default:
        return false;
    }
  }
  function isSameDirectionOfTravel(thisOp, lastEditOp, secondLastEditOp) {
    var thisPosition = getOpPosition(thisOp), lastPosition = getOpPosition(lastEditOp), secondLastPosition = getOpPosition(secondLastEditOp), diffLastToSecondLast = lastPosition - secondLastPosition, diffThisToLast = thisPosition - lastPosition;
    return diffThisToLast === diffLastToSecondLast;
  }
  function isAdjacentOperation(thisOp, lastEditOp) {
    var positionDifference = getOpPosition(thisOp) - getOpPosition(lastEditOp);
    return positionDifference === 0 || Math.abs(positionDifference) === 1;
  }
  function continuesOperations(thisOp, lastEditOp, secondLastEditOp) {
    if (!secondLastEditOp) {
      return isAdjacentOperation(thisOp, lastEditOp);
    }
    return isSameDirectionOfTravel(thisOp, lastEditOp, secondLastEditOp);
  }
  function continuesMostRecentEditOperation(thisOp, recentOperations) {
    var thisOpType = getOpType(thisOp), editOpsFinder = new ReverseIterator(recentOperations, isEditOperation), lastEditOp = editOpsFinder.previous();
    runtime.assert(Boolean(lastEditOp), "No edit operations found in state");
    if (thisOpType === getOpType(lastEditOp)) {
      return continuesOperations(thisOp, lastEditOp, editOpsFinder.previous());
    }
    return false;
  }
  function continuesMostRecentEditGroup(thisOp, recentOperations) {
    var thisOpType = getOpType(thisOp), editOpsFinder = new ReverseIterator(recentOperations, isEditOperation), candidateOp = editOpsFinder.previous(), lastEditOp, secondLastEditOp = null, inspectedGroupsCount, groupId;
    runtime.assert(Boolean(candidateOp), "No edit operations found in state");
    groupId = candidateOp.group;
    runtime.assert(groupId !== undefined, "Operation has no group");
    inspectedGroupsCount = 1;
    while (candidateOp && candidateOp.group === groupId) {
      if (thisOpType === getOpType(candidateOp)) {
        lastEditOp = candidateOp;
        break;
      }
      candidateOp = editOpsFinder.previous();
    }
    if (lastEditOp) {
      candidateOp = editOpsFinder.previous();
      while (candidateOp) {
        if (candidateOp.group !== groupId) {
          if (inspectedGroupsCount === 2) {
            break;
          }
          groupId = candidateOp.group;
          inspectedGroupsCount += 1;
        }
        if (thisOpType === getOpType(candidateOp)) {
          secondLastEditOp = candidateOp;
          break;
        }
        candidateOp = editOpsFinder.previous();
      }
      return continuesOperations(thisOp, lastEditOp, secondLastEditOp);
    }
    return false;
  }
  function isPartOfOperationSet(operation, recentOperations) {
    var areOperationsGrouped = operation.group !== undefined, lastOperation;
    if (!isEditOperation(operation)) {
      return true;
    }
    if (recentOperations.length === 0) {
      return true;
    }
    lastOperation = recentOperations[recentOperations.length - 1];
    if (areOperationsGrouped && operation.group === lastOperation.group) {
      return true;
    }
    if (canAggregateOperation(operation) && recentOperations.some(isEditOperation)) {
      if (areOperationsGrouped) {
        return continuesMostRecentEditGroup(operation, recentOperations);
      }
      return continuesMostRecentEditOperation(operation, recentOperations);
    }
    return false;
  }
  this.isPartOfOperationSet = isPartOfOperationSet;
};
(function() {
  var stateIdBase = 0;
  function StateId(mainId, subId) {
    this.mainId = mainId !== undefined ? mainId : -1;
    this.subId = subId !== undefined ? subId : -1;
  }
  function StateTransition(undoRules, initialOps, editOpsPossible) {
    var nextStateId, operations, editOpsCount;
    this.addOperation = function(op) {
      if (undoRules.isEditOperation(op)) {
        editOpsCount += 1;
      }
      operations.push(op);
    };
    this.isNextStateId = function(stateId) {
      return stateId.mainId === nextStateId && stateId.subId === editOpsCount;
    };
    this.getNextStateId = function() {
      return new StateId(nextStateId, editOpsCount);
    };
    this.getOperations = function() {
      return operations;
    };
    function addEditOpsCount(count, op) {
      return count + (undoRules.isEditOperation(op) ? 1 : 0);
    }
    function init() {
      stateIdBase += 1;
      nextStateId = stateIdBase;
      operations = initialOps || [];
      editOpsCount = initialOps && editOpsPossible ? initialOps.reduce(addEditOpsCount, 0) : 0;
    }
    init();
  }
  gui.TrivialUndoManager = function TrivialUndoManager(defaultRules) {
    var self = this, cursorns = "urn:webodf:names:cursor", domUtils = core.DomUtils, initialDoc, initialStateTransition, playFunc, document, unmodifiedStateId, currentUndoStateTransition, undoStateTransitions = [], redoStateTransitions = [], eventNotifier = new core.EventNotifier([gui.UndoManager.signalUndoStackChanged, gui.UndoManager.signalUndoStateCreated, gui.UndoManager.signalUndoStateModified, gui.UndoManager.signalDocumentModifiedChanged, gui.TrivialUndoManager.signalDocumentRootReplaced]), 
    undoRules = defaultRules || new gui.UndoStateRules, isExecutingOps = false;
    function isModified() {
      return currentUndoStateTransition.isNextStateId(unmodifiedStateId) !== true;
    }
    function executeOperations(stateTransition) {
      var operations = stateTransition.getOperations();
      if (operations.length > 0) {
        isExecutingOps = true;
        playFunc(operations);
        isExecutingOps = false;
      }
    }
    function emitStackChange() {
      eventNotifier.emit(gui.UndoManager.signalUndoStackChanged, {undoAvailable:self.hasUndoStates(), redoAvailable:self.hasRedoStates()});
    }
    function emitDocumentModifiedChange(oldModified) {
      var newModified = isModified();
      if (oldModified !== newModified) {
        eventNotifier.emit(gui.UndoManager.signalDocumentModifiedChanged, newModified);
      }
    }
    function mostRecentUndoStateTransition() {
      return undoStateTransitions[undoStateTransitions.length - 1];
    }
    function completeCurrentUndoState() {
      if (currentUndoStateTransition !== initialStateTransition && currentUndoStateTransition !== mostRecentUndoStateTransition()) {
        undoStateTransitions.push(currentUndoStateTransition);
      }
    }
    function removeNode(node) {
      var sibling = node.previousSibling || node.nextSibling;
      node.parentNode.removeChild(node);
      domUtils.normalizeTextNodes(sibling);
    }
    function removeCursors(root) {
      domUtils.getElementsByTagNameNS(root, cursorns, "cursor").forEach(removeNode);
      domUtils.getElementsByTagNameNS(root, cursorns, "anchor").forEach(removeNode);
    }
    function values(obj) {
      return Object.keys(obj).map(function(key) {
        return obj[key];
      });
    }
    function extractCursorStates(undoStateTransitions) {
      var addCursor = {}, moveCursor = {}, requiredAddOps = {}, remainingAddOps, ops, stateTransition = undoStateTransitions.pop();
      document.getMemberIds().forEach(function(memberid) {
        requiredAddOps[memberid] = true;
      });
      remainingAddOps = Object.keys(requiredAddOps).length;
      function processOp(op) {
        var spec = op.spec();
        if (!requiredAddOps[spec.memberid]) {
          return;
        }
        switch(spec.optype) {
          case "AddCursor":
            if (!addCursor[spec.memberid]) {
              addCursor[spec.memberid] = op;
              delete requiredAddOps[spec.memberid];
              remainingAddOps -= 1;
            }
            break;
          case "MoveCursor":
            if (!moveCursor[spec.memberid]) {
              moveCursor[spec.memberid] = op;
            }
            break;
        }
      }
      while (stateTransition && remainingAddOps > 0) {
        ops = stateTransition.getOperations();
        ops.reverse();
        ops.forEach(processOp);
        stateTransition = undoStateTransitions.pop();
      }
      return new StateTransition(undoRules, values(addCursor).concat(values(moveCursor)));
    }
    this.subscribe = function(signal, callback) {
      eventNotifier.subscribe(signal, callback);
    };
    this.unsubscribe = function(signal, callback) {
      eventNotifier.unsubscribe(signal, callback);
    };
    this.isDocumentModified = isModified;
    this.setDocumentModified = function(modified) {
      if (isModified() === modified) {
        return;
      }
      if (modified) {
        unmodifiedStateId = new StateId;
      } else {
        unmodifiedStateId = currentUndoStateTransition.getNextStateId();
      }
      eventNotifier.emit(gui.UndoManager.signalDocumentModifiedChanged, modified);
    };
    this.hasUndoStates = function() {
      return undoStateTransitions.length > 0;
    };
    this.hasRedoStates = function() {
      return redoStateTransitions.length > 0;
    };
    this.setDocument = function(newDocument) {
      document = newDocument;
    };
    this.purgeInitialState = function() {
      var oldModified = isModified();
      undoStateTransitions.length = 0;
      redoStateTransitions.length = 0;
      currentUndoStateTransition = initialStateTransition = new StateTransition(undoRules);
      unmodifiedStateId = currentUndoStateTransition.getNextStateId();
      initialDoc = null;
      emitStackChange();
      emitDocumentModifiedChange(oldModified);
    };
    function setInitialState() {
      var oldModified = isModified();
      initialDoc = document.cloneDocumentElement();
      removeCursors(initialDoc);
      completeCurrentUndoState();
      currentUndoStateTransition = initialStateTransition = extractCursorStates([initialStateTransition].concat(undoStateTransitions));
      undoStateTransitions.length = 0;
      redoStateTransitions.length = 0;
      if (!oldModified) {
        unmodifiedStateId = currentUndoStateTransition.getNextStateId();
      }
      emitStackChange();
      emitDocumentModifiedChange(oldModified);
    }
    this.setInitialState = setInitialState;
    this.initialize = function() {
      if (!initialDoc) {
        setInitialState();
      }
    };
    this.setPlaybackFunction = function(playback_func) {
      playFunc = playback_func;
    };
    this.onOperationExecuted = function(op) {
      if (isExecutingOps) {
        return;
      }
      var oldModified = isModified();
      if (undoRules.isEditOperation(op) && (currentUndoStateTransition === initialStateTransition || redoStateTransitions.length > 0) || !undoRules.isPartOfOperationSet(op, currentUndoStateTransition.getOperations())) {
        redoStateTransitions.length = 0;
        completeCurrentUndoState();
        currentUndoStateTransition = new StateTransition(undoRules, [op], true);
        undoStateTransitions.push(currentUndoStateTransition);
        eventNotifier.emit(gui.UndoManager.signalUndoStateCreated, {operations:currentUndoStateTransition.getOperations()});
        emitStackChange();
      } else {
        currentUndoStateTransition.addOperation(op);
        eventNotifier.emit(gui.UndoManager.signalUndoStateModified, {operations:currentUndoStateTransition.getOperations()});
      }
      emitDocumentModifiedChange(oldModified);
    };
    this.moveForward = function(states) {
      var moved = 0, oldModified = isModified(), redoOperations;
      while (states && redoStateTransitions.length) {
        redoOperations = redoStateTransitions.pop();
        undoStateTransitions.push(redoOperations);
        executeOperations(redoOperations);
        states -= 1;
        moved += 1;
      }
      if (moved) {
        currentUndoStateTransition = mostRecentUndoStateTransition();
        emitStackChange();
        emitDocumentModifiedChange(oldModified);
      }
      return moved;
    };
    this.moveBackward = function(states) {
      var moved = 0, oldModified = isModified();
      while (states && undoStateTransitions.length) {
        redoStateTransitions.push(undoStateTransitions.pop());
        states -= 1;
        moved += 1;
      }
      if (moved) {
        document.getMemberIds().forEach(function(memberid) {
          if (document.hasCursor(memberid)) {
            document.removeCursor(memberid);
          }
        });
        document.setDocumentElement(initialDoc.cloneNode(true));
        eventNotifier.emit(gui.TrivialUndoManager.signalDocumentRootReplaced, {});
        executeOperations(initialStateTransition);
        undoStateTransitions.forEach(executeOperations);
        currentUndoStateTransition = mostRecentUndoStateTransition() || initialStateTransition;
        emitStackChange();
        emitDocumentModifiedChange(oldModified);
      }
      return moved;
    };
    function init() {
      currentUndoStateTransition = initialStateTransition = new StateTransition(undoRules);
      unmodifiedStateId = currentUndoStateTransition.getNextStateId();
    }
    init();
  };
  gui.TrivialUndoManager.signalDocumentRootReplaced = "documentRootReplaced";
})();
odf.GraphicProperties = function(element, styleParseUtils, parent) {
  var self = this, stylens = odf.Namespaces.stylens, svgns = odf.Namespaces.svgns, getter;
  getter = {verticalPos:function() {
    var v = element.getAttributeNS(stylens, "vertical-pos");
    return v === "" ? undefined : v;
  }, verticalRel:function() {
    var v = element.getAttributeNS(stylens, "vertical-rel");
    return v === "" ? undefined : v;
  }, horizontalPos:function() {
    var v = element.getAttributeNS(stylens, "horizontal-pos");
    return v === "" ? undefined : v;
  }, horizontalRel:function() {
    var v = element.getAttributeNS(stylens, "horizontal-rel");
    return v === "" ? undefined : v;
  }, strokeWidth:function() {
    var a = element.getAttributeNS(svgns, "stroke-width");
    return styleParseUtils.parseLength(a);
  }};
  this.verticalPos = function() {
    return self.data.value("verticalPos");
  };
  this.verticalRel = function() {
    return self.data.value("verticalRel");
  };
  this.horizontalPos = function() {
    return self.data.value("horizontalPos");
  };
  this.horizontalRel = function() {
    return self.data.value("horizontalRel");
  };
  this.strokeWidth = function() {
    return self.data.value("strokeWidth");
  };
  this.data;
  function init() {
    var p = parent === undefined ? undefined : parent.data;
    self.data = new odf.LazyStyleProperties(p, getter);
  }
  init();
};
odf.ComputedGraphicProperties = function() {
  var g;
  this.setGraphicProperties = function(graphicProperties) {
    g = graphicProperties;
  };
  this.verticalPos = function() {
    return g && g.verticalPos() || "from-top";
  };
  this.verticalRel = function() {
    return g && g.verticalRel() || "page";
  };
  this.horizontalPos = function() {
    return g && g.horizontalPos() || "from-left";
  };
  this.horizontalRel = function() {
    return g && g.horizontalRel() || "page";
  };
};
odf.PageLayoutProperties = function(element, styleParseUtils, parent) {
  var self = this, fons = odf.Namespaces.fons, getter;
  getter = {pageHeight:function() {
    var a, value;
    if (element) {
      a = element.getAttributeNS(fons, "page-height");
      value = styleParseUtils.parseLength(a);
    }
    return value;
  }, pageWidth:function() {
    var a, value;
    if (element) {
      a = element.getAttributeNS(fons, "page-width");
      value = styleParseUtils.parseLength(a);
    }
    return value;
  }};
  this.pageHeight = function() {
    return self.data.value("pageHeight") || 1123;
  };
  this.pageWidth = function() {
    return self.data.value("pageWidth") || 794;
  };
  this.data;
  function init() {
    var p = parent === undefined ? undefined : parent.data;
    self.data = new odf.LazyStyleProperties(p, getter);
  }
  init();
};
odf.PageLayout = function(element, styleParseUtils, parent) {
  var self = this;
  this.pageLayout;
  function init() {
    var e = null;
    if (element) {
      e = styleParseUtils.getPropertiesElement("page-layout-properties", element);
    }
    self.pageLayout = new odf.PageLayoutProperties(e, styleParseUtils, parent && parent.pageLayout);
  }
  init();
};
odf.PageLayoutCache = function() {
};
odf.PageLayoutCache.prototype.getPageLayout = function(name) {
};
odf.PageLayoutCache.prototype.getDefaultPageLayout = function() {
};
odf.ParagraphProperties = function(element, styleParseUtils, parent) {
  var self = this, fons = odf.Namespaces.fons, getter;
  getter = {marginTop:function() {
    var a = element.getAttributeNS(fons, "margin-top"), value = styleParseUtils.parsePositiveLengthOrPercent(a, "marginTop", parent && parent.data);
    return value;
  }};
  this.marginTop = function() {
    return self.data.value("marginTop");
  };
  this.data;
  function init() {
    var p = parent === undefined ? undefined : parent.data;
    self.data = new odf.LazyStyleProperties(p, getter);
  }
  init();
};
odf.ComputedParagraphProperties = function() {
  var data = {}, styleChain = [];
  function value(name) {
    var v, i;
    if (data.hasOwnProperty(name)) {
      v = data[name];
    } else {
      for (i = 0;v === undefined && i < styleChain.length;i += 1) {
        v = styleChain[i][name]();
      }
      data[name] = v;
    }
    return v;
  }
  this.setStyleChain = function setStyleChain(newStyleChain) {
    styleChain = newStyleChain;
    data = {};
  };
  this.marginTop = function() {
    return value("marginTop") || 0;
  };
};
odf.TextProperties = function(element, styleParseUtils, parent) {
  var self = this, fons = odf.Namespaces.fons, getter;
  getter = {fontSize:function() {
    var a = element.getAttributeNS(fons, "font-size"), value = styleParseUtils.parsePositiveLengthOrPercent(a, "fontSize", parent && parent.data);
    return value;
  }};
  this.fontSize = function() {
    return self.data.value("fontSize");
  };
  this.data;
  function init() {
    var p = parent === undefined ? undefined : parent.data;
    self.data = new odf.LazyStyleProperties(p, getter);
  }
  init();
};
odf.ComputedTextProperties = function() {
  var data = {}, styleChain = [];
  function value(name) {
    var v, i;
    if (data.hasOwnProperty(name)) {
      v = data[name];
    } else {
      for (i = 0;v === undefined && i < styleChain.length;i += 1) {
        v = styleChain[i][name]();
      }
      data[name] = v;
    }
    return v;
  }
  this.setStyleChain = function setStyleChain(newStyleChain) {
    styleChain = newStyleChain;
    data = {};
  };
  this.fontSize = function() {
    return value("fontSize") || 12;
  };
};
odf.MasterPage = function(element, pageLayoutCache) {
  var self = this;
  this.pageLayout;
  function init() {
    var pageLayoutName;
    if (element) {
      pageLayoutName = element.getAttributeNS(odf.Namespaces.stylens, "page-layout-name");
      self.pageLayout = pageLayoutCache.getPageLayout(pageLayoutName);
    } else {
      self.pageLayout = pageLayoutCache.getDefaultPageLayout();
    }
  }
  init();
};
odf.MasterPageCache = function() {
};
odf.MasterPageCache.prototype.getMasterPage = function(name) {
};
odf.StylePileEntry = function(element, styleParseUtils, masterPageCache, parent) {
  this.text;
  this.paragraph;
  this.graphic;
  this.masterPage = function() {
    var masterPageName = element.getAttributeNS(odf.Namespaces.stylens, "master-page-name"), masterPage = null;
    if (masterPageName) {
      masterPage = masterPageCache.getMasterPage(masterPageName);
    }
    return masterPage;
  };
  function init(self) {
    var stylens = odf.Namespaces.stylens, family = element.getAttributeNS(stylens, "family"), e = null;
    if (family === "graphic" || family === "chart") {
      self.graphic = parent === undefined ? undefined : parent.graphic;
      e = styleParseUtils.getPropertiesElement("graphic-properties", element, e);
      if (e !== null) {
        self.graphic = new odf.GraphicProperties(e, styleParseUtils, self.graphic);
      }
    }
    if (family === "paragraph" || family === "table-cell" || family === "graphic" || family === "presentation" || family === "chart") {
      self.paragraph = parent === undefined ? undefined : parent.paragraph;
      e = styleParseUtils.getPropertiesElement("paragraph-properties", element, e);
      if (e !== null) {
        self.paragraph = new odf.ParagraphProperties(e, styleParseUtils, self.paragraph);
      }
    }
    if (family === "text" || family === "paragraph" || family === "table-cell" || family === "graphic" || family === "presentation" || family === "chart") {
      self.text = parent === undefined ? undefined : parent.text;
      e = styleParseUtils.getPropertiesElement("text-properties", element, e);
      if (e !== null) {
        self.text = new odf.TextProperties(e, styleParseUtils, self.text);
      }
    }
  }
  init(this);
};
odf.StylePile = function(styleParseUtils, masterPageCache) {
  var stylens = odf.Namespaces.stylens, commonStyles = {}, automaticStyles = {}, defaultStyle, parsedCommonStyles = {}, parsedAutomaticStyles = {}, getCommonStyle;
  function parseStyle(element, visitedStyles) {
    var parent, parentName, style;
    if (element.hasAttributeNS(stylens, "parent-style-name")) {
      parentName = element.getAttributeNS(stylens, "parent-style-name");
      if (visitedStyles.indexOf(parentName) === -1) {
        parent = getCommonStyle(parentName, visitedStyles);
      }
    }
    style = new odf.StylePileEntry(element, styleParseUtils, masterPageCache, parent);
    return style;
  }
  getCommonStyle = function(styleName, visitedStyles) {
    var style = parsedCommonStyles[styleName], element;
    if (!style) {
      element = commonStyles[styleName];
      if (element) {
        visitedStyles.push(styleName);
        style = parseStyle(element, visitedStyles);
        parsedCommonStyles[styleName] = style;
      }
    }
    return style;
  };
  function getStyle(styleName) {
    var style = parsedAutomaticStyles[styleName] || parsedCommonStyles[styleName], element, visitedStyles = [];
    if (!style) {
      element = automaticStyles[styleName];
      if (!element) {
        element = commonStyles[styleName];
        if (element) {
          visitedStyles.push(styleName);
        }
      }
      if (element) {
        style = parseStyle(element, visitedStyles);
      }
    }
    return style;
  }
  this.getStyle = getStyle;
  this.addCommonStyle = function(style) {
    var name;
    if (style.hasAttributeNS(stylens, "name")) {
      name = style.getAttributeNS(stylens, "name");
      if (!commonStyles.hasOwnProperty(name)) {
        commonStyles[name] = style;
      }
    }
  };
  this.addAutomaticStyle = function(style) {
    var name;
    if (style.hasAttributeNS(stylens, "name")) {
      name = style.getAttributeNS(stylens, "name");
      if (!automaticStyles.hasOwnProperty(name)) {
        automaticStyles[name] = style;
      }
    }
  };
  this.setDefaultStyle = function(style) {
    if (defaultStyle === undefined) {
      defaultStyle = parseStyle(style, []);
    }
  };
  this.getDefaultStyle = function() {
    return defaultStyle;
  };
};
odf.ComputedGraphicStyle = function() {
  this.text = new odf.ComputedTextProperties;
  this.paragraph = new odf.ComputedParagraphProperties;
  this.graphic = new odf.ComputedGraphicProperties;
};
odf.ComputedParagraphStyle = function() {
  this.text = new odf.ComputedTextProperties;
  this.paragraph = new odf.ComputedParagraphProperties;
};
odf.ComputedTextStyle = function() {
  this.text = new odf.ComputedTextProperties;
};
odf.StyleCache = function(odfroot) {
  var self = this, stylePiles, textStyleCache, paragraphStyleCache, graphicStyleCache, textStylePile, paragraphStylePile, graphicStylePile, textns = odf.Namespaces.textns, stylens = odf.Namespaces.stylens, styleInfo = new odf.StyleInfo, styleParseUtils = new odf.StyleParseUtils, masterPages, parsedMasterPages, defaultMasterPage, defaultPageLayout, pageLayouts, parsedPageLayouts;
  function appendClassNames(family, ns, element, chain) {
    var names = element.getAttributeNS(ns, "class-names"), stylename, i;
    if (names) {
      names = names.split(" ");
      for (i = 0;i < names.length;i += 1) {
        stylename = names[i];
        if (stylename) {
          chain.push(family);
          chain.push(stylename);
        }
      }
    }
  }
  function getGraphicStyleChain(element, chain) {
    var stylename = styleInfo.getStyleName("graphic", element);
    if (stylename !== undefined) {
      chain.push("graphic");
      chain.push(stylename);
    }
    return chain;
  }
  function getParagraphStyleChain(element, chain) {
    var stylename = styleInfo.getStyleName("paragraph", element);
    if (stylename !== undefined) {
      chain.push("paragraph");
      chain.push(stylename);
    }
    if (element.namespaceURI === textns && (element.localName === "h" || element.localName === "p")) {
      appendClassNames("paragraph", textns, element, chain);
    }
    return chain;
  }
  function createPropertiesChain(styleChain, propertiesName, defaultFamily) {
    var chain = [], i, lastProperties, family, styleName, pile, style, properties;
    for (i = 0;i < styleChain.length;i += 2) {
      family = styleChain[i];
      styleName = styleChain[i + 1];
      pile = stylePiles[family];
      style = pile.getStyle(styleName);
      if (style !== undefined) {
        properties = style[propertiesName];
        if (properties !== undefined && properties !== lastProperties) {
          chain.push(properties);
          lastProperties = properties;
        }
      }
    }
    pile = stylePiles[defaultFamily];
    style = pile.getDefaultStyle();
    if (style) {
      properties = style[propertiesName];
      if (properties !== undefined && properties !== lastProperties) {
        chain.push(properties);
      }
    }
    return chain;
  }
  this.getComputedGraphicStyle = function(element) {
    var styleChain = getGraphicStyleChain(element, []), key = styleChain.join("/"), computedStyle = graphicStyleCache[key];
    runtime.assert(styleChain.length % 2 === 0, "Invalid style chain.");
    if (computedStyle === undefined) {
      computedStyle = new odf.ComputedGraphicStyle;
      computedStyle.graphic.setGraphicProperties(createPropertiesChain(styleChain, "graphic", "graphic")[0]);
      computedStyle.text.setStyleChain(createPropertiesChain(styleChain, "text", "graphic"));
      computedStyle.paragraph.setStyleChain(createPropertiesChain(styleChain, "paragraph", "graphic"));
      graphicStyleCache[key] = computedStyle;
    }
    return computedStyle;
  };
  this.getComputedParagraphStyle = function(element) {
    var styleChain = getParagraphStyleChain(element, []), key = styleChain.join("/"), computedStyle = paragraphStyleCache[key];
    runtime.assert(styleChain.length % 2 === 0, "Invalid style chain.");
    if (computedStyle === undefined) {
      computedStyle = new odf.ComputedParagraphStyle;
      computedStyle.text.setStyleChain(createPropertiesChain(styleChain, "text", "paragraph"));
      computedStyle.paragraph.setStyleChain(createPropertiesChain(styleChain, "paragraph", "paragraph"));
      paragraphStyleCache[key] = computedStyle;
    }
    return computedStyle;
  };
  function getTextStyleChain(element, chain) {
    var stylename = styleInfo.getStyleName("text", element), parent = element.parentNode;
    if (stylename !== undefined) {
      chain.push("text");
      chain.push(stylename);
    }
    if (element.localName === "span" && element.namespaceURI === textns) {
      appendClassNames("text", textns, element, chain);
    }
    if (!parent || parent === odfroot) {
      return chain;
    }
    if (parent.namespaceURI === textns && (parent.localName === "p" || parent.localName === "h")) {
      getParagraphStyleChain(parent, chain);
    } else {
      getTextStyleChain(parent, chain);
    }
    return chain;
  }
  this.getComputedTextStyle = function(element) {
    var styleChain = getTextStyleChain(element, []), key = styleChain.join("/"), computedStyle = textStyleCache[key];
    runtime.assert(styleChain.length % 2 === 0, "Invalid style chain.");
    if (computedStyle === undefined) {
      computedStyle = new odf.ComputedTextStyle;
      computedStyle.text.setStyleChain(createPropertiesChain(styleChain, "text", "text"));
      textStyleCache[key] = computedStyle;
    }
    return computedStyle;
  };
  function getPileFromElement(element) {
    var family = element.getAttributeNS(stylens, "family");
    return stylePiles[family];
  }
  function addMasterPage(element) {
    var name = element.getAttributeNS(stylens, "name");
    if (name.length > 0 && !masterPages.hasOwnProperty(name)) {
      masterPages[name] = element;
    }
  }
  function getPageLayout(name) {
    var pageLayout = parsedPageLayouts[name], e;
    if (!pageLayout) {
      e = pageLayouts[name];
      if (e) {
        pageLayout = new odf.PageLayout(e, styleParseUtils, defaultPageLayout);
        parsedPageLayouts[name] = pageLayout;
      } else {
        pageLayout = defaultPageLayout;
      }
    }
    return pageLayout;
  }
  this.getPageLayout = getPageLayout;
  this.getDefaultPageLayout = function() {
    return defaultPageLayout;
  };
  function getMasterPage(name) {
    var masterPage = parsedMasterPages[name], element;
    if (masterPage === undefined) {
      element = masterPages[name];
      if (element) {
        masterPage = new odf.MasterPage(element, self);
        parsedMasterPages[name] = masterPage;
      } else {
        masterPage = null;
      }
    }
    return masterPage;
  }
  this.getMasterPage = getMasterPage;
  this.getDefaultMasterPage = function() {
    return defaultMasterPage;
  };
  function update() {
    var e, pile, defaultPageLayoutElement = null, defaultMasterPageElement = null;
    textStyleCache = {};
    paragraphStyleCache = {};
    graphicStyleCache = {};
    masterPages = {};
    parsedMasterPages = {};
    parsedPageLayouts = {};
    pageLayouts = {};
    textStylePile = new odf.StylePile(styleParseUtils, self);
    paragraphStylePile = new odf.StylePile(styleParseUtils, self);
    graphicStylePile = new odf.StylePile(styleParseUtils, self);
    stylePiles = {text:textStylePile, paragraph:paragraphStylePile, graphic:graphicStylePile};
    e = odfroot.styles.firstElementChild;
    while (e) {
      if (e.namespaceURI === stylens) {
        pile = getPileFromElement(e);
        if (pile) {
          if (e.localName === "style") {
            pile.addCommonStyle(e);
          } else {
            if (e.localName === "default-style") {
              pile.setDefaultStyle(e);
            }
          }
        } else {
          if (e.localName === "default-page-layout") {
            defaultPageLayoutElement = e;
          }
        }
      }
      e = e.nextElementSibling;
    }
    defaultPageLayout = new odf.PageLayout(defaultPageLayoutElement, styleParseUtils);
    e = odfroot.automaticStyles.firstElementChild;
    while (e) {
      if (e.namespaceURI === stylens) {
        pile = getPileFromElement(e);
        if (pile && e.localName === "style") {
          pile.addAutomaticStyle(e);
        } else {
          if (e.localName === "page-layout") {
            pageLayouts[e.getAttributeNS(stylens, "name")] = e;
          }
        }
      }
      e = e.nextElementSibling;
    }
    e = odfroot.masterStyles.firstElementChild;
    while (e) {
      if (e.namespaceURI === stylens && e.localName === "master-page") {
        defaultMasterPageElement = defaultMasterPageElement || e;
        addMasterPage(e);
      }
      e = e.nextElementSibling;
    }
    defaultMasterPage = new odf.MasterPage(defaultMasterPageElement, self);
  }
  this.update = update;
};
ops.OperationTransformMatrix = function OperationTransformMatrix() {
  function invertMoveCursorSpecRange(moveCursorSpec) {
    moveCursorSpec.position = moveCursorSpec.position + moveCursorSpec.length;
    moveCursorSpec.length *= -1;
  }
  function invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec) {
    var isBackwards = moveCursorSpec.length < 0;
    if (isBackwards) {
      invertMoveCursorSpecRange(moveCursorSpec);
    }
    return isBackwards;
  }
  function getStyleReferencingAttributes(setProperties, styleName) {
    var attributes = [];
    function check(attributeName) {
      if (setProperties[attributeName] === styleName) {
        attributes.push(attributeName);
      }
    }
    if (setProperties) {
      ["style:parent-style-name", "style:next-style-name"].forEach(check);
    }
    return attributes;
  }
  function dropStyleReferencingAttributes(setProperties, deletedStyleName) {
    function del(attributeName) {
      if (setProperties[attributeName] === deletedStyleName) {
        delete setProperties[attributeName];
      }
    }
    if (setProperties) {
      ["style:parent-style-name", "style:next-style-name"].forEach(del);
    }
  }
  function cloneOpspec(opspec) {
    var result = {};
    Object.keys(opspec).forEach(function(key) {
      if (typeof opspec[key] === "object") {
        result[key] = cloneOpspec(opspec[key]);
      } else {
        result[key] = opspec[key];
      }
    });
    return result;
  }
  function dropOverruledAndUnneededAttributes(minorSetProperties, minorRemovedProperties, majorSetProperties, majorRemovedProperties) {
    var i, name, majorChanged = false, minorChanged = false, removedPropertyNames, majorRemovedPropertyNames = [];
    if (majorRemovedProperties && majorRemovedProperties.attributes) {
      majorRemovedPropertyNames = majorRemovedProperties.attributes.split(",");
    }
    if (minorSetProperties && (majorSetProperties || majorRemovedPropertyNames.length > 0)) {
      Object.keys(minorSetProperties).forEach(function(key) {
        var value = minorSetProperties[key], overrulingPropertyValue;
        if (typeof value !== "object") {
          if (majorSetProperties) {
            overrulingPropertyValue = majorSetProperties[key];
          }
          if (overrulingPropertyValue !== undefined) {
            delete minorSetProperties[key];
            minorChanged = true;
            if (overrulingPropertyValue === value) {
              delete majorSetProperties[key];
              majorChanged = true;
            }
          } else {
            if (majorRemovedPropertyNames.indexOf(key) !== -1) {
              delete minorSetProperties[key];
              minorChanged = true;
            }
          }
        }
      });
    }
    if (minorRemovedProperties && minorRemovedProperties.attributes && (majorSetProperties || majorRemovedPropertyNames.length > 0)) {
      removedPropertyNames = minorRemovedProperties.attributes.split(",");
      for (i = 0;i < removedPropertyNames.length;i += 1) {
        name = removedPropertyNames[i];
        if (majorSetProperties && majorSetProperties[name] !== undefined || majorRemovedPropertyNames && majorRemovedPropertyNames.indexOf(name) !== -1) {
          removedPropertyNames.splice(i, 1);
          i -= 1;
          minorChanged = true;
        }
      }
      if (removedPropertyNames.length > 0) {
        minorRemovedProperties.attributes = removedPropertyNames.join(",");
      } else {
        delete minorRemovedProperties.attributes;
      }
    }
    return {majorChanged:majorChanged, minorChanged:minorChanged};
  }
  function hasProperties(properties) {
    var key;
    for (key in properties) {
      if (properties.hasOwnProperty(key)) {
        return true;
      }
    }
    return false;
  }
  function hasRemovedProperties(properties) {
    var key;
    for (key in properties) {
      if (properties.hasOwnProperty(key)) {
        if (key !== "attributes" || properties.attributes.length > 0) {
          return true;
        }
      }
    }
    return false;
  }
  function dropOverruledAndUnneededProperties(minorSet, minorRem, majorSet, majorRem, propertiesName) {
    var minorSP = minorSet ? minorSet[propertiesName] : null, minorRP = minorRem ? minorRem[propertiesName] : null, majorSP = majorSet ? majorSet[propertiesName] : null, majorRP = majorRem ? majorRem[propertiesName] : null, result;
    result = dropOverruledAndUnneededAttributes(minorSP, minorRP, majorSP, majorRP);
    if (minorSP && !hasProperties(minorSP)) {
      delete minorSet[propertiesName];
    }
    if (minorRP && !hasRemovedProperties(minorRP)) {
      delete minorRem[propertiesName];
    }
    if (majorSP && !hasProperties(majorSP)) {
      delete majorSet[propertiesName];
    }
    if (majorRP && !hasRemovedProperties(majorRP)) {
      delete majorRem[propertiesName];
    }
    return result;
  }
  function transformAddAnnotationAddAnnotation(addAnnotationSpecA, addAnnotationSpecB, hasAPriority) {
    var firstAnnotationSpec, secondAnnotationSpec;
    if (addAnnotationSpecA.position < addAnnotationSpecB.position) {
      firstAnnotationSpec = addAnnotationSpecA;
      secondAnnotationSpec = addAnnotationSpecB;
    } else {
      if (addAnnotationSpecB.position < addAnnotationSpecA.position) {
        firstAnnotationSpec = addAnnotationSpecB;
        secondAnnotationSpec = addAnnotationSpecA;
      } else {
        firstAnnotationSpec = hasAPriority ? addAnnotationSpecA : addAnnotationSpecB;
        secondAnnotationSpec = hasAPriority ? addAnnotationSpecB : addAnnotationSpecA;
      }
    }
    if (secondAnnotationSpec.position < firstAnnotationSpec.position + firstAnnotationSpec.length) {
      firstAnnotationSpec.length += 2;
    }
    secondAnnotationSpec.position += 2;
    return {opSpecsA:[addAnnotationSpecA], opSpecsB:[addAnnotationSpecB]};
  }
  function transformAddAnnotationApplyDirectStyling(addAnnotationSpec, applyDirectStylingSpec) {
    if (addAnnotationSpec.position <= applyDirectStylingSpec.position) {
      applyDirectStylingSpec.position += 2;
    } else {
      if (addAnnotationSpec.position <= applyDirectStylingSpec.position + applyDirectStylingSpec.length) {
        applyDirectStylingSpec.length += 2;
      }
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[applyDirectStylingSpec]};
  }
  function transformAddAnnotationInsertText(addAnnotationSpec, insertTextSpec) {
    if (insertTextSpec.position <= addAnnotationSpec.position) {
      addAnnotationSpec.position += insertTextSpec.text.length;
    } else {
      if (addAnnotationSpec.length !== undefined) {
        if (insertTextSpec.position <= addAnnotationSpec.position + addAnnotationSpec.length) {
          addAnnotationSpec.length += insertTextSpec.text.length;
        }
      }
      insertTextSpec.position += 2;
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[insertTextSpec]};
  }
  function transformAddAnnotationMergeParagraph(addAnnotationSpec, mergeParagraphSpec) {
    if (mergeParagraphSpec.sourceStartPosition <= addAnnotationSpec.position) {
      addAnnotationSpec.position -= 1;
    } else {
      if (addAnnotationSpec.length !== undefined) {
        if (mergeParagraphSpec.sourceStartPosition <= addAnnotationSpec.position + addAnnotationSpec.length) {
          addAnnotationSpec.length -= 1;
        }
      }
      mergeParagraphSpec.sourceStartPosition += 2;
      if (addAnnotationSpec.position < mergeParagraphSpec.destinationStartPosition) {
        mergeParagraphSpec.destinationStartPosition += 2;
      }
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[mergeParagraphSpec]};
  }
  function transformAddAnnotationMoveCursor(addAnnotationSpec, moveCursorSpec) {
    var isMoveCursorSpecRangeInverted = invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec);
    if (addAnnotationSpec.position < moveCursorSpec.position) {
      moveCursorSpec.position += 2;
    } else {
      if (addAnnotationSpec.position < moveCursorSpec.position + moveCursorSpec.length) {
        moveCursorSpec.length += 2;
      }
    }
    if (isMoveCursorSpecRangeInverted) {
      invertMoveCursorSpecRange(moveCursorSpec);
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[moveCursorSpec]};
  }
  function transformAddAnnotationRemoveAnnotation(addAnnotationSpec, removeAnnotationSpec) {
    if (addAnnotationSpec.position < removeAnnotationSpec.position) {
      if (removeAnnotationSpec.position < addAnnotationSpec.position + addAnnotationSpec.length) {
        addAnnotationSpec.length -= removeAnnotationSpec.length + 2;
      }
      removeAnnotationSpec.position += 2;
    } else {
      addAnnotationSpec.position -= removeAnnotationSpec.length + 2;
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[removeAnnotationSpec]};
  }
  function transformAddAnnotationRemoveText(addAnnotationSpec, removeTextSpec) {
    var removeTextSpecPosition = removeTextSpec.position, removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length, annotationSpecEnd, helperOpspec, addAnnotationSpecResult = [addAnnotationSpec], removeTextSpecResult = [removeTextSpec];
    if (addAnnotationSpec.position <= removeTextSpec.position) {
      removeTextSpec.position += 2;
    } else {
      if (addAnnotationSpec.position < removeTextSpecEnd) {
        removeTextSpec.length = addAnnotationSpec.position - removeTextSpec.position;
        helperOpspec = {optype:"RemoveText", memberid:removeTextSpec.memberid, timestamp:removeTextSpec.timestamp, position:addAnnotationSpec.position + 2, length:removeTextSpecEnd - addAnnotationSpec.position};
        removeTextSpecResult.unshift(helperOpspec);
      }
    }
    if (removeTextSpec.position + removeTextSpec.length <= addAnnotationSpec.position) {
      addAnnotationSpec.position -= removeTextSpec.length;
      if (addAnnotationSpec.length !== undefined && helperOpspec) {
        if (helperOpspec.length >= addAnnotationSpec.length) {
          addAnnotationSpec.length = 0;
        } else {
          addAnnotationSpec.length -= helperOpspec.length;
        }
      }
    } else {
      if (addAnnotationSpec.length !== undefined) {
        annotationSpecEnd = addAnnotationSpec.position + addAnnotationSpec.length;
        if (removeTextSpecEnd <= annotationSpecEnd) {
          addAnnotationSpec.length -= removeTextSpec.length;
        } else {
          if (removeTextSpecPosition < annotationSpecEnd) {
            addAnnotationSpec.length = removeTextSpecPosition - addAnnotationSpec.position;
          }
        }
      }
    }
    return {opSpecsA:addAnnotationSpecResult, opSpecsB:removeTextSpecResult};
  }
  function transformAddAnnotationSetParagraphStyle(addAnnotationSpec, setParagraphStyleSpec) {
    if (addAnnotationSpec.position < setParagraphStyleSpec.position) {
      setParagraphStyleSpec.position += 2;
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[setParagraphStyleSpec]};
  }
  function transformAddAnnotationSplitParagraph(addAnnotationSpec, splitParagraphSpec) {
    if (addAnnotationSpec.position < splitParagraphSpec.sourceParagraphPosition) {
      splitParagraphSpec.sourceParagraphPosition += 2;
    }
    if (splitParagraphSpec.position <= addAnnotationSpec.position) {
      addAnnotationSpec.position += 1;
    } else {
      if (addAnnotationSpec.length !== undefined) {
        if (splitParagraphSpec.position <= addAnnotationSpec.position + addAnnotationSpec.length) {
          addAnnotationSpec.length += 1;
        }
      }
      splitParagraphSpec.position += 2;
    }
    return {opSpecsA:[addAnnotationSpec], opSpecsB:[splitParagraphSpec]};
  }
  function transformAddStyleRemoveStyle(addStyleSpec, removeStyleSpec) {
    var setAttributes, helperOpspec, addStyleSpecResult = [addStyleSpec], removeStyleSpecResult = [removeStyleSpec];
    if (addStyleSpec.styleFamily === removeStyleSpec.styleFamily) {
      setAttributes = getStyleReferencingAttributes(addStyleSpec.setProperties, removeStyleSpec.styleName);
      if (setAttributes.length > 0) {
        helperOpspec = {optype:"UpdateParagraphStyle", memberid:removeStyleSpec.memberid, timestamp:removeStyleSpec.timestamp, styleName:addStyleSpec.styleName, removedProperties:{attributes:setAttributes.join(",")}};
        removeStyleSpecResult.unshift(helperOpspec);
      }
      dropStyleReferencingAttributes(addStyleSpec.setProperties, removeStyleSpec.styleName);
    }
    return {opSpecsA:addStyleSpecResult, opSpecsB:removeStyleSpecResult};
  }
  function transformApplyDirectStylingApplyDirectStyling(applyDirectStylingSpecA, applyDirectStylingSpecB, hasAPriority) {
    var majorSpec, minorSpec, majorSpecResult, minorSpecResult, majorSpecEnd, minorSpecEnd, dropResult, originalMajorSpec, originalMinorSpec, helperOpspecBefore, helperOpspecAfter, applyDirectStylingSpecAResult = [applyDirectStylingSpecA], applyDirectStylingSpecBResult = [applyDirectStylingSpecB];
    if (!(applyDirectStylingSpecA.position + applyDirectStylingSpecA.length <= applyDirectStylingSpecB.position || applyDirectStylingSpecA.position >= applyDirectStylingSpecB.position + applyDirectStylingSpecB.length)) {
      majorSpec = hasAPriority ? applyDirectStylingSpecA : applyDirectStylingSpecB;
      minorSpec = hasAPriority ? applyDirectStylingSpecB : applyDirectStylingSpecA;
      if (applyDirectStylingSpecA.position !== applyDirectStylingSpecB.position || applyDirectStylingSpecA.length !== applyDirectStylingSpecB.length) {
        originalMajorSpec = cloneOpspec(majorSpec);
        originalMinorSpec = cloneOpspec(minorSpec);
      }
      dropResult = dropOverruledAndUnneededProperties(minorSpec.setProperties, null, majorSpec.setProperties, null, "style:text-properties");
      if (dropResult.majorChanged || dropResult.minorChanged) {
        majorSpecResult = [];
        minorSpecResult = [];
        majorSpecEnd = majorSpec.position + majorSpec.length;
        minorSpecEnd = minorSpec.position + minorSpec.length;
        if (minorSpec.position < majorSpec.position) {
          if (dropResult.minorChanged) {
            helperOpspecBefore = cloneOpspec(originalMinorSpec);
            helperOpspecBefore.length = majorSpec.position - minorSpec.position;
            minorSpecResult.push(helperOpspecBefore);
            minorSpec.position = majorSpec.position;
            minorSpec.length = minorSpecEnd - minorSpec.position;
          }
        } else {
          if (majorSpec.position < minorSpec.position) {
            if (dropResult.majorChanged) {
              helperOpspecBefore = cloneOpspec(originalMajorSpec);
              helperOpspecBefore.length = minorSpec.position - majorSpec.position;
              majorSpecResult.push(helperOpspecBefore);
              majorSpec.position = minorSpec.position;
              majorSpec.length = majorSpecEnd - majorSpec.position;
            }
          }
        }
        if (minorSpecEnd > majorSpecEnd) {
          if (dropResult.minorChanged) {
            helperOpspecAfter = originalMinorSpec;
            helperOpspecAfter.position = majorSpecEnd;
            helperOpspecAfter.length = minorSpecEnd - majorSpecEnd;
            minorSpecResult.push(helperOpspecAfter);
            minorSpec.length = majorSpecEnd - minorSpec.position;
          }
        } else {
          if (majorSpecEnd > minorSpecEnd) {
            if (dropResult.majorChanged) {
              helperOpspecAfter = originalMajorSpec;
              helperOpspecAfter.position = minorSpecEnd;
              helperOpspecAfter.length = majorSpecEnd - minorSpecEnd;
              majorSpecResult.push(helperOpspecAfter);
              majorSpec.length = minorSpecEnd - majorSpec.position;
            }
          }
        }
        if (majorSpec.setProperties && hasProperties(majorSpec.setProperties)) {
          majorSpecResult.push(majorSpec);
        }
        if (minorSpec.setProperties && hasProperties(minorSpec.setProperties)) {
          minorSpecResult.push(minorSpec);
        }
        if (hasAPriority) {
          applyDirectStylingSpecAResult = majorSpecResult;
          applyDirectStylingSpecBResult = minorSpecResult;
        } else {
          applyDirectStylingSpecAResult = minorSpecResult;
          applyDirectStylingSpecBResult = majorSpecResult;
        }
      }
    }
    return {opSpecsA:applyDirectStylingSpecAResult, opSpecsB:applyDirectStylingSpecBResult};
  }
  function transformApplyDirectStylingInsertText(applyDirectStylingSpec, insertTextSpec) {
    if (insertTextSpec.position <= applyDirectStylingSpec.position) {
      applyDirectStylingSpec.position += insertTextSpec.text.length;
    } else {
      if (insertTextSpec.position <= applyDirectStylingSpec.position + applyDirectStylingSpec.length) {
        applyDirectStylingSpec.length += insertTextSpec.text.length;
      }
    }
    return {opSpecsA:[applyDirectStylingSpec], opSpecsB:[insertTextSpec]};
  }
  function transformApplyDirectStylingMergeParagraph(applyDirectStylingSpec, mergeParagraphSpec) {
    var pointA = applyDirectStylingSpec.position, pointB = applyDirectStylingSpec.position + applyDirectStylingSpec.length;
    if (pointA >= mergeParagraphSpec.sourceStartPosition) {
      pointA -= 1;
    }
    if (pointB >= mergeParagraphSpec.sourceStartPosition) {
      pointB -= 1;
    }
    applyDirectStylingSpec.position = pointA;
    applyDirectStylingSpec.length = pointB - pointA;
    return {opSpecsA:[applyDirectStylingSpec], opSpecsB:[mergeParagraphSpec]};
  }
  function transformApplyDirectStylingRemoveAnnotation(applyDirectStylingSpec, removeAnnotationSpec) {
    var pointA = applyDirectStylingSpec.position, pointB = applyDirectStylingSpec.position + applyDirectStylingSpec.length, removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, applyDirectStylingSpecResult = [applyDirectStylingSpec], removeAnnotationSpecResult = [removeAnnotationSpec];
    if (removeAnnotationSpec.position <= pointA && pointB <= removeAnnotationEnd) {
      applyDirectStylingSpecResult = [];
    } else {
      if (removeAnnotationEnd < pointA) {
        pointA -= removeAnnotationSpec.length + 2;
      }
      if (removeAnnotationEnd < pointB) {
        pointB -= removeAnnotationSpec.length + 2;
      }
      applyDirectStylingSpec.position = pointA;
      applyDirectStylingSpec.length = pointB - pointA;
    }
    return {opSpecsA:applyDirectStylingSpecResult, opSpecsB:removeAnnotationSpecResult};
  }
  function transformApplyDirectStylingRemoveText(applyDirectStylingSpec, removeTextSpec) {
    var applyDirectStylingSpecEnd = applyDirectStylingSpec.position + applyDirectStylingSpec.length, removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length, applyDirectStylingSpecResult = [applyDirectStylingSpec], removeTextSpecResult = [removeTextSpec];
    if (removeTextSpecEnd <= applyDirectStylingSpec.position) {
      applyDirectStylingSpec.position -= removeTextSpec.length;
    } else {
      if (removeTextSpec.position < applyDirectStylingSpecEnd) {
        if (applyDirectStylingSpec.position < removeTextSpec.position) {
          if (removeTextSpecEnd < applyDirectStylingSpecEnd) {
            applyDirectStylingSpec.length -= removeTextSpec.length;
          } else {
            applyDirectStylingSpec.length = removeTextSpec.position - applyDirectStylingSpec.position;
          }
        } else {
          applyDirectStylingSpec.position = removeTextSpec.position;
          if (removeTextSpecEnd < applyDirectStylingSpecEnd) {
            applyDirectStylingSpec.length = applyDirectStylingSpecEnd - removeTextSpecEnd;
          } else {
            applyDirectStylingSpecResult = [];
          }
        }
      }
    }
    return {opSpecsA:applyDirectStylingSpecResult, opSpecsB:removeTextSpecResult};
  }
  function transformApplyDirectStylingSplitParagraph(applyDirectStylingSpec, splitParagraphSpec) {
    if (splitParagraphSpec.position < applyDirectStylingSpec.position) {
      applyDirectStylingSpec.position += 1;
    } else {
      if (splitParagraphSpec.position < applyDirectStylingSpec.position + applyDirectStylingSpec.length) {
        applyDirectStylingSpec.length += 1;
      }
    }
    return {opSpecsA:[applyDirectStylingSpec], opSpecsB:[splitParagraphSpec]};
  }
  function transformInsertTextInsertText(insertTextSpecA, insertTextSpecB, hasAPriority) {
    if (insertTextSpecA.position < insertTextSpecB.position) {
      insertTextSpecB.position += insertTextSpecA.text.length;
    } else {
      if (insertTextSpecA.position > insertTextSpecB.position) {
        insertTextSpecA.position += insertTextSpecB.text.length;
      } else {
        if (hasAPriority) {
          insertTextSpecB.position += insertTextSpecA.text.length;
        } else {
          insertTextSpecA.position += insertTextSpecB.text.length;
        }
      }
    }
    return {opSpecsA:[insertTextSpecA], opSpecsB:[insertTextSpecB]};
  }
  function transformInsertTextMergeParagraph(insertTextSpec, mergeParagraphSpec) {
    if (insertTextSpec.position >= mergeParagraphSpec.sourceStartPosition) {
      insertTextSpec.position -= 1;
    } else {
      if (insertTextSpec.position < mergeParagraphSpec.sourceStartPosition) {
        mergeParagraphSpec.sourceStartPosition += insertTextSpec.text.length;
      }
      if (insertTextSpec.position < mergeParagraphSpec.destinationStartPosition) {
        mergeParagraphSpec.destinationStartPosition += insertTextSpec.text.length;
      }
    }
    return {opSpecsA:[insertTextSpec], opSpecsB:[mergeParagraphSpec]};
  }
  function transformInsertTextMoveCursor(insertTextSpec, moveCursorSpec) {
    var isMoveCursorSpecRangeInverted = invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec);
    if (insertTextSpec.position < moveCursorSpec.position) {
      moveCursorSpec.position += insertTextSpec.text.length;
    } else {
      if (insertTextSpec.position < moveCursorSpec.position + moveCursorSpec.length) {
        moveCursorSpec.length += insertTextSpec.text.length;
      }
    }
    if (isMoveCursorSpecRangeInverted) {
      invertMoveCursorSpecRange(moveCursorSpec);
    }
    return {opSpecsA:[insertTextSpec], opSpecsB:[moveCursorSpec]};
  }
  function transformInsertTextRemoveAnnotation(insertTextSpec, removeAnnotationSpec) {
    var insertTextSpecPosition = insertTextSpec.position, removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, insertTextSpecResult = [insertTextSpec], removeAnnotationSpecResult = [removeAnnotationSpec];
    if (removeAnnotationSpec.position <= insertTextSpecPosition && insertTextSpecPosition <= removeAnnotationEnd) {
      insertTextSpecResult = [];
      removeAnnotationSpec.length += insertTextSpec.text.length;
    } else {
      if (removeAnnotationEnd < insertTextSpec.position) {
        insertTextSpec.position -= removeAnnotationSpec.length + 2;
      } else {
        removeAnnotationSpec.position += insertTextSpec.text.length;
      }
    }
    return {opSpecsA:insertTextSpecResult, opSpecsB:removeAnnotationSpecResult};
  }
  function transformInsertTextRemoveText(insertTextSpec, removeTextSpec) {
    var helperOpspec, removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length, insertTextSpecResult = [insertTextSpec], removeTextSpecResult = [removeTextSpec];
    if (removeTextSpecEnd <= insertTextSpec.position) {
      insertTextSpec.position -= removeTextSpec.length;
    } else {
      if (insertTextSpec.position <= removeTextSpec.position) {
        removeTextSpec.position += insertTextSpec.text.length;
      } else {
        removeTextSpec.length = insertTextSpec.position - removeTextSpec.position;
        helperOpspec = {optype:"RemoveText", memberid:removeTextSpec.memberid, timestamp:removeTextSpec.timestamp, position:insertTextSpec.position + insertTextSpec.text.length, length:removeTextSpecEnd - insertTextSpec.position};
        removeTextSpecResult.unshift(helperOpspec);
        insertTextSpec.position = removeTextSpec.position;
      }
    }
    return {opSpecsA:insertTextSpecResult, opSpecsB:removeTextSpecResult};
  }
  function transformInsertTextSetParagraphStyle(insertTextSpec, setParagraphStyleSpec) {
    if (setParagraphStyleSpec.position > insertTextSpec.position) {
      setParagraphStyleSpec.position += insertTextSpec.text.length;
    }
    return {opSpecsA:[insertTextSpec], opSpecsB:[setParagraphStyleSpec]};
  }
  function transformInsertTextSplitParagraph(insertTextSpec, splitParagraphSpec) {
    if (insertTextSpec.position < splitParagraphSpec.sourceParagraphPosition) {
      splitParagraphSpec.sourceParagraphPosition += insertTextSpec.text.length;
    }
    if (insertTextSpec.position <= splitParagraphSpec.position) {
      splitParagraphSpec.position += insertTextSpec.text.length;
    } else {
      insertTextSpec.position += 1;
    }
    return {opSpecsA:[insertTextSpec], opSpecsB:[splitParagraphSpec]};
  }
  function transformMergeParagraphMergeParagraph(mergeParagraphSpecA, mergeParagraphSpecB, hasAPriority) {
    var specsForB = [mergeParagraphSpecA], specsForA = [mergeParagraphSpecB], priorityOp, styleParagraphFixup, moveCursorA, moveCursorB;
    if (mergeParagraphSpecA.destinationStartPosition === mergeParagraphSpecB.destinationStartPosition) {
      specsForB = [];
      specsForA = [];
      if (mergeParagraphSpecA.moveCursor) {
        moveCursorA = {optype:"MoveCursor", memberid:mergeParagraphSpecA.memberid, timestamp:mergeParagraphSpecA.timestamp, position:mergeParagraphSpecA.sourceStartPosition - 1};
        specsForB.push(moveCursorA);
      }
      if (mergeParagraphSpecB.moveCursor) {
        moveCursorB = {optype:"MoveCursor", memberid:mergeParagraphSpecB.memberid, timestamp:mergeParagraphSpecB.timestamp, position:mergeParagraphSpecB.sourceStartPosition - 1};
        specsForA.push(moveCursorB);
      }
      priorityOp = hasAPriority ? mergeParagraphSpecA : mergeParagraphSpecB;
      styleParagraphFixup = {optype:"SetParagraphStyle", memberid:priorityOp.memberid, timestamp:priorityOp.timestamp, position:priorityOp.destinationStartPosition, styleName:priorityOp.paragraphStyleName};
      if (hasAPriority) {
        specsForB.push(styleParagraphFixup);
      } else {
        specsForA.push(styleParagraphFixup);
      }
    } else {
      if (mergeParagraphSpecB.sourceStartPosition === mergeParagraphSpecA.destinationStartPosition) {
        mergeParagraphSpecA.destinationStartPosition = mergeParagraphSpecB.destinationStartPosition;
        mergeParagraphSpecA.sourceStartPosition -= 1;
        mergeParagraphSpecA.paragraphStyleName = mergeParagraphSpecB.paragraphStyleName;
      } else {
        if (mergeParagraphSpecA.sourceStartPosition === mergeParagraphSpecB.destinationStartPosition) {
          mergeParagraphSpecB.destinationStartPosition = mergeParagraphSpecA.destinationStartPosition;
          mergeParagraphSpecB.sourceStartPosition -= 1;
          mergeParagraphSpecB.paragraphStyleName = mergeParagraphSpecA.paragraphStyleName;
        } else {
          if (mergeParagraphSpecA.destinationStartPosition < mergeParagraphSpecB.destinationStartPosition) {
            mergeParagraphSpecB.destinationStartPosition -= 1;
            mergeParagraphSpecB.sourceStartPosition -= 1;
          } else {
            mergeParagraphSpecA.destinationStartPosition -= 1;
            mergeParagraphSpecA.sourceStartPosition -= 1;
          }
        }
      }
    }
    return {opSpecsA:specsForB, opSpecsB:specsForA};
  }
  function transformMergeParagraphMoveCursor(mergeParagraphSpec, moveCursorSpec) {
    var pointA = moveCursorSpec.position, pointB = moveCursorSpec.position + moveCursorSpec.length, start = Math.min(pointA, pointB), end = Math.max(pointA, pointB);
    if (start >= mergeParagraphSpec.sourceStartPosition) {
      start -= 1;
    }
    if (end >= mergeParagraphSpec.sourceStartPosition) {
      end -= 1;
    }
    if (moveCursorSpec.length >= 0) {
      moveCursorSpec.position = start;
      moveCursorSpec.length = end - start;
    } else {
      moveCursorSpec.position = end;
      moveCursorSpec.length = start - end;
    }
    return {opSpecsA:[mergeParagraphSpec], opSpecsB:[moveCursorSpec]};
  }
  function transformMergeParagraphRemoveAnnotation(mergeParagraphSpec, removeAnnotationSpec) {
    var removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, mergeParagraphSpecResult = [mergeParagraphSpec], removeAnnotationSpecResult = [removeAnnotationSpec];
    if (removeAnnotationSpec.position <= mergeParagraphSpec.destinationStartPosition && mergeParagraphSpec.sourceStartPosition <= removeAnnotationEnd) {
      mergeParagraphSpecResult = [];
      removeAnnotationSpec.length -= 1;
    } else {
      if (mergeParagraphSpec.sourceStartPosition < removeAnnotationSpec.position) {
        removeAnnotationSpec.position -= 1;
      } else {
        if (removeAnnotationEnd < mergeParagraphSpec.destinationStartPosition) {
          mergeParagraphSpec.destinationStartPosition -= removeAnnotationSpec.length + 2;
        }
        if (removeAnnotationEnd < mergeParagraphSpec.sourceStartPosition) {
          mergeParagraphSpec.sourceStartPosition -= removeAnnotationSpec.length + 2;
        }
      }
    }
    return {opSpecsA:mergeParagraphSpecResult, opSpecsB:removeAnnotationSpecResult};
  }
  function transformMergeParagraphRemoveText(mergeParagraphSpec, removeTextSpec) {
    if (removeTextSpec.position >= mergeParagraphSpec.sourceStartPosition) {
      removeTextSpec.position -= 1;
    } else {
      if (removeTextSpec.position < mergeParagraphSpec.destinationStartPosition) {
        mergeParagraphSpec.destinationStartPosition -= removeTextSpec.length;
      }
      if (removeTextSpec.position < mergeParagraphSpec.sourceStartPosition) {
        mergeParagraphSpec.sourceStartPosition -= removeTextSpec.length;
      }
    }
    return {opSpecsA:[mergeParagraphSpec], opSpecsB:[removeTextSpec]};
  }
  function transformMergeParagraphSetParagraphStyle(mergeParagraphSpec, setParagraphStyleSpec) {
    var opSpecsA = [mergeParagraphSpec], opSpecsB = [setParagraphStyleSpec];
    if (setParagraphStyleSpec.position > mergeParagraphSpec.sourceStartPosition) {
      setParagraphStyleSpec.position -= 1;
    } else {
      if (setParagraphStyleSpec.position === mergeParagraphSpec.destinationStartPosition || setParagraphStyleSpec.position === mergeParagraphSpec.sourceStartPosition) {
        setParagraphStyleSpec.position = mergeParagraphSpec.destinationStartPosition;
        mergeParagraphSpec.paragraphStyleName = setParagraphStyleSpec.styleName;
      }
    }
    return {opSpecsA:opSpecsA, opSpecsB:opSpecsB};
  }
  function transformMergeParagraphSplitParagraph(mergeParagraphSpec, splitParagraphSpec) {
    var styleSplitParagraph, moveCursorOp, opSpecsA = [mergeParagraphSpec], opSpecsB = [splitParagraphSpec];
    if (splitParagraphSpec.position < mergeParagraphSpec.destinationStartPosition) {
      mergeParagraphSpec.destinationStartPosition += 1;
      mergeParagraphSpec.sourceStartPosition += 1;
    } else {
      if (splitParagraphSpec.position >= mergeParagraphSpec.destinationStartPosition && splitParagraphSpec.position < mergeParagraphSpec.sourceStartPosition) {
        splitParagraphSpec.paragraphStyleName = mergeParagraphSpec.paragraphStyleName;
        styleSplitParagraph = {optype:"SetParagraphStyle", memberid:mergeParagraphSpec.memberid, timestamp:mergeParagraphSpec.timestamp, position:mergeParagraphSpec.destinationStartPosition, styleName:mergeParagraphSpec.paragraphStyleName};
        opSpecsA.push(styleSplitParagraph);
        if (splitParagraphSpec.position === mergeParagraphSpec.sourceStartPosition - 1 && mergeParagraphSpec.moveCursor) {
          moveCursorOp = {optype:"MoveCursor", memberid:mergeParagraphSpec.memberid, timestamp:mergeParagraphSpec.timestamp, position:splitParagraphSpec.position, length:0};
          opSpecsA.push(moveCursorOp);
        }
        mergeParagraphSpec.destinationStartPosition = splitParagraphSpec.position + 1;
        mergeParagraphSpec.sourceStartPosition += 1;
      } else {
        if (splitParagraphSpec.position >= mergeParagraphSpec.sourceStartPosition) {
          splitParagraphSpec.position -= 1;
          splitParagraphSpec.sourceParagraphPosition -= 1;
        }
      }
    }
    return {opSpecsA:opSpecsA, opSpecsB:opSpecsB};
  }
  function transformUpdateParagraphStyleUpdateParagraphStyle(updateParagraphStyleSpecA, updateParagraphStyleSpecB, hasAPriority) {
    var majorSpec, minorSpec, updateParagraphStyleSpecAResult = [updateParagraphStyleSpecA], updateParagraphStyleSpecBResult = [updateParagraphStyleSpecB];
    if (updateParagraphStyleSpecA.styleName === updateParagraphStyleSpecB.styleName) {
      majorSpec = hasAPriority ? updateParagraphStyleSpecA : updateParagraphStyleSpecB;
      minorSpec = hasAPriority ? updateParagraphStyleSpecB : updateParagraphStyleSpecA;
      dropOverruledAndUnneededProperties(minorSpec.setProperties, minorSpec.removedProperties, majorSpec.setProperties, majorSpec.removedProperties, "style:paragraph-properties");
      dropOverruledAndUnneededProperties(minorSpec.setProperties, minorSpec.removedProperties, majorSpec.setProperties, majorSpec.removedProperties, "style:text-properties");
      dropOverruledAndUnneededAttributes(minorSpec.setProperties || null, minorSpec.removedProperties || null, majorSpec.setProperties || null, majorSpec.removedProperties || null);
      if (!(majorSpec.setProperties && hasProperties(majorSpec.setProperties)) && !(majorSpec.removedProperties && hasRemovedProperties(majorSpec.removedProperties))) {
        if (hasAPriority) {
          updateParagraphStyleSpecAResult = [];
        } else {
          updateParagraphStyleSpecBResult = [];
        }
      }
      if (!(minorSpec.setProperties && hasProperties(minorSpec.setProperties)) && !(minorSpec.removedProperties && hasRemovedProperties(minorSpec.removedProperties))) {
        if (hasAPriority) {
          updateParagraphStyleSpecBResult = [];
        } else {
          updateParagraphStyleSpecAResult = [];
        }
      }
    }
    return {opSpecsA:updateParagraphStyleSpecAResult, opSpecsB:updateParagraphStyleSpecBResult};
  }
  function transformUpdateMetadataUpdateMetadata(updateMetadataSpecA, updateMetadataSpecB, hasAPriority) {
    var majorSpec, minorSpec, updateMetadataSpecAResult = [updateMetadataSpecA], updateMetadataSpecBResult = [updateMetadataSpecB];
    majorSpec = hasAPriority ? updateMetadataSpecA : updateMetadataSpecB;
    minorSpec = hasAPriority ? updateMetadataSpecB : updateMetadataSpecA;
    dropOverruledAndUnneededAttributes(minorSpec.setProperties || null, minorSpec.removedProperties || null, majorSpec.setProperties || null, majorSpec.removedProperties || null);
    if (!(majorSpec.setProperties && hasProperties(majorSpec.setProperties)) && !(majorSpec.removedProperties && hasRemovedProperties(majorSpec.removedProperties))) {
      if (hasAPriority) {
        updateMetadataSpecAResult = [];
      } else {
        updateMetadataSpecBResult = [];
      }
    }
    if (!(minorSpec.setProperties && hasProperties(minorSpec.setProperties)) && !(minorSpec.removedProperties && hasRemovedProperties(minorSpec.removedProperties))) {
      if (hasAPriority) {
        updateMetadataSpecBResult = [];
      } else {
        updateMetadataSpecAResult = [];
      }
    }
    return {opSpecsA:updateMetadataSpecAResult, opSpecsB:updateMetadataSpecBResult};
  }
  function transformSetParagraphStyleSetParagraphStyle(setParagraphStyleSpecA, setParagraphStyleSpecB, hasAPriority) {
    if (setParagraphStyleSpecA.position === setParagraphStyleSpecB.position) {
      if (hasAPriority) {
        setParagraphStyleSpecB.styleName = setParagraphStyleSpecA.styleName;
      } else {
        setParagraphStyleSpecA.styleName = setParagraphStyleSpecB.styleName;
      }
    }
    return {opSpecsA:[setParagraphStyleSpecA], opSpecsB:[setParagraphStyleSpecB]};
  }
  function transformSetParagraphStyleSplitParagraph(setParagraphStyleSpec, splitParagraphSpec) {
    var opSpecsA = [setParagraphStyleSpec], opSpecsB = [splitParagraphSpec], setParagraphClone;
    if (setParagraphStyleSpec.position > splitParagraphSpec.position) {
      setParagraphStyleSpec.position += 1;
    } else {
      if (setParagraphStyleSpec.position === splitParagraphSpec.sourceParagraphPosition) {
        splitParagraphSpec.paragraphStyleName = setParagraphStyleSpec.styleName;
        setParagraphClone = cloneOpspec(setParagraphStyleSpec);
        setParagraphClone.position = splitParagraphSpec.position + 1;
        opSpecsA.push(setParagraphClone);
      }
    }
    return {opSpecsA:opSpecsA, opSpecsB:opSpecsB};
  }
  function transformSplitParagraphSplitParagraph(splitParagraphSpecA, splitParagraphSpecB, hasAPriority) {
    var specABeforeB, specBBeforeA;
    if (splitParagraphSpecA.position < splitParagraphSpecB.position) {
      specABeforeB = true;
    } else {
      if (splitParagraphSpecB.position < splitParagraphSpecA.position) {
        specBBeforeA = true;
      } else {
        if (splitParagraphSpecA.position === splitParagraphSpecB.position) {
          if (hasAPriority) {
            specABeforeB = true;
          } else {
            specBBeforeA = true;
          }
        }
      }
    }
    if (specABeforeB) {
      splitParagraphSpecB.position += 1;
      if (splitParagraphSpecA.position < splitParagraphSpecB.sourceParagraphPosition) {
        splitParagraphSpecB.sourceParagraphPosition += 1;
      } else {
        splitParagraphSpecB.sourceParagraphPosition = splitParagraphSpecA.position + 1;
      }
    } else {
      if (specBBeforeA) {
        splitParagraphSpecA.position += 1;
        if (splitParagraphSpecB.position < splitParagraphSpecB.sourceParagraphPosition) {
          splitParagraphSpecA.sourceParagraphPosition += 1;
        } else {
          splitParagraphSpecA.sourceParagraphPosition = splitParagraphSpecB.position + 1;
        }
      }
    }
    return {opSpecsA:[splitParagraphSpecA], opSpecsB:[splitParagraphSpecB]};
  }
  function transformMoveCursorRemoveAnnotation(moveCursorSpec, removeAnnotationSpec) {
    var isMoveCursorSpecRangeInverted = invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec), moveCursorSpecEnd = moveCursorSpec.position + moveCursorSpec.length, removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length;
    if (removeAnnotationSpec.position <= moveCursorSpec.position && moveCursorSpecEnd <= removeAnnotationEnd) {
      moveCursorSpec.position = removeAnnotationSpec.position - 1;
      moveCursorSpec.length = 0;
    } else {
      if (removeAnnotationEnd < moveCursorSpec.position) {
        moveCursorSpec.position -= removeAnnotationSpec.length + 2;
      } else {
        if (removeAnnotationEnd < moveCursorSpecEnd) {
          moveCursorSpec.length -= removeAnnotationSpec.length + 2;
        }
      }
      if (isMoveCursorSpecRangeInverted) {
        invertMoveCursorSpecRange(moveCursorSpec);
      }
    }
    return {opSpecsA:[moveCursorSpec], opSpecsB:[removeAnnotationSpec]};
  }
  function transformMoveCursorRemoveCursor(moveCursorSpec, removeCursorSpec) {
    var isSameCursorRemoved = moveCursorSpec.memberid === removeCursorSpec.memberid;
    return {opSpecsA:isSameCursorRemoved ? [] : [moveCursorSpec], opSpecsB:[removeCursorSpec]};
  }
  function transformMoveCursorRemoveText(moveCursorSpec, removeTextSpec) {
    var isMoveCursorSpecRangeInverted = invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec), moveCursorSpecEnd = moveCursorSpec.position + moveCursorSpec.length, removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length;
    if (removeTextSpecEnd <= moveCursorSpec.position) {
      moveCursorSpec.position -= removeTextSpec.length;
    } else {
      if (removeTextSpec.position < moveCursorSpecEnd) {
        if (moveCursorSpec.position < removeTextSpec.position) {
          if (removeTextSpecEnd < moveCursorSpecEnd) {
            moveCursorSpec.length -= removeTextSpec.length;
          } else {
            moveCursorSpec.length = removeTextSpec.position - moveCursorSpec.position;
          }
        } else {
          moveCursorSpec.position = removeTextSpec.position;
          if (removeTextSpecEnd < moveCursorSpecEnd) {
            moveCursorSpec.length = moveCursorSpecEnd - removeTextSpecEnd;
          } else {
            moveCursorSpec.length = 0;
          }
        }
      }
    }
    if (isMoveCursorSpecRangeInverted) {
      invertMoveCursorSpecRange(moveCursorSpec);
    }
    return {opSpecsA:[moveCursorSpec], opSpecsB:[removeTextSpec]};
  }
  function transformMoveCursorSplitParagraph(moveCursorSpec, splitParagraphSpec) {
    var isMoveCursorSpecRangeInverted = invertMoveCursorSpecRangeOnNegativeLength(moveCursorSpec);
    if (splitParagraphSpec.position < moveCursorSpec.position) {
      moveCursorSpec.position += 1;
    } else {
      if (splitParagraphSpec.position < moveCursorSpec.position + moveCursorSpec.length) {
        moveCursorSpec.length += 1;
      }
    }
    if (isMoveCursorSpecRangeInverted) {
      invertMoveCursorSpecRange(moveCursorSpec);
    }
    return {opSpecsA:[moveCursorSpec], opSpecsB:[splitParagraphSpec]};
  }
  function transformRemoveAnnotationRemoveAnnotation(removeAnnotationSpecA, removeAnnotationSpecB) {
    var removeAnnotationSpecAResult = [removeAnnotationSpecA], removeAnnotationSpecBResult = [removeAnnotationSpecB];
    if (removeAnnotationSpecA.position === removeAnnotationSpecB.position && removeAnnotationSpecA.length === removeAnnotationSpecB.length) {
      removeAnnotationSpecAResult = [];
      removeAnnotationSpecBResult = [];
    } else {
      if (removeAnnotationSpecA.position < removeAnnotationSpecB.position) {
        removeAnnotationSpecB.position -= removeAnnotationSpecA.length + 2;
      } else {
        removeAnnotationSpecA.position -= removeAnnotationSpecB.length + 2;
      }
    }
    return {opSpecsA:removeAnnotationSpecAResult, opSpecsB:removeAnnotationSpecBResult};
  }
  function transformRemoveAnnotationRemoveText(removeAnnotationSpec, removeTextSpec) {
    var removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length, removeAnnotationSpecResult = [removeAnnotationSpec], removeTextSpecResult = [removeTextSpec];
    if (removeAnnotationSpec.position <= removeTextSpec.position && removeTextSpecEnd <= removeAnnotationEnd) {
      removeTextSpecResult = [];
      removeAnnotationSpec.length -= removeTextSpec.length;
    } else {
      if (removeTextSpecEnd < removeAnnotationSpec.position) {
        removeAnnotationSpec.position -= removeTextSpec.length;
      } else {
        if (removeTextSpec.position < removeAnnotationSpec.position) {
          removeAnnotationSpec.position = removeTextSpec.position + 1;
          removeTextSpec.length -= removeAnnotationSpec.length + 2;
        } else {
          removeTextSpec.position -= removeAnnotationSpec.length + 2;
        }
      }
    }
    return {opSpecsA:removeAnnotationSpecResult, opSpecsB:removeTextSpecResult};
  }
  function transformRemoveAnnotationSetParagraphStyle(removeAnnotationSpec, setParagraphStyleSpec) {
    var setParagraphStyleSpecPosition = setParagraphStyleSpec.position, removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, removeAnnotationSpecResult = [removeAnnotationSpec], setParagraphStyleSpecResult = [setParagraphStyleSpec];
    if (removeAnnotationSpec.position <= setParagraphStyleSpecPosition && setParagraphStyleSpecPosition <= removeAnnotationEnd) {
      setParagraphStyleSpecResult = [];
    } else {
      if (removeAnnotationEnd < setParagraphStyleSpecPosition) {
        setParagraphStyleSpec.position -= removeAnnotationSpec.length + 2;
      }
    }
    return {opSpecsA:removeAnnotationSpecResult, opSpecsB:setParagraphStyleSpecResult};
  }
  function transformRemoveAnnotationSplitParagraph(removeAnnotationSpec, splitParagraphSpec) {
    var splitParagraphSpecPosition = splitParagraphSpec.position, removeAnnotationEnd = removeAnnotationSpec.position + removeAnnotationSpec.length, removeAnnotationSpecResult = [removeAnnotationSpec], splitParagraphSpecResult = [splitParagraphSpec];
    if (removeAnnotationSpec.position <= splitParagraphSpecPosition && splitParagraphSpecPosition <= removeAnnotationEnd) {
      splitParagraphSpecResult = [];
      removeAnnotationSpec.length += 1;
    } else {
      if (removeAnnotationEnd < splitParagraphSpec.sourceParagraphPosition) {
        splitParagraphSpec.sourceParagraphPosition -= removeAnnotationSpec.length + 2;
      }
      if (removeAnnotationEnd < splitParagraphSpecPosition) {
        splitParagraphSpec.position -= removeAnnotationSpec.length + 2;
      } else {
        removeAnnotationSpec.position += 1;
      }
    }
    return {opSpecsA:removeAnnotationSpecResult, opSpecsB:splitParagraphSpecResult};
  }
  function transformRemoveCursorRemoveCursor(removeCursorSpecA, removeCursorSpecB) {
    var isSameMemberid = removeCursorSpecA.memberid === removeCursorSpecB.memberid;
    return {opSpecsA:isSameMemberid ? [] : [removeCursorSpecA], opSpecsB:isSameMemberid ? [] : [removeCursorSpecB]};
  }
  function transformRemoveStyleRemoveStyle(removeStyleSpecA, removeStyleSpecB) {
    var isSameStyle = removeStyleSpecA.styleName === removeStyleSpecB.styleName && removeStyleSpecA.styleFamily === removeStyleSpecB.styleFamily;
    return {opSpecsA:isSameStyle ? [] : [removeStyleSpecA], opSpecsB:isSameStyle ? [] : [removeStyleSpecB]};
  }
  function transformRemoveStyleSetParagraphStyle(removeStyleSpec, setParagraphStyleSpec) {
    var helperOpspec, removeStyleSpecResult = [removeStyleSpec], setParagraphStyleSpecResult = [setParagraphStyleSpec];
    if (removeStyleSpec.styleFamily === "paragraph" && removeStyleSpec.styleName === setParagraphStyleSpec.styleName) {
      helperOpspec = {optype:"SetParagraphStyle", memberid:removeStyleSpec.memberid, timestamp:removeStyleSpec.timestamp, position:setParagraphStyleSpec.position, styleName:""};
      removeStyleSpecResult.unshift(helperOpspec);
      setParagraphStyleSpec.styleName = "";
    }
    return {opSpecsA:removeStyleSpecResult, opSpecsB:setParagraphStyleSpecResult};
  }
  function transformRemoveStyleUpdateParagraphStyle(removeStyleSpec, updateParagraphStyleSpec) {
    var setAttributes, helperOpspec, removeStyleSpecResult = [removeStyleSpec], updateParagraphStyleSpecResult = [updateParagraphStyleSpec];
    if (removeStyleSpec.styleFamily === "paragraph") {
      setAttributes = getStyleReferencingAttributes(updateParagraphStyleSpec.setProperties, removeStyleSpec.styleName);
      if (setAttributes.length > 0) {
        helperOpspec = {optype:"UpdateParagraphStyle", memberid:removeStyleSpec.memberid, timestamp:removeStyleSpec.timestamp, styleName:updateParagraphStyleSpec.styleName, removedProperties:{attributes:setAttributes.join(",")}};
        removeStyleSpecResult.unshift(helperOpspec);
      }
      if (removeStyleSpec.styleName === updateParagraphStyleSpec.styleName) {
        updateParagraphStyleSpecResult = [];
      } else {
        dropStyleReferencingAttributes(updateParagraphStyleSpec.setProperties, removeStyleSpec.styleName);
      }
    }
    return {opSpecsA:removeStyleSpecResult, opSpecsB:updateParagraphStyleSpecResult};
  }
  function transformRemoveTextRemoveText(removeTextSpecA, removeTextSpecB) {
    var removeTextSpecAEnd = removeTextSpecA.position + removeTextSpecA.length, removeTextSpecBEnd = removeTextSpecB.position + removeTextSpecB.length, removeTextSpecAResult = [removeTextSpecA], removeTextSpecBResult = [removeTextSpecB];
    if (removeTextSpecBEnd <= removeTextSpecA.position) {
      removeTextSpecA.position -= removeTextSpecB.length;
    } else {
      if (removeTextSpecAEnd <= removeTextSpecB.position) {
        removeTextSpecB.position -= removeTextSpecA.length;
      } else {
        if (removeTextSpecB.position < removeTextSpecAEnd) {
          if (removeTextSpecA.position < removeTextSpecB.position) {
            if (removeTextSpecBEnd < removeTextSpecAEnd) {
              removeTextSpecA.length = removeTextSpecA.length - removeTextSpecB.length;
            } else {
              removeTextSpecA.length = removeTextSpecB.position - removeTextSpecA.position;
            }
            if (removeTextSpecAEnd < removeTextSpecBEnd) {
              removeTextSpecB.position = removeTextSpecA.position;
              removeTextSpecB.length = removeTextSpecBEnd - removeTextSpecAEnd;
            } else {
              removeTextSpecBResult = [];
            }
          } else {
            if (removeTextSpecAEnd < removeTextSpecBEnd) {
              removeTextSpecB.length = removeTextSpecB.length - removeTextSpecA.length;
            } else {
              if (removeTextSpecB.position < removeTextSpecA.position) {
                removeTextSpecB.length = removeTextSpecA.position - removeTextSpecB.position;
              } else {
                removeTextSpecBResult = [];
              }
            }
            if (removeTextSpecBEnd < removeTextSpecAEnd) {
              removeTextSpecA.position = removeTextSpecB.position;
              removeTextSpecA.length = removeTextSpecAEnd - removeTextSpecBEnd;
            } else {
              removeTextSpecAResult = [];
            }
          }
        }
      }
    }
    return {opSpecsA:removeTextSpecAResult, opSpecsB:removeTextSpecBResult};
  }
  function transformRemoveTextSetParagraphStyle(removeTextSpec, setParagraphStyleSpec) {
    if (removeTextSpec.position < setParagraphStyleSpec.position) {
      setParagraphStyleSpec.position -= removeTextSpec.length;
    }
    return {opSpecsA:[removeTextSpec], opSpecsB:[setParagraphStyleSpec]};
  }
  function transformRemoveTextSplitParagraph(removeTextSpec, splitParagraphSpec) {
    var removeTextSpecEnd = removeTextSpec.position + removeTextSpec.length, helperOpspec, removeTextSpecResult = [removeTextSpec], splitParagraphSpecResult = [splitParagraphSpec];
    if (splitParagraphSpec.position <= removeTextSpec.position) {
      removeTextSpec.position += 1;
    } else {
      if (splitParagraphSpec.position < removeTextSpecEnd) {
        removeTextSpec.length = splitParagraphSpec.position - removeTextSpec.position;
        helperOpspec = {optype:"RemoveText", memberid:removeTextSpec.memberid, timestamp:removeTextSpec.timestamp, position:splitParagraphSpec.position + 1, length:removeTextSpecEnd - splitParagraphSpec.position};
        removeTextSpecResult.unshift(helperOpspec);
      }
    }
    if (removeTextSpec.position + removeTextSpec.length <= splitParagraphSpec.position) {
      splitParagraphSpec.position -= removeTextSpec.length;
    } else {
      if (removeTextSpec.position < splitParagraphSpec.position) {
        splitParagraphSpec.position = removeTextSpec.position;
      }
    }
    if (removeTextSpec.position + removeTextSpec.length < splitParagraphSpec.sourceParagraphPosition) {
      splitParagraphSpec.sourceParagraphPosition -= removeTextSpec.length;
    }
    return {opSpecsA:removeTextSpecResult, opSpecsB:splitParagraphSpecResult};
  }
  function passUnchanged(opSpecA, opSpecB) {
    return {opSpecsA:[opSpecA], opSpecsB:[opSpecB]};
  }
  var transformations;
  transformations = {"AddAnnotation":{"AddAnnotation":transformAddAnnotationAddAnnotation, "AddCursor":passUnchanged, "AddMember":passUnchanged, "AddStyle":passUnchanged, "ApplyDirectStyling":transformAddAnnotationApplyDirectStyling, "InsertText":transformAddAnnotationInsertText, "MergeParagraph":transformAddAnnotationMergeParagraph, "MoveCursor":transformAddAnnotationMoveCursor, "RemoveAnnotation":transformAddAnnotationRemoveAnnotation, "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, 
  "RemoveStyle":passUnchanged, "RemoveText":transformAddAnnotationRemoveText, "SetParagraphStyle":transformAddAnnotationSetParagraphStyle, "SplitParagraph":transformAddAnnotationSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "AddCursor":{"AddCursor":passUnchanged, "AddMember":passUnchanged, "AddStyle":passUnchanged, "ApplyDirectStyling":passUnchanged, "InsertText":passUnchanged, "MergeParagraph":passUnchanged, "MoveCursor":passUnchanged, 
  "RemoveAnnotation":passUnchanged, "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":passUnchanged, "SetParagraphStyle":passUnchanged, "SplitParagraph":passUnchanged, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "AddMember":{"AddStyle":passUnchanged, "ApplyDirectStyling":passUnchanged, "InsertText":passUnchanged, "MergeParagraph":passUnchanged, "MoveCursor":passUnchanged, "RemoveAnnotation":passUnchanged, 
  "RemoveCursor":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":passUnchanged, "SetParagraphStyle":passUnchanged, "SplitParagraph":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "AddStyle":{"AddStyle":passUnchanged, "ApplyDirectStyling":passUnchanged, "InsertText":passUnchanged, "MergeParagraph":passUnchanged, "MoveCursor":passUnchanged, "RemoveAnnotation":passUnchanged, "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, "RemoveStyle":transformAddStyleRemoveStyle, 
  "RemoveText":passUnchanged, "SetParagraphStyle":passUnchanged, "SplitParagraph":passUnchanged, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "ApplyDirectStyling":{"ApplyDirectStyling":transformApplyDirectStylingApplyDirectStyling, "InsertText":transformApplyDirectStylingInsertText, "MergeParagraph":transformApplyDirectStylingMergeParagraph, "MoveCursor":passUnchanged, "RemoveAnnotation":transformApplyDirectStylingRemoveAnnotation, "RemoveCursor":passUnchanged, 
  "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":transformApplyDirectStylingRemoveText, "SetParagraphStyle":passUnchanged, "SplitParagraph":transformApplyDirectStylingSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "InsertText":{"InsertText":transformInsertTextInsertText, "MergeParagraph":transformInsertTextMergeParagraph, "MoveCursor":transformInsertTextMoveCursor, "RemoveAnnotation":transformInsertTextRemoveAnnotation, 
  "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":transformInsertTextRemoveText, "SetParagraphStyle":transformInsertTextSetParagraphStyle, "SplitParagraph":transformInsertTextSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "MergeParagraph":{"MergeParagraph":transformMergeParagraphMergeParagraph, "MoveCursor":transformMergeParagraphMoveCursor, "RemoveAnnotation":transformMergeParagraphRemoveAnnotation, 
  "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":transformMergeParagraphRemoveText, "SetParagraphStyle":transformMergeParagraphSetParagraphStyle, "SplitParagraph":transformMergeParagraphSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "MoveCursor":{"MoveCursor":passUnchanged, "RemoveAnnotation":transformMoveCursorRemoveAnnotation, "RemoveCursor":transformMoveCursorRemoveCursor, 
  "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":transformMoveCursorRemoveText, "SetParagraphStyle":passUnchanged, "SplitParagraph":transformMoveCursorSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "RemoveAnnotation":{"RemoveAnnotation":transformRemoveAnnotationRemoveAnnotation, "RemoveCursor":passUnchanged, "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":transformRemoveAnnotationRemoveText, 
  "SetParagraphStyle":transformRemoveAnnotationSetParagraphStyle, "SplitParagraph":transformRemoveAnnotationSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "RemoveCursor":{"RemoveCursor":transformRemoveCursorRemoveCursor, "RemoveMember":passUnchanged, "RemoveStyle":passUnchanged, "RemoveText":passUnchanged, "SetParagraphStyle":passUnchanged, "SplitParagraph":passUnchanged, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, 
  "UpdateParagraphStyle":passUnchanged}, "RemoveMember":{"RemoveStyle":passUnchanged, "RemoveText":passUnchanged, "SetParagraphStyle":passUnchanged, "SplitParagraph":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "RemoveStyle":{"RemoveStyle":transformRemoveStyleRemoveStyle, "RemoveText":passUnchanged, "SetParagraphStyle":transformRemoveStyleSetParagraphStyle, "SplitParagraph":passUnchanged, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":transformRemoveStyleUpdateParagraphStyle}, 
  "RemoveText":{"RemoveText":transformRemoveTextRemoveText, "SetParagraphStyle":transformRemoveTextSetParagraphStyle, "SplitParagraph":transformRemoveTextSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "SetParagraphStyle":{"SetParagraphStyle":transformSetParagraphStyleSetParagraphStyle, "SplitParagraph":transformSetParagraphStyleSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, 
  "SplitParagraph":{"SplitParagraph":transformSplitParagraphSplitParagraph, "UpdateMember":passUnchanged, "UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "UpdateMember":{"UpdateMetadata":passUnchanged, "UpdateParagraphStyle":passUnchanged}, "UpdateMetadata":{"UpdateMetadata":transformUpdateMetadataUpdateMetadata, "UpdateParagraphStyle":passUnchanged}, "UpdateParagraphStyle":{"UpdateParagraphStyle":transformUpdateParagraphStyleUpdateParagraphStyle}};
  this.passUnchanged = passUnchanged;
  this.extendTransformations = function(moreTransformations) {
    Object.keys(moreTransformations).forEach(function(optypeA) {
      var moreTransformationsOptypeAMap = moreTransformations[optypeA], optypeAMap, isExtendingOptypeAMap = transformations.hasOwnProperty(optypeA);
      runtime.log((isExtendingOptypeAMap ? "Extending" : "Adding") + " map for optypeA: " + optypeA);
      if (!isExtendingOptypeAMap) {
        transformations[optypeA] = {};
      }
      optypeAMap = transformations[optypeA];
      Object.keys(moreTransformationsOptypeAMap).forEach(function(optypeB) {
        var isOverwritingOptypeBEntry = optypeAMap.hasOwnProperty(optypeB);
        runtime.assert(optypeA <= optypeB, "Wrong order:" + optypeA + ", " + optypeB);
        runtime.log("  " + (isOverwritingOptypeBEntry ? "Overwriting" : "Adding") + " entry for optypeB: " + optypeB);
        optypeAMap[optypeB] = moreTransformationsOptypeAMap[optypeB];
      });
    });
  };
  this.transformOpspecVsOpspec = function(opSpecA, opSpecB) {
    var isOptypeAAlphaNumericSmaller = opSpecA.optype <= opSpecB.optype, helper, transformationFunctionMap, transformationFunction, result;
    runtime.log("Crosstransforming:");
    runtime.log(runtime.toJson(opSpecA));
    runtime.log(runtime.toJson(opSpecB));
    if (!isOptypeAAlphaNumericSmaller) {
      helper = opSpecA;
      opSpecA = opSpecB;
      opSpecB = helper;
    }
    transformationFunctionMap = transformations[opSpecA.optype];
    transformationFunction = transformationFunctionMap && transformationFunctionMap[opSpecB.optype];
    if (transformationFunction) {
      result = transformationFunction(opSpecA, opSpecB, !isOptypeAAlphaNumericSmaller);
      if (!isOptypeAAlphaNumericSmaller && result !== null) {
        result = {opSpecsA:result.opSpecsB, opSpecsB:result.opSpecsA};
      }
    } else {
      result = null;
    }
    runtime.log("result:");
    if (result) {
      runtime.log(runtime.toJson(result.opSpecsA));
      runtime.log(runtime.toJson(result.opSpecsB));
    } else {
      runtime.log("null");
    }
    return result;
  };
};
ops.OperationTransformer = function OperationTransformer() {
  var operationTransformMatrix = new ops.OperationTransformMatrix;
  function transformOpVsOp(opSpecA, opSpecB) {
    return operationTransformMatrix.transformOpspecVsOpspec(opSpecA, opSpecB);
  }
  function transformOpListVsOp(opSpecsA, opSpecB) {
    var transformResult, transformListResult, transformedOpspecsA = [], transformedOpspecsB = [];
    while (opSpecsA.length > 0 && opSpecB) {
      transformResult = transformOpVsOp(opSpecsA.shift(), opSpecB);
      if (!transformResult) {
        return null;
      }
      transformedOpspecsA = transformedOpspecsA.concat(transformResult.opSpecsA);
      if (transformResult.opSpecsB.length === 0) {
        transformedOpspecsA = transformedOpspecsA.concat(opSpecsA);
        opSpecB = null;
        break;
      }
      while (transformResult.opSpecsB.length > 1) {
        transformListResult = transformOpListVsOp(opSpecsA, transformResult.opSpecsB.shift());
        if (!transformListResult) {
          return null;
        }
        transformedOpspecsB = transformedOpspecsB.concat(transformListResult.opSpecsB);
        opSpecsA = transformListResult.opSpecsA;
      }
      opSpecB = transformResult.opSpecsB.pop();
    }
    if (opSpecB) {
      transformedOpspecsB.push(opSpecB);
    }
    return {opSpecsA:transformedOpspecsA, opSpecsB:transformedOpspecsB};
  }
  this.getOperationTransformMatrix = function() {
    return operationTransformMatrix;
  };
  this.transform = function(opSpecsA, opSpecsB) {
    var transformResult, transformedOpspecsB = [];
    while (opSpecsB.length > 0) {
      transformResult = transformOpListVsOp(opSpecsA, opSpecsB.shift());
      if (!transformResult) {
        return null;
      }
      opSpecsA = transformResult.opSpecsA;
      transformedOpspecsB = transformedOpspecsB.concat(transformResult.opSpecsB);
    }
    return {opSpecsA:opSpecsA, opSpecsB:transformedOpspecsB};
  };
};
var webodf_css = '@namespace draw url(urn:oasis:names:tc:opendocument:xmlns:drawing:1.0);@namespace fo url(urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0);@namespace office url(urn:oasis:names:tc:opendocument:xmlns:office:1.0);@namespace presentation url(urn:oasis:names:tc:opendocument:xmlns:presentation:1.0);@namespace style url(urn:oasis:names:tc:opendocument:xmlns:style:1.0);@namespace svg url(urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0);@namespace table url(urn:oasis:names:tc:opendocument:xmlns:table:1.0);@namespace text url(urn:oasis:names:tc:opendocument:xmlns:text:1.0);@namespace webodfhelper url(urn:webodf:names:helper);@namespace cursor url(urn:webodf:names:cursor);@namespace editinfo url(urn:webodf:names:editinfo);@namespace annotation url(urn:webodf:names:annotation);@namespace dc url(http://purl.org/dc/elements/1.1/);@namespace svgns url(http://www.w3.org/2000/svg);office|document > *, office|document-content > * {display: none;}office|body, office|document {display: inline-block;position: relative;}text|p, text|h {display: block;padding: 0;margin: 0;line-height: normal;position: relative;}text|p::after, text|h::after {content: "\\200B";white-space: pre;}*[webodfhelper|containsparagraphanchor] {position: relative;}text|s {white-space: pre;}text|tab {display: inline;white-space: pre;}text|tracked-changes {display: none;}office|binary-data {display: none;}office|text {display: block;text-align: left;overflow: visible;word-wrap: break-word;}office|text::selection {background: transparent;}.webodf-virtualSelections *::selection {background: transparent;}.webodf-virtualSelections *::-moz-selection {background: transparent;}office|text * draw|text-box {display: block;border: 1px solid #d3d3d3;}office|text draw|frame {z-index: 1;}office|spreadsheet {display: block;border-collapse: collapse;empty-cells: show;font-family: sans-serif;font-size: 10pt;text-align: left;page-break-inside: avoid;overflow: hidden;}office|presentation {display: inline-block;text-align: left;}#shadowContent {display: inline-block;text-align: left;}draw|page {display: block;position: relative;overflow: hidden;}presentation|notes, presentation|footer-decl, presentation|date-time-decl {display: none;}@media print {draw|page {border: 1pt solid black;page-break-inside: avoid;}presentation|notes {}}office|spreadsheet text|p {border: 0px;padding: 1px;margin: 0px;}office|spreadsheet table|table {margin: 3px;}office|spreadsheet table|table:after {}office|spreadsheet table|table-row {counter-increment: row;}office|spreadsheet table|table-row:before {width: 3em;background: #cccccc;border: 1px solid black;text-align: center;content: counter(row);display: table-cell;}office|spreadsheet table|table-cell {border: 1px solid #cccccc;}table|table {display: table;}draw|frame table|table {width: 100%;height: 100%;background: white;}table|table-header-rows {display: table-header-group;}table|table-row {display: table-row;}table|table-column {display: table-column;}table|table-cell {width: 0.889in;display: table-cell;word-break: break-all;}draw|frame {display: block;}draw|image {display: block;width: 100%;height: 100%;top: 0px;left: 0px;background-repeat: no-repeat;background-size: 100% 100%;-moz-background-size: 100% 100%;}draw|frame > draw|image:nth-of-type(n+2) {display: none;}text|list:before {display: none;content:"";}text|list {display: block;}text|list-item {display: block;}text|number {display:none;}text|a {color: blue;text-decoration: underline;cursor: pointer;}.webodf-inactiveLinks text|a {cursor: text;}text|note-citation {vertical-align: super;font-size: smaller;}text|note-body {display: none;}text|note:hover text|note-citation {background: #dddddd;}text|note:hover text|note-body {display: block;left:1em;max-width: 80%;position: absolute;background: #ffffaa;}text|bibliography-source {display: none;}svg|title, svg|desc {display: none;}video {width: 100%;height: 100%}cursor|anchor {display: none;}cursor|cursor {display: none;}.webodf-caretOverlay {position: absolute;top: 5%;height: 1em;z-index: 10;padding-left: 1px;pointer-events: none;}.webodf-caretOverlay .caret {position: absolute;border-left: 2px solid black;top: 0;bottom: 0;right: 0;}.webodf-caretOverlay .handle {position: absolute;margin-top: 5px;padding-top: 3px;margin-left: auto;margin-right: auto;width: 64px;height: 68px;border-radius: 5px;opacity: 0.3;text-align: center;background-color: black;box-shadow: 0px 0px 5px rgb(90, 90, 90);border: 1px solid black;top: -85px;right: -32px;}.webodf-caretOverlay .handle > img {box-shadow: 0px 0px 5px rgb(90, 90, 90) inset;background-color: rgb(200, 200, 200);border-radius: 5px;border: 2px solid;height: 60px;width: 60px;display: block;margin: auto;}.webodf-caretOverlay .handle.active {opacity: 0.8;}.webodf-caretOverlay .handle:after {content: " ";position: absolute;width: 0px;height: 0px;border-style: solid;border-width: 8.7px 5px 0 5px;border-color: black transparent transparent transparent;top: 100%;left: 43%;}.webodf-caretSizer {display: inline-block;width: 0;visibility: hidden;}#eventTrap {display: block;position: absolute;bottom: 0;left: 0;outline: none;opacity: 0;color: rgba(255, 255, 255, 0);pointer-events: none;white-space: pre;overflow: hidden;}cursor|cursor > #composer {text-decoration: underline;}cursor|cursor[cursor|caret-sizer-active="true"],cursor|cursor[cursor|composing="true"] {display: inline;}editinfo|editinfo {display: inline-block;}.editInfoMarker {position: absolute;width: 10px;height: 100%;left: -20px;opacity: 0.8;top: 0;border-radius: 5px;background-color: transparent;box-shadow: 0px 0px 5px rgba(50, 50, 50, 0.75);}.editInfoMarker:hover {box-shadow: 0px 0px 8px rgba(0, 0, 0, 1);}.editInfoHandle {position: absolute;background-color: black;padding: 5px;border-radius: 5px;opacity: 0.8;box-shadow: 0px 0px 5px rgba(50, 50, 50, 0.75);bottom: 100%;margin-bottom: 10px;z-index: 3;left: -25px;}.editInfoHandle:after {content: " ";position: absolute;width: 0px;height: 0px;border-style: solid;border-width: 8.7px 5px 0 5px;border-color: black transparent transparent transparent;top: 100%;left: 5px;}.editInfo {font-family: sans-serif;font-weight: normal;font-style: normal;text-decoration: none;color: white;width: 100%;height: 12pt;}.editInfoColor {float: left;width: 10pt;height: 10pt;border: 1px solid white;}.editInfoAuthor {float: left;margin-left: 5pt;font-size: 10pt;text-align: left;height: 12pt;line-height: 12pt;}.editInfoTime {float: right;margin-left: 30pt;font-size: 8pt;font-style: italic;color: yellow;height: 12pt;line-height: 12pt;}.annotationWrapper {display: inline;position: relative;}.annotationRemoveButton:before {content: "\u00d7";color: white;padding: 5px;line-height: 1em;}.annotationRemoveButton {width: 20px;height: 20px;border-radius: 10px;background-color: black;box-shadow: 0px 0px 5px rgba(50, 50, 50, 0.75);position: absolute;top: -10px;left: -10px;z-index: 3;text-align: center;font-family: sans-serif;font-style: normal;font-weight: normal;text-decoration: none;font-size: 15px;}.annotationRemoveButton:hover {cursor: pointer;box-shadow: 0px 0px 5px rgba(0, 0, 0, 1);}.annotationNote {width: 4cm;position: absolute;display: inline;z-index: 10;top: 0;}.annotationNote > office|annotation {display: block;text-align: left;}.annotationConnector {position: absolute;display: inline;top: 0;z-index: 2;border-top: 1px dashed brown;}.annotationConnector.angular {-moz-transform-origin: left top;-webkit-transform-origin: left top;-ms-transform-origin: left top;transform-origin: left top;}.annotationConnector.horizontal {left: 0;}.annotationConnector.horizontal:before {content: "";display: inline;position: absolute;width: 0px;height: 0px;border-style: solid;border-width: 8.7px 5px 0 5px;border-color: brown transparent transparent transparent;top: -1px;left: -5px;}office|annotation {width: 100%;height: 100%;display: none;background: rgb(198, 238, 184);background: -moz-linear-gradient(90deg, rgb(198, 238, 184) 30%, rgb(180, 196, 159) 100%);background: -webkit-linear-gradient(90deg, rgb(198, 238, 184) 30%, rgb(180, 196, 159) 100%);background: -o-linear-gradient(90deg, rgb(198, 238, 184) 30%, rgb(180, 196, 159) 100%);background: -ms-linear-gradient(90deg, rgb(198, 238, 184) 30%, rgb(180, 196, 159) 100%);background: linear-gradient(180deg, rgb(198, 238, 184) 30%, rgb(180, 196, 159) 100%);box-shadow: 0 3px 4px -3px #ccc;}office|annotation > dc|creator {display: block;font-size: 10pt;font-weight: normal;font-style: normal;font-family: sans-serif;color: white;background-color: brown;padding: 4px;}office|annotation > dc|date {display: block;font-size: 10pt;font-weight: normal;font-style: normal;font-family: sans-serif;border: 4px solid transparent;color: black;}office|annotation > text|list {display: block;padding: 5px;}office|annotation text|p {font-size: 10pt;color: black;font-weight: normal;font-style: normal;text-decoration: none;font-family: sans-serif;}#annotationsPane {background-color: #EAEAEA;width: 4cm;height: 100%;display: none;position: absolute;outline: 1px solid #ccc;}.webodf-annotationHighlight {background-color: yellow;position: relative;}.webodf-selectionOverlay {position: absolute;pointer-events: none;top: 0;left: 0;top: 0;left: 0;width: 100%;height: 100%;z-index: 15;}.webodf-selectionOverlay > polygon {fill-opacity: 0.3;stroke-opacity: 0.8;stroke-width: 1;fill-rule: evenodd;}.webodf-selectionOverlay > .webodf-draggable {fill-opacity: 0.8;stroke-opacity: 0;stroke-width: 8;pointer-events: all;display: none;-moz-transform-origin: center center;-webkit-transform-origin: center center;-ms-transform-origin: center center;transform-origin: center center;}#imageSelector {display: none;position: absolute;border-style: solid;border-color: black;}#imageSelector > div {width: 5px;height: 5px;display: block;position: absolute;border: 1px solid black;background-color: #ffffff;}#imageSelector > .topLeft {top: -4px;left: -4px;}#imageSelector > .topRight {top: -4px;right: -4px;}#imageSelector > .bottomRight {right: -4px;bottom: -4px;}#imageSelector > .bottomLeft {bottom: -4px;left: -4px;}#imageSelector > .topMiddle {top: -4px;left: 50%;margin-left: -2.5px;}#imageSelector > .rightMiddle {top: 50%;right: -4px;margin-top: -2.5px;}#imageSelector > .bottomMiddle {bottom: -4px;left: 50%;margin-left: -2.5px;}#imageSelector > .leftMiddle {top: 50%;left: -4px;margin-top: -2.5px;}div.webodf-customScrollbars::-webkit-scrollbar{width: 8px;height: 8px;background-color: transparent;}div.webodf-customScrollbars::-webkit-scrollbar-track{background-color: transparent;}div.webodf-customScrollbars::-webkit-scrollbar-thumb{background-color: #444;border-radius: 4px;}.webodf-hyperlinkTooltip {display: none;color: white;background-color: black;border-radius: 5px;box-shadow: 2px 2px 5px gray;padding: 3px;position: absolute;max-width: 210px;text-align: left;word-break: break-all;z-index: 16;}.webodf-hyperlinkTooltipText {display: block;font-weight: bold;}';
/*

 @licstart
JSZip - A Javascript class for generating and reading zip files
<http://stuartk.com/jszip>

(c) 2009-2014 Stuart Knightley <stuart [at] stuartk.com>
Dual licenced under the MIT license or GPLv3. See https://raw.github.com/Stuk/jszip/master/LICENSE.markdown.

JSZip uses the library pako released under the MIT license :
https://github.com/nodeca/pako/blob/master/LICENSE
 @licend
*/
!function(e) {
  var globalScope = typeof window !== "undefined" ? window : typeof global !== "undefined" ? global : {}, externs = globalScope.externs || (globalScope.externs = {});
  externs.JSZip = e();
}(function() {
  var define, module, exports;
  return function e(t, n, r) {
    function s(o, u) {
      if (!n[o]) {
        if (!t[o]) {
          var a = typeof require == "function" && require;
          if (!u && a) {
            return a(o, !0);
          }
          if (i) {
            return i(o, !0);
          }
          throw new Error("Cannot find module '" + o + "'");
        }
        var f = n[o] = {exports:{}};
        t[o][0].call(f.exports, function(e) {
          var n = t[o][1][e];
          return s(n ? n : e);
        }, f, f.exports, e, t, n, r);
      }
      return n[o].exports;
    }
    var i = typeof require == "function" && require;
    for (var o = 0;o < r.length;o++) {
      s(r[o]);
    }
    return s;
  }({1:[function(_dereq_, module, exports) {
    var _keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    exports.encode = function(input, utf8) {
      var output = "";
      var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
      var i = 0;
      while (i < input.length) {
        chr1 = input.charCodeAt(i++);
        chr2 = input.charCodeAt(i++);
        chr3 = input.charCodeAt(i++);
        enc1 = chr1 >> 2;
        enc2 = (chr1 & 3) << 4 | chr2 >> 4;
        enc3 = (chr2 & 15) << 2 | chr3 >> 6;
        enc4 = chr3 & 63;
        if (isNaN(chr2)) {
          enc3 = enc4 = 64;
        } else {
          if (isNaN(chr3)) {
            enc4 = 64;
          }
        }
        output = output + _keyStr.charAt(enc1) + _keyStr.charAt(enc2) + _keyStr.charAt(enc3) + _keyStr.charAt(enc4);
      }
      return output;
    };
    exports.decode = function(input, utf8) {
      var output = "";
      var chr1, chr2, chr3;
      var enc1, enc2, enc3, enc4;
      var i = 0;
      input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
      while (i < input.length) {
        enc1 = _keyStr.indexOf(input.charAt(i++));
        enc2 = _keyStr.indexOf(input.charAt(i++));
        enc3 = _keyStr.indexOf(input.charAt(i++));
        enc4 = _keyStr.indexOf(input.charAt(i++));
        chr1 = enc1 << 2 | enc2 >> 4;
        chr2 = (enc2 & 15) << 4 | enc3 >> 2;
        chr3 = (enc3 & 3) << 6 | enc4;
        output = output + String.fromCharCode(chr1);
        if (enc3 != 64) {
          output = output + String.fromCharCode(chr2);
        }
        if (enc4 != 64) {
          output = output + String.fromCharCode(chr3);
        }
      }
      return output;
    };
  }, {}], 2:[function(_dereq_, module, exports) {
    function CompressedObject() {
      this.compressedSize = 0;
      this.uncompressedSize = 0;
      this.crc32 = 0;
      this.compressionMethod = null;
      this.compressedContent = null;
    }
    CompressedObject.prototype = {getContent:function() {
      return null;
    }, getCompressedContent:function() {
      return null;
    }};
    module.exports = CompressedObject;
  }, {}], 3:[function(_dereq_, module, exports) {
    exports.STORE = {magic:"\x00\x00", compress:function(content) {
      return content;
    }, uncompress:function(content) {
      return content;
    }, compressInputType:null, uncompressInputType:null};
    exports.DEFLATE = _dereq_("./flate");
  }, {"./flate":8}], 4:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    var table = [0, 1996959894, 3993919788, 2567524794, 124634137, 1886057615, 3915621685, 2657392035, 249268274, 2044508324, 3772115230, 2547177864, 162941995, 2125561021, 3887607047, 2428444049, 498536548, 1789927666, 4089016648, 2227061214, 450548861, 1843258603, 4107580753, 2211677639, 325883990, 1684777152, 4251122042, 2321926636, 335633487, 1661365465, 4195302755, 2366115317, 997073096, 1281953886, 3579855332, 2724688242, 1006888145, 1258607687, 3524101629, 2768942443, 901097722, 1119000684, 
    3686517206, 2898065728, 853044451, 1172266101, 3705015759, 2882616665, 651767980, 1373503546, 3369554304, 3218104598, 565507253, 1454621731, 3485111705, 3099436303, 671266974, 1594198024, 3322730930, 2970347812, 795835527, 1483230225, 3244367275, 3060149565, 1994146192, 31158534, 2563907772, 4023717930, 1907459465, 112637215, 2680153253, 3904427059, 2013776290, 251722036, 2517215374, 3775830040, 2137656763, 141376813, 2439277719, 3865271297, 1802195444, 476864866, 2238001368, 4066508878, 1812370925, 
    453092731, 2181625025, 4111451223, 1706088902, 314042704, 2344532202, 4240017532, 1658658271, 366619977, 2362670323, 4224994405, 1303535960, 984961486, 2747007092, 3569037538, 1256170817, 1037604311, 2765210733, 3554079995, 1131014506, 879679996, 2909243462, 3663771856, 1141124467, 855842277, 2852801631, 3708648649, 1342533948, 654459306, 3188396048, 3373015174, 1466479909, 544179635, 3110523913, 3462522015, 1591671054, 702138776, 2966460450, 3352799412, 1504918807, 783551873, 3082640443, 3233442989, 
    3988292384, 2596254646, 62317068, 1957810842, 3939845945, 2647816111, 81470997, 1943803523, 3814918930, 2489596804, 225274430, 2053790376, 3826175755, 2466906013, 167816743, 2097651377, 4027552580, 2265490386, 503444072, 1762050814, 4150417245, 2154129355, 426522225, 1852507879, 4275313526, 2312317920, 282753626, 1742555852, 4189708143, 2394877945, 397917763, 1622183637, 3604390888, 2714866558, 953729732, 1340076626, 3518719985, 2797360999, 1068828381, 1219638859, 3624741850, 2936675148, 906185462, 
    1090812512, 3747672003, 2825379669, 829329135, 1181335161, 3412177804, 3160834842, 628085408, 1382605366, 3423369109, 3138078467, 570562233, 1426400815, 3317316542, 2998733608, 733239954, 1555261956, 3268935591, 3050360625, 752459403, 1541320221, 2607071920, 3965973030, 1969922972, 40735498, 2617837225, 3943577151, 1913087877, 83908371, 2512341634, 3803740692, 2075208622, 213261112, 2463272603, 3855990285, 2094854071, 198958881, 2262029012, 4057260610, 1759359992, 534414190, 2176718541, 4139329115, 
    1873836001, 414664567, 2282248934, 4279200368, 1711684554, 285281116, 2405801727, 4167216745, 1634467795, 376229701, 2685067896, 3608007406, 1308918612, 956543938, 2808555105, 3495958263, 1231636301, 1047427035, 2932959818, 3654703836, 1088359270, 936918E3, 2847714899, 3736837829, 1202900863, 817233897, 3183342108, 3401237130, 1404277552, 615818150, 3134207493, 3453421203, 1423857449, 601450431, 3009837614, 3294710456, 1567103746, 711928724, 3020668471, 3272380065, 1510334235, 755167117];
    module.exports = function crc32(input, crc) {
      if (typeof input === "undefined" || !input.length) {
        return 0;
      }
      var isArray = utils.getTypeOf(input) !== "string";
      if (typeof crc == "undefined") {
        crc = 0;
      }
      var x = 0;
      var y = 0;
      var b = 0;
      crc = crc ^ -1;
      for (var i = 0, iTop = input.length;i < iTop;i++) {
        b = isArray ? input[i] : input.charCodeAt(i);
        y = (crc ^ b) & 255;
        x = table[y];
        crc = crc >>> 8 ^ x;
      }
      return crc ^ -1;
    };
  }, {"./utils":21}], 5:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    function DataReader(data) {
      this.data = null;
      this.length = 0;
      this.index = 0;
    }
    DataReader.prototype = {checkOffset:function(offset) {
      this.checkIndex(this.index + offset);
    }, checkIndex:function(newIndex) {
      if (this.length < newIndex || newIndex < 0) {
        throw new Error("End of data reached (data length = " + this.length + ", asked index = " + newIndex + "). Corrupted zip ?");
      }
    }, setIndex:function(newIndex) {
      this.checkIndex(newIndex);
      this.index = newIndex;
    }, skip:function(n) {
      this.setIndex(this.index + n);
    }, byteAt:function(i) {
    }, readInt:function(size) {
      var result = 0, i;
      this.checkOffset(size);
      for (i = this.index + size - 1;i >= this.index;i--) {
        result = (result << 8) + this.byteAt(i);
      }
      this.index += size;
      return result;
    }, readString:function(size) {
      return utils.transformTo("string", this.readData(size));
    }, readData:function(size) {
    }, lastIndexOfSignature:function(sig) {
    }, readDate:function() {
      var dostime = this.readInt(4);
      return new Date((dostime >> 25 & 127) + 1980, (dostime >> 21 & 15) - 1, dostime >> 16 & 31, dostime >> 11 & 31, dostime >> 5 & 63, (dostime & 31) << 1);
    }};
    module.exports = DataReader;
  }, {"./utils":21}], 6:[function(_dereq_, module, exports) {
    exports.base64 = false;
    exports.binary = false;
    exports.dir = false;
    exports.createFolders = false;
    exports.date = null;
    exports.compression = null;
    exports.comment = null;
  }, {}], 7:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    exports.string2binary = function(str) {
      return utils.string2binary(str);
    };
    exports.string2Uint8Array = function(str) {
      return utils.transformTo("uint8array", str);
    };
    exports.uint8Array2String = function(array) {
      return utils.transformTo("string", array);
    };
    exports.string2Blob = function(str) {
      var buffer = utils.transformTo("arraybuffer", str);
      return utils.arrayBuffer2Blob(buffer);
    };
    exports.arrayBuffer2Blob = function(buffer) {
      return utils.arrayBuffer2Blob(buffer);
    };
    exports.transformTo = function(outputType, input) {
      return utils.transformTo(outputType, input);
    };
    exports.getTypeOf = function(input) {
      return utils.getTypeOf(input);
    };
    exports.checkSupport = function(type) {
      return utils.checkSupport(type);
    };
    exports.MAX_VALUE_16BITS = utils.MAX_VALUE_16BITS;
    exports.MAX_VALUE_32BITS = utils.MAX_VALUE_32BITS;
    exports.pretty = function(str) {
      return utils.pretty(str);
    };
    exports.findCompression = function(compressionMethod) {
      return utils.findCompression(compressionMethod);
    };
    exports.isRegExp = function(object) {
      return utils.isRegExp(object);
    };
  }, {"./utils":21}], 8:[function(_dereq_, module, exports) {
    var USE_TYPEDARRAY = typeof Uint8Array !== "undefined" && typeof Uint16Array !== "undefined" && typeof Uint32Array !== "undefined";
    var pako = _dereq_("pako");
    exports.uncompressInputType = USE_TYPEDARRAY ? "uint8array" : "array";
    exports.compressInputType = USE_TYPEDARRAY ? "uint8array" : "array";
    exports.magic = "\b\x00";
    exports.compress = function(input) {
      return pako.deflateRaw(input);
    };
    exports.uncompress = function(input) {
      return pako.inflateRaw(input);
    };
  }, {"pako":24}], 9:[function(_dereq_, module, exports) {
    var base64 = _dereq_("./base64");
    function JSZip(data, options) {
      if (!(this instanceof JSZip)) {
        return new JSZip(data, options);
      }
      this.files = {};
      this.comment = null;
      this.root = "";
      if (data) {
        this.load(data, options);
      }
      this.clone = function() {
        var newObj = new JSZip;
        for (var i in this) {
          if (typeof this[i] !== "function") {
            newObj[i] = this[i];
          }
        }
        return newObj;
      };
    }
    JSZip.prototype = _dereq_("./object");
    JSZip.prototype.load = _dereq_("./load");
    JSZip.support = _dereq_("./support");
    JSZip.defaults = _dereq_("./defaults");
    JSZip.utils = _dereq_("./deprecatedPublicUtils");
    JSZip.base64 = {encode:function(input) {
      return base64.encode(input);
    }, decode:function(input) {
      return base64.decode(input);
    }};
    JSZip.compressions = _dereq_("./compressions");
    module.exports = JSZip;
  }, {"./base64":1, "./compressions":3, "./defaults":6, "./deprecatedPublicUtils":7, "./load":10, "./object":13, "./support":17}], 10:[function(_dereq_, module, exports) {
    var base64 = _dereq_("./base64");
    var ZipEntries = _dereq_("./zipEntries");
    module.exports = function(data, options) {
      var files, zipEntries, i, input;
      options = options || {};
      if (options.base64) {
        data = base64.decode(data);
      }
      zipEntries = new ZipEntries(data, options);
      files = zipEntries.files;
      for (i = 0;i < files.length;i++) {
        input = files[i];
        this.file(input.fileName, input.decompressed, {binary:true, optimizedBinaryString:true, date:input.date, dir:input.dir, comment:input.fileComment.length ? input.fileComment : null, createFolders:options.createFolders});
      }
      if (zipEntries.zipComment.length) {
        this.comment = zipEntries.zipComment;
      }
      return this;
    };
  }, {"./base64":1, "./zipEntries":22}], 11:[function(_dereq_, module, exports) {
    (function(Buffer) {
      module.exports = function(data, encoding) {
        return new Buffer(data, encoding);
      };
      module.exports.test = function(b) {
        return Buffer.isBuffer(b);
      };
    }).call(this, typeof Buffer !== "undefined" ? Buffer : undefined);
  }, {}], 12:[function(_dereq_, module, exports) {
    var Uint8ArrayReader = _dereq_("./uint8ArrayReader");
    function NodeBufferReader(data) {
      this.data = data;
      this.length = this.data.length;
      this.index = 0;
    }
    NodeBufferReader.prototype = new Uint8ArrayReader;
    NodeBufferReader.prototype.readData = function(size) {
      this.checkOffset(size);
      var result = this.data.slice(this.index, this.index + size);
      this.index += size;
      return result;
    };
    module.exports = NodeBufferReader;
  }, {"./uint8ArrayReader":18}], 13:[function(_dereq_, module, exports) {
    var support = _dereq_("./support");
    var utils = _dereq_("./utils");
    var crc32 = _dereq_("./crc32");
    var signature = _dereq_("./signature");
    var defaults = _dereq_("./defaults");
    var base64 = _dereq_("./base64");
    var compressions = _dereq_("./compressions");
    var CompressedObject = _dereq_("./compressedObject");
    var nodeBuffer = _dereq_("./nodeBuffer");
    var utf8 = _dereq_("./utf8");
    var StringWriter = _dereq_("./stringWriter");
    var Uint8ArrayWriter = _dereq_("./uint8ArrayWriter");
    var getRawData = function(file) {
      if (file._data instanceof CompressedObject) {
        file._data = file._data.getContent();
        file.options.binary = true;
        file.options.base64 = false;
        if (utils.getTypeOf(file._data) === "uint8array") {
          var copy = file._data;
          file._data = new Uint8Array(copy.length);
          if (copy.length !== 0) {
            file._data.set(copy, 0);
          }
        }
      }
      return file._data;
    };
    var getBinaryData = function(file) {
      var result = getRawData(file), type = utils.getTypeOf(result);
      if (type === "string") {
        if (!file.options.binary) {
          if (support.nodebuffer) {
            return nodeBuffer(result, "utf-8");
          }
        }
        return file.asBinary();
      }
      return result;
    };
    var dataToString = function(asUTF8) {
      var result = getRawData(this);
      if (result === null || typeof result === "undefined") {
        return "";
      }
      if (this.options.base64) {
        result = base64.decode(result);
      }
      if (asUTF8 && this.options.binary) {
        result = out.utf8decode(result);
      } else {
        result = utils.transformTo("string", result);
      }
      if (!asUTF8 && !this.options.binary) {
        result = utils.transformTo("string", out.utf8encode(result));
      }
      return result;
    };
    var ZipObject = function(name, data, options) {
      this.name = name;
      this.dir = options.dir;
      this.date = options.date;
      this.comment = options.comment;
      this._data = data;
      this.options = options;
      this._initialMetadata = {dir:options.dir, date:options.date};
    };
    ZipObject.prototype = {asText:function() {
      return dataToString.call(this, true);
    }, asBinary:function() {
      return dataToString.call(this, false);
    }, asNodeBuffer:function() {
      var result = getBinaryData(this);
      return utils.transformTo("nodebuffer", result);
    }, asUint8Array:function() {
      var result = getBinaryData(this);
      return utils.transformTo("uint8array", result);
    }, asArrayBuffer:function() {
      return this.asUint8Array().buffer;
    }};
    var decToHex = function(dec, bytes) {
      var hex = "", i;
      for (i = 0;i < bytes;i++) {
        hex += String.fromCharCode(dec & 255);
        dec = dec >>> 8;
      }
      return hex;
    };
    var extend = function() {
      var result = {}, i, attr;
      for (i = 0;i < arguments.length;i++) {
        for (attr in arguments[i]) {
          if (arguments[i].hasOwnProperty(attr) && typeof result[attr] === "undefined") {
            result[attr] = arguments[i][attr];
          }
        }
      }
      return result;
    };
    var prepareFileAttrs = function(o) {
      o = o || {};
      if (o.base64 === true && (o.binary === null || o.binary === undefined)) {
        o.binary = true;
      }
      o = extend(o, defaults);
      o.date = o.date || new Date;
      if (o.compression !== null) {
        o.compression = o.compression.toUpperCase();
      }
      return o;
    };
    var fileAdd = function(name, data, o) {
      var dataType = utils.getTypeOf(data), parent;
      o = prepareFileAttrs(o);
      if (o.createFolders && (parent = parentFolder(name))) {
        folderAdd.call(this, parent, true);
      }
      if (o.dir || data === null || typeof data === "undefined") {
        o.base64 = false;
        o.binary = false;
        data = null;
      } else {
        if (dataType === "string") {
          if (o.binary && !o.base64) {
            if (o.optimizedBinaryString !== true) {
              data = utils.string2binary(data);
            }
          }
        } else {
          o.base64 = false;
          o.binary = true;
          if (!dataType && !(data instanceof CompressedObject)) {
            throw new Error("The data of '" + name + "' is in an unsupported format !");
          }
          if (dataType === "arraybuffer") {
            data = utils.transformTo("uint8array", data);
          }
        }
      }
      var object = new ZipObject(name, data, o);
      this.files[name] = object;
      return object;
    };
    var parentFolder = function(path) {
      if (path.slice(-1) == "/") {
        path = path.substring(0, path.length - 1);
      }
      var lastSlash = path.lastIndexOf("/");
      return lastSlash > 0 ? path.substring(0, lastSlash) : "";
    };
    var folderAdd = function(name, createFolders) {
      if (name.slice(-1) != "/") {
        name += "/";
      }
      createFolders = typeof createFolders !== "undefined" ? createFolders : false;
      if (!this.files[name]) {
        fileAdd.call(this, name, null, {dir:true, createFolders:createFolders});
      }
      return this.files[name];
    };
    var generateCompressedObjectFrom = function(file, compression) {
      var result = new CompressedObject, content;
      if (file._data instanceof CompressedObject) {
        result.uncompressedSize = file._data.uncompressedSize;
        result.crc32 = file._data.crc32;
        if (result.uncompressedSize === 0 || file.dir) {
          compression = compressions["STORE"];
          result.compressedContent = "";
          result.crc32 = 0;
        } else {
          if (file._data.compressionMethod === compression.magic) {
            result.compressedContent = file._data.getCompressedContent();
          } else {
            content = file._data.getContent();
            result.compressedContent = compression.compress(utils.transformTo(compression.compressInputType, content));
          }
        }
      } else {
        content = getBinaryData(file);
        if (!content || content.length === 0 || file.dir) {
          compression = compressions["STORE"];
          content = "";
        }
        result.uncompressedSize = content.length;
        result.crc32 = crc32(content);
        result.compressedContent = compression.compress(utils.transformTo(compression.compressInputType, content));
      }
      result.compressedSize = result.compressedContent.length;
      result.compressionMethod = compression.magic;
      return result;
    };
    var generateZipParts = function(name, file, compressedObject, offset) {
      var data = compressedObject.compressedContent, utfEncodedFileName = utils.transformTo("string", utf8.utf8encode(file.name)), comment = file.comment || "", utfEncodedComment = utils.transformTo("string", utf8.utf8encode(comment)), useUTF8ForFileName = utfEncodedFileName.length !== file.name.length, useUTF8ForComment = utfEncodedComment.length !== comment.length, o = file.options, dosTime, dosDate, extraFields = "", unicodePathExtraField = "", unicodeCommentExtraField = "", dir, date;
      if (file._initialMetadata.dir !== file.dir) {
        dir = file.dir;
      } else {
        dir = o.dir;
      }
      if (file._initialMetadata.date !== file.date) {
        date = file.date;
      } else {
        date = o.date;
      }
      dosTime = date.getHours();
      dosTime = dosTime << 6;
      dosTime = dosTime | date.getMinutes();
      dosTime = dosTime << 5;
      dosTime = dosTime | date.getSeconds() / 2;
      dosDate = date.getFullYear() - 1980;
      dosDate = dosDate << 4;
      dosDate = dosDate | date.getMonth() + 1;
      dosDate = dosDate << 5;
      dosDate = dosDate | date.getDate();
      if (useUTF8ForFileName) {
        unicodePathExtraField = decToHex(1, 1) + decToHex(crc32(utfEncodedFileName), 4) + utfEncodedFileName;
        extraFields += "up" + decToHex(unicodePathExtraField.length, 2) + unicodePathExtraField;
      }
      if (useUTF8ForComment) {
        unicodeCommentExtraField = decToHex(1, 1) + decToHex(this.crc32(utfEncodedComment), 4) + utfEncodedComment;
        extraFields += "uc" + decToHex(unicodeCommentExtraField.length, 2) + unicodeCommentExtraField;
      }
      var header = "";
      header += "\n\x00";
      header += useUTF8ForFileName || useUTF8ForComment ? "\x00\b" : "\x00\x00";
      header += compressedObject.compressionMethod;
      header += decToHex(dosTime, 2);
      header += decToHex(dosDate, 2);
      header += decToHex(compressedObject.crc32, 4);
      header += decToHex(compressedObject.compressedSize, 4);
      header += decToHex(compressedObject.uncompressedSize, 4);
      header += decToHex(utfEncodedFileName.length, 2);
      header += decToHex(extraFields.length, 2);
      var fileRecord = signature.LOCAL_FILE_HEADER + header + utfEncodedFileName + extraFields;
      var dirRecord = signature.CENTRAL_FILE_HEADER + "\u0014\x00" + header + decToHex(utfEncodedComment.length, 2) + "\x00\x00" + "\x00\x00" + (dir === true ? "\u0010\x00\x00\x00" : "\x00\x00\x00\x00") + decToHex(offset, 4) + utfEncodedFileName + extraFields + utfEncodedComment;
      return {fileRecord:fileRecord, dirRecord:dirRecord, compressedObject:compressedObject};
    };
    var out = {load:function(stream, options) {
      throw new Error("Load method is not defined. Is the file jszip-load.js included ?");
    }, filter:function(search) {
      var result = [], filename, relativePath, file, fileClone;
      for (filename in this.files) {
        if (!this.files.hasOwnProperty(filename)) {
          continue;
        }
        file = this.files[filename];
        fileClone = new ZipObject(file.name, file._data, extend(file.options));
        relativePath = filename.slice(this.root.length, filename.length);
        if (filename.slice(0, this.root.length) === this.root && search(relativePath, fileClone)) {
          result.push(fileClone);
        }
      }
      return result;
    }, file:function(name, data, o) {
      if (arguments.length === 1) {
        if (utils.isRegExp(name)) {
          var regexp = name;
          return this.filter(function(relativePath, file) {
            return !file.dir && regexp.test(relativePath);
          });
        } else {
          return this.filter(function(relativePath, file) {
            return !file.dir && relativePath === name;
          })[0] || null;
        }
      } else {
        name = this.root + name;
        fileAdd.call(this, name, data, o);
      }
      return this;
    }, folder:function(arg) {
      if (!arg) {
        return this;
      }
      if (utils.isRegExp(arg)) {
        return this.filter(function(relativePath, file) {
          return file.dir && arg.test(relativePath);
        });
      }
      var name = this.root + arg;
      var newFolder = folderAdd.call(this, name);
      var ret = this.clone();
      ret.root = newFolder.name;
      return ret;
    }, remove:function(name) {
      name = this.root + name;
      var file = this.files[name];
      if (!file) {
        if (name.slice(-1) != "/") {
          name += "/";
        }
        file = this.files[name];
      }
      if (file && !file.dir) {
        delete this.files[name];
      } else {
        var kids = this.filter(function(relativePath, file) {
          return file.name.slice(0, name.length) === name;
        });
        for (var i = 0;i < kids.length;i++) {
          delete this.files[kids[i].name];
        }
      }
      return this;
    }, generate:function(options) {
      options = extend(options || {}, {base64:true, compression:"STORE", type:"base64", comment:null});
      utils.checkSupport(options.type);
      var zipData = [], localDirLength = 0, centralDirLength = 0, writer, i, utfEncodedComment = utils.transformTo("string", this.utf8encode(options.comment || this.comment || ""));
      for (var name in this.files) {
        if (!this.files.hasOwnProperty(name)) {
          continue;
        }
        var file = this.files[name];
        var compressionName = file.options.compression || options.compression.toUpperCase();
        var compression = compressions[compressionName];
        if (!compression) {
          throw new Error(compressionName + " is not a valid compression method !");
        }
        var compressedObject = generateCompressedObjectFrom.call(this, file, compression);
        var zipPart = generateZipParts.call(this, name, file, compressedObject, localDirLength);
        localDirLength += zipPart.fileRecord.length + compressedObject.compressedSize;
        centralDirLength += zipPart.dirRecord.length;
        zipData.push(zipPart);
      }
      var dirEnd = "";
      dirEnd = signature.CENTRAL_DIRECTORY_END + "\x00\x00" + "\x00\x00" + decToHex(zipData.length, 2) + decToHex(zipData.length, 2) + decToHex(centralDirLength, 4) + decToHex(localDirLength, 4) + decToHex(utfEncodedComment.length, 2) + utfEncodedComment;
      var typeName = options.type.toLowerCase();
      if (typeName === "uint8array" || typeName === "arraybuffer" || typeName === "blob" || typeName === "nodebuffer") {
        writer = new Uint8ArrayWriter(localDirLength + centralDirLength + dirEnd.length);
      } else {
        writer = new StringWriter(localDirLength + centralDirLength + dirEnd.length);
      }
      for (i = 0;i < zipData.length;i++) {
        writer.append(zipData[i].fileRecord);
        writer.append(zipData[i].compressedObject.compressedContent);
      }
      for (i = 0;i < zipData.length;i++) {
        writer.append(zipData[i].dirRecord);
      }
      writer.append(dirEnd);
      var zip = writer.finalize();
      switch(options.type.toLowerCase()) {
        case "uint8array":
        ;
        case "arraybuffer":
        ;
        case "nodebuffer":
          return utils.transformTo(options.type.toLowerCase(), zip);
        case "blob":
          return utils.arrayBuffer2Blob(utils.transformTo("arraybuffer", zip));
        case "base64":
          return options.base64 ? base64.encode(zip) : zip;
        default:
          return zip;
      }
    }, crc32:function(input, crc) {
      return crc32(input, crc);
    }, utf8encode:function(string) {
      return utils.transformTo("string", utf8.utf8encode(string));
    }, utf8decode:function(input) {
      return utf8.utf8decode(input);
    }};
    module.exports = out;
  }, {"./base64":1, "./compressedObject":2, "./compressions":3, "./crc32":4, "./defaults":6, "./nodeBuffer":11, "./signature":14, "./stringWriter":16, "./support":17, "./uint8ArrayWriter":19, "./utf8":20, "./utils":21}], 14:[function(_dereq_, module, exports) {
    exports.LOCAL_FILE_HEADER = "PK\u0003\u0004";
    exports.CENTRAL_FILE_HEADER = "PK\u0001\u0002";
    exports.CENTRAL_DIRECTORY_END = "PK\u0005\u0006";
    exports.ZIP64_CENTRAL_DIRECTORY_LOCATOR = "PK\u0006\u0007";
    exports.ZIP64_CENTRAL_DIRECTORY_END = "PK\u0006\u0006";
    exports.DATA_DESCRIPTOR = "PK\u0007\b";
  }, {}], 15:[function(_dereq_, module, exports) {
    var DataReader = _dereq_("./dataReader");
    var utils = _dereq_("./utils");
    function StringReader(data, optimizedBinaryString) {
      this.data = data;
      if (!optimizedBinaryString) {
        this.data = utils.string2binary(this.data);
      }
      this.length = this.data.length;
      this.index = 0;
    }
    StringReader.prototype = new DataReader;
    StringReader.prototype.byteAt = function(i) {
      return this.data.charCodeAt(i);
    };
    StringReader.prototype.lastIndexOfSignature = function(sig) {
      return this.data.lastIndexOf(sig);
    };
    StringReader.prototype.readData = function(size) {
      this.checkOffset(size);
      var result = this.data.slice(this.index, this.index + size);
      this.index += size;
      return result;
    };
    module.exports = StringReader;
  }, {"./dataReader":5, "./utils":21}], 16:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    var StringWriter = function() {
      this.data = [];
    };
    StringWriter.prototype = {append:function(input) {
      input = utils.transformTo("string", input);
      this.data.push(input);
    }, finalize:function() {
      return this.data.join("");
    }};
    module.exports = StringWriter;
  }, {"./utils":21}], 17:[function(_dereq_, module, exports) {
    (function(Buffer) {
      exports.base64 = true;
      exports.array = true;
      exports.string = true;
      exports.arraybuffer = typeof ArrayBuffer !== "undefined" && typeof Uint8Array !== "undefined";
      exports.nodebuffer = typeof Buffer !== "undefined";
      exports.uint8array = typeof Uint8Array !== "undefined";
      if (typeof ArrayBuffer === "undefined") {
        exports.blob = false;
      } else {
        var buffer = new ArrayBuffer(0);
        try {
          exports.blob = (new Blob([buffer], {type:"application/zip"})).size === 0;
        } catch (e) {
          try {
            var Builder = window.BlobBuilder || window.WebKitBlobBuilder || window.MozBlobBuilder || window.MSBlobBuilder;
            var builder = new Builder;
            builder.append(buffer);
            exports.blob = builder.getBlob("application/zip").size === 0;
          } catch (e) {
            exports.blob = false;
          }
        }
      }
    }).call(this, typeof Buffer !== "undefined" ? Buffer : undefined);
  }, {}], 18:[function(_dereq_, module, exports) {
    var DataReader = _dereq_("./dataReader");
    function Uint8ArrayReader(data) {
      if (data) {
        this.data = data;
        this.length = this.data.length;
        this.index = 0;
      }
    }
    Uint8ArrayReader.prototype = new DataReader;
    Uint8ArrayReader.prototype.byteAt = function(i) {
      return this.data[i];
    };
    Uint8ArrayReader.prototype.lastIndexOfSignature = function(sig) {
      var sig0 = sig.charCodeAt(0), sig1 = sig.charCodeAt(1), sig2 = sig.charCodeAt(2), sig3 = sig.charCodeAt(3);
      for (var i = this.length - 4;i >= 0;--i) {
        if (this.data[i] === sig0 && this.data[i + 1] === sig1 && this.data[i + 2] === sig2 && this.data[i + 3] === sig3) {
          return i;
        }
      }
      return -1;
    };
    Uint8ArrayReader.prototype.readData = function(size) {
      this.checkOffset(size);
      if (size === 0) {
        return new Uint8Array(0);
      }
      var result = this.data.subarray(this.index, this.index + size);
      this.index += size;
      return result;
    };
    module.exports = Uint8ArrayReader;
  }, {"./dataReader":5}], 19:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    var Uint8ArrayWriter = function(length) {
      this.data = new Uint8Array(length);
      this.index = 0;
    };
    Uint8ArrayWriter.prototype = {append:function(input) {
      if (input.length !== 0) {
        input = utils.transformTo("uint8array", input);
        this.data.set(input, this.index);
        this.index += input.length;
      }
    }, finalize:function() {
      return this.data;
    }};
    module.exports = Uint8ArrayWriter;
  }, {"./utils":21}], 20:[function(_dereq_, module, exports) {
    var utils = _dereq_("./utils");
    var support = _dereq_("./support");
    var nodeBuffer = _dereq_("./nodeBuffer");
    var _utf8len = new Array(256);
    for (var i = 0;i < 256;i++) {
      _utf8len[i] = i >= 252 ? 6 : i >= 248 ? 5 : i >= 240 ? 4 : i >= 224 ? 3 : i >= 192 ? 2 : 1;
    }
    _utf8len[254] = _utf8len[254] = 1;
    var string2buf = function(str) {
      var buf, c, c2, m_pos, i, str_len = str.length, buf_len = 0;
      for (m_pos = 0;m_pos < str_len;m_pos++) {
        c = str.charCodeAt(m_pos);
        if ((c & 64512) === 55296 && m_pos + 1 < str_len) {
          c2 = str.charCodeAt(m_pos + 1);
          if ((c2 & 64512) === 56320) {
            c = 65536 + (c - 55296 << 10) + (c2 - 56320);
            m_pos++;
          }
        }
        buf_len += c < 128 ? 1 : c < 2048 ? 2 : c < 65536 ? 3 : 4;
      }
      if (support.uint8array) {
        buf = new Uint8Array(buf_len);
      } else {
        buf = new Array(buf_len);
      }
      for (i = 0, m_pos = 0;i < buf_len;m_pos++) {
        c = str.charCodeAt(m_pos);
        if ((c & 64512) === 55296 && m_pos + 1 < str_len) {
          c2 = str.charCodeAt(m_pos + 1);
          if ((c2 & 64512) === 56320) {
            c = 65536 + (c - 55296 << 10) + (c2 - 56320);
            m_pos++;
          }
        }
        if (c < 128) {
          buf[i++] = c;
        } else {
          if (c < 2048) {
            buf[i++] = 192 | c >>> 6;
            buf[i++] = 128 | c & 63;
          } else {
            if (c < 65536) {
              buf[i++] = 224 | c >>> 12;
              buf[i++] = 128 | c >>> 6 & 63;
              buf[i++] = 128 | c & 63;
            } else {
              buf[i++] = 240 | c >>> 18;
              buf[i++] = 128 | c >>> 12 & 63;
              buf[i++] = 128 | c >>> 6 & 63;
              buf[i++] = 128 | c & 63;
            }
          }
        }
      }
      return buf;
    };
    var utf8border = function(buf, max) {
      var pos;
      max = max || buf.length;
      if (max > buf.length) {
        max = buf.length;
      }
      pos = max - 1;
      while (pos >= 0 && (buf[pos] & 192) === 128) {
        pos--;
      }
      if (pos < 0) {
        return max;
      }
      if (pos === 0) {
        return max;
      }
      return pos + _utf8len[buf[pos]] > max ? pos : max;
    };
    var buf2string = function(buf) {
      var str, i, out, c, c_len;
      var len = buf.length;
      var utf16buf = new Array(len * 2);
      for (out = 0, i = 0;i < len;) {
        c = buf[i++];
        if (c < 128) {
          utf16buf[out++] = c;
          continue;
        }
        c_len = _utf8len[c];
        if (c_len > 4) {
          utf16buf[out++] = 65533;
          i += c_len - 1;
          continue;
        }
        c &= c_len === 2 ? 31 : c_len === 3 ? 15 : 7;
        while (c_len > 1 && i < len) {
          c = c << 6 | buf[i++] & 63;
          c_len--;
        }
        if (c_len > 1) {
          utf16buf[out++] = 65533;
          continue;
        }
        if (c < 65536) {
          utf16buf[out++] = c;
        } else {
          c -= 65536;
          utf16buf[out++] = 55296 | c >> 10 & 1023;
          utf16buf[out++] = 56320 | c & 1023;
        }
      }
      if (utf16buf.length !== out) {
        if (utf16buf.subarray) {
          utf16buf = utf16buf.subarray(0, out);
        } else {
          utf16buf.length = out;
        }
      }
      return utils.applyFromCharCode(utf16buf);
    };
    exports.utf8encode = function utf8encode(str) {
      if (support.nodebuffer) {
        return nodeBuffer(str, "utf-8");
      }
      return string2buf(str);
    };
    exports.utf8decode = function utf8decode(buf) {
      if (support.nodebuffer) {
        return utils.transformTo("nodebuffer", buf).toString("utf-8");
      }
      buf = utils.transformTo(support.uint8array ? "uint8array" : "array", buf);
      var result = [], k = 0, len = buf.length, chunk = 65536;
      while (k < len) {
        var nextBoundary = utf8border(buf, Math.min(k + chunk, len));
        if (support.uint8array) {
          result.push(buf2string(buf.subarray(k, nextBoundary)));
        } else {
          result.push(buf2string(buf.slice(k, nextBoundary)));
        }
        k = nextBoundary;
      }
      return result.join("");
    };
  }, {"./nodeBuffer":11, "./support":17, "./utils":21}], 21:[function(_dereq_, module, exports) {
    var support = _dereq_("./support");
    var compressions = _dereq_("./compressions");
    var nodeBuffer = _dereq_("./nodeBuffer");
    exports.string2binary = function(str) {
      var result = "";
      for (var i = 0;i < str.length;i++) {
        result += String.fromCharCode(str.charCodeAt(i) & 255);
      }
      return result;
    };
    exports.arrayBuffer2Blob = function(buffer) {
      exports.checkSupport("blob");
      try {
        return new Blob([buffer], {type:"application/zip"});
      } catch (e) {
        try {
          var Builder = window.BlobBuilder || window.WebKitBlobBuilder || window.MozBlobBuilder || window.MSBlobBuilder;
          var builder = new Builder;
          builder.append(buffer);
          return builder.getBlob("application/zip");
        } catch (e) {
          throw new Error("Bug : can't construct the Blob.");
        }
      }
    };
    function identity(input) {
      return input;
    }
    function stringToArrayLike(str, array) {
      for (var i = 0;i < str.length;++i) {
        array[i] = str.charCodeAt(i) & 255;
      }
      return array;
    }
    function arrayLikeToString(array) {
      var chunk = 65536;
      var result = [], len = array.length, type = exports.getTypeOf(array), k = 0, canUseApply = true;
      try {
        switch(type) {
          case "uint8array":
            String.fromCharCode.apply(null, new Uint8Array(0));
            break;
          case "nodebuffer":
            String.fromCharCode.apply(null, nodeBuffer(0));
            break;
        }
      } catch (e) {
        canUseApply = false;
      }
      if (!canUseApply) {
        var resultStr = "";
        for (var i = 0;i < array.length;i++) {
          resultStr += String.fromCharCode(array[i]);
        }
        return resultStr;
      }
      while (k < len && chunk > 1) {
        try {
          if (type === "array" || type === "nodebuffer") {
            result.push(String.fromCharCode.apply(null, array.slice(k, Math.min(k + chunk, len))));
          } else {
            result.push(String.fromCharCode.apply(null, array.subarray(k, Math.min(k + chunk, len))));
          }
          k += chunk;
        } catch (e) {
          chunk = Math.floor(chunk / 2);
        }
      }
      return result.join("");
    }
    exports.applyFromCharCode = arrayLikeToString;
    function arrayLikeToArrayLike(arrayFrom, arrayTo) {
      for (var i = 0;i < arrayFrom.length;i++) {
        arrayTo[i] = arrayFrom[i];
      }
      return arrayTo;
    }
    var transform = {};
    transform["string"] = {"string":identity, "array":function(input) {
      return stringToArrayLike(input, new Array(input.length));
    }, "arraybuffer":function(input) {
      return transform["string"]["uint8array"](input).buffer;
    }, "uint8array":function(input) {
      return stringToArrayLike(input, new Uint8Array(input.length));
    }, "nodebuffer":function(input) {
      return stringToArrayLike(input, nodeBuffer(input.length));
    }};
    transform["array"] = {"string":arrayLikeToString, "array":identity, "arraybuffer":function(input) {
      return (new Uint8Array(input)).buffer;
    }, "uint8array":function(input) {
      return new Uint8Array(input);
    }, "nodebuffer":function(input) {
      return nodeBuffer(input);
    }};
    transform["arraybuffer"] = {"string":function(input) {
      return arrayLikeToString(new Uint8Array(input));
    }, "array":function(input) {
      return arrayLikeToArrayLike(new Uint8Array(input), new Array(input.byteLength));
    }, "arraybuffer":identity, "uint8array":function(input) {
      return new Uint8Array(input);
    }, "nodebuffer":function(input) {
      return nodeBuffer(new Uint8Array(input));
    }};
    transform["uint8array"] = {"string":arrayLikeToString, "array":function(input) {
      return arrayLikeToArrayLike(input, new Array(input.length));
    }, "arraybuffer":function(input) {
      return input.buffer;
    }, "uint8array":identity, "nodebuffer":function(input) {
      return nodeBuffer(input);
    }};
    transform["nodebuffer"] = {"string":arrayLikeToString, "array":function(input) {
      return arrayLikeToArrayLike(input, new Array(input.length));
    }, "arraybuffer":function(input) {
      return transform["nodebuffer"]["uint8array"](input).buffer;
    }, "uint8array":function(input) {
      return arrayLikeToArrayLike(input, new Uint8Array(input.length));
    }, "nodebuffer":identity};
    exports.transformTo = function(outputType, input) {
      if (!input) {
        input = "";
      }
      if (!outputType) {
        return input;
      }
      exports.checkSupport(outputType);
      var inputType = exports.getTypeOf(input);
      var result = transform[inputType][outputType](input);
      return result;
    };
    exports.getTypeOf = function(input) {
      if (typeof input === "string") {
        return "string";
      }
      if (Object.prototype.toString.call(input) === "[object Array]") {
        return "array";
      }
      if (support.nodebuffer && nodeBuffer.test(input)) {
        return "nodebuffer";
      }
      if (support.uint8array && input instanceof Uint8Array) {
        return "uint8array";
      }
      if (support.arraybuffer && input instanceof ArrayBuffer) {
        return "arraybuffer";
      }
    };
    exports.checkSupport = function(type) {
      var supported = support[type.toLowerCase()];
      if (!supported) {
        throw new Error(type + " is not supported by this browser");
      }
    };
    exports.MAX_VALUE_16BITS = 65535;
    exports.MAX_VALUE_32BITS = -1;
    exports.pretty = function(str) {
      var res = "", code, i;
      for (i = 0;i < (str || "").length;i++) {
        code = str.charCodeAt(i);
        res += "\\x" + (code < 16 ? "0" : "") + code.toString(16).toUpperCase();
      }
      return res;
    };
    exports.findCompression = function(compressionMethod) {
      for (var method in compressions) {
        if (!compressions.hasOwnProperty(method)) {
          continue;
        }
        if (compressions[method].magic === compressionMethod) {
          return compressions[method];
        }
      }
      return null;
    };
    exports.isRegExp = function(object) {
      return Object.prototype.toString.call(object) === "[object RegExp]";
    };
  }, {"./compressions":3, "./nodeBuffer":11, "./support":17}], 22:[function(_dereq_, module, exports) {
    var StringReader = _dereq_("./stringReader");
    var NodeBufferReader = _dereq_("./nodeBufferReader");
    var Uint8ArrayReader = _dereq_("./uint8ArrayReader");
    var utils = _dereq_("./utils");
    var sig = _dereq_("./signature");
    var ZipEntry = _dereq_("./zipEntry");
    var support = _dereq_("./support");
    var jszipProto = _dereq_("./object");
    function ZipEntries(data, loadOptions) {
      this.files = [];
      this.loadOptions = loadOptions;
      if (data) {
        this.load(data);
      }
    }
    ZipEntries.prototype = {checkSignature:function(expectedSignature) {
      var signature = this.reader.readString(4);
      if (signature !== expectedSignature) {
        throw new Error("Corrupted zip or bug : unexpected signature " + "(" + utils.pretty(signature) + ", expected " + utils.pretty(expectedSignature) + ")");
      }
    }, readBlockEndOfCentral:function() {
      this.diskNumber = this.reader.readInt(2);
      this.diskWithCentralDirStart = this.reader.readInt(2);
      this.centralDirRecordsOnThisDisk = this.reader.readInt(2);
      this.centralDirRecords = this.reader.readInt(2);
      this.centralDirSize = this.reader.readInt(4);
      this.centralDirOffset = this.reader.readInt(4);
      this.zipCommentLength = this.reader.readInt(2);
      this.zipComment = this.reader.readString(this.zipCommentLength);
      this.zipComment = jszipProto.utf8decode(this.zipComment);
    }, readBlockZip64EndOfCentral:function() {
      this.zip64EndOfCentralSize = this.reader.readInt(8);
      this.versionMadeBy = this.reader.readString(2);
      this.versionNeeded = this.reader.readInt(2);
      this.diskNumber = this.reader.readInt(4);
      this.diskWithCentralDirStart = this.reader.readInt(4);
      this.centralDirRecordsOnThisDisk = this.reader.readInt(8);
      this.centralDirRecords = this.reader.readInt(8);
      this.centralDirSize = this.reader.readInt(8);
      this.centralDirOffset = this.reader.readInt(8);
      this.zip64ExtensibleData = {};
      var extraDataSize = this.zip64EndOfCentralSize - 44, index = 0, extraFieldId, extraFieldLength, extraFieldValue;
      while (index < extraDataSize) {
        extraFieldId = this.reader.readInt(2);
        extraFieldLength = this.reader.readInt(4);
        extraFieldValue = this.reader.readString(extraFieldLength);
        this.zip64ExtensibleData[extraFieldId] = {id:extraFieldId, length:extraFieldLength, value:extraFieldValue};
      }
    }, readBlockZip64EndOfCentralLocator:function() {
      this.diskWithZip64CentralDirStart = this.reader.readInt(4);
      this.relativeOffsetEndOfZip64CentralDir = this.reader.readInt(8);
      this.disksCount = this.reader.readInt(4);
      if (this.disksCount > 1) {
        throw new Error("Multi-volumes zip are not supported");
      }
    }, readLocalFiles:function() {
      var i, file;
      for (i = 0;i < this.files.length;i++) {
        file = this.files[i];
        this.reader.setIndex(file.localHeaderOffset);
        this.checkSignature(sig.LOCAL_FILE_HEADER);
        file.readLocalPart(this.reader);
        file.handleUTF8();
      }
    }, readCentralDir:function() {
      var file;
      this.reader.setIndex(this.centralDirOffset);
      while (this.reader.readString(4) === sig.CENTRAL_FILE_HEADER) {
        file = new ZipEntry({zip64:this.zip64}, this.loadOptions);
        file.readCentralPart(this.reader);
        this.files.push(file);
      }
    }, readEndOfCentral:function() {
      var offset = this.reader.lastIndexOfSignature(sig.CENTRAL_DIRECTORY_END);
      if (offset === -1) {
        throw new Error("Corrupted zip : can't find end of central directory");
      }
      this.reader.setIndex(offset);
      this.checkSignature(sig.CENTRAL_DIRECTORY_END);
      this.readBlockEndOfCentral();
      if (this.diskNumber === utils.MAX_VALUE_16BITS || this.diskWithCentralDirStart === utils.MAX_VALUE_16BITS || this.centralDirRecordsOnThisDisk === utils.MAX_VALUE_16BITS || this.centralDirRecords === utils.MAX_VALUE_16BITS || this.centralDirSize === utils.MAX_VALUE_32BITS || this.centralDirOffset === utils.MAX_VALUE_32BITS) {
        this.zip64 = true;
        offset = this.reader.lastIndexOfSignature(sig.ZIP64_CENTRAL_DIRECTORY_LOCATOR);
        if (offset === -1) {
          throw new Error("Corrupted zip : can't find the ZIP64 end of central directory locator");
        }
        this.reader.setIndex(offset);
        this.checkSignature(sig.ZIP64_CENTRAL_DIRECTORY_LOCATOR);
        this.readBlockZip64EndOfCentralLocator();
        this.reader.setIndex(this.relativeOffsetEndOfZip64CentralDir);
        this.checkSignature(sig.ZIP64_CENTRAL_DIRECTORY_END);
        this.readBlockZip64EndOfCentral();
      }
    }, prepareReader:function(data) {
      var type = utils.getTypeOf(data);
      if (type === "string" && !support.uint8array) {
        this.reader = new StringReader(data, this.loadOptions.optimizedBinaryString);
      } else {
        if (type === "nodebuffer") {
          this.reader = new NodeBufferReader(data);
        } else {
          this.reader = new Uint8ArrayReader(utils.transformTo("uint8array", data));
        }
      }
    }, load:function(data) {
      this.prepareReader(data);
      this.readEndOfCentral();
      this.readCentralDir();
      this.readLocalFiles();
    }};
    module.exports = ZipEntries;
  }, {"./nodeBufferReader":12, "./object":13, "./signature":14, "./stringReader":15, "./support":17, "./uint8ArrayReader":18, "./utils":21, "./zipEntry":23}], 23:[function(_dereq_, module, exports) {
    var StringReader = _dereq_("./stringReader");
    var utils = _dereq_("./utils");
    var CompressedObject = _dereq_("./compressedObject");
    var jszipProto = _dereq_("./object");
    function ZipEntry(options, loadOptions) {
      this.options = options;
      this.loadOptions = loadOptions;
    }
    ZipEntry.prototype = {isEncrypted:function() {
      return (this.bitFlag & 1) === 1;
    }, useUTF8:function() {
      return (this.bitFlag & 2048) === 2048;
    }, prepareCompressedContent:function(reader, from, length) {
      return function() {
        var previousIndex = reader.index;
        reader.setIndex(from);
        var compressedFileData = reader.readData(length);
        reader.setIndex(previousIndex);
        return compressedFileData;
      };
    }, prepareContent:function(reader, from, length, compression, uncompressedSize) {
      return function() {
        var compressedFileData = utils.transformTo(compression.uncompressInputType, this.getCompressedContent());
        var uncompressedFileData = compression.uncompress(compressedFileData);
        if (uncompressedFileData.length !== uncompressedSize) {
          throw new Error("Bug : uncompressed data size mismatch");
        }
        return uncompressedFileData;
      };
    }, readLocalPart:function(reader) {
      var compression, localExtraFieldsLength;
      reader.skip(22);
      this.fileNameLength = reader.readInt(2);
      localExtraFieldsLength = reader.readInt(2);
      this.fileName = reader.readString(this.fileNameLength);
      reader.skip(localExtraFieldsLength);
      if (this.compressedSize == -1 || this.uncompressedSize == -1) {
        throw new Error("Bug or corrupted zip : didn't get enough informations from the central directory " + "(compressedSize == -1 || uncompressedSize == -1)");
      }
      compression = utils.findCompression(this.compressionMethod);
      if (compression === null) {
        throw new Error("Corrupted zip : compression " + utils.pretty(this.compressionMethod) + " unknown (inner file : " + this.fileName + ")");
      }
      this.decompressed = new CompressedObject;
      this.decompressed.compressedSize = this.compressedSize;
      this.decompressed.uncompressedSize = this.uncompressedSize;
      this.decompressed.crc32 = this.crc32;
      this.decompressed.compressionMethod = this.compressionMethod;
      this.decompressed.getCompressedContent = this.prepareCompressedContent(reader, reader.index, this.compressedSize, compression);
      this.decompressed.getContent = this.prepareContent(reader, reader.index, this.compressedSize, compression, this.uncompressedSize);
      if (this.loadOptions.checkCRC32) {
        this.decompressed = utils.transformTo("string", this.decompressed.getContent());
        if (jszipProto.crc32(this.decompressed) !== this.crc32) {
          throw new Error("Corrupted zip : CRC32 mismatch");
        }
      }
    }, readCentralPart:function(reader) {
      this.versionMadeBy = reader.readString(2);
      this.versionNeeded = reader.readInt(2);
      this.bitFlag = reader.readInt(2);
      this.compressionMethod = reader.readString(2);
      this.date = reader.readDate();
      this.crc32 = reader.readInt(4);
      this.compressedSize = reader.readInt(4);
      this.uncompressedSize = reader.readInt(4);
      this.fileNameLength = reader.readInt(2);
      this.extraFieldsLength = reader.readInt(2);
      this.fileCommentLength = reader.readInt(2);
      this.diskNumberStart = reader.readInt(2);
      this.internalFileAttributes = reader.readInt(2);
      this.externalFileAttributes = reader.readInt(4);
      this.localHeaderOffset = reader.readInt(4);
      if (this.isEncrypted()) {
        throw new Error("Encrypted zip are not supported");
      }
      this.fileName = reader.readString(this.fileNameLength);
      this.readExtraFields(reader);
      this.parseZIP64ExtraField(reader);
      this.fileComment = reader.readString(this.fileCommentLength);
      this.dir = this.externalFileAttributes & 16 ? true : false;
    }, parseZIP64ExtraField:function(reader) {
      if (!this.extraFields[1]) {
        return;
      }
      var extraReader = new StringReader(this.extraFields[1].value);
      if (this.uncompressedSize === utils.MAX_VALUE_32BITS) {
        this.uncompressedSize = extraReader.readInt(8);
      }
      if (this.compressedSize === utils.MAX_VALUE_32BITS) {
        this.compressedSize = extraReader.readInt(8);
      }
      if (this.localHeaderOffset === utils.MAX_VALUE_32BITS) {
        this.localHeaderOffset = extraReader.readInt(8);
      }
      if (this.diskNumberStart === utils.MAX_VALUE_32BITS) {
        this.diskNumberStart = extraReader.readInt(4);
      }
    }, readExtraFields:function(reader) {
      var start = reader.index, extraFieldId, extraFieldLength, extraFieldValue;
      this.extraFields = this.extraFields || {};
      while (reader.index < start + this.extraFieldsLength) {
        extraFieldId = reader.readInt(2);
        extraFieldLength = reader.readInt(2);
        extraFieldValue = reader.readString(extraFieldLength);
        this.extraFields[extraFieldId] = {id:extraFieldId, length:extraFieldLength, value:extraFieldValue};
      }
    }, handleUTF8:function() {
      if (this.useUTF8()) {
        this.fileName = jszipProto.utf8decode(this.fileName);
        this.fileComment = jszipProto.utf8decode(this.fileComment);
      } else {
        var upath = this.findExtraFieldUnicodePath();
        if (upath !== null) {
          this.fileName = upath;
        }
        var ucomment = this.findExtraFieldUnicodeComment();
        if (ucomment !== null) {
          this.fileComment = ucomment;
        }
      }
    }, findExtraFieldUnicodePath:function() {
      var upathField = this.extraFields[28789];
      if (upathField) {
        var extraReader = new StringReader(upathField.value);
        if (extraReader.readInt(1) !== 1) {
          return null;
        }
        if (jszipProto.crc32(this.fileName) !== extraReader.readInt(4)) {
          return null;
        }
        return jszipProto.utf8decode(extraReader.readString(upathField.length - 5));
      }
      return null;
    }, findExtraFieldUnicodeComment:function() {
      var ucommentField = this.extraFields[25461];
      if (ucommentField) {
        var extraReader = new StringReader(ucommentField.value);
        if (extraReader.readInt(1) !== 1) {
          return null;
        }
        if (jszipProto.crc32(this.fileComment) !== extraReader.readInt(4)) {
          return null;
        }
        return jszipProto.utf8decode(extraReader.readString(ucommentField.length - 5));
      }
      return null;
    }};
    module.exports = ZipEntry;
  }, {"./compressedObject":2, "./object":13, "./stringReader":15, "./utils":21}], 24:[function(_dereq_, module, exports) {
    var assign = _dereq_("./lib/utils/common").assign;
    var deflate = _dereq_("./lib/deflate");
    var inflate = _dereq_("./lib/inflate");
    var constants = _dereq_("./lib/zlib/constants");
    var pako = {};
    assign(pako, deflate, inflate, constants);
    module.exports = pako;
  }, {"./lib/deflate":25, "./lib/inflate":26, "./lib/utils/common":27, "./lib/zlib/constants":30}], 25:[function(_dereq_, module, exports) {
    var zlib_deflate = _dereq_("./zlib/deflate.js");
    var utils = _dereq_("./utils/common");
    var strings = _dereq_("./utils/strings");
    var msg = _dereq_("./zlib/messages");
    var zstream = _dereq_("./zlib/zstream");
    var Z_NO_FLUSH = 0;
    var Z_FINISH = 4;
    var Z_OK = 0;
    var Z_STREAM_END = 1;
    var Z_DEFAULT_COMPRESSION = -1;
    var Z_DEFAULT_STRATEGY = 0;
    var Z_DEFLATED = 8;
    var Deflate = function(options) {
      this.options = utils.assign({level:Z_DEFAULT_COMPRESSION, method:Z_DEFLATED, chunkSize:16384, windowBits:15, memLevel:8, strategy:Z_DEFAULT_STRATEGY, to:""}, options || {});
      var opt = this.options;
      if (opt.raw && opt.windowBits > 0) {
        opt.windowBits = -opt.windowBits;
      } else {
        if (opt.gzip && opt.windowBits > 0 && opt.windowBits < 16) {
          opt.windowBits += 16;
        }
      }
      this.err = 0;
      this.msg = "";
      this.ended = false;
      this.chunks = [];
      this.strm = new zstream;
      this.strm.avail_out = 0;
      var status = zlib_deflate.deflateInit2(this.strm, opt.level, opt.method, opt.windowBits, opt.memLevel, opt.strategy);
      if (status !== Z_OK) {
        throw new Error(msg[status]);
      }
      if (opt.header) {
        zlib_deflate.deflateSetHeader(this.strm, opt.header);
      }
    };
    Deflate.prototype.push = function(data, mode) {
      var strm = this.strm;
      var chunkSize = this.options.chunkSize;
      var status, _mode;
      if (this.ended) {
        return false;
      }
      _mode = mode === ~~mode ? mode : mode === true ? Z_FINISH : Z_NO_FLUSH;
      if (typeof data === "string") {
        strm.input = strings.string2buf(data);
      } else {
        strm.input = data;
      }
      strm.next_in = 0;
      strm.avail_in = strm.input.length;
      do {
        if (strm.avail_out === 0) {
          strm.output = new utils.Buf8(chunkSize);
          strm.next_out = 0;
          strm.avail_out = chunkSize;
        }
        status = zlib_deflate.deflate(strm, _mode);
        if (status !== Z_STREAM_END && status !== Z_OK) {
          this.onEnd(status);
          this.ended = true;
          return false;
        }
        if (strm.avail_out === 0 || strm.avail_in === 0 && _mode === Z_FINISH) {
          if (this.options.to === "string") {
            this.onData(strings.buf2binstring(utils.shrinkBuf(strm.output, strm.next_out)));
          } else {
            this.onData(utils.shrinkBuf(strm.output, strm.next_out));
          }
        }
      } while ((strm.avail_in > 0 || strm.avail_out === 0) && status !== Z_STREAM_END);
      if (_mode === Z_FINISH) {
        status = zlib_deflate.deflateEnd(this.strm);
        this.onEnd(status);
        this.ended = true;
        return status === Z_OK;
      }
      return true;
    };
    Deflate.prototype.onData = function(chunk) {
      this.chunks.push(chunk);
    };
    Deflate.prototype.onEnd = function(status) {
      if (status === Z_OK) {
        if (this.options.to === "string") {
          this.result = this.chunks.join("");
        } else {
          this.result = utils.flattenChunks(this.chunks);
        }
      }
      this.chunks = [];
      this.err = status;
      this.msg = this.strm.msg;
    };
    function deflate(input, options) {
      var deflator = new Deflate(options);
      deflator.push(input, true);
      if (deflator.err) {
        throw deflator.msg;
      }
      return deflator.result;
    }
    function deflateRaw(input, options) {
      options = options || {};
      options.raw = true;
      return deflate(input, options);
    }
    function gzip(input, options) {
      options = options || {};
      options.gzip = true;
      return deflate(input, options);
    }
    exports.Deflate = Deflate;
    exports.deflate = deflate;
    exports.deflateRaw = deflateRaw;
    exports.gzip = gzip;
  }, {"./utils/common":27, "./utils/strings":28, "./zlib/deflate.js":32, "./zlib/messages":37, "./zlib/zstream":39}], 26:[function(_dereq_, module, exports) {
    var zlib_inflate = _dereq_("./zlib/inflate.js");
    var utils = _dereq_("./utils/common");
    var strings = _dereq_("./utils/strings");
    var c = _dereq_("./zlib/constants");
    var msg = _dereq_("./zlib/messages");
    var zstream = _dereq_("./zlib/zstream");
    var gzheader = _dereq_("./zlib/gzheader");
    var Inflate = function(options) {
      this.options = utils.assign({chunkSize:16384, windowBits:0, to:""}, options || {});
      var opt = this.options;
      if (opt.raw && opt.windowBits >= 0 && opt.windowBits < 16) {
        opt.windowBits = -opt.windowBits;
        if (opt.windowBits === 0) {
          opt.windowBits = -15;
        }
      }
      if (opt.windowBits >= 0 && opt.windowBits < 16 && !(options && options.windowBits)) {
        opt.windowBits += 32;
      }
      if (opt.windowBits > 15 && opt.windowBits < 48) {
        if ((opt.windowBits & 15) === 0) {
          opt.windowBits |= 15;
        }
      }
      this.err = 0;
      this.msg = "";
      this.ended = false;
      this.chunks = [];
      this.strm = new zstream;
      this.strm.avail_out = 0;
      var status = zlib_inflate.inflateInit2(this.strm, opt.windowBits);
      if (status !== c.Z_OK) {
        throw new Error(msg[status]);
      }
      this.header = new gzheader;
      zlib_inflate.inflateGetHeader(this.strm, this.header);
    };
    Inflate.prototype.push = function(data, mode) {
      var strm = this.strm;
      var chunkSize = this.options.chunkSize;
      var status, _mode;
      var next_out_utf8, tail, utf8str;
      if (this.ended) {
        return false;
      }
      _mode = mode === ~~mode ? mode : mode === true ? c.Z_FINISH : c.Z_NO_FLUSH;
      if (typeof data === "string") {
        strm.input = strings.binstring2buf(data);
      } else {
        strm.input = data;
      }
      strm.next_in = 0;
      strm.avail_in = strm.input.length;
      do {
        if (strm.avail_out === 0) {
          strm.output = new utils.Buf8(chunkSize);
          strm.next_out = 0;
          strm.avail_out = chunkSize;
        }
        status = zlib_inflate.inflate(strm, c.Z_NO_FLUSH);
        if (status !== c.Z_STREAM_END && status !== c.Z_OK) {
          this.onEnd(status);
          this.ended = true;
          return false;
        }
        if (strm.next_out) {
          if (strm.avail_out === 0 || status === c.Z_STREAM_END || strm.avail_in === 0 && _mode === c.Z_FINISH) {
            if (this.options.to === "string") {
              next_out_utf8 = strings.utf8border(strm.output, strm.next_out);
              tail = strm.next_out - next_out_utf8;
              utf8str = strings.buf2string(strm.output, next_out_utf8);
              strm.next_out = tail;
              strm.avail_out = chunkSize - tail;
              if (tail) {
                utils.arraySet(strm.output, strm.output, next_out_utf8, tail, 0);
              }
              this.onData(utf8str);
            } else {
              this.onData(utils.shrinkBuf(strm.output, strm.next_out));
            }
          }
        }
      } while (strm.avail_in > 0 && status !== c.Z_STREAM_END);
      if (status === c.Z_STREAM_END) {
        _mode = c.Z_FINISH;
      }
      if (_mode === c.Z_FINISH) {
        status = zlib_inflate.inflateEnd(this.strm);
        this.onEnd(status);
        this.ended = true;
        return status === c.Z_OK;
      }
      return true;
    };
    Inflate.prototype.onData = function(chunk) {
      this.chunks.push(chunk);
    };
    Inflate.prototype.onEnd = function(status) {
      if (status === c.Z_OK) {
        if (this.options.to === "string") {
          this.result = this.chunks.join("");
        } else {
          this.result = utils.flattenChunks(this.chunks);
        }
      }
      this.chunks = [];
      this.err = status;
      this.msg = this.strm.msg;
    };
    function inflate(input, options) {
      var inflator = new Inflate(options);
      inflator.push(input, true);
      if (inflator.err) {
        throw inflator.msg;
      }
      return inflator.result;
    }
    function inflateRaw(input, options) {
      options = options || {};
      options.raw = true;
      return inflate(input, options);
    }
    exports.Inflate = Inflate;
    exports.inflate = inflate;
    exports.inflateRaw = inflateRaw;
    exports.ungzip = inflate;
  }, {"./utils/common":27, "./utils/strings":28, "./zlib/constants":30, "./zlib/gzheader":33, "./zlib/inflate.js":35, "./zlib/messages":37, "./zlib/zstream":39}], 27:[function(_dereq_, module, exports) {
    var TYPED_OK = typeof Uint8Array !== "undefined" && typeof Uint16Array !== "undefined" && typeof Int32Array !== "undefined";
    exports.assign = function(obj) {
      var sources = Array.prototype.slice.call(arguments, 1);
      while (sources.length) {
        var source = sources.shift();
        if (!source) {
          continue;
        }
        if (typeof source !== "object") {
          throw new TypeError(source + "must be non-object");
        }
        for (var p in source) {
          if (source.hasOwnProperty(p)) {
            obj[p] = source[p];
          }
        }
      }
      return obj;
    };
    exports.shrinkBuf = function(buf, size) {
      if (buf.length === size) {
        return buf;
      }
      if (buf.subarray) {
        return buf.subarray(0, size);
      }
      buf.length = size;
      return buf;
    };
    var fnTyped = {arraySet:function(dest, src, src_offs, len, dest_offs) {
      if (src.subarray && dest.subarray) {
        dest.set(src.subarray(src_offs, src_offs + len), dest_offs);
        return;
      }
      for (var i = 0;i < len;i++) {
        dest[dest_offs + i] = src[src_offs + i];
      }
    }, flattenChunks:function(chunks) {
      var i, l, len, pos, chunk, result;
      len = 0;
      for (i = 0, l = chunks.length;i < l;i++) {
        len += chunks[i].length;
      }
      result = new Uint8Array(len);
      pos = 0;
      for (i = 0, l = chunks.length;i < l;i++) {
        chunk = chunks[i];
        result.set(chunk, pos);
        pos += chunk.length;
      }
      return result;
    }};
    var fnUntyped = {arraySet:function(dest, src, src_offs, len, dest_offs) {
      for (var i = 0;i < len;i++) {
        dest[dest_offs + i] = src[src_offs + i];
      }
    }, flattenChunks:function(chunks) {
      return [].concat.apply([], chunks);
    }};
    exports.setTyped = function(on) {
      if (on) {
        exports.Buf8 = Uint8Array;
        exports.Buf16 = Uint16Array;
        exports.Buf32 = Int32Array;
        exports.assign(exports, fnTyped);
      } else {
        exports.Buf8 = Array;
        exports.Buf16 = Array;
        exports.Buf32 = Array;
        exports.assign(exports, fnUntyped);
      }
    };
    exports.setTyped(TYPED_OK);
  }, {}], 28:[function(_dereq_, module, exports) {
    var utils = _dereq_("./common");
    var STR_APPLY_OK = true;
    var STR_APPLY_UIA_OK = true;
    try {
      String.fromCharCode.apply(null, [0]);
    } catch (__) {
      STR_APPLY_OK = false;
    }
    try {
      String.fromCharCode.apply(null, new Uint8Array(1));
    } catch (__) {
      STR_APPLY_UIA_OK = false;
    }
    var _utf8len = new utils.Buf8(256);
    for (var i = 0;i < 256;i++) {
      _utf8len[i] = i >= 252 ? 6 : i >= 248 ? 5 : i >= 240 ? 4 : i >= 224 ? 3 : i >= 192 ? 2 : 1;
    }
    _utf8len[254] = _utf8len[254] = 1;
    exports.string2buf = function(str) {
      var buf, c, c2, m_pos, i, str_len = str.length, buf_len = 0;
      for (m_pos = 0;m_pos < str_len;m_pos++) {
        c = str.charCodeAt(m_pos);
        if ((c & 64512) === 55296 && m_pos + 1 < str_len) {
          c2 = str.charCodeAt(m_pos + 1);
          if ((c2 & 64512) === 56320) {
            c = 65536 + (c - 55296 << 10) + (c2 - 56320);
            m_pos++;
          }
        }
        buf_len += c < 128 ? 1 : c < 2048 ? 2 : c < 65536 ? 3 : 4;
      }
      buf = new utils.Buf8(buf_len);
      for (i = 0, m_pos = 0;i < buf_len;m_pos++) {
        c = str.charCodeAt(m_pos);
        if ((c & 64512) === 55296 && m_pos + 1 < str_len) {
          c2 = str.charCodeAt(m_pos + 1);
          if ((c2 & 64512) === 56320) {
            c = 65536 + (c - 55296 << 10) + (c2 - 56320);
            m_pos++;
          }
        }
        if (c < 128) {
          buf[i++] = c;
        } else {
          if (c < 2048) {
            buf[i++] = 192 | c >>> 6;
            buf[i++] = 128 | c & 63;
          } else {
            if (c < 65536) {
              buf[i++] = 224 | c >>> 12;
              buf[i++] = 128 | c >>> 6 & 63;
              buf[i++] = 128 | c & 63;
            } else {
              buf[i++] = 240 | c >>> 18;
              buf[i++] = 128 | c >>> 12 & 63;
              buf[i++] = 128 | c >>> 6 & 63;
              buf[i++] = 128 | c & 63;
            }
          }
        }
      }
      return buf;
    };
    function buf2binstring(buf, len) {
      if (len < 65537) {
        if (buf.subarray && STR_APPLY_UIA_OK || !buf.subarray && STR_APPLY_OK) {
          return String.fromCharCode.apply(null, utils.shrinkBuf(buf, len));
        }
      }
      var result = "";
      for (var i = 0;i < len;i++) {
        result += String.fromCharCode(buf[i]);
      }
      return result;
    }
    exports.buf2binstring = function(buf) {
      return buf2binstring(buf, buf.length);
    };
    exports.binstring2buf = function(str) {
      var buf = new utils.Buf8(str.length);
      for (var i = 0, len = buf.length;i < len;i++) {
        buf[i] = str.charCodeAt(i);
      }
      return buf;
    };
    exports.buf2string = function(buf, max) {
      var i, out, c, c_len;
      var len = max || buf.length;
      var utf16buf = new Array(len * 2);
      for (out = 0, i = 0;i < len;) {
        c = buf[i++];
        if (c < 128) {
          utf16buf[out++] = c;
          continue;
        }
        c_len = _utf8len[c];
        if (c_len > 4) {
          utf16buf[out++] = 65533;
          i += c_len - 1;
          continue;
        }
        c &= c_len === 2 ? 31 : c_len === 3 ? 15 : 7;
        while (c_len > 1 && i < len) {
          c = c << 6 | buf[i++] & 63;
          c_len--;
        }
        if (c_len > 1) {
          utf16buf[out++] = 65533;
          continue;
        }
        if (c < 65536) {
          utf16buf[out++] = c;
        } else {
          c -= 65536;
          utf16buf[out++] = 55296 | c >> 10 & 1023;
          utf16buf[out++] = 56320 | c & 1023;
        }
      }
      return buf2binstring(utf16buf, out);
    };
    exports.utf8border = function(buf, max) {
      var pos;
      max = max || buf.length;
      if (max > buf.length) {
        max = buf.length;
      }
      pos = max - 1;
      while (pos >= 0 && (buf[pos] & 192) === 128) {
        pos--;
      }
      if (pos < 0) {
        return max;
      }
      if (pos === 0) {
        return max;
      }
      return pos + _utf8len[buf[pos]] > max ? pos : max;
    };
  }, {"./common":27}], 29:[function(_dereq_, module, exports) {
    function adler32(adler, buf, len, pos) {
      var s1 = adler & 65535 | 0, s2 = adler >>> 16 & 65535 | 0, n = 0;
      while (len !== 0) {
        n = len > 2E3 ? 2E3 : len;
        len -= n;
        do {
          s1 = s1 + buf[pos++] | 0;
          s2 = s2 + s1 | 0;
        } while (--n);
        s1 %= 65521;
        s2 %= 65521;
      }
      return s1 | s2 << 16 | 0;
    }
    module.exports = adler32;
  }, {}], 30:[function(_dereq_, module, exports) {
    module.exports = {Z_NO_FLUSH:0, Z_PARTIAL_FLUSH:1, Z_SYNC_FLUSH:2, Z_FULL_FLUSH:3, Z_FINISH:4, Z_BLOCK:5, Z_TREES:6, Z_OK:0, Z_STREAM_END:1, Z_NEED_DICT:2, Z_ERRNO:-1, Z_STREAM_ERROR:-2, Z_DATA_ERROR:-3, Z_BUF_ERROR:-5, Z_NO_COMPRESSION:0, Z_BEST_SPEED:1, Z_BEST_COMPRESSION:9, Z_DEFAULT_COMPRESSION:-1, Z_FILTERED:1, Z_HUFFMAN_ONLY:2, Z_RLE:3, Z_FIXED:4, Z_DEFAULT_STRATEGY:0, Z_BINARY:0, Z_TEXT:1, Z_UNKNOWN:2, Z_DEFLATED:8};
  }, {}], 31:[function(_dereq_, module, exports) {
    function makeTable() {
      var c, table = [];
      for (var n = 0;n < 256;n++) {
        c = n;
        for (var k = 0;k < 8;k++) {
          c = c & 1 ? 3988292384 ^ c >>> 1 : c >>> 1;
        }
        table[n] = c;
      }
      return table;
    }
    var crcTable = makeTable();
    function crc32(crc, buf, len, pos) {
      var t = crcTable, end = pos + len;
      crc = crc ^ -1;
      for (var i = pos;i < end;i++) {
        crc = crc >>> 8 ^ t[(crc ^ buf[i]) & 255];
      }
      return crc ^ -1;
    }
    module.exports = crc32;
  }, {}], 32:[function(_dereq_, module, exports) {
    var utils = _dereq_("../utils/common");
    var trees = _dereq_("./trees");
    var adler32 = _dereq_("./adler32");
    var crc32 = _dereq_("./crc32");
    var msg = _dereq_("./messages");
    var Z_NO_FLUSH = 0;
    var Z_PARTIAL_FLUSH = 1;
    var Z_FULL_FLUSH = 3;
    var Z_FINISH = 4;
    var Z_BLOCK = 5;
    var Z_OK = 0;
    var Z_STREAM_END = 1;
    var Z_STREAM_ERROR = -2;
    var Z_DATA_ERROR = -3;
    var Z_BUF_ERROR = -5;
    var Z_DEFAULT_COMPRESSION = -1;
    var Z_FILTERED = 1;
    var Z_HUFFMAN_ONLY = 2;
    var Z_RLE = 3;
    var Z_FIXED = 4;
    var Z_DEFAULT_STRATEGY = 0;
    var Z_UNKNOWN = 2;
    var Z_DEFLATED = 8;
    var MAX_MEM_LEVEL = 9;
    var MAX_WBITS = 15;
    var DEF_MEM_LEVEL = 8;
    var LENGTH_CODES = 29;
    var LITERALS = 256;
    var L_CODES = LITERALS + 1 + LENGTH_CODES;
    var D_CODES = 30;
    var BL_CODES = 19;
    var HEAP_SIZE = 2 * L_CODES + 1;
    var MAX_BITS = 15;
    var MIN_MATCH = 3;
    var MAX_MATCH = 258;
    var MIN_LOOKAHEAD = MAX_MATCH + MIN_MATCH + 1;
    var PRESET_DICT = 32;
    var INIT_STATE = 42;
    var EXTRA_STATE = 69;
    var NAME_STATE = 73;
    var COMMENT_STATE = 91;
    var HCRC_STATE = 103;
    var BUSY_STATE = 113;
    var FINISH_STATE = 666;
    var BS_NEED_MORE = 1;
    var BS_BLOCK_DONE = 2;
    var BS_FINISH_STARTED = 3;
    var BS_FINISH_DONE = 4;
    var OS_CODE = 3;
    function err(strm, errorCode) {
      strm.msg = msg[errorCode];
      return errorCode;
    }
    function rank(f) {
      return (f << 1) - (f > 4 ? 9 : 0);
    }
    function zero(buf) {
      var len = buf.length;
      while (--len >= 0) {
        buf[len] = 0;
      }
    }
    function flush_pending(strm) {
      var s = strm.state;
      var len = s.pending;
      if (len > strm.avail_out) {
        len = strm.avail_out;
      }
      if (len === 0) {
        return;
      }
      utils.arraySet(strm.output, s.pending_buf, s.pending_out, len, strm.next_out);
      strm.next_out += len;
      s.pending_out += len;
      strm.total_out += len;
      strm.avail_out -= len;
      s.pending -= len;
      if (s.pending === 0) {
        s.pending_out = 0;
      }
    }
    function flush_block_only(s, last) {
      trees._tr_flush_block(s, s.block_start >= 0 ? s.block_start : -1, s.strstart - s.block_start, last);
      s.block_start = s.strstart;
      flush_pending(s.strm);
    }
    function put_byte(s, b) {
      s.pending_buf[s.pending++] = b;
    }
    function putShortMSB(s, b) {
      s.pending_buf[s.pending++] = b >>> 8 & 255;
      s.pending_buf[s.pending++] = b & 255;
    }
    function read_buf(strm, buf, start, size) {
      var len = strm.avail_in;
      if (len > size) {
        len = size;
      }
      if (len === 0) {
        return 0;
      }
      strm.avail_in -= len;
      utils.arraySet(buf, strm.input, strm.next_in, len, start);
      if (strm.state.wrap === 1) {
        strm.adler = adler32(strm.adler, buf, len, start);
      } else {
        if (strm.state.wrap === 2) {
          strm.adler = crc32(strm.adler, buf, len, start);
        }
      }
      strm.next_in += len;
      strm.total_in += len;
      return len;
    }
    function longest_match(s, cur_match) {
      var chain_length = s.max_chain_length;
      var scan = s.strstart;
      var match;
      var len;
      var best_len = s.prev_length;
      var nice_match = s.nice_match;
      var limit = s.strstart > s.w_size - MIN_LOOKAHEAD ? s.strstart - (s.w_size - MIN_LOOKAHEAD) : 0;
      var _win = s.window;
      var wmask = s.w_mask;
      var prev = s.prev;
      var strend = s.strstart + MAX_MATCH;
      var scan_end1 = _win[scan + best_len - 1];
      var scan_end = _win[scan + best_len];
      if (s.prev_length >= s.good_match) {
        chain_length >>= 2;
      }
      if (nice_match > s.lookahead) {
        nice_match = s.lookahead;
      }
      do {
        match = cur_match;
        if (_win[match + best_len] !== scan_end || _win[match + best_len - 1] !== scan_end1 || _win[match] !== _win[scan] || _win[++match] !== _win[scan + 1]) {
          continue;
        }
        scan += 2;
        match++;
        do {
        } while (_win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && _win[++scan] === _win[++match] && scan < strend);
        len = MAX_MATCH - (strend - scan);
        scan = strend - MAX_MATCH;
        if (len > best_len) {
          s.match_start = cur_match;
          best_len = len;
          if (len >= nice_match) {
            break;
          }
          scan_end1 = _win[scan + best_len - 1];
          scan_end = _win[scan + best_len];
        }
      } while ((cur_match = prev[cur_match & wmask]) > limit && --chain_length !== 0);
      if (best_len <= s.lookahead) {
        return best_len;
      }
      return s.lookahead;
    }
    function fill_window(s) {
      var _w_size = s.w_size;
      var p, n, m, more, str;
      do {
        more = s.window_size - s.lookahead - s.strstart;
        if (s.strstart >= _w_size + (_w_size - MIN_LOOKAHEAD)) {
          utils.arraySet(s.window, s.window, _w_size, _w_size, 0);
          s.match_start -= _w_size;
          s.strstart -= _w_size;
          s.block_start -= _w_size;
          n = s.hash_size;
          p = n;
          do {
            m = s.head[--p];
            s.head[p] = m >= _w_size ? m - _w_size : 0;
          } while (--n);
          n = _w_size;
          p = n;
          do {
            m = s.prev[--p];
            s.prev[p] = m >= _w_size ? m - _w_size : 0;
          } while (--n);
          more += _w_size;
        }
        if (s.strm.avail_in === 0) {
          break;
        }
        n = read_buf(s.strm, s.window, s.strstart + s.lookahead, more);
        s.lookahead += n;
        if (s.lookahead + s.insert >= MIN_MATCH) {
          str = s.strstart - s.insert;
          s.ins_h = s.window[str];
          s.ins_h = (s.ins_h << s.hash_shift ^ s.window[str + 1]) & s.hash_mask;
          while (s.insert) {
            s.ins_h = (s.ins_h << s.hash_shift ^ s.window[str + MIN_MATCH - 1]) & s.hash_mask;
            s.prev[str & s.w_mask] = s.head[s.ins_h];
            s.head[s.ins_h] = str;
            str++;
            s.insert--;
            if (s.lookahead + s.insert < MIN_MATCH) {
              break;
            }
          }
        }
      } while (s.lookahead < MIN_LOOKAHEAD && s.strm.avail_in !== 0);
    }
    function deflate_stored(s, flush) {
      var max_block_size = 65535;
      if (max_block_size > s.pending_buf_size - 5) {
        max_block_size = s.pending_buf_size - 5;
      }
      for (;;) {
        if (s.lookahead <= 1) {
          fill_window(s);
          if (s.lookahead === 0 && flush === Z_NO_FLUSH) {
            return BS_NEED_MORE;
          }
          if (s.lookahead === 0) {
            break;
          }
        }
        s.strstart += s.lookahead;
        s.lookahead = 0;
        var max_start = s.block_start + max_block_size;
        if (s.strstart === 0 || s.strstart >= max_start) {
          s.lookahead = s.strstart - max_start;
          s.strstart = max_start;
          flush_block_only(s, false);
          if (s.strm.avail_out === 0) {
            return BS_NEED_MORE;
          }
        }
        if (s.strstart - s.block_start >= s.w_size - MIN_LOOKAHEAD) {
          flush_block_only(s, false);
          if (s.strm.avail_out === 0) {
            return BS_NEED_MORE;
          }
        }
      }
      s.insert = 0;
      if (flush === Z_FINISH) {
        flush_block_only(s, true);
        if (s.strm.avail_out === 0) {
          return BS_FINISH_STARTED;
        }
        return BS_FINISH_DONE;
      }
      if (s.strstart > s.block_start) {
        flush_block_only(s, false);
        if (s.strm.avail_out === 0) {
          return BS_NEED_MORE;
        }
      }
      return BS_NEED_MORE;
    }
    function deflate_fast(s, flush) {
      var hash_head;
      var bflush;
      for (;;) {
        if (s.lookahead < MIN_LOOKAHEAD) {
          fill_window(s);
          if (s.lookahead < MIN_LOOKAHEAD && flush === Z_NO_FLUSH) {
            return BS_NEED_MORE;
          }
          if (s.lookahead === 0) {
            break;
          }
        }
        hash_head = 0;
        if (s.lookahead >= MIN_MATCH) {
          s.ins_h = (s.ins_h << s.hash_shift ^ s.window[s.strstart + MIN_MATCH - 1]) & s.hash_mask;
          hash_head = s.prev[s.strstart & s.w_mask] = s.head[s.ins_h];
          s.head[s.ins_h] = s.strstart;
        }
        if (hash_head !== 0 && s.strstart - hash_head <= s.w_size - MIN_LOOKAHEAD) {
          s.match_length = longest_match(s, hash_head);
        }
        if (s.match_length >= MIN_MATCH) {
          bflush = trees._tr_tally(s, s.strstart - s.match_start, s.match_length - MIN_MATCH);
          s.lookahead -= s.match_length;
          if (s.match_length <= s.max_lazy_match && s.lookahead >= MIN_MATCH) {
            s.match_length--;
            do {
              s.strstart++;
              s.ins_h = (s.ins_h << s.hash_shift ^ s.window[s.strstart + MIN_MATCH - 1]) & s.hash_mask;
              hash_head = s.prev[s.strstart & s.w_mask] = s.head[s.ins_h];
              s.head[s.ins_h] = s.strstart;
            } while (--s.match_length !== 0);
            s.strstart++;
          } else {
            s.strstart += s.match_length;
            s.match_length = 0;
            s.ins_h = s.window[s.strstart];
            s.ins_h = (s.ins_h << s.hash_shift ^ s.window[s.strstart + 1]) & s.hash_mask;
          }
        } else {
          bflush = trees._tr_tally(s, 0, s.window[s.strstart]);
          s.lookahead--;
          s.strstart++;
        }
        if (bflush) {
          flush_block_only(s, false);
          if (s.strm.avail_out === 0) {
            return BS_NEED_MORE;
          }
        }
      }
      s.insert = s.strstart < MIN_MATCH - 1 ? s.strstart : MIN_MATCH - 1;
      if (flush === Z_FINISH) {
        flush_block_only(s, true);
        if (s.strm.avail_out === 0) {
          return BS_FINISH_STARTED;
        }
        return BS_FINISH_DONE;
      }
      if (s.last_lit) {
        flush_block_only(s, false);
        if (s.strm.avail_out === 0) {
          return BS_NEED_MORE;
        }
      }
      return BS_BLOCK_DONE;
    }
    function deflate_slow(s, flush) {
      var hash_head;
      var bflush;
      var max_insert;
      for (;;) {
        if (s.lookahead < MIN_LOOKAHEAD) {
          fill_window(s);
          if (s.lookahead < MIN_LOOKAHEAD && flush === Z_NO_FLUSH) {
            return BS_NEED_MORE;
          }
          if (s.lookahead === 0) {
            break;
          }
        }
        hash_head = 0;
        if (s.lookahead >= MIN_MATCH) {
          s.ins_h = (s.ins_h << s.hash_shift ^ s.window[s.strstart + MIN_MATCH - 1]) & s.hash_mask;
          hash_head = s.prev[s.strstart & s.w_mask] = s.head[s.ins_h];
          s.head[s.ins_h] = s.strstart;
        }
        s.prev_length = s.match_length;
        s.prev_match = s.match_start;
        s.match_length = MIN_MATCH - 1;
        if (hash_head !== 0 && s.prev_length < s.max_lazy_match && s.strstart - hash_head <= s.w_size - MIN_LOOKAHEAD) {
          s.match_length = longest_match(s, hash_head);
          if (s.match_length <= 5 && (s.strategy === Z_FILTERED || s.match_length === MIN_MATCH && s.strstart - s.match_start > 4096)) {
            s.match_length = MIN_MATCH - 1;
          }
        }
        if (s.prev_length >= MIN_MATCH && s.match_length <= s.prev_length) {
          max_insert = s.strstart + s.lookahead - MIN_MATCH;
          bflush = trees._tr_tally(s, s.strstart - 1 - s.prev_match, s.prev_length - MIN_MATCH);
          s.lookahead -= s.prev_length - 1;
          s.prev_length -= 2;
          do {
            if (++s.strstart <= max_insert) {
              s.ins_h = (s.ins_h << s.hash_shift ^ s.window[s.strstart + MIN_MATCH - 1]) & s.hash_mask;
              hash_head = s.prev[s.strstart & s.w_mask] = s.head[s.ins_h];
              s.head[s.ins_h] = s.strstart;
            }
          } while (--s.prev_length !== 0);
          s.match_available = 0;
          s.match_length = MIN_MATCH - 1;
          s.strstart++;
          if (bflush) {
            flush_block_only(s, false);
            if (s.strm.avail_out === 0) {
              return BS_NEED_MORE;
            }
          }
        } else {
          if (s.match_available) {
            bflush = trees._tr_tally(s, 0, s.window[s.strstart - 1]);
            if (bflush) {
              flush_block_only(s, false);
            }
            s.strstart++;
            s.lookahead--;
            if (s.strm.avail_out === 0) {
              return BS_NEED_MORE;
            }
          } else {
            s.match_available = 1;
            s.strstart++;
            s.lookahead--;
          }
        }
      }
      if (s.match_available) {
        bflush = trees._tr_tally(s, 0, s.window[s.strstart - 1]);
        s.match_available = 0;
      }
      s.insert = s.strstart < MIN_MATCH - 1 ? s.strstart : MIN_MATCH - 1;
      if (flush === Z_FINISH) {
        flush_block_only(s, true);
        if (s.strm.avail_out === 0) {
          return BS_FINISH_STARTED;
        }
        return BS_FINISH_DONE;
      }
      if (s.last_lit) {
        flush_block_only(s, false);
        if (s.strm.avail_out === 0) {
          return BS_NEED_MORE;
        }
      }
      return BS_BLOCK_DONE;
    }
    function deflate_rle(s, flush) {
      var bflush;
      var prev;
      var scan, strend;
      var _win = s.window;
      for (;;) {
        if (s.lookahead <= MAX_MATCH) {
          fill_window(s);
          if (s.lookahead <= MAX_MATCH && flush === Z_NO_FLUSH) {
            return BS_NEED_MORE;
          }
          if (s.lookahead === 0) {
            break;
          }
        }
        s.match_length = 0;
        if (s.lookahead >= MIN_MATCH && s.strstart > 0) {
          scan = s.strstart - 1;
          prev = _win[scan];
          if (prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan]) {
            strend = s.strstart + MAX_MATCH;
            do {
            } while (prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && prev === _win[++scan] && scan < strend);
            s.match_length = MAX_MATCH - (strend - scan);
            if (s.match_length > s.lookahead) {
              s.match_length = s.lookahead;
            }
          }
        }
        if (s.match_length >= MIN_MATCH) {
          bflush = trees._tr_tally(s, 1, s.match_length - MIN_MATCH);
          s.lookahead -= s.match_length;
          s.strstart += s.match_length;
          s.match_length = 0;
        } else {
          bflush = trees._tr_tally(s, 0, s.window[s.strstart]);
          s.lookahead--;
          s.strstart++;
        }
        if (bflush) {
          flush_block_only(s, false);
          if (s.strm.avail_out === 0) {
            return BS_NEED_MORE;
          }
        }
      }
      s.insert = 0;
      if (flush === Z_FINISH) {
        flush_block_only(s, true);
        if (s.strm.avail_out === 0) {
          return BS_FINISH_STARTED;
        }
        return BS_FINISH_DONE;
      }
      if (s.last_lit) {
        flush_block_only(s, false);
        if (s.strm.avail_out === 0) {
          return BS_NEED_MORE;
        }
      }
      return BS_BLOCK_DONE;
    }
    function deflate_huff(s, flush) {
      var bflush;
      for (;;) {
        if (s.lookahead === 0) {
          fill_window(s);
          if (s.lookahead === 0) {
            if (flush === Z_NO_FLUSH) {
              return BS_NEED_MORE;
            }
            break;
          }
        }
        s.match_length = 0;
        bflush = trees._tr_tally(s, 0, s.window[s.strstart]);
        s.lookahead--;
        s.strstart++;
        if (bflush) {
          flush_block_only(s, false);
          if (s.strm.avail_out === 0) {
            return BS_NEED_MORE;
          }
        }
      }
      s.insert = 0;
      if (flush === Z_FINISH) {
        flush_block_only(s, true);
        if (s.strm.avail_out === 0) {
          return BS_FINISH_STARTED;
        }
        return BS_FINISH_DONE;
      }
      if (s.last_lit) {
        flush_block_only(s, false);
        if (s.strm.avail_out === 0) {
          return BS_NEED_MORE;
        }
      }
      return BS_BLOCK_DONE;
    }
    var Config = function(good_length, max_lazy, nice_length, max_chain, func) {
      this.good_length = good_length;
      this.max_lazy = max_lazy;
      this.nice_length = nice_length;
      this.max_chain = max_chain;
      this.func = func;
    };
    var configuration_table;
    configuration_table = [new Config(0, 0, 0, 0, deflate_stored), new Config(4, 4, 8, 4, deflate_fast), new Config(4, 5, 16, 8, deflate_fast), new Config(4, 6, 32, 32, deflate_fast), new Config(4, 4, 16, 16, deflate_slow), new Config(8, 16, 32, 32, deflate_slow), new Config(8, 16, 128, 128, deflate_slow), new Config(8, 32, 128, 256, deflate_slow), new Config(32, 128, 258, 1024, deflate_slow), new Config(32, 258, 258, 4096, deflate_slow)];
    function lm_init(s) {
      s.window_size = 2 * s.w_size;
      zero(s.head);
      s.max_lazy_match = configuration_table[s.level].max_lazy;
      s.good_match = configuration_table[s.level].good_length;
      s.nice_match = configuration_table[s.level].nice_length;
      s.max_chain_length = configuration_table[s.level].max_chain;
      s.strstart = 0;
      s.block_start = 0;
      s.lookahead = 0;
      s.insert = 0;
      s.match_length = s.prev_length = MIN_MATCH - 1;
      s.match_available = 0;
      s.ins_h = 0;
    }
    function DeflateState() {
      this.strm = null;
      this.status = 0;
      this.pending_buf = null;
      this.pending_buf_size = 0;
      this.pending_out = 0;
      this.pending = 0;
      this.wrap = 0;
      this.gzhead = null;
      this.gzindex = 0;
      this.method = Z_DEFLATED;
      this.last_flush = -1;
      this.w_size = 0;
      this.w_bits = 0;
      this.w_mask = 0;
      this.window = null;
      this.window_size = 0;
      this.prev = null;
      this.head = null;
      this.ins_h = 0;
      this.hash_size = 0;
      this.hash_bits = 0;
      this.hash_mask = 0;
      this.hash_shift = 0;
      this.block_start = 0;
      this.match_length = 0;
      this.prev_match = 0;
      this.match_available = 0;
      this.strstart = 0;
      this.match_start = 0;
      this.lookahead = 0;
      this.prev_length = 0;
      this.max_chain_length = 0;
      this.max_lazy_match = 0;
      this.level = 0;
      this.strategy = 0;
      this.good_match = 0;
      this.nice_match = 0;
      this.dyn_ltree = new utils.Buf16(HEAP_SIZE * 2);
      this.dyn_dtree = new utils.Buf16((2 * D_CODES + 1) * 2);
      this.bl_tree = new utils.Buf16((2 * BL_CODES + 1) * 2);
      zero(this.dyn_ltree);
      zero(this.dyn_dtree);
      zero(this.bl_tree);
      this.l_desc = null;
      this.d_desc = null;
      this.bl_desc = null;
      this.bl_count = new utils.Buf16(MAX_BITS + 1);
      this.heap = new utils.Buf16(2 * L_CODES + 1);
      zero(this.heap);
      this.heap_len = 0;
      this.heap_max = 0;
      this.depth = new utils.Buf16(2 * L_CODES + 1);
      zero(this.depth);
      this.l_buf = 0;
      this.lit_bufsize = 0;
      this.last_lit = 0;
      this.d_buf = 0;
      this.opt_len = 0;
      this.static_len = 0;
      this.matches = 0;
      this.insert = 0;
      this.bi_buf = 0;
      this.bi_valid = 0;
    }
    function deflateResetKeep(strm) {
      var s;
      if (!strm || !strm.state) {
        return err(strm, Z_STREAM_ERROR);
      }
      strm.total_in = strm.total_out = 0;
      strm.data_type = Z_UNKNOWN;
      s = strm.state;
      s.pending = 0;
      s.pending_out = 0;
      if (s.wrap < 0) {
        s.wrap = -s.wrap;
      }
      s.status = s.wrap ? INIT_STATE : BUSY_STATE;
      strm.adler = s.wrap === 2 ? 0 : 1;
      s.last_flush = Z_NO_FLUSH;
      trees._tr_init(s);
      return Z_OK;
    }
    function deflateReset(strm) {
      var ret = deflateResetKeep(strm);
      if (ret === Z_OK) {
        lm_init(strm.state);
      }
      return ret;
    }
    function deflateSetHeader(strm, head) {
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      if (strm.state.wrap !== 2) {
        return Z_STREAM_ERROR;
      }
      strm.state.gzhead = head;
      return Z_OK;
    }
    function deflateInit2(strm, level, method, windowBits, memLevel, strategy) {
      if (!strm) {
        return Z_STREAM_ERROR;
      }
      var wrap = 1;
      if (level === Z_DEFAULT_COMPRESSION) {
        level = 6;
      }
      if (windowBits < 0) {
        wrap = 0;
        windowBits = -windowBits;
      } else {
        if (windowBits > 15) {
          wrap = 2;
          windowBits -= 16;
        }
      }
      if (memLevel < 1 || memLevel > MAX_MEM_LEVEL || method !== Z_DEFLATED || windowBits < 8 || windowBits > 15 || level < 0 || level > 9 || strategy < 0 || strategy > Z_FIXED) {
        return err(strm, Z_STREAM_ERROR);
      }
      if (windowBits === 8) {
        windowBits = 9;
      }
      var s = new DeflateState;
      strm.state = s;
      s.strm = strm;
      s.wrap = wrap;
      s.gzhead = null;
      s.w_bits = windowBits;
      s.w_size = 1 << s.w_bits;
      s.w_mask = s.w_size - 1;
      s.hash_bits = memLevel + 7;
      s.hash_size = 1 << s.hash_bits;
      s.hash_mask = s.hash_size - 1;
      s.hash_shift = ~~((s.hash_bits + MIN_MATCH - 1) / MIN_MATCH);
      s.window = new utils.Buf8(s.w_size * 2);
      s.head = new utils.Buf16(s.hash_size);
      s.prev = new utils.Buf16(s.w_size);
      s.lit_bufsize = 1 << memLevel + 6;
      s.pending_buf_size = s.lit_bufsize * 4;
      s.pending_buf = new utils.Buf8(s.pending_buf_size);
      s.d_buf = s.lit_bufsize >> 1;
      s.l_buf = (1 + 2) * s.lit_bufsize;
      s.level = level;
      s.strategy = strategy;
      s.method = method;
      return deflateReset(strm);
    }
    function deflateInit(strm, level) {
      return deflateInit2(strm, level, Z_DEFLATED, MAX_WBITS, DEF_MEM_LEVEL, Z_DEFAULT_STRATEGY);
    }
    function deflate(strm, flush) {
      var old_flush, s;
      var beg, val;
      if (!strm || !strm.state || flush > Z_BLOCK || flush < 0) {
        return strm ? err(strm, Z_STREAM_ERROR) : Z_STREAM_ERROR;
      }
      s = strm.state;
      if (!strm.output || !strm.input && strm.avail_in !== 0 || s.status === FINISH_STATE && flush !== Z_FINISH) {
        return err(strm, strm.avail_out === 0 ? Z_BUF_ERROR : Z_STREAM_ERROR);
      }
      s.strm = strm;
      old_flush = s.last_flush;
      s.last_flush = flush;
      if (s.status === INIT_STATE) {
        if (s.wrap === 2) {
          strm.adler = 0;
          put_byte(s, 31);
          put_byte(s, 139);
          put_byte(s, 8);
          if (!s.gzhead) {
            put_byte(s, 0);
            put_byte(s, 0);
            put_byte(s, 0);
            put_byte(s, 0);
            put_byte(s, 0);
            put_byte(s, s.level === 9 ? 2 : s.strategy >= Z_HUFFMAN_ONLY || s.level < 2 ? 4 : 0);
            put_byte(s, OS_CODE);
            s.status = BUSY_STATE;
          } else {
            put_byte(s, (s.gzhead.text ? 1 : 0) + (s.gzhead.hcrc ? 2 : 0) + (!s.gzhead.extra ? 0 : 4) + (!s.gzhead.name ? 0 : 8) + (!s.gzhead.comment ? 0 : 16));
            put_byte(s, s.gzhead.time & 255);
            put_byte(s, s.gzhead.time >> 8 & 255);
            put_byte(s, s.gzhead.time >> 16 & 255);
            put_byte(s, s.gzhead.time >> 24 & 255);
            put_byte(s, s.level === 9 ? 2 : s.strategy >= Z_HUFFMAN_ONLY || s.level < 2 ? 4 : 0);
            put_byte(s, s.gzhead.os & 255);
            if (s.gzhead.extra && s.gzhead.extra.length) {
              put_byte(s, s.gzhead.extra.length & 255);
              put_byte(s, s.gzhead.extra.length >> 8 & 255);
            }
            if (s.gzhead.hcrc) {
              strm.adler = crc32(strm.adler, s.pending_buf, s.pending, 0);
            }
            s.gzindex = 0;
            s.status = EXTRA_STATE;
          }
        } else {
          var header = Z_DEFLATED + (s.w_bits - 8 << 4) << 8;
          var level_flags = -1;
          if (s.strategy >= Z_HUFFMAN_ONLY || s.level < 2) {
            level_flags = 0;
          } else {
            if (s.level < 6) {
              level_flags = 1;
            } else {
              if (s.level === 6) {
                level_flags = 2;
              } else {
                level_flags = 3;
              }
            }
          }
          header |= level_flags << 6;
          if (s.strstart !== 0) {
            header |= PRESET_DICT;
          }
          header += 31 - header % 31;
          s.status = BUSY_STATE;
          putShortMSB(s, header);
          if (s.strstart !== 0) {
            putShortMSB(s, strm.adler >>> 16);
            putShortMSB(s, strm.adler & 65535);
          }
          strm.adler = 1;
        }
      }
      if (s.status === EXTRA_STATE) {
        if (s.gzhead.extra) {
          beg = s.pending;
          while (s.gzindex < (s.gzhead.extra.length & 65535)) {
            if (s.pending === s.pending_buf_size) {
              if (s.gzhead.hcrc && s.pending > beg) {
                strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
              }
              flush_pending(strm);
              beg = s.pending;
              if (s.pending === s.pending_buf_size) {
                break;
              }
            }
            put_byte(s, s.gzhead.extra[s.gzindex] & 255);
            s.gzindex++;
          }
          if (s.gzhead.hcrc && s.pending > beg) {
            strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
          }
          if (s.gzindex === s.gzhead.extra.length) {
            s.gzindex = 0;
            s.status = NAME_STATE;
          }
        } else {
          s.status = NAME_STATE;
        }
      }
      if (s.status === NAME_STATE) {
        if (s.gzhead.name) {
          beg = s.pending;
          do {
            if (s.pending === s.pending_buf_size) {
              if (s.gzhead.hcrc && s.pending > beg) {
                strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
              }
              flush_pending(strm);
              beg = s.pending;
              if (s.pending === s.pending_buf_size) {
                val = 1;
                break;
              }
            }
            if (s.gzindex < s.gzhead.name.length) {
              val = s.gzhead.name.charCodeAt(s.gzindex++) & 255;
            } else {
              val = 0;
            }
            put_byte(s, val);
          } while (val !== 0);
          if (s.gzhead.hcrc && s.pending > beg) {
            strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
          }
          if (val === 0) {
            s.gzindex = 0;
            s.status = COMMENT_STATE;
          }
        } else {
          s.status = COMMENT_STATE;
        }
      }
      if (s.status === COMMENT_STATE) {
        if (s.gzhead.comment) {
          beg = s.pending;
          do {
            if (s.pending === s.pending_buf_size) {
              if (s.gzhead.hcrc && s.pending > beg) {
                strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
              }
              flush_pending(strm);
              beg = s.pending;
              if (s.pending === s.pending_buf_size) {
                val = 1;
                break;
              }
            }
            if (s.gzindex < s.gzhead.comment.length) {
              val = s.gzhead.comment.charCodeAt(s.gzindex++) & 255;
            } else {
              val = 0;
            }
            put_byte(s, val);
          } while (val !== 0);
          if (s.gzhead.hcrc && s.pending > beg) {
            strm.adler = crc32(strm.adler, s.pending_buf, s.pending - beg, beg);
          }
          if (val === 0) {
            s.status = HCRC_STATE;
          }
        } else {
          s.status = HCRC_STATE;
        }
      }
      if (s.status === HCRC_STATE) {
        if (s.gzhead.hcrc) {
          if (s.pending + 2 > s.pending_buf_size) {
            flush_pending(strm);
          }
          if (s.pending + 2 <= s.pending_buf_size) {
            put_byte(s, strm.adler & 255);
            put_byte(s, strm.adler >> 8 & 255);
            strm.adler = 0;
            s.status = BUSY_STATE;
          }
        } else {
          s.status = BUSY_STATE;
        }
      }
      if (s.pending !== 0) {
        flush_pending(strm);
        if (strm.avail_out === 0) {
          s.last_flush = -1;
          return Z_OK;
        }
      } else {
        if (strm.avail_in === 0 && rank(flush) <= rank(old_flush) && flush !== Z_FINISH) {
          return err(strm, Z_BUF_ERROR);
        }
      }
      if (s.status === FINISH_STATE && strm.avail_in !== 0) {
        return err(strm, Z_BUF_ERROR);
      }
      if (strm.avail_in !== 0 || s.lookahead !== 0 || flush !== Z_NO_FLUSH && s.status !== FINISH_STATE) {
        var bstate = s.strategy === Z_HUFFMAN_ONLY ? deflate_huff(s, flush) : s.strategy === Z_RLE ? deflate_rle(s, flush) : configuration_table[s.level].func(s, flush);
        if (bstate === BS_FINISH_STARTED || bstate === BS_FINISH_DONE) {
          s.status = FINISH_STATE;
        }
        if (bstate === BS_NEED_MORE || bstate === BS_FINISH_STARTED) {
          if (strm.avail_out === 0) {
            s.last_flush = -1;
          }
          return Z_OK;
        }
        if (bstate === BS_BLOCK_DONE) {
          if (flush === Z_PARTIAL_FLUSH) {
            trees._tr_align(s);
          } else {
            if (flush !== Z_BLOCK) {
              trees._tr_stored_block(s, 0, 0, false);
              if (flush === Z_FULL_FLUSH) {
                zero(s.head);
                if (s.lookahead === 0) {
                  s.strstart = 0;
                  s.block_start = 0;
                  s.insert = 0;
                }
              }
            }
          }
          flush_pending(strm);
          if (strm.avail_out === 0) {
            s.last_flush = -1;
            return Z_OK;
          }
        }
      }
      if (flush !== Z_FINISH) {
        return Z_OK;
      }
      if (s.wrap <= 0) {
        return Z_STREAM_END;
      }
      if (s.wrap === 2) {
        put_byte(s, strm.adler & 255);
        put_byte(s, strm.adler >> 8 & 255);
        put_byte(s, strm.adler >> 16 & 255);
        put_byte(s, strm.adler >> 24 & 255);
        put_byte(s, strm.total_in & 255);
        put_byte(s, strm.total_in >> 8 & 255);
        put_byte(s, strm.total_in >> 16 & 255);
        put_byte(s, strm.total_in >> 24 & 255);
      } else {
        putShortMSB(s, strm.adler >>> 16);
        putShortMSB(s, strm.adler & 65535);
      }
      flush_pending(strm);
      if (s.wrap > 0) {
        s.wrap = -s.wrap;
      }
      return s.pending !== 0 ? Z_OK : Z_STREAM_END;
    }
    function deflateEnd(strm) {
      var status;
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      status = strm.state.status;
      if (status !== INIT_STATE && status !== EXTRA_STATE && status !== NAME_STATE && status !== COMMENT_STATE && status !== HCRC_STATE && status !== BUSY_STATE && status !== FINISH_STATE) {
        return err(strm, Z_STREAM_ERROR);
      }
      strm.state = null;
      return status === BUSY_STATE ? err(strm, Z_DATA_ERROR) : Z_OK;
    }
    exports.deflateInit = deflateInit;
    exports.deflateInit2 = deflateInit2;
    exports.deflateReset = deflateReset;
    exports.deflateResetKeep = deflateResetKeep;
    exports.deflateSetHeader = deflateSetHeader;
    exports.deflate = deflate;
    exports.deflateEnd = deflateEnd;
    exports.deflateInfo = "pako deflate (from Nodeca project)";
  }, {"../utils/common":27, "./adler32":29, "./crc32":31, "./messages":37, "./trees":38}], 33:[function(_dereq_, module, exports) {
    function GZheader() {
      this.text = 0;
      this.time = 0;
      this.xflags = 0;
      this.os = 0;
      this.extra = null;
      this.extra_len = 0;
      this.name = "";
      this.comment = "";
      this.hcrc = 0;
      this.done = false;
    }
    module.exports = GZheader;
  }, {}], 34:[function(_dereq_, module, exports) {
    var BAD = 30;
    var TYPE = 12;
    module.exports = function inflate_fast(strm, start) {
      var state;
      var _in;
      var last;
      var _out;
      var beg;
      var end;
      var dmax;
      var wsize;
      var whave;
      var wnext;
      var window;
      var hold;
      var bits;
      var lcode;
      var dcode;
      var lmask;
      var dmask;
      var here;
      var op;
      var len;
      var dist;
      var from;
      var from_source;
      var input, output;
      state = strm.state;
      _in = strm.next_in;
      input = strm.input;
      last = _in + (strm.avail_in - 5);
      _out = strm.next_out;
      output = strm.output;
      beg = _out - (start - strm.avail_out);
      end = _out + (strm.avail_out - 257);
      dmax = state.dmax;
      wsize = state.wsize;
      whave = state.whave;
      wnext = state.wnext;
      window = state.window;
      hold = state.hold;
      bits = state.bits;
      lcode = state.lencode;
      dcode = state.distcode;
      lmask = (1 << state.lenbits) - 1;
      dmask = (1 << state.distbits) - 1;
      top: do {
        if (bits < 15) {
          hold += input[_in++] << bits;
          bits += 8;
          hold += input[_in++] << bits;
          bits += 8;
        }
        here = lcode[hold & lmask];
        dolen: for (;;) {
          op = here >>> 24;
          hold >>>= op;
          bits -= op;
          op = here >>> 16 & 255;
          if (op === 0) {
            output[_out++] = here & 65535;
          } else {
            if (op & 16) {
              len = here & 65535;
              op &= 15;
              if (op) {
                if (bits < op) {
                  hold += input[_in++] << bits;
                  bits += 8;
                }
                len += hold & (1 << op) - 1;
                hold >>>= op;
                bits -= op;
              }
              if (bits < 15) {
                hold += input[_in++] << bits;
                bits += 8;
                hold += input[_in++] << bits;
                bits += 8;
              }
              here = dcode[hold & dmask];
              dodist: for (;;) {
                op = here >>> 24;
                hold >>>= op;
                bits -= op;
                op = here >>> 16 & 255;
                if (op & 16) {
                  dist = here & 65535;
                  op &= 15;
                  if (bits < op) {
                    hold += input[_in++] << bits;
                    bits += 8;
                    if (bits < op) {
                      hold += input[_in++] << bits;
                      bits += 8;
                    }
                  }
                  dist += hold & (1 << op) - 1;
                  if (dist > dmax) {
                    strm.msg = "invalid distance too far back";
                    state.mode = BAD;
                    break top;
                  }
                  hold >>>= op;
                  bits -= op;
                  op = _out - beg;
                  if (dist > op) {
                    op = dist - op;
                    if (op > whave) {
                      if (state.sane) {
                        strm.msg = "invalid distance too far back";
                        state.mode = BAD;
                        break top;
                      }
                    }
                    from = 0;
                    from_source = window;
                    if (wnext === 0) {
                      from += wsize - op;
                      if (op < len) {
                        len -= op;
                        do {
                          output[_out++] = window[from++];
                        } while (--op);
                        from = _out - dist;
                        from_source = output;
                      }
                    } else {
                      if (wnext < op) {
                        from += wsize + wnext - op;
                        op -= wnext;
                        if (op < len) {
                          len -= op;
                          do {
                            output[_out++] = window[from++];
                          } while (--op);
                          from = 0;
                          if (wnext < len) {
                            op = wnext;
                            len -= op;
                            do {
                              output[_out++] = window[from++];
                            } while (--op);
                            from = _out - dist;
                            from_source = output;
                          }
                        }
                      } else {
                        from += wnext - op;
                        if (op < len) {
                          len -= op;
                          do {
                            output[_out++] = window[from++];
                          } while (--op);
                          from = _out - dist;
                          from_source = output;
                        }
                      }
                    }
                    while (len > 2) {
                      output[_out++] = from_source[from++];
                      output[_out++] = from_source[from++];
                      output[_out++] = from_source[from++];
                      len -= 3;
                    }
                    if (len) {
                      output[_out++] = from_source[from++];
                      if (len > 1) {
                        output[_out++] = from_source[from++];
                      }
                    }
                  } else {
                    from = _out - dist;
                    do {
                      output[_out++] = output[from++];
                      output[_out++] = output[from++];
                      output[_out++] = output[from++];
                      len -= 3;
                    } while (len > 2);
                    if (len) {
                      output[_out++] = output[from++];
                      if (len > 1) {
                        output[_out++] = output[from++];
                      }
                    }
                  }
                } else {
                  if ((op & 64) === 0) {
                    here = dcode[(here & 65535) + (hold & (1 << op) - 1)];
                    continue dodist;
                  } else {
                    strm.msg = "invalid distance code";
                    state.mode = BAD;
                    break top;
                  }
                }
                break;
              }
            } else {
              if ((op & 64) === 0) {
                here = lcode[(here & 65535) + (hold & (1 << op) - 1)];
                continue dolen;
              } else {
                if (op & 32) {
                  state.mode = TYPE;
                  break top;
                } else {
                  strm.msg = "invalid literal/length code";
                  state.mode = BAD;
                  break top;
                }
              }
            }
          }
          break;
        }
      } while (_in < last && _out < end);
      len = bits >> 3;
      _in -= len;
      bits -= len << 3;
      hold &= (1 << bits) - 1;
      strm.next_in = _in;
      strm.next_out = _out;
      strm.avail_in = _in < last ? 5 + (last - _in) : 5 - (_in - last);
      strm.avail_out = _out < end ? 257 + (end - _out) : 257 - (_out - end);
      state.hold = hold;
      state.bits = bits;
      return;
    };
  }, {}], 35:[function(_dereq_, module, exports) {
    var utils = _dereq_("../utils/common");
    var adler32 = _dereq_("./adler32");
    var crc32 = _dereq_("./crc32");
    var inflate_fast = _dereq_("./inffast");
    var inflate_table = _dereq_("./inftrees");
    var CODES = 0;
    var LENS = 1;
    var DISTS = 2;
    var Z_FINISH = 4;
    var Z_BLOCK = 5;
    var Z_TREES = 6;
    var Z_OK = 0;
    var Z_STREAM_END = 1;
    var Z_NEED_DICT = 2;
    var Z_STREAM_ERROR = -2;
    var Z_DATA_ERROR = -3;
    var Z_MEM_ERROR = -4;
    var Z_BUF_ERROR = -5;
    var Z_DEFLATED = 8;
    var HEAD = 1;
    var FLAGS = 2;
    var TIME = 3;
    var OS = 4;
    var EXLEN = 5;
    var EXTRA = 6;
    var NAME = 7;
    var COMMENT = 8;
    var HCRC = 9;
    var DICTID = 10;
    var DICT = 11;
    var TYPE = 12;
    var TYPEDO = 13;
    var STORED = 14;
    var COPY_ = 15;
    var COPY = 16;
    var TABLE = 17;
    var LENLENS = 18;
    var CODELENS = 19;
    var LEN_ = 20;
    var LEN = 21;
    var LENEXT = 22;
    var DIST = 23;
    var DISTEXT = 24;
    var MATCH = 25;
    var LIT = 26;
    var CHECK = 27;
    var LENGTH = 28;
    var DONE = 29;
    var BAD = 30;
    var MEM = 31;
    var SYNC = 32;
    var ENOUGH_LENS = 852;
    var ENOUGH_DISTS = 592;
    var MAX_WBITS = 15;
    var DEF_WBITS = MAX_WBITS;
    function ZSWAP32(q) {
      return (q >>> 24 & 255) + (q >>> 8 & 65280) + ((q & 65280) << 8) + ((q & 255) << 24);
    }
    function InflateState() {
      this.mode = 0;
      this.last = false;
      this.wrap = 0;
      this.havedict = false;
      this.flags = 0;
      this.dmax = 0;
      this.check = 0;
      this.total = 0;
      this.head = null;
      this.wbits = 0;
      this.wsize = 0;
      this.whave = 0;
      this.wnext = 0;
      this.window = null;
      this.hold = 0;
      this.bits = 0;
      this.length = 0;
      this.offset = 0;
      this.extra = 0;
      this.lencode = null;
      this.distcode = null;
      this.lenbits = 0;
      this.distbits = 0;
      this.ncode = 0;
      this.nlen = 0;
      this.ndist = 0;
      this.have = 0;
      this.next = null;
      this.lens = new utils.Buf16(320);
      this.work = new utils.Buf16(288);
      this.lendyn = null;
      this.distdyn = null;
      this.sane = 0;
      this.back = 0;
      this.was = 0;
    }
    function inflateResetKeep(strm) {
      var state;
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      state = strm.state;
      strm.total_in = strm.total_out = state.total = 0;
      strm.msg = "";
      if (state.wrap) {
        strm.adler = state.wrap & 1;
      }
      state.mode = HEAD;
      state.last = 0;
      state.havedict = 0;
      state.dmax = 32768;
      state.head = null;
      state.hold = 0;
      state.bits = 0;
      state.lencode = state.lendyn = new utils.Buf32(ENOUGH_LENS);
      state.distcode = state.distdyn = new utils.Buf32(ENOUGH_DISTS);
      state.sane = 1;
      state.back = -1;
      return Z_OK;
    }
    function inflateReset(strm) {
      var state;
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      state = strm.state;
      state.wsize = 0;
      state.whave = 0;
      state.wnext = 0;
      return inflateResetKeep(strm);
    }
    function inflateReset2(strm, windowBits) {
      var wrap;
      var state;
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      state = strm.state;
      if (windowBits < 0) {
        wrap = 0;
        windowBits = -windowBits;
      } else {
        wrap = (windowBits >> 4) + 1;
        if (windowBits < 48) {
          windowBits &= 15;
        }
      }
      if (windowBits && (windowBits < 8 || windowBits > 15)) {
        return Z_STREAM_ERROR;
      }
      if (state.window !== null && state.wbits !== windowBits) {
        state.window = null;
      }
      state.wrap = wrap;
      state.wbits = windowBits;
      return inflateReset(strm);
    }
    function inflateInit2(strm, windowBits) {
      var ret;
      var state;
      if (!strm) {
        return Z_STREAM_ERROR;
      }
      state = new InflateState;
      strm.state = state;
      state.window = null;
      ret = inflateReset2(strm, windowBits);
      if (ret !== Z_OK) {
        strm.state = null;
      }
      return ret;
    }
    function inflateInit(strm) {
      return inflateInit2(strm, DEF_WBITS);
    }
    var virgin = true;
    var lenfix, distfix;
    function fixedtables(state) {
      if (virgin) {
        var sym;
        lenfix = new utils.Buf32(512);
        distfix = new utils.Buf32(32);
        sym = 0;
        while (sym < 144) {
          state.lens[sym++] = 8;
        }
        while (sym < 256) {
          state.lens[sym++] = 9;
        }
        while (sym < 280) {
          state.lens[sym++] = 7;
        }
        while (sym < 288) {
          state.lens[sym++] = 8;
        }
        inflate_table(LENS, state.lens, 0, 288, lenfix, 0, state.work, {bits:9});
        sym = 0;
        while (sym < 32) {
          state.lens[sym++] = 5;
        }
        inflate_table(DISTS, state.lens, 0, 32, distfix, 0, state.work, {bits:5});
        virgin = false;
      }
      state.lencode = lenfix;
      state.lenbits = 9;
      state.distcode = distfix;
      state.distbits = 5;
    }
    function updatewindow(strm, src, end, copy) {
      var dist;
      var state = strm.state;
      if (state.window === null) {
        state.wsize = 1 << state.wbits;
        state.wnext = 0;
        state.whave = 0;
        state.window = new utils.Buf8(state.wsize);
      }
      if (copy >= state.wsize) {
        utils.arraySet(state.window, src, end - state.wsize, state.wsize, 0);
        state.wnext = 0;
        state.whave = state.wsize;
      } else {
        dist = state.wsize - state.wnext;
        if (dist > copy) {
          dist = copy;
        }
        utils.arraySet(state.window, src, end - copy, dist, state.wnext);
        copy -= dist;
        if (copy) {
          utils.arraySet(state.window, src, end - copy, copy, 0);
          state.wnext = copy;
          state.whave = state.wsize;
        } else {
          state.wnext += dist;
          if (state.wnext === state.wsize) {
            state.wnext = 0;
          }
          if (state.whave < state.wsize) {
            state.whave += dist;
          }
        }
      }
      return 0;
    }
    function inflate(strm, flush) {
      var state;
      var input, output;
      var next;
      var put;
      var have, left;
      var hold;
      var bits;
      var _in, _out;
      var copy;
      var from;
      var from_source;
      var here = 0;
      var here_bits, here_op, here_val;
      var last_bits, last_op, last_val;
      var len;
      var ret;
      var hbuf = new utils.Buf8(4);
      var opts;
      var n;
      var order = [16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15];
      if (!strm || !strm.state || !strm.output || !strm.input && strm.avail_in !== 0) {
        return Z_STREAM_ERROR;
      }
      state = strm.state;
      if (state.mode === TYPE) {
        state.mode = TYPEDO;
      }
      put = strm.next_out;
      output = strm.output;
      left = strm.avail_out;
      next = strm.next_in;
      input = strm.input;
      have = strm.avail_in;
      hold = state.hold;
      bits = state.bits;
      _in = have;
      _out = left;
      ret = Z_OK;
      inf_leave: for (;;) {
        switch(state.mode) {
          case HEAD:
            if (state.wrap === 0) {
              state.mode = TYPEDO;
              break;
            }
            while (bits < 16) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if (state.wrap & 2 && hold === 35615) {
              state.check = 0;
              hbuf[0] = hold & 255;
              hbuf[1] = hold >>> 8 & 255;
              state.check = crc32(state.check, hbuf, 2, 0);
              hold = 0;
              bits = 0;
              state.mode = FLAGS;
              break;
            }
            state.flags = 0;
            if (state.head) {
              state.head.done = false;
            }
            if (!(state.wrap & 1) || (((hold & 255) << 8) + (hold >> 8)) % 31) {
              strm.msg = "incorrect header check";
              state.mode = BAD;
              break;
            }
            if ((hold & 15) !== Z_DEFLATED) {
              strm.msg = "unknown compression method";
              state.mode = BAD;
              break;
            }
            hold >>>= 4;
            bits -= 4;
            len = (hold & 15) + 8;
            if (state.wbits === 0) {
              state.wbits = len;
            } else {
              if (len > state.wbits) {
                strm.msg = "invalid window size";
                state.mode = BAD;
                break;
              }
            }
            state.dmax = 1 << len;
            strm.adler = state.check = 1;
            state.mode = hold & 512 ? DICTID : TYPE;
            hold = 0;
            bits = 0;
            break;
          case FLAGS:
            while (bits < 16) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            state.flags = hold;
            if ((state.flags & 255) !== Z_DEFLATED) {
              strm.msg = "unknown compression method";
              state.mode = BAD;
              break;
            }
            if (state.flags & 57344) {
              strm.msg = "unknown header flags set";
              state.mode = BAD;
              break;
            }
            if (state.head) {
              state.head.text = hold >> 8 & 1;
            }
            if (state.flags & 512) {
              hbuf[0] = hold & 255;
              hbuf[1] = hold >>> 8 & 255;
              state.check = crc32(state.check, hbuf, 2, 0);
            }
            hold = 0;
            bits = 0;
            state.mode = TIME;
          case TIME:
            while (bits < 32) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if (state.head) {
              state.head.time = hold;
            }
            if (state.flags & 512) {
              hbuf[0] = hold & 255;
              hbuf[1] = hold >>> 8 & 255;
              hbuf[2] = hold >>> 16 & 255;
              hbuf[3] = hold >>> 24 & 255;
              state.check = crc32(state.check, hbuf, 4, 0);
            }
            hold = 0;
            bits = 0;
            state.mode = OS;
          case OS:
            while (bits < 16) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if (state.head) {
              state.head.xflags = hold & 255;
              state.head.os = hold >> 8;
            }
            if (state.flags & 512) {
              hbuf[0] = hold & 255;
              hbuf[1] = hold >>> 8 & 255;
              state.check = crc32(state.check, hbuf, 2, 0);
            }
            hold = 0;
            bits = 0;
            state.mode = EXLEN;
          case EXLEN:
            if (state.flags & 1024) {
              while (bits < 16) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              state.length = hold;
              if (state.head) {
                state.head.extra_len = hold;
              }
              if (state.flags & 512) {
                hbuf[0] = hold & 255;
                hbuf[1] = hold >>> 8 & 255;
                state.check = crc32(state.check, hbuf, 2, 0);
              }
              hold = 0;
              bits = 0;
            } else {
              if (state.head) {
                state.head.extra = null;
              }
            }
            state.mode = EXTRA;
          case EXTRA:
            if (state.flags & 1024) {
              copy = state.length;
              if (copy > have) {
                copy = have;
              }
              if (copy) {
                if (state.head) {
                  len = state.head.extra_len - state.length;
                  if (!state.head.extra) {
                    state.head.extra = new Array(state.head.extra_len);
                  }
                  utils.arraySet(state.head.extra, input, next, copy, len);
                }
                if (state.flags & 512) {
                  state.check = crc32(state.check, input, copy, next);
                }
                have -= copy;
                next += copy;
                state.length -= copy;
              }
              if (state.length) {
                break inf_leave;
              }
            }
            state.length = 0;
            state.mode = NAME;
          case NAME:
            if (state.flags & 2048) {
              if (have === 0) {
                break inf_leave;
              }
              copy = 0;
              do {
                len = input[next + copy++];
                if (state.head && len && state.length < 65536) {
                  state.head.name += String.fromCharCode(len);
                }
              } while (len && copy < have);
              if (state.flags & 512) {
                state.check = crc32(state.check, input, copy, next);
              }
              have -= copy;
              next += copy;
              if (len) {
                break inf_leave;
              }
            } else {
              if (state.head) {
                state.head.name = null;
              }
            }
            state.length = 0;
            state.mode = COMMENT;
          case COMMENT:
            if (state.flags & 4096) {
              if (have === 0) {
                break inf_leave;
              }
              copy = 0;
              do {
                len = input[next + copy++];
                if (state.head && len && state.length < 65536) {
                  state.head.comment += String.fromCharCode(len);
                }
              } while (len && copy < have);
              if (state.flags & 512) {
                state.check = crc32(state.check, input, copy, next);
              }
              have -= copy;
              next += copy;
              if (len) {
                break inf_leave;
              }
            } else {
              if (state.head) {
                state.head.comment = null;
              }
            }
            state.mode = HCRC;
          case HCRC:
            if (state.flags & 512) {
              while (bits < 16) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              if (hold !== (state.check & 65535)) {
                strm.msg = "header crc mismatch";
                state.mode = BAD;
                break;
              }
              hold = 0;
              bits = 0;
            }
            if (state.head) {
              state.head.hcrc = state.flags >> 9 & 1;
              state.head.done = true;
            }
            strm.adler = state.check = 0;
            state.mode = TYPE;
            break;
          case DICTID:
            while (bits < 32) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            strm.adler = state.check = ZSWAP32(hold);
            hold = 0;
            bits = 0;
            state.mode = DICT;
          case DICT:
            if (state.havedict === 0) {
              strm.next_out = put;
              strm.avail_out = left;
              strm.next_in = next;
              strm.avail_in = have;
              state.hold = hold;
              state.bits = bits;
              return Z_NEED_DICT;
            }
            strm.adler = state.check = 1;
            state.mode = TYPE;
          case TYPE:
            if (flush === Z_BLOCK || flush === Z_TREES) {
              break inf_leave;
            }
          ;
          case TYPEDO:
            if (state.last) {
              hold >>>= bits & 7;
              bits -= bits & 7;
              state.mode = CHECK;
              break;
            }
            while (bits < 3) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            state.last = hold & 1;
            hold >>>= 1;
            bits -= 1;
            switch(hold & 3) {
              case 0:
                state.mode = STORED;
                break;
              case 1:
                fixedtables(state);
                state.mode = LEN_;
                if (flush === Z_TREES) {
                  hold >>>= 2;
                  bits -= 2;
                  break inf_leave;
                }
                break;
              case 2:
                state.mode = TABLE;
                break;
              case 3:
                strm.msg = "invalid block type";
                state.mode = BAD;
            }
            hold >>>= 2;
            bits -= 2;
            break;
          case STORED:
            hold >>>= bits & 7;
            bits -= bits & 7;
            while (bits < 32) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if ((hold & 65535) !== (hold >>> 16 ^ 65535)) {
              strm.msg = "invalid stored block lengths";
              state.mode = BAD;
              break;
            }
            state.length = hold & 65535;
            hold = 0;
            bits = 0;
            state.mode = COPY_;
            if (flush === Z_TREES) {
              break inf_leave;
            }
          ;
          case COPY_:
            state.mode = COPY;
          case COPY:
            copy = state.length;
            if (copy) {
              if (copy > have) {
                copy = have;
              }
              if (copy > left) {
                copy = left;
              }
              if (copy === 0) {
                break inf_leave;
              }
              utils.arraySet(output, input, next, copy, put);
              have -= copy;
              next += copy;
              left -= copy;
              put += copy;
              state.length -= copy;
              break;
            }
            state.mode = TYPE;
            break;
          case TABLE:
            while (bits < 14) {
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            state.nlen = (hold & 31) + 257;
            hold >>>= 5;
            bits -= 5;
            state.ndist = (hold & 31) + 1;
            hold >>>= 5;
            bits -= 5;
            state.ncode = (hold & 15) + 4;
            hold >>>= 4;
            bits -= 4;
            if (state.nlen > 286 || state.ndist > 30) {
              strm.msg = "too many length or distance symbols";
              state.mode = BAD;
              break;
            }
            state.have = 0;
            state.mode = LENLENS;
          case LENLENS:
            while (state.have < state.ncode) {
              while (bits < 3) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              state.lens[order[state.have++]] = hold & 7;
              hold >>>= 3;
              bits -= 3;
            }
            while (state.have < 19) {
              state.lens[order[state.have++]] = 0;
            }
            state.lencode = state.lendyn;
            state.lenbits = 7;
            opts = {bits:state.lenbits};
            ret = inflate_table(CODES, state.lens, 0, 19, state.lencode, 0, state.work, opts);
            state.lenbits = opts.bits;
            if (ret) {
              strm.msg = "invalid code lengths set";
              state.mode = BAD;
              break;
            }
            state.have = 0;
            state.mode = CODELENS;
          case CODELENS:
            while (state.have < state.nlen + state.ndist) {
              for (;;) {
                here = state.lencode[hold & (1 << state.lenbits) - 1];
                here_bits = here >>> 24;
                here_op = here >>> 16 & 255;
                here_val = here & 65535;
                if (here_bits <= bits) {
                  break;
                }
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              if (here_val < 16) {
                hold >>>= here_bits;
                bits -= here_bits;
                state.lens[state.have++] = here_val;
              } else {
                if (here_val === 16) {
                  n = here_bits + 2;
                  while (bits < n) {
                    if (have === 0) {
                      break inf_leave;
                    }
                    have--;
                    hold += input[next++] << bits;
                    bits += 8;
                  }
                  hold >>>= here_bits;
                  bits -= here_bits;
                  if (state.have === 0) {
                    strm.msg = "invalid bit length repeat";
                    state.mode = BAD;
                    break;
                  }
                  len = state.lens[state.have - 1];
                  copy = 3 + (hold & 3);
                  hold >>>= 2;
                  bits -= 2;
                } else {
                  if (here_val === 17) {
                    n = here_bits + 3;
                    while (bits < n) {
                      if (have === 0) {
                        break inf_leave;
                      }
                      have--;
                      hold += input[next++] << bits;
                      bits += 8;
                    }
                    hold >>>= here_bits;
                    bits -= here_bits;
                    len = 0;
                    copy = 3 + (hold & 7);
                    hold >>>= 3;
                    bits -= 3;
                  } else {
                    n = here_bits + 7;
                    while (bits < n) {
                      if (have === 0) {
                        break inf_leave;
                      }
                      have--;
                      hold += input[next++] << bits;
                      bits += 8;
                    }
                    hold >>>= here_bits;
                    bits -= here_bits;
                    len = 0;
                    copy = 11 + (hold & 127);
                    hold >>>= 7;
                    bits -= 7;
                  }
                }
                if (state.have + copy > state.nlen + state.ndist) {
                  strm.msg = "invalid bit length repeat";
                  state.mode = BAD;
                  break;
                }
                while (copy--) {
                  state.lens[state.have++] = len;
                }
              }
            }
            if (state.mode === BAD) {
              break;
            }
            if (state.lens[256] === 0) {
              strm.msg = "invalid code -- missing end-of-block";
              state.mode = BAD;
              break;
            }
            state.lenbits = 9;
            opts = {bits:state.lenbits};
            ret = inflate_table(LENS, state.lens, 0, state.nlen, state.lencode, 0, state.work, opts);
            state.lenbits = opts.bits;
            if (ret) {
              strm.msg = "invalid literal/lengths set";
              state.mode = BAD;
              break;
            }
            state.distbits = 6;
            state.distcode = state.distdyn;
            opts = {bits:state.distbits};
            ret = inflate_table(DISTS, state.lens, state.nlen, state.ndist, state.distcode, 0, state.work, opts);
            state.distbits = opts.bits;
            if (ret) {
              strm.msg = "invalid distances set";
              state.mode = BAD;
              break;
            }
            state.mode = LEN_;
            if (flush === Z_TREES) {
              break inf_leave;
            }
          ;
          case LEN_:
            state.mode = LEN;
          case LEN:
            if (have >= 6 && left >= 258) {
              strm.next_out = put;
              strm.avail_out = left;
              strm.next_in = next;
              strm.avail_in = have;
              state.hold = hold;
              state.bits = bits;
              inflate_fast(strm, _out);
              put = strm.next_out;
              output = strm.output;
              left = strm.avail_out;
              next = strm.next_in;
              input = strm.input;
              have = strm.avail_in;
              hold = state.hold;
              bits = state.bits;
              if (state.mode === TYPE) {
                state.back = -1;
              }
              break;
            }
            state.back = 0;
            for (;;) {
              here = state.lencode[hold & (1 << state.lenbits) - 1];
              here_bits = here >>> 24;
              here_op = here >>> 16 & 255;
              here_val = here & 65535;
              if (here_bits <= bits) {
                break;
              }
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if (here_op && (here_op & 240) === 0) {
              last_bits = here_bits;
              last_op = here_op;
              last_val = here_val;
              for (;;) {
                here = state.lencode[last_val + ((hold & (1 << last_bits + last_op) - 1) >> last_bits)];
                here_bits = here >>> 24;
                here_op = here >>> 16 & 255;
                here_val = here & 65535;
                if (last_bits + here_bits <= bits) {
                  break;
                }
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              hold >>>= last_bits;
              bits -= last_bits;
              state.back += last_bits;
            }
            hold >>>= here_bits;
            bits -= here_bits;
            state.back += here_bits;
            state.length = here_val;
            if (here_op === 0) {
              state.mode = LIT;
              break;
            }
            if (here_op & 32) {
              state.back = -1;
              state.mode = TYPE;
              break;
            }
            if (here_op & 64) {
              strm.msg = "invalid literal/length code";
              state.mode = BAD;
              break;
            }
            state.extra = here_op & 15;
            state.mode = LENEXT;
          case LENEXT:
            if (state.extra) {
              n = state.extra;
              while (bits < n) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              state.length += hold & (1 << state.extra) - 1;
              hold >>>= state.extra;
              bits -= state.extra;
              state.back += state.extra;
            }
            state.was = state.length;
            state.mode = DIST;
          case DIST:
            for (;;) {
              here = state.distcode[hold & (1 << state.distbits) - 1];
              here_bits = here >>> 24;
              here_op = here >>> 16 & 255;
              here_val = here & 65535;
              if (here_bits <= bits) {
                break;
              }
              if (have === 0) {
                break inf_leave;
              }
              have--;
              hold += input[next++] << bits;
              bits += 8;
            }
            if ((here_op & 240) === 0) {
              last_bits = here_bits;
              last_op = here_op;
              last_val = here_val;
              for (;;) {
                here = state.distcode[last_val + ((hold & (1 << last_bits + last_op) - 1) >> last_bits)];
                here_bits = here >>> 24;
                here_op = here >>> 16 & 255;
                here_val = here & 65535;
                if (last_bits + here_bits <= bits) {
                  break;
                }
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              hold >>>= last_bits;
              bits -= last_bits;
              state.back += last_bits;
            }
            hold >>>= here_bits;
            bits -= here_bits;
            state.back += here_bits;
            if (here_op & 64) {
              strm.msg = "invalid distance code";
              state.mode = BAD;
              break;
            }
            state.offset = here_val;
            state.extra = here_op & 15;
            state.mode = DISTEXT;
          case DISTEXT:
            if (state.extra) {
              n = state.extra;
              while (bits < n) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              state.offset += hold & (1 << state.extra) - 1;
              hold >>>= state.extra;
              bits -= state.extra;
              state.back += state.extra;
            }
            if (state.offset > state.dmax) {
              strm.msg = "invalid distance too far back";
              state.mode = BAD;
              break;
            }
            state.mode = MATCH;
          case MATCH:
            if (left === 0) {
              break inf_leave;
            }
            copy = _out - left;
            if (state.offset > copy) {
              copy = state.offset - copy;
              if (copy > state.whave) {
                if (state.sane) {
                  strm.msg = "invalid distance too far back";
                  state.mode = BAD;
                  break;
                }
              }
              if (copy > state.wnext) {
                copy -= state.wnext;
                from = state.wsize - copy;
              } else {
                from = state.wnext - copy;
              }
              if (copy > state.length) {
                copy = state.length;
              }
              from_source = state.window;
            } else {
              from_source = output;
              from = put - state.offset;
              copy = state.length;
            }
            if (copy > left) {
              copy = left;
            }
            left -= copy;
            state.length -= copy;
            do {
              output[put++] = from_source[from++];
            } while (--copy);
            if (state.length === 0) {
              state.mode = LEN;
            }
            break;
          case LIT:
            if (left === 0) {
              break inf_leave;
            }
            output[put++] = state.length;
            left--;
            state.mode = LEN;
            break;
          case CHECK:
            if (state.wrap) {
              while (bits < 32) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold |= input[next++] << bits;
                bits += 8;
              }
              _out -= left;
              strm.total_out += _out;
              state.total += _out;
              if (_out) {
                strm.adler = state.check = state.flags ? crc32(state.check, output, _out, put - _out) : adler32(state.check, output, _out, put - _out);
              }
              _out = left;
              if ((state.flags ? hold : ZSWAP32(hold)) !== state.check) {
                strm.msg = "incorrect data check";
                state.mode = BAD;
                break;
              }
              hold = 0;
              bits = 0;
            }
            state.mode = LENGTH;
          case LENGTH:
            if (state.wrap && state.flags) {
              while (bits < 32) {
                if (have === 0) {
                  break inf_leave;
                }
                have--;
                hold += input[next++] << bits;
                bits += 8;
              }
              if (hold !== (state.total & 4294967295)) {
                strm.msg = "incorrect length check";
                state.mode = BAD;
                break;
              }
              hold = 0;
              bits = 0;
            }
            state.mode = DONE;
          case DONE:
            ret = Z_STREAM_END;
            break inf_leave;
          case BAD:
            ret = Z_DATA_ERROR;
            break inf_leave;
          case MEM:
            return Z_MEM_ERROR;
          case SYNC:
          ;
          default:
            return Z_STREAM_ERROR;
        }
      }
      strm.next_out = put;
      strm.avail_out = left;
      strm.next_in = next;
      strm.avail_in = have;
      state.hold = hold;
      state.bits = bits;
      if (state.wsize || _out !== strm.avail_out && state.mode < BAD && (state.mode < CHECK || flush !== Z_FINISH)) {
        if (updatewindow(strm, strm.output, strm.next_out, _out - strm.avail_out)) {
          state.mode = MEM;
          return Z_MEM_ERROR;
        }
      }
      _in -= strm.avail_in;
      _out -= strm.avail_out;
      strm.total_in += _in;
      strm.total_out += _out;
      state.total += _out;
      if (state.wrap && _out) {
        strm.adler = state.check = state.flags ? crc32(state.check, output, _out, strm.next_out - _out) : adler32(state.check, output, _out, strm.next_out - _out);
      }
      strm.data_type = state.bits + (state.last ? 64 : 0) + (state.mode === TYPE ? 128 : 0) + (state.mode === LEN_ || state.mode === COPY_ ? 256 : 0);
      if ((_in === 0 && _out === 0 || flush === Z_FINISH) && ret === Z_OK) {
        ret = Z_BUF_ERROR;
      }
      return ret;
    }
    function inflateEnd(strm) {
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      var state = strm.state;
      if (state.window) {
        state.window = null;
      }
      strm.state = null;
      return Z_OK;
    }
    function inflateGetHeader(strm, head) {
      var state;
      if (!strm || !strm.state) {
        return Z_STREAM_ERROR;
      }
      state = strm.state;
      if ((state.wrap & 2) === 0) {
        return Z_STREAM_ERROR;
      }
      state.head = head;
      head.done = false;
      return Z_OK;
    }
    exports.inflateReset = inflateReset;
    exports.inflateReset2 = inflateReset2;
    exports.inflateResetKeep = inflateResetKeep;
    exports.inflateInit = inflateInit;
    exports.inflateInit2 = inflateInit2;
    exports.inflate = inflate;
    exports.inflateEnd = inflateEnd;
    exports.inflateGetHeader = inflateGetHeader;
    exports.inflateInfo = "pako inflate (from Nodeca project)";
  }, {"../utils/common":27, "./adler32":29, "./crc32":31, "./inffast":34, "./inftrees":36}], 36:[function(_dereq_, module, exports) {
    var utils = _dereq_("../utils/common");
    var MAXBITS = 15;
    var ENOUGH_LENS = 852;
    var ENOUGH_DISTS = 592;
    var CODES = 0;
    var LENS = 1;
    var DISTS = 2;
    var lbase = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31, 35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258, 0, 0];
    var lext = [16, 16, 16, 16, 16, 16, 16, 16, 17, 17, 17, 17, 18, 18, 18, 18, 19, 19, 19, 19, 20, 20, 20, 20, 21, 21, 21, 21, 16, 72, 78];
    var dbase = [1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193, 257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145, 8193, 12289, 16385, 24577, 0, 0];
    var dext = [16, 16, 16, 16, 17, 17, 18, 18, 19, 19, 20, 20, 21, 21, 22, 22, 23, 23, 24, 24, 25, 25, 26, 26, 27, 27, 28, 28, 29, 29, 64, 64];
    module.exports = function inflate_table(type, lens, lens_index, codes, table, table_index, work, opts) {
      var bits = opts.bits;
      var len = 0;
      var sym = 0;
      var min = 0, max = 0;
      var root = 0;
      var curr = 0;
      var drop = 0;
      var left = 0;
      var used = 0;
      var huff = 0;
      var incr;
      var fill;
      var low;
      var mask;
      var next;
      var base = null;
      var base_index = 0;
      var end;
      var count = new utils.Buf16(MAXBITS + 1);
      var offs = new utils.Buf16(MAXBITS + 1);
      var extra = null;
      var extra_index = 0;
      var here_bits, here_op, here_val;
      for (len = 0;len <= MAXBITS;len++) {
        count[len] = 0;
      }
      for (sym = 0;sym < codes;sym++) {
        count[lens[lens_index + sym]]++;
      }
      root = bits;
      for (max = MAXBITS;max >= 1;max--) {
        if (count[max] !== 0) {
          break;
        }
      }
      if (root > max) {
        root = max;
      }
      if (max === 0) {
        table[table_index++] = 1 << 24 | 64 << 16 | 0;
        table[table_index++] = 1 << 24 | 64 << 16 | 0;
        opts.bits = 1;
        return 0;
      }
      for (min = 1;min < max;min++) {
        if (count[min] !== 0) {
          break;
        }
      }
      if (root < min) {
        root = min;
      }
      left = 1;
      for (len = 1;len <= MAXBITS;len++) {
        left <<= 1;
        left -= count[len];
        if (left < 0) {
          return -1;
        }
      }
      if (left > 0 && (type === CODES || max !== 1)) {
        return -1;
      }
      offs[1] = 0;
      for (len = 1;len < MAXBITS;len++) {
        offs[len + 1] = offs[len] + count[len];
      }
      for (sym = 0;sym < codes;sym++) {
        if (lens[lens_index + sym] !== 0) {
          work[offs[lens[lens_index + sym]]++] = sym;
        }
      }
      if (type === CODES) {
        base = extra = work;
        end = 19;
      } else {
        if (type === LENS) {
          base = lbase;
          base_index -= 257;
          extra = lext;
          extra_index -= 257;
          end = 256;
        } else {
          base = dbase;
          extra = dext;
          end = -1;
        }
      }
      huff = 0;
      sym = 0;
      len = min;
      next = table_index;
      curr = root;
      drop = 0;
      low = -1;
      used = 1 << root;
      mask = used - 1;
      if (type === LENS && used > ENOUGH_LENS || type === DISTS && used > ENOUGH_DISTS) {
        return 1;
      }
      var i = 0;
      for (;;) {
        i++;
        here_bits = len - drop;
        if (work[sym] < end) {
          here_op = 0;
          here_val = work[sym];
        } else {
          if (work[sym] > end) {
            here_op = extra[extra_index + work[sym]];
            here_val = base[base_index + work[sym]];
          } else {
            here_op = 32 + 64;
            here_val = 0;
          }
        }
        incr = 1 << len - drop;
        fill = 1 << curr;
        min = fill;
        do {
          fill -= incr;
          table[next + (huff >> drop) + fill] = here_bits << 24 | here_op << 16 | here_val | 0;
        } while (fill !== 0);
        incr = 1 << len - 1;
        while (huff & incr) {
          incr >>= 1;
        }
        if (incr !== 0) {
          huff &= incr - 1;
          huff += incr;
        } else {
          huff = 0;
        }
        sym++;
        if (--count[len] === 0) {
          if (len === max) {
            break;
          }
          len = lens[lens_index + work[sym]];
        }
        if (len > root && (huff & mask) !== low) {
          if (drop === 0) {
            drop = root;
          }
          next += min;
          curr = len - drop;
          left = 1 << curr;
          while (curr + drop < max) {
            left -= count[curr + drop];
            if (left <= 0) {
              break;
            }
            curr++;
            left <<= 1;
          }
          used += 1 << curr;
          if (type === LENS && used > ENOUGH_LENS || type === DISTS && used > ENOUGH_DISTS) {
            return 1;
          }
          low = huff & mask;
          table[low] = root << 24 | curr << 16 | next - table_index | 0;
        }
      }
      if (huff !== 0) {
        table[next + huff] = len - drop << 24 | 64 << 16 | 0;
      }
      opts.bits = root;
      return 0;
    };
  }, {"../utils/common":27}], 37:[function(_dereq_, module, exports) {
    module.exports = {2:"need dictionary", 1:"stream end", 0:"", "-1":"file error", "-2":"stream error", "-3":"data error", "-4":"insufficient memory", "-5":"buffer error", "-6":"incompatible version"};
  }, {}], 38:[function(_dereq_, module, exports) {
    var utils = _dereq_("../utils/common");
    var Z_FIXED = 4;
    var Z_BINARY = 0;
    var Z_TEXT = 1;
    var Z_UNKNOWN = 2;
    function zero(buf) {
      var len = buf.length;
      while (--len >= 0) {
        buf[len] = 0;
      }
    }
    var STORED_BLOCK = 0;
    var STATIC_TREES = 1;
    var DYN_TREES = 2;
    var MIN_MATCH = 3;
    var MAX_MATCH = 258;
    var LENGTH_CODES = 29;
    var LITERALS = 256;
    var L_CODES = LITERALS + 1 + LENGTH_CODES;
    var D_CODES = 30;
    var BL_CODES = 19;
    var HEAP_SIZE = 2 * L_CODES + 1;
    var MAX_BITS = 15;
    var Buf_size = 16;
    var MAX_BL_BITS = 7;
    var END_BLOCK = 256;
    var REP_3_6 = 16;
    var REPZ_3_10 = 17;
    var REPZ_11_138 = 18;
    var extra_lbits = [0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0];
    var extra_dbits = [0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13];
    var extra_blbits = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 3, 7];
    var bl_order = [16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15];
    var DIST_CODE_LEN = 512;
    var static_ltree = new Array((L_CODES + 2) * 2);
    zero(static_ltree);
    var static_dtree = new Array(D_CODES * 2);
    zero(static_dtree);
    var _dist_code = new Array(DIST_CODE_LEN);
    zero(_dist_code);
    var _length_code = new Array(MAX_MATCH - MIN_MATCH + 1);
    zero(_length_code);
    var base_length = new Array(LENGTH_CODES);
    zero(base_length);
    var base_dist = new Array(D_CODES);
    zero(base_dist);
    var StaticTreeDesc = function(static_tree, extra_bits, extra_base, elems, max_length) {
      this.static_tree = static_tree;
      this.extra_bits = extra_bits;
      this.extra_base = extra_base;
      this.elems = elems;
      this.max_length = max_length;
      this.has_stree = static_tree && static_tree.length;
    };
    var static_l_desc;
    var static_d_desc;
    var static_bl_desc;
    var TreeDesc = function(dyn_tree, stat_desc) {
      this.dyn_tree = dyn_tree;
      this.max_code = 0;
      this.stat_desc = stat_desc;
    };
    function d_code(dist) {
      return dist < 256 ? _dist_code[dist] : _dist_code[256 + (dist >>> 7)];
    }
    function put_short(s, w) {
      s.pending_buf[s.pending++] = w & 255;
      s.pending_buf[s.pending++] = w >>> 8 & 255;
    }
    function send_bits(s, value, length) {
      if (s.bi_valid > Buf_size - length) {
        s.bi_buf |= value << s.bi_valid & 65535;
        put_short(s, s.bi_buf);
        s.bi_buf = value >> Buf_size - s.bi_valid;
        s.bi_valid += length - Buf_size;
      } else {
        s.bi_buf |= value << s.bi_valid & 65535;
        s.bi_valid += length;
      }
    }
    function send_code(s, c, tree) {
      send_bits(s, tree[c * 2], tree[c * 2 + 1]);
    }
    function bi_reverse(code, len) {
      var res = 0;
      do {
        res |= code & 1;
        code >>>= 1;
        res <<= 1;
      } while (--len > 0);
      return res >>> 1;
    }
    function bi_flush(s) {
      if (s.bi_valid === 16) {
        put_short(s, s.bi_buf);
        s.bi_buf = 0;
        s.bi_valid = 0;
      } else {
        if (s.bi_valid >= 8) {
          s.pending_buf[s.pending++] = s.bi_buf & 255;
          s.bi_buf >>= 8;
          s.bi_valid -= 8;
        }
      }
    }
    function gen_bitlen(s, desc) {
      var tree = desc.dyn_tree;
      var max_code = desc.max_code;
      var stree = desc.stat_desc.static_tree;
      var has_stree = desc.stat_desc.has_stree;
      var extra = desc.stat_desc.extra_bits;
      var base = desc.stat_desc.extra_base;
      var max_length = desc.stat_desc.max_length;
      var h;
      var n, m;
      var bits;
      var xbits;
      var f;
      var overflow = 0;
      for (bits = 0;bits <= MAX_BITS;bits++) {
        s.bl_count[bits] = 0;
      }
      tree[s.heap[s.heap_max] * 2 + 1] = 0;
      for (h = s.heap_max + 1;h < HEAP_SIZE;h++) {
        n = s.heap[h];
        bits = tree[tree[n * 2 + 1] * 2 + 1] + 1;
        if (bits > max_length) {
          bits = max_length;
          overflow++;
        }
        tree[n * 2 + 1] = bits;
        if (n > max_code) {
          continue;
        }
        s.bl_count[bits]++;
        xbits = 0;
        if (n >= base) {
          xbits = extra[n - base];
        }
        f = tree[n * 2];
        s.opt_len += f * (bits + xbits);
        if (has_stree) {
          s.static_len += f * (stree[n * 2 + 1] + xbits);
        }
      }
      if (overflow === 0) {
        return;
      }
      do {
        bits = max_length - 1;
        while (s.bl_count[bits] === 0) {
          bits--;
        }
        s.bl_count[bits]--;
        s.bl_count[bits + 1] += 2;
        s.bl_count[max_length]--;
        overflow -= 2;
      } while (overflow > 0);
      for (bits = max_length;bits !== 0;bits--) {
        n = s.bl_count[bits];
        while (n !== 0) {
          m = s.heap[--h];
          if (m > max_code) {
            continue;
          }
          if (tree[m * 2 + 1] !== bits) {
            s.opt_len += (bits - tree[m * 2 + 1]) * tree[m * 2];
            tree[m * 2 + 1] = bits;
          }
          n--;
        }
      }
    }
    function gen_codes(tree, max_code, bl_count) {
      var next_code = new Array(MAX_BITS + 1);
      var code = 0;
      var bits;
      var n;
      for (bits = 1;bits <= MAX_BITS;bits++) {
        next_code[bits] = code = code + bl_count[bits - 1] << 1;
      }
      for (n = 0;n <= max_code;n++) {
        var len = tree[n * 2 + 1];
        if (len === 0) {
          continue;
        }
        tree[n * 2] = bi_reverse(next_code[len]++, len);
      }
    }
    function tr_static_init() {
      var n;
      var bits;
      var length;
      var code;
      var dist;
      var bl_count = new Array(MAX_BITS + 1);
      length = 0;
      for (code = 0;code < LENGTH_CODES - 1;code++) {
        base_length[code] = length;
        for (n = 0;n < 1 << extra_lbits[code];n++) {
          _length_code[length++] = code;
        }
      }
      _length_code[length - 1] = code;
      dist = 0;
      for (code = 0;code < 16;code++) {
        base_dist[code] = dist;
        for (n = 0;n < 1 << extra_dbits[code];n++) {
          _dist_code[dist++] = code;
        }
      }
      dist >>= 7;
      for (;code < D_CODES;code++) {
        base_dist[code] = dist << 7;
        for (n = 0;n < 1 << extra_dbits[code] - 7;n++) {
          _dist_code[256 + dist++] = code;
        }
      }
      for (bits = 0;bits <= MAX_BITS;bits++) {
        bl_count[bits] = 0;
      }
      n = 0;
      while (n <= 143) {
        static_ltree[n * 2 + 1] = 8;
        n++;
        bl_count[8]++;
      }
      while (n <= 255) {
        static_ltree[n * 2 + 1] = 9;
        n++;
        bl_count[9]++;
      }
      while (n <= 279) {
        static_ltree[n * 2 + 1] = 7;
        n++;
        bl_count[7]++;
      }
      while (n <= 287) {
        static_ltree[n * 2 + 1] = 8;
        n++;
        bl_count[8]++;
      }
      gen_codes(static_ltree, L_CODES + 1, bl_count);
      for (n = 0;n < D_CODES;n++) {
        static_dtree[n * 2 + 1] = 5;
        static_dtree[n * 2] = bi_reverse(n, 5);
      }
      static_l_desc = new StaticTreeDesc(static_ltree, extra_lbits, LITERALS + 1, L_CODES, MAX_BITS);
      static_d_desc = new StaticTreeDesc(static_dtree, extra_dbits, 0, D_CODES, MAX_BITS);
      static_bl_desc = new StaticTreeDesc(new Array(0), extra_blbits, 0, BL_CODES, MAX_BL_BITS);
    }
    function init_block(s) {
      var n;
      for (n = 0;n < L_CODES;n++) {
        s.dyn_ltree[n * 2] = 0;
      }
      for (n = 0;n < D_CODES;n++) {
        s.dyn_dtree[n * 2] = 0;
      }
      for (n = 0;n < BL_CODES;n++) {
        s.bl_tree[n * 2] = 0;
      }
      s.dyn_ltree[END_BLOCK * 2] = 1;
      s.opt_len = s.static_len = 0;
      s.last_lit = s.matches = 0;
    }
    function bi_windup(s) {
      if (s.bi_valid > 8) {
        put_short(s, s.bi_buf);
      } else {
        if (s.bi_valid > 0) {
          s.pending_buf[s.pending++] = s.bi_buf;
        }
      }
      s.bi_buf = 0;
      s.bi_valid = 0;
    }
    function copy_block(s, buf, len, header) {
      bi_windup(s);
      if (header) {
        put_short(s, len);
        put_short(s, ~len);
      }
      utils.arraySet(s.pending_buf, s.window, buf, len, s.pending);
      s.pending += len;
    }
    function smaller(tree, n, m, depth) {
      var _n2 = n * 2;
      var _m2 = m * 2;
      return tree[_n2] < tree[_m2] || tree[_n2] === tree[_m2] && depth[n] <= depth[m];
    }
    function pqdownheap(s, tree, k) {
      var v = s.heap[k];
      var j = k << 1;
      while (j <= s.heap_len) {
        if (j < s.heap_len && smaller(tree, s.heap[j + 1], s.heap[j], s.depth)) {
          j++;
        }
        if (smaller(tree, v, s.heap[j], s.depth)) {
          break;
        }
        s.heap[k] = s.heap[j];
        k = j;
        j <<= 1;
      }
      s.heap[k] = v;
    }
    function compress_block(s, ltree, dtree) {
      var dist;
      var lc;
      var lx = 0;
      var code;
      var extra;
      if (s.last_lit !== 0) {
        do {
          dist = s.pending_buf[s.d_buf + lx * 2] << 8 | s.pending_buf[s.d_buf + lx * 2 + 1];
          lc = s.pending_buf[s.l_buf + lx];
          lx++;
          if (dist === 0) {
            send_code(s, lc, ltree);
          } else {
            code = _length_code[lc];
            send_code(s, code + LITERALS + 1, ltree);
            extra = extra_lbits[code];
            if (extra !== 0) {
              lc -= base_length[code];
              send_bits(s, lc, extra);
            }
            dist--;
            code = d_code(dist);
            send_code(s, code, dtree);
            extra = extra_dbits[code];
            if (extra !== 0) {
              dist -= base_dist[code];
              send_bits(s, dist, extra);
            }
          }
        } while (lx < s.last_lit);
      }
      send_code(s, END_BLOCK, ltree);
    }
    function build_tree(s, desc) {
      var tree = desc.dyn_tree;
      var stree = desc.stat_desc.static_tree;
      var has_stree = desc.stat_desc.has_stree;
      var elems = desc.stat_desc.elems;
      var n, m;
      var max_code = -1;
      var node;
      s.heap_len = 0;
      s.heap_max = HEAP_SIZE;
      for (n = 0;n < elems;n++) {
        if (tree[n * 2] !== 0) {
          s.heap[++s.heap_len] = max_code = n;
          s.depth[n] = 0;
        } else {
          tree[n * 2 + 1] = 0;
        }
      }
      while (s.heap_len < 2) {
        node = s.heap[++s.heap_len] = max_code < 2 ? ++max_code : 0;
        tree[node * 2] = 1;
        s.depth[node] = 0;
        s.opt_len--;
        if (has_stree) {
          s.static_len -= stree[node * 2 + 1];
        }
      }
      desc.max_code = max_code;
      for (n = s.heap_len >> 1;n >= 1;n--) {
        pqdownheap(s, tree, n);
      }
      node = elems;
      do {
        n = s.heap[1];
        s.heap[1] = s.heap[s.heap_len--];
        pqdownheap(s, tree, 1);
        m = s.heap[1];
        s.heap[--s.heap_max] = n;
        s.heap[--s.heap_max] = m;
        tree[node * 2] = tree[n * 2] + tree[m * 2];
        s.depth[node] = (s.depth[n] >= s.depth[m] ? s.depth[n] : s.depth[m]) + 1;
        tree[n * 2 + 1] = tree[m * 2 + 1] = node;
        s.heap[1] = node++;
        pqdownheap(s, tree, 1);
      } while (s.heap_len >= 2);
      s.heap[--s.heap_max] = s.heap[1];
      gen_bitlen(s, desc);
      gen_codes(tree, max_code, s.bl_count);
    }
    function scan_tree(s, tree, max_code) {
      var n;
      var prevlen = -1;
      var curlen;
      var nextlen = tree[0 * 2 + 1];
      var count = 0;
      var max_count = 7;
      var min_count = 4;
      if (nextlen === 0) {
        max_count = 138;
        min_count = 3;
      }
      tree[(max_code + 1) * 2 + 1] = 65535;
      for (n = 0;n <= max_code;n++) {
        curlen = nextlen;
        nextlen = tree[(n + 1) * 2 + 1];
        if (++count < max_count && curlen === nextlen) {
          continue;
        } else {
          if (count < min_count) {
            s.bl_tree[curlen * 2] += count;
          } else {
            if (curlen !== 0) {
              if (curlen !== prevlen) {
                s.bl_tree[curlen * 2]++;
              }
              s.bl_tree[REP_3_6 * 2]++;
            } else {
              if (count <= 10) {
                s.bl_tree[REPZ_3_10 * 2]++;
              } else {
                s.bl_tree[REPZ_11_138 * 2]++;
              }
            }
          }
        }
        count = 0;
        prevlen = curlen;
        if (nextlen === 0) {
          max_count = 138;
          min_count = 3;
        } else {
          if (curlen === nextlen) {
            max_count = 6;
            min_count = 3;
          } else {
            max_count = 7;
            min_count = 4;
          }
        }
      }
    }
    function send_tree(s, tree, max_code) {
      var n;
      var prevlen = -1;
      var curlen;
      var nextlen = tree[0 * 2 + 1];
      var count = 0;
      var max_count = 7;
      var min_count = 4;
      if (nextlen === 0) {
        max_count = 138;
        min_count = 3;
      }
      for (n = 0;n <= max_code;n++) {
        curlen = nextlen;
        nextlen = tree[(n + 1) * 2 + 1];
        if (++count < max_count && curlen === nextlen) {
          continue;
        } else {
          if (count < min_count) {
            do {
              send_code(s, curlen, s.bl_tree);
            } while (--count !== 0);
          } else {
            if (curlen !== 0) {
              if (curlen !== prevlen) {
                send_code(s, curlen, s.bl_tree);
                count--;
              }
              send_code(s, REP_3_6, s.bl_tree);
              send_bits(s, count - 3, 2);
            } else {
              if (count <= 10) {
                send_code(s, REPZ_3_10, s.bl_tree);
                send_bits(s, count - 3, 3);
              } else {
                send_code(s, REPZ_11_138, s.bl_tree);
                send_bits(s, count - 11, 7);
              }
            }
          }
        }
        count = 0;
        prevlen = curlen;
        if (nextlen === 0) {
          max_count = 138;
          min_count = 3;
        } else {
          if (curlen === nextlen) {
            max_count = 6;
            min_count = 3;
          } else {
            max_count = 7;
            min_count = 4;
          }
        }
      }
    }
    function build_bl_tree(s) {
      var max_blindex;
      scan_tree(s, s.dyn_ltree, s.l_desc.max_code);
      scan_tree(s, s.dyn_dtree, s.d_desc.max_code);
      build_tree(s, s.bl_desc);
      for (max_blindex = BL_CODES - 1;max_blindex >= 3;max_blindex--) {
        if (s.bl_tree[bl_order[max_blindex] * 2 + 1] !== 0) {
          break;
        }
      }
      s.opt_len += 3 * (max_blindex + 1) + 5 + 5 + 4;
      return max_blindex;
    }
    function send_all_trees(s, lcodes, dcodes, blcodes) {
      var rank;
      send_bits(s, lcodes - 257, 5);
      send_bits(s, dcodes - 1, 5);
      send_bits(s, blcodes - 4, 4);
      for (rank = 0;rank < blcodes;rank++) {
        send_bits(s, s.bl_tree[bl_order[rank] * 2 + 1], 3);
      }
      send_tree(s, s.dyn_ltree, lcodes - 1);
      send_tree(s, s.dyn_dtree, dcodes - 1);
    }
    function detect_data_type(s) {
      var black_mask = 4093624447;
      var n;
      for (n = 0;n <= 31;n++, black_mask >>>= 1) {
        if (black_mask & 1 && s.dyn_ltree[n * 2] !== 0) {
          return Z_BINARY;
        }
      }
      if (s.dyn_ltree[9 * 2] !== 0 || s.dyn_ltree[10 * 2] !== 0 || s.dyn_ltree[13 * 2] !== 0) {
        return Z_TEXT;
      }
      for (n = 32;n < LITERALS;n++) {
        if (s.dyn_ltree[n * 2] !== 0) {
          return Z_TEXT;
        }
      }
      return Z_BINARY;
    }
    var static_init_done = false;
    function _tr_init(s) {
      if (!static_init_done) {
        tr_static_init();
        static_init_done = true;
      }
      s.l_desc = new TreeDesc(s.dyn_ltree, static_l_desc);
      s.d_desc = new TreeDesc(s.dyn_dtree, static_d_desc);
      s.bl_desc = new TreeDesc(s.bl_tree, static_bl_desc);
      s.bi_buf = 0;
      s.bi_valid = 0;
      init_block(s);
    }
    function _tr_stored_block(s, buf, stored_len, last) {
      send_bits(s, (STORED_BLOCK << 1) + (last ? 1 : 0), 3);
      copy_block(s, buf, stored_len, true);
    }
    function _tr_align(s) {
      send_bits(s, STATIC_TREES << 1, 3);
      send_code(s, END_BLOCK, static_ltree);
      bi_flush(s);
    }
    function _tr_flush_block(s, buf, stored_len, last) {
      var opt_lenb, static_lenb;
      var max_blindex = 0;
      if (s.level > 0) {
        if (s.strm.data_type === Z_UNKNOWN) {
          s.strm.data_type = detect_data_type(s);
        }
        build_tree(s, s.l_desc);
        build_tree(s, s.d_desc);
        max_blindex = build_bl_tree(s);
        opt_lenb = s.opt_len + 3 + 7 >>> 3;
        static_lenb = s.static_len + 3 + 7 >>> 3;
        if (static_lenb <= opt_lenb) {
          opt_lenb = static_lenb;
        }
      } else {
        opt_lenb = static_lenb = stored_len + 5;
      }
      if (stored_len + 4 <= opt_lenb && buf !== -1) {
        _tr_stored_block(s, buf, stored_len, last);
      } else {
        if (s.strategy === Z_FIXED || static_lenb === opt_lenb) {
          send_bits(s, (STATIC_TREES << 1) + (last ? 1 : 0), 3);
          compress_block(s, static_ltree, static_dtree);
        } else {
          send_bits(s, (DYN_TREES << 1) + (last ? 1 : 0), 3);
          send_all_trees(s, s.l_desc.max_code + 1, s.d_desc.max_code + 1, max_blindex + 1);
          compress_block(s, s.dyn_ltree, s.dyn_dtree);
        }
      }
      init_block(s);
      if (last) {
        bi_windup(s);
      }
    }
    function _tr_tally(s, dist, lc) {
      s.pending_buf[s.d_buf + s.last_lit * 2] = dist >>> 8 & 255;
      s.pending_buf[s.d_buf + s.last_lit * 2 + 1] = dist & 255;
      s.pending_buf[s.l_buf + s.last_lit] = lc & 255;
      s.last_lit++;
      if (dist === 0) {
        s.dyn_ltree[lc * 2]++;
      } else {
        s.matches++;
        dist--;
        s.dyn_ltree[(_length_code[lc] + LITERALS + 1) * 2]++;
        s.dyn_dtree[d_code(dist) * 2]++;
      }
      return s.last_lit === s.lit_bufsize - 1;
    }
    exports._tr_init = _tr_init;
    exports._tr_stored_block = _tr_stored_block;
    exports._tr_flush_block = _tr_flush_block;
    exports._tr_tally = _tr_tally;
    exports._tr_align = _tr_align;
  }, {"../utils/common":27}], 39:[function(_dereq_, module, exports) {
    function ZStream() {
      this.input = null;
      this.next_in = 0;
      this.avail_in = 0;
      this.total_in = 0;
      this.output = null;
      this.next_out = 0;
      this.avail_out = 0;
      this.total_out = 0;
      this.msg = "";
      this.state = null;
      this.data_type = 2;
      this.adler = 0;
    }
    module.exports = ZStream;
  }, {}]}, {}, [9])(9);
});

