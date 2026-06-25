import { apiFetch } from './api.js';

// ── Item drag ────────────────────────────────────────────────────────────────

let draggedEl = null;
let activeList = null;

const createDragPreview = (item) => {
	const preview = item.cloneNode(true);
	preview.style.cssText =
		'position:fixed;top:-9999px;left:-9999px;width:180px;opacity:0.9;' +
		'border:1px solid #bbb;border-radius:6px;background:white;padding:8px;' +
		'box-shadow:0 4px 12px rgba(0,0,0,0.15);pointer-events:none;';
	const interactions = preview.querySelector('.interactions');
	if (interactions) interactions.remove();
	document.body.appendChild(preview);
	return preview;
};

export const dragStart = (e) => {
	e.stopPropagation();
	draggedEl = e.currentTarget;
	e.currentTarget.classList.add('dragging');
	const preview = createDragPreview(e.currentTarget);
	e.dataTransfer.setDragImage(preview, 20, 20);
	requestAnimationFrame(() => preview.remove());
};

export const dragEnd = (e) => {
	e.currentTarget.classList.remove('dragging');
	e.currentTarget.draggable = false;
	document.querySelectorAll('.drag-list').forEach((c) => c.classList.remove('drop'));
	if (draggedEl) saveOrder();
	draggedEl = null;
	activeList = null;
};

const getDragAfterElement = (list, y) => {
	const items = [...list.querySelectorAll('.drag-item:not(.dragging)')];
	return items.reduce(
		(closest, child) => {
			const box = child.getBoundingClientRect();
			const offset = y - box.top - box.height / 2;
			if (offset < 0 && offset > closest.offset) {
				return { offset, element: child };
			}
			return closest;
		},
		{ offset: Number.NEGATIVE_INFINITY },
	).element;
};

export const dragOverList = (e) => {
	e.preventDefault();
	if (!draggedEl) return;
	const list = e.currentTarget;
	if (activeList && activeList !== list) {
		activeList.classList.remove('drop');
	}
	activeList = list;
	list.classList.add('drop');
	const afterElement = getDragAfterElement(list, e.clientY);
	if (afterElement) {
		list.insertBefore(draggedEl, afterElement);
	} else {
		list.appendChild(draggedEl);
	}
};

export const drop = (e) => {
	e.stopPropagation();
	e.preventDefault();
	document.querySelectorAll('.drag-list').forEach((c) => c.classList.remove('drop'));
};

export const saveOrder = () => {
	const order = {};
	document.querySelectorAll('.drag-list').forEach((list) => {
		order[list.dataset.column] = [...list.querySelectorAll('.drag-item')].map((i) => i.dataset.id);
	});
	apiFetch('api/save_order.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(order),
	}).catch((err) => console.error('saveOrder failed:', err));
};

// ── Column drag ──────────────────────────────────────────────────────────────

let draggedCol = null;

export const colDragStart = (e) => {
	draggedCol = e.currentTarget.closest('.container');
	draggedCol.classList.add('col-dragging');
	const label = draggedCol.querySelector('h2')?.textContent || '';
	const itemCount = draggedCol.querySelectorAll('.drag-item').length;
	const preview = document.createElement('div');
	preview.style.cssText =
		'position:fixed;top:-9999px;left:-9999px;width:220px;padding:12px 16px;' +
		'background:white;border:1px solid #ccc;border-radius:8px;' +
		'box-shadow:0 8px 24px rgba(0,0,0,0.18);pointer-events:none;font-family:inherit;';
	preview.innerHTML =
		`<div style="font-weight:600;font-size:0.95rem;margin-bottom:4px">${label}</div>` +
		`<div style="font-size:0.78rem;color:#888">${itemCount} item${itemCount !== 1 ? 's' : ''}</div>`;
	document.body.appendChild(preview);
	e.dataTransfer.setDragImage(preview, 30, 20);
	requestAnimationFrame(() => preview.remove());
};

export const colDragEnd = () => {
	if (draggedCol) {
		draggedCol.classList.remove('col-dragging');
		draggedCol.draggable = false;
		saveColumnOrder();
	}
	draggedCol = null;
};

const getColAfterElement = (container, x) => {
	const cols = [...container.querySelectorAll('.container:not(.col-dragging):not(.wrapper--add-col)')];
	return cols.reduce(
		(closest, child) => {
			const box = child.getBoundingClientRect();
			const offset = x - box.left - box.width / 2;
			if (offset < 0 && offset > closest.offset) {
				return { offset, element: child };
			}
			return closest;
		},
		{ offset: Number.NEGATIVE_INFINITY },
	).element;
};

export const colDragOver = (e) => {
	if (!draggedCol) return;
	e.preventDefault();
	const container = e.currentTarget;
	const afterElement = getColAfterElement(container, e.clientX);
	if (afterElement) {
		container.insertBefore(draggedCol, afterElement);
	} else {
		const addColWrapper = container.querySelector('.wrapper--add-col');
		if (addColWrapper) {
			container.insertBefore(draggedCol, addColWrapper);
		} else {
			container.appendChild(draggedCol);
		}
	}
};

export const colDrop = (e) => {
	if (!draggedCol) return;
	e.stopPropagation();
	e.preventDefault();
};

const saveColumnOrder = () => {
	const lists = document.getElementById('drag-lists');
	const order = [...lists.querySelectorAll('.container:not(.wrapper--add-col)')].map(
		(c) => c.dataset.colKey,
	);
	apiFetch('api/save_column_order.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ order }),
	}).catch((err) => console.error('saveColumnOrder failed:', err));
};
