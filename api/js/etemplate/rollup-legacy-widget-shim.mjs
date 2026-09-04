/**
 * Rollup plugin: synthesizes the trivial "1a-shim" et2_widget_*.ts re-exports
 * (`class et2_X extends Et2Y {}` / `type et2_X = Et2Y`) as virtual modules
 * instead of maintaining them as real files.
 *
 * Only covers widgets whose entire legacy file was nothing but such a
 * pass-through (see api/js/etemplate/widget-migration-status.md, "1a-shim").
 * et2_widget_dialog.ts is deliberately NOT in this list - it has real behaviour
 * (custom constructor, attribute-registry generation, its own
 * customElements.define("legacy-dialog", ...)) and stays a real file.
 *
 * A matching .d.ts sibling is kept for each entry (see generate-legacy-widget-dts.mjs)
 * so `tsc`/IDEs still resolve real types for these classes; this plugin only replaces
 * what rollup bundles at runtime.
 */
import path from 'path';
import {fileURLToPath} from 'url';

// Manifest targetModule paths are relative to this file's own directory
// (api/js/etemplate/, where the real et2_widget_*.ts files used to live) -
// NOT relative to whatever directory a consumer's import specifier happens to
// route through (eg. legacy-shims/, for consumers updated to import the .d.ts
// there). Anchoring here keeps resolution correct regardless of how deep or
// via what path a consumer's specifier reaches this plugin.
const ETEMPLATE_DIR = path.dirname(fileURLToPath(import.meta.url));

export const SHIM_MANIFEST = {
    et2_widget_checkbox: [
        {legacyName: 'et2_checkbox', targetModule: './Et2Checkbox/Et2Checkbox', targetExport: 'Et2Checkbox'}
    ],
    et2_widget_diff: [
        {legacyName: 'et2_diff', targetModule: './Et2Diff/Et2Diff', targetExport: 'Et2Diff'}
    ],
    et2_widget_template: [
        {legacyName: 'et2_template', targetModule: './Et2Template/Et2Template', targetExport: 'Et2Template'}
    ],
    et2_widget_htmlarea: [
        {legacyName: 'et2_htmlarea', targetModule: './Et2HtmlArea/Et2HtmlArea', targetExport: 'Et2HtmlArea'}
    ],
    et2_widget_selectbox: [
        {legacyName: 'et2_selectbox', targetModule: './Et2Select/Et2Select', targetExport: 'Et2Select'},
        {legacyName: 'et2_selectbox_ro', targetModule: './Et2Select/Select/Et2SelectReadonly', targetExport: 'Et2SelectReadonly'}
    ],
    et2_widget_image: [
        {legacyName: 'et2_image', targetModule: './Et2Image/Et2Image', targetExport: 'Et2Image'},
        {legacyName: 'et2_appicon', targetModule: './Et2Image/Et2AppIcon', targetExport: 'Et2AppIcon'},
        {legacyName: 'et2_avatar', targetModule: './Et2Avatar/Et2Avatar', targetExport: 'Et2Avatar'},
        {legacyName: 'et2_lavatar', targetModule: './Et2Avatar/Et2LAvatar', targetExport: 'Et2LAvatar'}
    ],
    et2_widget_link: [
        {legacyName: 'et2_link_to', targetModule: './Et2Link/Et2LinkTo', targetExport: 'Et2LinkTo'},
        {legacyName: 'et2_link_apps', targetModule: './Et2Link/Et2LinkAppSelect', targetExport: 'Et2LinkAppSelect'},
        {legacyName: 'et2_link_entry', targetModule: './Et2Link/Et2LinkEntry', targetExport: 'Et2LinkEntry'},
        {legacyName: 'et2_link', targetModule: './Et2Link/Et2Link', targetExport: 'Et2Link'},
        {legacyName: 'et2_link_entry_ro', targetModule: './Et2Link/Et2LinkEntry', targetExport: 'Et2LinkEntryReadonly'},
        {legacyName: 'et2_link_string', targetModule: './Et2Link/Et2LinkString', targetExport: 'Et2LinkString'},
        {legacyName: 'et2_link_list', targetModule: './Et2Link/Et2LinkList', targetExport: 'Et2LinkList'},
        {legacyName: 'et2_link_add', targetModule: './Et2Link/Et2LinkAdd', targetExport: 'Et2LinkAdd'}
    ],
    et2_widget_selectAccount: [
        {legacyName: 'et2_selectAccount', targetModule: './Et2Select/Select/Et2SelectAccount', targetExport: 'Et2SelectAccount'},
        {legacyName: 'et2_selectAccount_ro', targetModule: './Et2Select/Select/Et2SelectReadonly', targetExport: 'Et2SelectAccountReadonly'}
    ],
    et2_widget_tabs: [
        {legacyName: 'et2_tabbox', targetModule: './Layout/Et2Tabs/Et2Tabs', targetExport: 'Et2Tabs'}
    ],
    et2_widget_taglist: [
        {legacyName: 'et2_taglist', targetModule: './Et2Select/Et2Select', targetExport: 'Et2Select'},
        {legacyName: 'et2_taglist_account', targetModule: './Et2Select/Select/Et2SelectAccount', targetExport: 'Et2SelectAccount'},
        {legacyName: 'et2_taglist_email', targetModule: './Et2Email/Et2Email', targetExport: 'Et2Email'},
        {legacyName: 'et2_taglist_category', targetModule: './Et2Select/Select/Et2SelectCategory', targetExport: 'Et2SelectCategory'},
        {legacyName: 'et2_taglist_thumbnail', targetModule: './Et2Select/Select/Et2SelectThumbnail', targetExport: 'Et2SelectThumbnail'},
        {legacyName: 'et2_taglist_state', targetModule: './Et2Select/Select/Et2SelectState', targetExport: 'Et2SelectState'}
    ],
    et2_widget_hbox: [
        {legacyName: 'et2_hbox', targetModule: './Layout/Et2Box/Et2Box', targetExport: 'Et2HBox'}
    ],
    et2_widget_number: [
        {legacyName: 'et2_number', targetModule: './Et2Textbox/Et2Number', targetExport: 'Et2Number'},
        {legacyName: 'et2_number_ro', targetModule: './Et2Textbox/Et2NumberReadonly', targetExport: 'Et2NumberReadonly'}
    ],
    et2_widget_textbox: [
        {legacyName: 'et2_textbox', targetModule: './Et2Textbox/Et2Textbox', targetExport: 'Et2Textbox'},
        {legacyName: 'et2_textbox_ro', targetModule: './Et2Textbox/Et2TextboxReadonly', targetExport: 'Et2TextboxReadonly'},
        {legacyName: 'et2_searchbox', targetModule: './Et2Textbox/Et2Searchbox', targetExport: 'Et2Searchbox'}
    ],
    et2_widget_button: [
        {legacyName: 'et2_button', targetModule: './Et2Button/Et2Button', targetExport: 'Et2Button'},
        {legacyName: 'et2_buttononly', targetModule: './Et2Button/Et2Button', targetExport: 'Et2Button'}
    ],
    et2_widget_date: [
        {legacyName: 'et2_date', targetModule: './Et2Date/Et2Date', targetExport: 'Et2Date'},
        {legacyName: 'et2_date_duration', targetModule: './Et2Date/Et2DateDuration', targetExport: 'Et2DateDuration'},
        {legacyName: 'et2_date_duration_ro', targetModule: './Et2Date/Et2DateDurationReadonly', targetExport: 'Et2DateDurationReadonly'},
        {legacyName: 'et2_date_ro', targetModule: './Et2Date/Et2DateReadonly', targetExport: 'Et2DateReadonly'},
        {legacyName: 'et2_date_range', targetModule: './Et2Date/Et2DateRange', targetExport: 'Et2DateRange'}
    ],
    et2_widget_file: [
        {legacyName: 'et2_file', targetModule: './Et2File/Et2File', targetExport: 'Et2File'}
    ]
};

const VIRTUAL_PREFIX = '\0legacy-shim:';

export function legacyWidgetShimPlugin()
{
    return {
        name: 'legacy-widget-shim',
        resolveId(source, importer)
        {
            if(!importer) return null;

            const base = source.split('/').pop().replace(/\.tsx?$/, '');
            if(!SHIM_MANIFEST[base]) return null;

            return VIRTUAL_PREFIX + base;
        },
        load(id)
        {
            if(!id.startsWith(VIRTUAL_PREFIX)) return null;

            const base = id.slice(VIRTUAL_PREFIX.length);
            const dir = ETEMPLATE_DIR;
            const manifest = SHIM_MANIFEST[base];

            const importsByModule = new Map();
            for(const entry of manifest)
            {
                if(!importsByModule.has(entry.targetModule)) importsByModule.set(entry.targetModule, new Set());
                importsByModule.get(entry.targetModule).add(entry.targetExport);
            }

            const lines = [];
            for(const [mod, exportsSet] of importsByModule)
            {
                lines.push(`import {${[...exportsSet].join(', ')}} from ${JSON.stringify(path.resolve(dir, mod))};`);
            }
            for(const entry of manifest)
            {
                lines.push(`export class ${entry.legacyName} extends ${entry.targetExport} {}`);
            }
            return lines.join('\n') + '\n';
        }
    };
}
