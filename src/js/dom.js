import { apiFetch } from './api.js';
import { openModal } from './dialog.js';
import { dragStart, dragEnd } from './drag.js';

const EDIT_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M3 21v-4.25L16.2 3.575q.3-.275.663-.425t.762-.15t.775.15t.65.45L20.425 5q.3.275.438.65T21 6.4q0 .4-.137.763t-.438.662L7.25 21zM17.6 7.8L19 6.4L17.6 5l-1.4 1.4z"/></svg>`;
const DELETE_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M7 21q-.825 0-1.412-.587T5 19V6H4V4h5V3h6v1h5v2h-1v13q0 .825-.587 1.413T17 21zM17 6H7v13h10zM9 17h2V8H9zm4 0h2V8h-2zM7 6v13z"/></svg>`;
const DRAG_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M9 20q-.825 0-1.412-.587T7 18t.588-1.412T9 16t1.413.588T11 18t-.587 1.413T9 20m6 0q-.825 0-1.412-.587T13 18t.588-1.412T15 16t1.413.588T17 18t-.587 1.413T15 20m-6-6q-.825 0-1.412-.587T7 12t.588-1.412T9 10t1.413.588T11 12t-.587 1.413T9 14m6 0q-.825 0-1.412-.587T13 12t.588-1.412T15 10t1.413.588T17 12t-.587 1.413T15 14M9 8q-.825 0-1.412-.587T7 6t.588-1.412T9 4t1.413.588T11 6t-.587 1.413T9 8m6 0q-.825 0-1.412-.587T13 6t.588-1.412T15 4t1.413.588T17 6t-.587 1.413T15 8"/></svg>`;

export const updateAssignedBadges = (card, assignees) => {
	let container = card.querySelector('.assigned-badges');
	if (assignees && assignees.length > 0) {
		if (!container) {
			container = document.createElement('div');
			container.className = 'assigned-badges';
			card.querySelector('.interactions').before(container);
		}
		container.innerHTML = '';
		assignees.forEach((a) => {
			const badge = document.createElement('div');
			badge.className = 'assigned-badge';
			badge.textContent = a.initials;
			badge.style.background = a.color;
			badge.title = a.name;
			container.appendChild(badge);
		});
	} else if (container) {
		container.remove();
	}
};

export const updateAttachmentBadge = (card, count) => {
	let badge = card.querySelector('.item-attachment-badge');
	if (count > 0) {
		if (!badge) {
			badge = document.createElement('div');
			badge.className = 'item-attachment-badge';
			card.querySelector('.interactions').before(badge);
		}
		badge.innerHTML = `<span>📎</span> ${count}`;
	} else if (badge) {
		badge.remove();
	}
};

export const deleteItem = (btn) => {
	const item = btn.closest('.drag-item');
	apiFetch('api/delete_item.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ id: item.dataset.id }),
	})
		.then(() => {
			if (!document.startViewTransition) {
				item.remove();
				return;
			}
			document.startViewTransition(() => item.remove());
		})
		.catch((err) => console.error('deleteItem failed:', err));
};

export const createItemEl = (id, content, attachCount, assignees = []) => {
	const li = document.createElement('li');
	li.className = 'drag-item';
	li.dataset.id = id;

	const contentDiv = document.createElement('div');
	contentDiv.className = 'item-content';
	contentDiv.innerHTML = content;
	li.appendChild(contentDiv);

	if (attachCount > 0) {
		const badge = document.createElement('div');
		badge.className = 'item-attachment-badge';
		badge.innerHTML = `<span>📎</span> ${attachCount}`;
		li.appendChild(badge);
	}

	if (assignees.length > 0) {
		const container = document.createElement('div');
		container.className = 'assigned-badges';
		assignees.forEach((a) => {
			const ab = document.createElement('div');
			ab.className = 'assigned-badge';
			ab.textContent = a.initials;
			ab.style.background = a.color;
			ab.title = a.name;
			container.appendChild(ab);
		});
		li.appendChild(container);
	}

	const interactions = document.createElement('div');
	interactions.className = 'interactions';
	interactions.innerHTML = `
		<button class="edit-btn" title="Edit">${EDIT_ICON_SVG}</button>
		<button class="delete-btn" title="Delete">${DELETE_ICON_SVG}</button>
		<button class="drag-btn" title="Drag">${DRAG_ICON_SVG}</button>`;
	li.appendChild(interactions);

	attachItemListeners(li);
	return li;
};

export const attachItemListeners = (item) => {
	const dragBtn = item.querySelector('.drag-btn');

	dragBtn.addEventListener('mousedown', () => {
		item.draggable = true;
	});
	dragBtn.addEventListener('mouseup', () => {
		item.draggable = false;
	});

	item.addEventListener('dragstart', dragStart);
	item.addEventListener('dragend', dragEnd);

	item.querySelector('.delete-btn').addEventListener('click', (e) => deleteItem(e.currentTarget));

	item.querySelector('.edit-btn').addEventListener('click', () => {
		const list = item.closest('.drag-list');
		const wrapper = item.closest('.container');
		openModal('edit', list.dataset.column, wrapper.querySelector('h2').textContent, item.dataset.id);
	});
};
