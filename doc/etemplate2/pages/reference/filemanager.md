## Filemanager (Vfs) widgets

These widgets all live under `api/js/etemplate/Et2Vfs/` and share the Virtual File System (Vfs)
layer, but they're spread across several sidebar categories by *purpose* (some are inputs, some
are read-only display) rather than grouped as their own category - a widget's "Belongs to:
Filemanager" tag is how you find its siblings regardless of which purpose bucket it landed in.

Not every Vfs-related widget is listed here: sub-widgets that only ever exist to serve one specific
parent (like `Et2VfsSelectRow`, used exclusively inside `Et2VfsSelectDialog`) are documented on
their parent's page instead, since they're never placed independently.

### Widgets in this group

{% for c in components %}
{% if c.belongsTo == "Filemanager" %}
- [{{ c.name | classNameToComponentName }}](/components/{{ c.tagName }}/)
{% endif %}
{% endfor %}
