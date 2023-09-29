const selectionMode = document.querySelector('#selection-mode');
const tree = document.querySelector('.tree-selectable');

selectionMode.addEventListener('sl-change', () => {
    tree.querySelectorAll('sl-tree-item').forEach(item => (item.selected = false));
    tree.selection = selectionMode.value;
});