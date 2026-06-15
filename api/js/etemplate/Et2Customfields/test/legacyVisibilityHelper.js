let previousTypeFilter = null;
const normalizeFields = (fields) => {
    if (!fields) {
        return {};
    }
    if (typeof fields === "string") {
        const result = {};
        fields.split(",").map((name) => name.trim()).filter(Boolean).forEach((name) => result[name] = true);
        return result;
    }
    return Object.assign({}, fields);
};
const hasPrivate = (field) => {
    const value = field.private;
    if (Array.isArray(value)) {
        return value.length > 0;
    }
    if (typeof value === "string") {
        return value.length > 0;
    }
    return !!value;
};
/**
 * Legacy-logic helper copied from `et2_extension_customfields` constructor branches.
 * It is intentionally used as a baseline contract reference for migration tests.
 */
export function legacyVisibility(input) {
    var _a, _b, _c;
    const options = {
        customfields: input.customfields || {},
        fields: normalizeFields(input.fields),
        exclude: input.exclude || "",
        typeFilter: (_a = input.typeFilter) !== null && _a !== void 0 ? _a : null,
        tab: (_b = input.tab) !== null && _b !== void 0 ? _b : null,
        mode: input.mode || "customfields",
        defaultTabMatch: (_c = input.defaultTabMatch) !== null && _c !== void 0 ? _c : null
    };
    const exclude = new Set(String(options.exclude).split(",").map((name) => name.trim()).filter(Boolean));
    if (options.typeFilter === "previous") {
        options.typeFilter = previousTypeFilter;
    }
    if (typeof options.typeFilter === "string") {
        options.typeFilter = options.typeFilter.split(",").map((type) => type.trim()).filter(Boolean);
    }
    previousTypeFilter = Array.isArray(options.typeFilter) ? options.typeFilter : null;
    if (Array.isArray(options.typeFilter) && options.typeFilter.length) {
        for (const fieldName of Object.keys(options.customfields)) {
            const field = options.customfields[fieldName];
            if (!field.type2 || field.type2.length === 0 || field.type2 === "0") {
                options.fields[fieldName] = true;
                continue;
            }
            const types = typeof field.type2 === "string" ? field.type2.split(",") : field.type2;
            options.fields[fieldName] = false;
            for (const type of types) {
                if (options.typeFilter.includes(type)) {
                    options.fields[fieldName] = true;
                }
            }
        }
    }
    const hasExplicitFields = Object.keys(options.fields).length > 0;
    if (!hasExplicitFields) {
        for (const fieldName of Object.keys(options.customfields)) {
            if (exclude.has(fieldName)) {
                options.fields[fieldName] = false;
                continue;
            }
            const field = options.customfields[fieldName];
            if (options.mode === "customfields-filters") {
                options.fields[fieldName] = true;
            }
            else if (field.tab) {
                options.fields[fieldName] = field.tab === options.tab;
            }
            else if (options.defaultTabMatch !== null) {
                if (hasPrivate(field)) {
                    options.fields[fieldName] = options.defaultTabMatch !== "-non-private";
                }
                else {
                    options.fields[fieldName] = options.defaultTabMatch !== "-private";
                }
            }
            else {
                options.fields[fieldName] = true;
            }
        }
        return options.fields;
    }
    for (const fieldName of Object.keys(options.customfields)) {
        const field = options.customfields[fieldName];
        if (Array.isArray(options.typeFilter) && options.typeFilter.length && options.fields[fieldName] !== true) {
            continue;
        }
        if (exclude.has(fieldName)) {
            options.fields[fieldName] = false;
        }
        else if (options.defaultTabMatch !== null ? !!field.tab : field.tab !== options.tab && !!options.tab) {
            options.fields[fieldName] = false;
        }
        else if (options.defaultTabMatch !== null) {
            if (hasPrivate(field)) {
                options.fields[fieldName] = options.defaultTabMatch !== "-non-private";
            }
            else if (options.fields[fieldName]) {
                options.fields[fieldName] = options.defaultTabMatch !== "-private";
            }
        }
    }
    for (const fieldName of Object.keys(options.customfields)) {
        if (typeof options.fields[fieldName] === "undefined") {
            options.fields[fieldName] = false;
        }
    }
    return options.fields;
}
export const sampleCustomfields = {
    cf_text: { label: "Text", type: "text", type2: "task", private: "", tab: null },
    cf_project: { label: "Project", type: "select", type2: "project,task", private: "", tab: "extra" },
    cf_private: { label: "Private", type: "select", type2: "0", private: "1", tab: null },
    cf_file: { label: "File", type: "filemanager", type2: "", private: "", tab: null }
};
//# sourceMappingURL=legacyVisibilityHelper.js.map