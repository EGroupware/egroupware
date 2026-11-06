import {assert} from "@open-wc/testing";
import {
	Et2HistorylogWidgetRegistry,
	HISTORY_CF_PREFIX,
	HISTORY_FILE_STATUS,
	HISTORY_LINK_STATUS,
	HISTORY_USER_AGENT_STATUS
} from "../Et2HistorylogWidgetRegistry";

/**
 * Contract under test:
 * - `status-widgets` entries (an app's map of field -> display widget) resolve to a render spec.
 * - The built-in statuses (~link~, ~file~, user_agent_action) exist without an app declaring them.
 * - Custom fields resolve through the shared customfield->widget mapper.
 * - The label list is one list, used by both the "Changed" column and its filter.
 *
 * Setup strategy:
 * - Construct the registry directly with the shapes apps actually pass.  No DOM, no egw: the
 *   registry is deliberately pure so this can be tested without a rendered widget.
 *
 * Pass criteria: documented per test.
 */

// The registry asks customElements whether a tag exists; these are all registered by the widgets
// imported through etemplate2, and the ones used below are real.
import "../../Et2Date/Et2DateTime";
import "../../Et2Select/Et2Select";
import "../../Et2Description/Et2Description";
import "../../Et2Vfs/Et2VfsPath";
import "../../Et2Link/Et2Link";
import "../../Layout/Et2Box/Et2Box";

const lang = (s : string) => s;

describe("Et2HistorylogWidgetRegistry", () =>
{
	/**
	 * Pass criteria: the three statuses the widget adds itself are present (or, for
	 * user_agent_action, at least offered as a label - it is plain text, with no widget).
	 */
	it("provides the built-in statuses without the app declaring them", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({}, {}, lang);

		assert.equal(registry.get(HISTORY_LINK_STATUS)?.tagName, "et2-link",
			"~link~ must render as a link");
		assert.equal(registry.get(HISTORY_FILE_STATUS)?.tagName, "et2-vfs-path",
			"~file~ must render as a vfs path - it is what the attachment rows carry");

		const labels = registry.labels().map(l => l.value);
		assert.include(labels, HISTORY_LINK_STATUS);
		assert.include(labels, HISTORY_FILE_STATUS);
		assert.include(labels, HISTORY_USER_AGENT_STATUS,
			"user_agent_action has no widget but must still be offered as a label/filter value");
	});

	/**
	 * Pass criteria: a plain widget name resolves, including via the readonly variant and the
	 * et2- prefix the legacy widget also tried.
	 */
	it("resolves a plain widget name", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({
			'start': 'date-time',
			'note': 'description'
		}, {}, lang);

		assert.equal(registry.get('start')?.tagName, "et2-date-time",
			"'date-time' must find et2-date-time");
		assert.equal(registry.get('note')?.tagName, "et2-description");
	});

	/**
	 * Pass criteria: a name carrying legacy options after a colon (eg. "link-entry:infolog")
	 * resolves the widget and maps the option onto the target's declared legacyOptions.
	 */
	it("resolves a widget name with legacy options", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({'parent': 'link:addressbook'}, {}, lang);

		const spec = registry.get('parent');
		assert.equal(spec?.tagName, "et2-link", "the widget before the colon must be resolved");
		// Et2Link declares legacyOptions = ['app'], so the part after the colon lands there
		assert.equal(spec?.attrs?.app, "addressbook",
			"the legacy option after the colon must be mapped onto the target's declared legacyOption");

		// An empty legacy option is "not set" and must not overwrite the attribute with ""
		const blank = new Et2HistorylogWidgetRegistry({'p': 'link:'}, {}, lang);
		assert.notProperty(blank.get('p')!.attrs, "app",
			"an empty legacy option must be skipped, not applied as an empty string");
	});

	/**
	 * A value->label map is select options for one widget, not a multi-part value.
	 *
	 * Pass criteria: resolves to a single select carrying those options.
	 */
	it("treats a plain value->label map as select options", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({
			'public': {'': 'No', 1: 'Yes'}
		}, {}, lang);

		const spec = registry.get('public');
		assert.equal(spec?.tagName, "et2-select");
		assert.isUndefined(spec?.parts, "a plain option map is not a multi-part value");
		assert.isArray(spec?.attrs?.select_options);
		assert.equal(spec!.attrs.select_options.length, 2);
	});

	/**
	 * Calendar's `participants` is the real shape here: an array whose entries are a widget name
	 * and two option maps, rendered stacked in one cell.
	 *
	 * Pass criteria: resolves to a box with one sub-spec per part, in declaration order.
	 */
	it("resolves a multi-part value", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({
			'participants': ['date-time', {'U': 'Unknown', 'A': 'Accepted'}, 'description']
		}, {}, lang);

		const spec = registry.get('participants');
		assert.equal(spec?.tagName, "et2-vbox", "a multi-part value stacks its parts");
		assert.equal(spec?.parts?.length, 3, "one sub-spec per declared part");
		assert.equal(spec!.parts![0].spec.tagName, "et2-date-time");
		assert.equal(spec!.parts![1].spec.tagName, "et2-select",
			"an options object among the parts becomes a select");
		assert.equal(spec!.parts![2].spec.tagName, "et2-description");
	});

	/**
	 * An app may wrap a single entry in a one-element array; the server already unwraps that for
	 * sel_options, and the registry has to agree or it would build a pointless box.
	 *
	 * Pass criteria: a one-element array behaves like the bare entry.
	 */
	it("unwraps a single-element array", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({'end': ['date-time']}, {}, lang);

		assert.equal(registry.get('end')?.tagName, "et2-date-time");
		assert.isUndefined(registry.get('end')?.parts);
	});

	/**
	 * Pass criteria: a widget name that does not exist resolves to null, so the cell falls back to
	 * plain text rather than rendering a placeholder or throwing.
	 */
	it("returns null for an unknown widget name", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({'weird': 'no-such-widget-at-all'}, {}, lang);

		assert.isNull(registry.get('weird'),
			"an unresolvable widget must fall back to text, not throw");
	});

	/**
	 * Pass criteria: custom fields get a spec and a label, keyed with the '#' prefix the history
	 * rows use, without the app listing them in status-widgets.
	 */
	it("resolves custom fields from their definitions", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({}, {
			'mydate': {type: 'date-time', label: 'My date'},
			'mytext': {type: 'text', label: 'My text'}
		}, lang);

		assert.isNotNull(registry.get(HISTORY_CF_PREFIX + 'mydate'),
			"a custom field must resolve without being declared in status-widgets");
		assert.include(registry.labels().map(l => l.value), HISTORY_CF_PREFIX + 'mytext');
		assert.include(registry.labels().map(l => l.label), 'My text',
			"the custom field's own label must be offered");
	});

	/**
	 * The server sends the app's own field labels for the status column.  They are better than the
	 * registry's fallbacks, so they win - but they must not drop the ones only we know about.
	 *
	 * Pass criteria: server labels are merged in, ours are kept, and a server label overrides.
	 */
	it("merges server-sent labels without losing its own", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({}, {}, lang);
		registry.mergeLabels({
			'St': 'Status',
			'Ow': 'Owner',
			[HISTORY_FILE_STATUS]: 'Attachment'
		});

		const byValue = new Map(registry.labels().map(l => [l.value, l.label]));
		assert.equal(byValue.get('St'), 'Status', "a server label must be added");
		assert.equal(byValue.get(HISTORY_FILE_STATUS), 'Attachment',
			"a server label for a built-in status must override our fallback");
		assert.isTrue(byValue.has(HISTORY_LINK_STATUS),
			"merging must not drop a built-in label the server did not mention");
	});

	/**
	 * `labels()` is what both the "Changed" column and its filter are given, so a caller must not
	 * be able to mutate the registry's copy through it.
	 */
	it("hands out a copy of its label list", () =>
	{
		const registry = new Et2HistorylogWidgetRegistry({}, {}, lang);
		const before = registry.labels().length;
		registry.labels().push({value: 'x', label: 'x'});

		assert.equal(registry.labels().length, before, "labels() must not expose its own array");
	});
});
