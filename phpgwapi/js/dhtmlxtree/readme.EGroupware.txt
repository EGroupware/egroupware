EGroupware uses a different directory structure from current dhtmlxTree packages:

phpgwapi/js/dhtmlxtree/
+- css
|  +- dhtmlxtree.css   --> dhtmlxTree/codebase/dhtmlxtree.css
+- js
   +- dhtmlxcommon.js  --> dhtmlxTree/codebase/dhtmlcommon.js
   +- dhtmlxtree.js    --> dhtmlxTree/codebase/dhtmlxtree.js
   +- ext              --> dhtmlxTree/codebase/ext
      +- ...

These files/directories are copied from dhtmlxTree/codebase to not modify all code.

If dhtmlxtree get updated, these files have to be copied again, or code need to be changed.
