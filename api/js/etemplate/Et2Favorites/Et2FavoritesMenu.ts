import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Favorite} from "./Favorite";
import {property} from "lit/decorators/property.js";
import {repeat} from "lit/directives/repeat.js";
import {keyed} from "lit/directives/keyed.js";
import {live} from "lit/directives/live.js";
import {classMap} from "lit/directives/class-map.js";
import {createRef, Ref, ref} from "lit/directives/ref.js";
import Sortable from "sortablejs/modular/sortable.complete.esm.js";
import {SlTree} from "@shoelace-style/shoelace";
import {state} from "lit/decorators/state.js";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";

import styles from "./Et2FavoritesMenu.styles";

/**
 * Something shown in the list: a favorite, or a folder holding some
 */
type ListEntry = { key : string, favorite : Favorite } | { folder : string, items : { key : string, favorite : Favorite }[] };

/**
 * @summary A tree listing a user's favorites, which can be put in folders.  Populated from the user's preferences.
 *
 * Folders hold favorites, not other folders.  The user's own favorites keep their folder themselves.  A favorite
 * shared with a group is not the user's to change, so where the user wants it is kept in their own preferences.
 *
 * @dependency sl-tree
 * @dependency sl-tree-item
 * @dependency sl-dropdown
 * @dependency sl-menu
 * @dependency sl-menu-item
 * @dependency et2-button-icon
 *
 * @csspart menu - The tree
 *
 * @slot - Add additional tree items
 */
@customElement("et2-favorites-menu")
export class Et2FavoritesMenu extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			super.styles,
			styles
		]
	};

	/**
	 * The current application we're showing favorites for.
	 *
	 * @type {string}
	 */
	@property()
	application : string;

	@property()
	noAdd : boolean = false;

	@property({type: Boolean})
	sortable : boolean = false;

	@state()
	activeFavorite : string = "";

	private favorites : { [name : string] : Favorite } = {
		'blank': {
			name: typeof this.egw()?.lang == "function" ? this.egw().lang("No filters") : "No filters",
			state: {},
			group: false
		}
	};
	private loadingPromise = Promise.resolve();
	/** Until the favorites are loaded there is nothing to show but that they are loading */
	private loaded = false;
	private _sortables = new Map<HTMLElement, Sortable>();
	private treeRef : Ref<SlTree> = createRef();
	/** Folders the user has open */
	private openFolders : Set<string>;
	/** Changing this throws the tree away, so the DOM always matches the data again after a drag */
	private _epoch = 0;
	/** What the dragged favorite is over, if it would be put onto it instead of beside it */
	private _dropTarget : HTMLElement | null = null;

	constructor()
	{
		super();
		this.handlePreferenceChange = this.handlePreferenceChange.bind(this);
		this.handleStateChange = this.handleStateChange.bind(this);
		this.handleStart = this.handleStart.bind(this);
		this.handleMove = this.handleMove.bind(this);
		this.handleChange = this.handleChange.bind(this);
		this.handleSortEnd = this.handleSortEnd.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.application)
		{
			this._load();
		}
		document.addEventListener("preferenceChange", this.handlePreferenceChange);
		document.addEventListener("et2-filter", this.handleStateChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._destroySortables();
		document.removeEventListener("preferenceChange", this.handlePreferenceChange);
		document.removeEventListener("et2-filter", this.handleStateChange);
	}

	firstUpdated(changedProperties : PropertyValues<this> | Map<PropertyKey, unknown> | undefined)
	{
		super.firstUpdated(changedProperties);
		this.loadingPromise.then(async() =>
		{
			await this.updateComplete;
			this.highlightFavorite(window.app[this.application]?.getState());
		});
	}

	willUpdate(changedProperties : PropertyValues)
	{
		if(changedProperties.has("application") && this.application)
		{
			this._load();
		}
		if(changedProperties.has("activeFavorite") && this.favorites[this.activeFavorite]?.folder)
		{
			// Show the favorite that is in use
			this.getOpenFolders().add(this.favorites[this.activeFavorite].folder);
		}
	}

	private _load()
	{
		this.loadingPromise = Favorite.load(this.egw(), this.application).then((favorites) =>
		{
			this.favorites = favorites;
			this.loaded = true;
			this._epoch++;
			this.requestUpdate();
		});
	}

	/**
	 * Folders the user has open, remembered per application
	 */
	private getOpenFolders() : Set<string>
	{
		if(!this.openFolders)
		{
			this.openFolders = new Set();
			try
			{
				JSON.parse(window.localStorage.getItem("egw_favorite_folders_" + this.application) ?? "[]")
					.forEach(folder => this.openFolders.add(folder));
			}
			catch(e)
			{
				// Not remembered
			}
		}
		return this.openFolders;
	}

	private saveOpenFolders()
	{
		try
		{
			window.localStorage.setItem("egw_favorite_folders_" + this.application, JSON.stringify([...this.getOpenFolders()]));
		}
		catch(e)
		{
			// Not remembered
		}
	}

	/**
	 * Favorites as listed: a folder is listed where its first favorite is
	 */
	private listEntries() : ListEntry[]
	{
		const entries : ListEntry[] = [];
		const folders = new Map<string, { key : string, favorite : Favorite }[]>();
		Object.entries(this.favorites).forEach(([key, favorite]) =>
		{
			const folder = key === "blank" ? "" : favorite.folder ?? "";
			if(!folder)
			{
				entries.push({key, favorite});
				return;
			}
			if(!folders.has(folder))
			{
				folders.set(folder, []);
				entries.push({folder, items: folders.get(folder)});
			}
			folders.get(folder).push({key, favorite});
		});
		return entries;
	}

	private _destroySortables()
	{
		this._sortables.forEach(sortable => sortable.el && sortable.destroy());
		this._sortables.clear();
	}

	/**
	 * Make the tree, and each folder in it, something favorites can be dragged in and out of
	 */
	private syncSortables()
	{
		const tree = this.treeRef.value;
		if(!tree || !tree.isConnected || !this.sortable)
		{
			this._destroySortables();
			return;
		}
		const containers = [tree, ...Array.from(tree.querySelectorAll<HTMLElement>(":scope > sl-tree-item.folder"))];
		this._sortables.forEach((sortable, el) =>
		{
			if(!containers.includes(el))
			{
				sortable.el && sortable.destroy();
				this._sortables.delete(el);
			}
		});
		containers.forEach(container =>
		{
			if(!this._sortables.has(container))
			{
				this._sortables.set(container, Sortable.create(container, {
					group: {
						name: "favorites",
						// "No filters" stays out of folders
						put: (to, from, dragged) => dragged.dataset.name !== "blank" || to.el === tree
					},
					ghostClass: "ui-fav-sortable-placeholder",
					// Folders are there to be dropped onto, not moved
					draggable: "sl-tree-item:not(.add)",
					// Not from a folder (a selector would also match the folder around a favorite that is in it), and not
					// from the button for its actions: a click always has some movement in it, which started a drag and
					// swapped the button for the icon to drop onto while it was being pressed.
					filter: (event : Event) =>
					{
						const path = event.composedPath();
						return (<HTMLElement>path.find((el : HTMLElement) => el.localName === "sl-tree-item"))?.classList.contains("folder") ||
							path.some((el : HTMLElement) => el.classList?.contains("more"));
					},
					preventOnFilter: false,
					delay: 25,
					onStart: this.handleStart,
					onMove: this.handleMove,
					onChange: this.handleChange,
					onEnd: this.handleSortEnd
				}));
			}
		});
	}

	private clearDropTarget()
	{
		this._dropTarget?.classList.remove("drop-onto");
		this._dropTarget = null;
	}

	/**
	 * The icon for dropping onto something, if that is what the pointer is over
	 */
	private dropIconUnder(event : Event) : HTMLElement | undefined
	{
		return <HTMLElement>event?.composedPath?.().find((el : HTMLElement) => el.classList?.contains("drop-icon"));
	}

	/**
	 * Mark what the dragged favorite would be dropped onto
	 */
	private dropOnto(over : HTMLElement)
	{
		this._dropTarget = over;
		over.classList.add("drop-onto");
	}

	/**
	 * A favorite is being dragged: the "..." buttons make way for the icons to drop onto
	 */
	protected handleStart()
	{
		this.toggleAttribute("dragging", true);
	}

	/**
	 * The dragged favorite is over another favorite.
	 *
	 * Over its folder icon it is "onto" it, which puts the favorite in the same folder (or makes one) instead of
	 * beside it, and the list is left as it is.  Anywhere else on the row it is reordered, the same as in any list.
	 * Folders are dealt with in handleFolderDragOver().
	 */
	protected handleMove(event : Sortable.MoveEvent, originalEvent : Event) : boolean
	{
		this.clearDropTarget();
		const dragged = <HTMLElement>event.dragged;
		const over = <HTMLElement>originalEvent?.composedPath?.().find((el : HTMLElement) => el.localName === "sl-tree-item");
		if(!over || over === dragged)
		{
			return true;
		}
		if(over.classList.contains("folder"))
		{
			return false;
		}
		if(this.dropIconUnder(originalEvent) && dragged.dataset.name !== "blank" && over.dataset.name !== "blank")
		{
			this.dropOnto(over);
			return false;
		}
		return true;
	}

	/**
	 * A favorite in a folder has to be in the tree item's "children" slot, and one that is not in a folder must not
	 * be, or it is not shown anywhere.  Sortable moves the element and leaves that alone.
	 */
	private syncSlot(item : HTMLElement)
	{
		item.parentElement?.classList.contains("folder") ? item.setAttribute("slot", "children") : item.removeAttribute("slot");
	}

	/**
	 * The dragged favorite was put somewhere else in the list, or in another folder
	 */
	protected handleChange(event : Sortable.SortableEvent)
	{
		this.syncSlot(<HTMLElement>event.item);
	}

	/**
	 * The dragged favorite is over the header of a folder.
	 *
	 * Over the folder icon it is onto the folder, which puts it in the folder.  Over the rest of the header it
	 * is reordered, which puts it beside the folder, at the top level, on the side it came from.  It is never put in a folder that is closed, where it
	 * would not be seen, and Sortable does not ask about the folder's own header (it is the container, not something
	 * in it), so all this is done here, before Sortable sees the event.
	 */
	protected handleFolderDragOver(event : DragEvent)
	{
		const folder = <HTMLElement>event.currentTarget;
		const dragged = <HTMLElement>Sortable.dragged;
		const over = event.composedPath().find((el : HTMLElement) => el.localName === "sl-tree-item");
		if(!dragged || over !== folder || dragged === folder)
		{
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		this.clearDropTarget();

		if(this.dropIconUnder(event) && dragged.dataset.name !== "blank")
		{
			this.dropOnto(folder);
			return;
		}
		// Reordering: beside the folder, on the side the favorite is coming from
		const movingDown = !!(dragged.compareDocumentPosition(folder) & Node.DOCUMENT_POSITION_FOLLOWING);
		folder.parentElement.insertBefore(dragged, movingDown ? folder.nextElementSibling : folder);
		this.syncSlot(dragged);
	}

	/**
	 * Something was dropped, work out what changed.
	 *
	 * Where the favorite ended up in the DOM is only used to read the new order.  The tree is rebuilt
	 * from the data afterwards.
	 */
	protected async handleSortEnd(event : Sortable.SortableEvent)
	{
		const dragged = <HTMLElement>event.item;
		const key = dragged.dataset.name;
		const target = this._dropTarget;
		this.clearDropTarget();
		this.toggleAttribute("dragging", false);
		

		const tree = this.treeRef.value;
		const changes : { [key : string] : string } = {};
		let order = Object.keys(this.favorites);

		if(target)
		{
			// Onto a folder, or onto a favorite that is not in one
			const folder = target.classList.contains("folder") ? target.dataset.folder : "";
			if(folder)
			{
				if(folder !== (this.favorites[key]?.folder ?? ""))
				{
					changes[key] = folder;
				}
			}
			else
			{
				// Two favorites make a folder
				const name = await this.promptFolder(this.egw().lang("New folder"));
				if(name)
				{
					changes[key] = name;
					changes[target.dataset.name] = name;
				}
			}
		}
		else if(tree)
		{
			order = [];
			Array.from(tree.children).forEach((item : HTMLElement) =>
			{
				if(item.localName !== "sl-tree-item" || item.classList.contains("add"))
				{
					return;
				}
				if(item.classList.contains("folder"))
				{
					Array.from(item.children).filter((child : HTMLElement) => child.dataset?.name).forEach((child : HTMLElement) =>
					{
						order.push(child.dataset.name);
						changes[child.dataset.name] = item.dataset.folder;
					});
				}
				else
				{
					order.push(item.dataset.name);
					changes[item.dataset.name] = "";
				}
			});
			// Only what moved
			Object.keys(changes).forEach(k => (changes[k] === (this.favorites[k]?.folder ?? "")) && delete changes[k]);
		}

		const sorted = Object.keys(this.favorites);
		if(order.length === sorted.length && order.some((k, i) => k !== sorted[i]))
		{
			this.egw().set_preference(this.application, "fav_sort_pref", order);
			// Trigger event so other widgets can update and be in sync
			Favorite.notify(this.application, "fav_sort_pref");
		}
		if(Object.keys(changes).length)
		{
			await Favorite.setFolders(this.egw(), this.application, changes);
		}
		this._load();
		this.requestUpdate();
	}

	/**
	 * Ask for a folder name
	 *
	 * @return the name, or "" if the user did not give one
	 */
	private promptFolder(value : string = "") : Promise<string>
	{
		return new Promise(resolve =>
		{
			Et2Dialog.show_prompt((button, name) =>
			{
				resolve(button === Et2Dialog.OK_BUTTON ? Favorite.cleanFolder(name) : "");
			}, this.egw().lang("Folder name"), this.egw().lang("Folder"), value, Et2Dialog.BUTTONS_OK_CANCEL, this.egw());
		});
	}

	/**
	 * Highlight the favorite that matches the given state, if any
	 *
	 * @param currentState
	 * @return {Promise<void>}
	 */
	public async highlightFavorite(currentState)
	{
		let best_match = null;
		let best_count = 0;
		this.activeFavorite = "";

		// Skip it all if currentState is empty
		if(!currentState || Object.keys(currentState).length == 0)
		{
			return;
		}

		Object.entries(this.favorites).forEach(([name, favorite]) =>
		{
			const app_object = window.app[this.application];
			if (app_object) favorite = app_object.fixState(favorite);

			let match_count = 0;
			let extra_keys = Object.keys(favorite.state);

			// Look through each key in the current state
			for(const state_key in currentState)
			{
				extra_keys.splice(extra_keys.indexOf(state_key), 1);
				if(typeof favorite.state != "undefined" && typeof currentState[state_key] != "undefined" && typeof favorite.state[state_key] != "undefined" && (currentState[state_key] == favorite.state[state_key] || !currentState[state_key] && !favorite.state[state_key]))
				{
					match_count++;
				}
				else if(state_key == "selectcols" && typeof favorite.state["selectcols"] == "undefined")
				{
					// Skip, not set in favorite
				}
				else if(typeof currentState[state_key] != "undefined" && currentState[state_key] && typeof currentState[state_key] === "object"
					&& typeof favorite.state != "undefined" && typeof favorite.state[state_key] != "undefined" && favorite.state[state_key] && typeof favorite.state[state_key] === "object")
				{
					if((typeof currentState[state_key].length !== "undefined" || typeof currentState[state_key].length !== "undefined")
						&& (currentState[state_key].length || Object.keys(currentState[state_key]).length) != (favorite.state[state_key].length || Object.keys(favorite.state[state_key]).length))
					{
						// State or favorite has a length, but the other does not
						if((currentState[state_key].length === 0 || Object.keys(currentState[state_key]).length === 0) &&
							(favorite.state[state_key].length == 0 || Object.keys(favorite.state[state_key]).length === 0))
						{
							// Just missing, or one is an array and the other is an object
							continue;
						}
						// One has a value and the other doesn't, no match
						return;
					}
					else if(currentState[state_key].length !== "undefined" && typeof favorite.state[state_key].length !== "undefined" &&
						currentState[state_key].length === 0 && favorite.state[state_key].length === 0)
					{
						// Both set, but both empty
						match_count++;
						continue;
					}
					// Consider sub-objects (column filters) individually
					for(var sub_key in currentState[state_key])
					{
						if(currentState[state_key][sub_key] == favorite.state[state_key][sub_key] || !currentState[state_key][sub_key] && !favorite.state[state_key][sub_key])
						{
							match_count++;
						}
						else if(currentState[state_key][sub_key] && favorite.state[state_key][sub_key] &&
							typeof currentState[state_key][sub_key] === "object" && typeof favorite.state[state_key][sub_key] === "object")
						{
							// Too deep to keep going, just string compare for perfect match
							if(JSON.stringify(currentState[state_key][sub_key]) === JSON.stringify(favorite.state[state_key][sub_key]))
							{
								match_count++;
							}
						}
						else if(typeof currentState[state_key][sub_key] !== "undefined" && currentState[state_key][sub_key] != favorite.state[state_key][sub_key])
						{
							// Different values, do not match
							return;
						}
					}
				}
				else if(typeof currentState[state_key] !== "undefined"
					&& typeof favorite.state != "undefined" && typeof favorite.state[state_key] !== "undefined"
					&& currentState[state_key] != favorite.state[state_key])
				{
					// Different values, do not match
					return;
				}
			}
			// Check for anything set that the current one does not have
			for(var i = 0; i < extra_keys.length; i++)
			{
				if(favorite.state[extra_keys[i]])
				{
					return;
				}
			}
			// match noFilter, if none is set to a non-empty value
			if (name === 'blank')
			{
				match_count = !currentState.filter && !currentState.filter2 && !currentState.cat_id && !currentState.search ? 9 : 0;
				Object.entries(currentState.col_filter || {}).forEach(([name, value]) => {
					if (value) match_count=0;
				});
			}
			// Better match?  Hold on to it and keep looking
			if(match_count > best_count)
			{
				best_match = name;
				best_count = match_count;
			}
		});
		if(best_match)
		{
			this.activeFavorite = best_match;
		}
	}

	/**
	 * Nextmatch filter has changed, change which favorite is highlighted
	 *
	 * @param e
	 */
	handleStateChange(e)
	{
		if(e && e.detail?.nm?.getInstanceManager().app == this.application)
		{
			// Get the full state of the app and highlight if a favourite matches
			this.highlightFavorite(e.detail.activeFilters ?? this.getInstanceManager()?.app_obj[this.application]?.getState());
		}
		// Could also be a non-nm state change
	}

	handlePreferenceChange(e)
	{
		if(e && e.detail?.application == this.application)
		{
			this._load();
			this.requestUpdate();
		}
	}

	/**
	 * The user picked a favorite, by click or keyboard
	 */
	protected handleSelect(item : HTMLElement)
	{
		const name = item?.dataset?.name;
		if(!name)
		{
			return;
		}
		if(name == Favorite.ADD_VALUE)
		{
			this.handleAdd(new Event("click"));
		}
		else
		{
			Favorite.applyFavorite(this.egw(), this.application, name);
		}
		// The tree selects what was clicked, show what really matches the state
		this.requestUpdate();
	}

	protected handleTreeClick(event : MouseEvent)
	{
		const item = <HTMLElement>event.composedPath().find((el : HTMLElement) => el.localName === "sl-tree-item");
		if(item && !item.classList.contains("folder"))
		{
			this.handleSelect(item);
		}
	}

	protected handleTreeKeydown(event : KeyboardEvent)
	{
		const item = <HTMLElement>event.composedPath().find((el : HTMLElement) => el.localName === "sl-tree-item");
		if(event.key === "Enter" && item && item === event.target && !item.classList.contains("folder"))
		{
			this.handleSelect(item);
		}
	}

	/**
	 * A folder was opened or closed
	 */
	protected handleFolderToggle(event : Event)
	{
		const folder = (<HTMLElement>event.target)?.dataset?.folder;
		if(!folder)
		{
			return;
		}
		event.type === "sl-expand" ? this.getOpenFolders().add(folder) : this.getOpenFolders().delete(folder);
		this.saveOpenFolders();
	}

	handleAdd(event)
	{
		event.stopPropagation();
		if(this.egw().window && this.egw().window.app[this.application])
		{
			this.egw().window.app[this.application].add_favorite({});
		}
	}

	protected handleFavoriteAction(event : CustomEvent, key : string)
	{
		const action = event.detail.item.value;
		if(action === "edit" || action === "move")
		{
			Favorite.edit(this.egw(), this.application, key, action === "move");
		}
		else if(action === "delete")
		{
			this.handleDelete(event, key);
		}
	}

	protected async handleFolderAction(event : CustomEvent, folder : string)
	{
		const keys = Object.keys(this.favorites).filter(key => this.favorites[key].folder === folder);
		let name = "";
		if(event.detail.item.value === "rename")
		{
			name = await this.promptFolder(folder);
			if(!name || name === folder)
			{
				return;
			}
		}
		await Favorite.setFolders(this.egw(), this.application, Object.fromEntries(keys.map(key => [key, name])));
	}

	handleDelete(event, key : string)
	{
		// Don't trigger click
		event.stopPropagation();

		const item = <HTMLElement>this.treeRef.value?.querySelector(`sl-tree-item[data-name="${key}"]`);
		if(item)
		{
			item.inert = true;
			item.style.opacity = "0.5";
		}

		// Remove from server
		Favorite.remove(this.egw(), this.application, key).then(() =>
		{
			// Remove from widget
			delete this.favorites[key];
			this._epoch++;
			this.requestUpdate();

			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new CustomEvent("preferenceChange", {
					bubbles: true,
					composed: true,
					detail: {
						application: this.application,
						preference: key
					}
				}));
			});
		});

		this.requestUpdate();
	}

	/**
	 * The "..." button with what can be done to a favorite
	 */
	protected favoriteActionsTemplate(key : string, favorite : Favorite) : TemplateResult | symbol
	{
		if(['blank', Favorite.ADD_VALUE].includes(key))
		{
			return nothing;
		}
		const lang = (phrase : string) => this.egw()?.lang(phrase) ?? phrase;
		const canEdit = Favorite.canEdit(this.egw(), favorite, key);
		// Favorites shared with a group are only deleted by an admin
		const canDelete = !Favorite.isShared(favorite) || Favorite.isAdmin(this.egw());
		return html`
            <sl-dropdown class="more" placement="bottom-end" hoist
                         @click=${(e : Event) => e.stopPropagation()}
                         @keydown=${(e : Event) => e.stopPropagation()}
            >
                <et2-button-icon slot="trigger" image="three-dots-vertical" noSubmit label=${lang("More...")}
                ></et2-button-icon>
                <sl-menu @sl-select=${(e : CustomEvent) => this.handleFavoriteAction(e, key)}>
                    ${canEdit ? html`
                        <sl-menu-item value="edit"><sl-icon slot="prefix" name="pencil"></sl-icon>${lang("Edit")}</sl-menu-item>` : nothing}
                    <sl-menu-item value="move"><sl-icon slot="prefix" name="folder-symlink"></sl-icon>${lang("Move to folder")}</sl-menu-item>
                    ${canDelete ? html`
                        <sl-menu-item value="delete"><sl-icon slot="prefix" name="trash"></sl-icon>${lang("Delete")}</sl-menu-item>` : nothing}
                </sl-menu>
            </sl-dropdown>`;
	}

	protected folderActionsTemplate(folder : string) : TemplateResult
	{
		const lang = (phrase : string) => this.egw()?.lang(phrase) ?? phrase;
		return html`
            <sl-dropdown class="more" placement="bottom-end" hoist
                         @click=${(e : Event) => e.stopPropagation()}
                         @keydown=${(e : Event) => e.stopPropagation()}
            >
                <et2-button-icon slot="trigger" image="three-dots-vertical" noSubmit label=${lang("More...")}
                ></et2-button-icon>
                <sl-menu @sl-select=${(e : CustomEvent) => this.handleFolderAction(e, folder)}>
                    <sl-menu-item value="rename"><sl-icon slot="prefix" name="pencil"></sl-icon>${lang("Rename folder")}</sl-menu-item>
                    <sl-menu-item value="ungroup"><sl-icon slot="prefix" name="folder-x"></sl-icon>${lang("Remove folder")}</sl-menu-item>
                </sl-menu>
            </sl-dropdown>`;
	}

	/**
	 * The icon to drop a dragged favorite onto, to put it in the folder.  Only there while dragging.
	 */
	protected dropIconTemplate(name : string, inFolder : boolean = false) : TemplateResult | symbol
	{
		// Folders are one level deep: there is nothing to put a favorite that is in one onto
		return ["blank", Favorite.ADD_VALUE].includes(name) || inFolder ? nothing : html`
            <sl-icon class="drop-icon" name=${name === "folder" ? "folder-symlink" : "folder-plus"}></sl-icon>`;
	}

	protected menuItemTemplate(name : string, favorite : Favorite, inFolder : boolean = false) : TemplateResult
	{
		const isAdd = name === Favorite.ADD_VALUE;
		return html`
            <sl-tree-item class=${classMap({favorite: true, add: isAdd})} data-name="${name}"
                          .selected=${live(!isAdd && name == this.activeFavorite)}
            >
                <span class="row">
                    <sl-icon name=${isAdd ? "plus-circle" : "record-fill"}></sl-icon>
                    <span class="name">${favorite.name}</span>
                    ${this.dropIconTemplate(name, inFolder)}
                    ${this.favoriteActionsTemplate(name, favorite)}
                </span>
            </sl-tree-item>`;
	}

	protected folderTemplate(folder : string, items : { key : string, favorite : Favorite }[]) : TemplateResult
	{
		const open = this.getOpenFolders().has(folder);
		return html`
            <sl-tree-item class=${classMap({folder: true, "has-active": items.some(i => i.key === this.activeFavorite)})}
                          data-folder="${folder}"
                          .expanded=${live(open)}
                          @dragover=${{handleEvent: (e : DragEvent) => this.handleFolderDragOver(e), capture: true}}
            >
                <span class="row">
                    <sl-icon name="folder"></sl-icon>
                    <span class="name">${folder}</span>
                    ${this.dropIconTemplate("folder")}
                    ${this.folderActionsTemplate(folder)}
                </span>
                ${repeat(items, i => i.key, i => this.menuItemTemplate(i.key, i.favorite, true))}
            </sl-tree-item>`;
	}

	protected loadingTemplate()
	{
		return html`
            <sl-tree-item class="loading">${typeof this.egw()?.lang == "function" ? this.egw().lang("Loading") : "Loading"}
            </sl-tree-item>`;
	}

	/**
	 * Sortable needs the elements, so it is set up after every render.  That is cheap if nothing changed.
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(this.loaded)
		{
			this.syncSortables();
		}
	}

	/**
	 * What is shown is made from what is already loaded, in one go.  Waiting for something at this point would
	 * show something else for a moment and then rebuild everything, which also replaces the element the pointer
	 * is on while a click is under way, so that the click lands on the row behind it.
	 */
	render()
	{
		if(!this.loaded)
		{
			return html`${this.loadingTemplate()}`;
		}
		const entries = this.listEntries();
		return html`
            ${this.label ? html`
                <div part="label" class="label">${this.label}</div>` : nothing}
            ${keyed(this._epoch, html`
                <sl-tree
                        part="menu"
                        class=${classMap({"no-folders": !entries.some(entry => "folder" in entry)})}
                        selection="leaf"
                        ${ref(this.treeRef)}
                        @click=${this.handleTreeClick}
                        @keydown=${this.handleTreeKeydown}
                        @sl-expand=${this.handleFolderToggle}
                        @sl-collapse=${this.handleFolderToggle}
                >
                    ${repeat(entries, (entry) => "folder" in entry ? "folder:" + entry.folder : entry.key,
                             (entry) => "folder" in entry ? this.folderTemplate(entry.folder, entry.items) : this.menuItemTemplate(entry.key, entry.favorite))}
                    <slot></slot>
                    ${this.noAdd ? nothing : this.menuItemTemplate(Favorite.ADD_VALUE, <Favorite>{
                        name: this.egw().lang("Add current"),
                        state: {},
                        group: false
                    })}
                </sl-tree>`)}
		`;
	}
}
