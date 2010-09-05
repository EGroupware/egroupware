= importexport =

Importexport is a framework for egroupware to handle imports and exports. 
The idea behind importexport is to have a common userinterface in all apps 
regarding import and export stuff AND to have common backends whitch 
handle the stuff. Importexport can nothing without the plugins of the 
applications and its specific definitions.

== plugins ==
Attending importeport framework with you application is pretty easy.

You just need to have your plugins in files which start with 
class.import_<name> or
class.export_<name>
in 
EGW_INCLUDE_ROOT/YourApp/importexport/

These pulugins only need to implement the corresponding interface
EGW_INCLUDE_ROOT/importexport/inc/class.iface_import_plugin.inc.php or
EGW_INCLUDE_ROOT/importexport/inc/class.iface_export_plugin.inc.php

Thats all, pretty easy, isn't it?

== definitions ==
The bases of all imports and exports is the '''definition'''.

A definition defines all nessesary parameters to perform the desired action.
Moreover definitions can be stored and thus the same import / export can be redone
by loading the definition. Definitions are also reachable by the importexport 
'''command line interface'''.

An important point is, that the ACLs for import/export actions are given by the definitions. 
That means, that your plugin can not work w.o. a definition. However, your plugin don't
need to parse that definition. This is up to you.

Definitions can be created in admin->importexport->define{im|ex}ports. They are stored 
in the databasae but could be {im|ex}ported itselve. 

Definitions (as xml files) residing in the folder <yourapp/importexport/definitions> 
will be imported at apps installation time automatically.


== import ==

== export ==
Starting an export is as easy as just putting a button in your app with: 
onClick="importexport.uiexport.export_dialog&appname=<appname>&have_selection=<{true|false}>"
If parameter "have_selection" if true, export askes (javascript) opener.get_selection();to retreave
a list of identifiers of selected records.

NOTE: javascript function get_selection() is the only function which is not part of an interface yet.

==Discussion of interfaces==
To make live easy there are several general plugins which can be found 
EGW_INCLUDE_ROOT/importexport/inc/import_...
EGW_INCLUDE_ROOT/importexport/inc/export_...



