import { apiFetch } from './api.js';
import { openModal } from './dialog.js';
import { dragStart, dragEnd } from './drag.js';
import { ATTACHMENT_ICON_SVG, EDIT_ICON_SVG, DELETE_ICON_SVG, DRAG_ICON_SVG } from './icons.js';

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
		badge.innerHTML = `<span style="rotate: 90deg;">${ATTACHMENT_ICON_SVG}</span> ${count}`;
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
		badge.innerHTML = `<span style="rotate: 90deg;">${ATTACHMENT_ICON_SVG}</span> ${attachCount}`;
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
