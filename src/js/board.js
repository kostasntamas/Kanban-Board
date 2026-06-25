import '../css/global.css';
import '../css/board.css';

import { dragOverList, drop, colDragStart, colDragEnd, colDragOver, colDrop } from './drag.js';
import { attachItemListeners } from './dom.js';
import { openModal } from './dialog.js';
import { deleteColumn } from './columns.js';

document.querySelectorAll('.drag-list').forEach((list) => {
	list.addEventListener('dragover', dragOverList);
	list.addEventListener('drop', drop);
});

document.querySelectorAll('.drag-item').forEach((item) => attachItemListeners(item));

document.querySelectorAll('.add-btn').forEach((btn) => {
	btn.addEventListener('click', () => openModal('add', btn.dataset.col, btn.dataset.label));
});

document.querySelectorAll('.delete-col-btn').forEach((btn) => {
	btn.addEventListener('click', () => deleteColumn(btn.closest('.container')));
});

// Column drag
const dragLists = document.getElementById('drag-lists');
dragLists.addEventListener('dragover', colDragOver);
dragLists.addEventListener('drop', colDrop);

document.querySelectorAll('.col-drag-btn').forEach((btn) => {
	const wrapper = btn.closest('.container');
	btn.addEventListener('mousedown', () => {
		wrapper.draggable = true;
	});
	btn.addEventListener('mouseup', () => {
		wrapper.draggable = false;
	});
	wrapper.addEventListener('dragstart', colDragStart);
	wrapper.addEventListener('dragend', colDragEnd);
});
