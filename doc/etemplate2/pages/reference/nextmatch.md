## Nextmatch widgets

Nextmatch (`api/js/etemplate/Et2Nextmatch/`) is the searchable/sortable/filterable list widget
built on top of [Et2Datagrid](/components/et2-datagrid/). Its header family - the base
[Nextmatch Header](/components/et2-nextmatch-header/) and its variations (sortable headers, and
the various filter headers that are really `Et2Select`/`Et2SelectAccount`/`Et2LinkEntry`
subclasses repurposed as column filters via `FilterMixin`) - all live in the `Data Grid` sidebar
category alongside Nextmatch itself, since their whole purpose is grid-specific even where their
actual implementation inherits from an unrelated widget elsewhere.

### Widgets in this group

{% for c in components %}
{% if c.belongsTo == "Nextmatch" %}
- [{{ c.name | classNameToComponentName }}](/components/{{ c.tagName }}/)
{% endif %}
{% endfor %}
