import { apiFetch } from './api.js';
import { openModal, addColumnOption, removeColumnOption } from './dialog.js';
import { dragOverList, drop, colDragStart, colDragEnd } from './drag.js';

const colDialog = document.getElementById('col-dialog');
const colLabelInput = document.getElementById('col-label-input');

document.getElementById('add-col-trigger').addEventListener('click', () => {
	colLabelInput.value = '';
	colDialog.showModal();
	setTimeout(() => colLabelInput.focus(), 50);
});

colDialog.addEventListener('cancel', (e) => {
	e.preventDefault();
	colDialog.close();
});
colDialog.addEventListener('click', (e) => {
	const r = colDialog.getBoundingClientRect();
	if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
		colDialog.close();
	}
});
document.getElementById('col-cancel-btn').addEventListener('click', () => colDialog.close());
colLabelInput.addEventListener('keydown', (e) => {
	if (e.key === 'Enter') document.getElementById('col-create-btn').click();
});

document.getElementById('col-create-btn').addEventListener('click', () => {
	const label = colLabelInput.value.trim();
	if (!label) return;

	apiFetch('api/add_column.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ label }),
	})
		.then((r) => r.json())
		.then((data) => {
			if (!data.key) throw new Error('Failed to create column');
			colDialog.close();
			addColumnOption(data.key, data.label);
			addColumnToDOM(data.key, data.label);
		})
		.catch((err) => console.error('Failed to add column:', err));
});

export const deleteColumn = (wrapper) => {
	const key = wrapper.dataset.colKey;
	const label = wrapper.querySelector('h2').textContent;
	const itemCount = wrapper.querySelectorAll('.drag-item').length;
	const msg =
		itemCount > 0
			? `Delete "${label}" and its ${itemCount} item${itemCount > 1 ? 's' : ''}?`
			: `Delete column "${label}"?`;

	if (!confirm(msg)) return;

	apiFetch('api/delete_column.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ key }),
	})
		.then(() => {
			removeColumnOption(key);
			if (document.startViewTransition) {
				document.startViewTransition(() => wrapper.remove());
			} else {
				wrapper.remove();
			}
		})
		.catch((err) => console.error('Failed to delete column:', err));
};

const DRAG_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M9 20q-.825 0-1.412-.587T7 18t.588-1.412T9 16t1.413.588T11 18t-.587 1.413T9 20m6 0q-.825 0-1.412-.587T13 18t.588-1.412T15 16t1.413.588T17 18t-.587 1.413T15 20m-6-6q-.825 0-1.412-.587T7 12t.588-1.412T9 10t1.413.588T11 12t-.587 1.413T9 14m6 0q-.825 0-1.412-.587T13 12t.588-1.412T15 10t1.413.588T17 12t-.587 1.413T15 14M9 8q-.825 0-1.412-.587T7 6t.588-1.412T9 4t1.413.588T11 6t-.587 1.413T9 8m6 0q-.825 0-1.412-.587T13 6t.588-1.412T15 4t1.413.588T17 6t-.587 1.413T15 8"/></svg>`;

const addColumnToDOM = (key, label) => {
	const wrapper = document.createElement('div');
	wrapper.className = 'container shadow';
	wrapper.dataset.colKey = key;

	const colHeader = document.createElement('div');
	colHeader.className = 'col-header';
	const colDragBtn = document.createElement('button');
	colDragBtn.className = 'col-drag-btn';
	colDragBtn.title = 'Drag column';
	colDragBtn.innerHTML = DRAG_ICON_SVG;
	const h2 = document.createElement('h2');
	h2.textContent = label;
	const deleteColBtn = document.createElement('button');
	deleteColBtn.className = 'delete-col-btn';
	deleteColBtn.title = 'Delete column';
	deleteColBtn.textContent = '×';
	colHeader.appendChild(colDragBtn);
	colHeader.appendChild(h2);
	colHeader.appendChild(deleteColBtn);
	wrapper.appendChild(colHeader);

	const addForm = document.createElement('div');
	addForm.className = 'add-form';
	const addBtn = document.createElement('button');
	addBtn.className = 'add-btn';
	addBtn.dataset.col = key;
	addBtn.dataset.label = label;
	addBtn.textContent = '+ Add';
	addForm.appendChild(addBtn);
	wrapper.appendChild(addForm);

	const ul = document.createElement('ul');
	ul.className = 'drag-list';
	ul.dataset.column = key;
	wrapper.appendChild(ul);

	const dragLists = document.getElementById('drag-lists');
	const addColWrapper = dragLists.querySelector('.wrapper--add-col');
	if (addColWrapper) {
		dragLists.insertBefore(wrapper, addColWrapper);
	} else {
		dragLists.appendChild(wrapper);
	}

	ul.addEventListener('dragover', dragOverList);
	ul.addEventListener('drop', drop);

	colDragBtn.addEventListener('mousedown', () => { wrapper.draggable = true; });
	colDragBtn.addEventListener('mouseup', () => { wrapper.draggable = false; });
	wrapper.addEventListener('dragstart', colDragStart);
	wrapper.addEventListener('dragend', colDragEnd);

	addBtn.addEventListener('click', () => openModal('add', key, label));
	deleteColBtn.addEventListener('click', () => deleteColumn(wrapper));
};
