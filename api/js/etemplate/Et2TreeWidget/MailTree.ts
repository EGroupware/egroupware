import {Et2Tree} from "./Et2Tree";

export function initMailTree(): Et2Tree {
    const changeFunction = () => {
        console.log("change"+tree)
    }
    const tree: Et2Tree = document.querySelector("et2-tree");
    tree.selection = "single";
    tree.addEventListener("sl-selection-change", (event)=>{console.log(event)})
    return tree;
}

