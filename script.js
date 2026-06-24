'use strict';

// ── Drag & Drop ───────────────────────────────────────────────────────────────

let draggedEl = null;

const dragStart = (e) => {
	draggedEl = e.currentTarget;
	e.currentTarget.classList.add('dragging');
};

const dragEnd = (e) => {
	e.currentTarget.classList.remove('dragging');
	e.currentTarget.draggable = false;
	draggedEl = null;
};

const dragEnter = (e) => e.currentTarget.classList.add('drop');
const dragLeave = (e) => e.currentTarget.classList.remove('drop');
const allowDrop = (e) => e.preventDefault();

const drop = (e) => {
	e.stopPropagation();
	e.preventDefault();
	document.querySelectorAll('.drag-list').forEach((c) => c.classList.remove('drop'));
	if (!draggedEl) return;
	const target = e.currentTarget.classList.contains('drag-list')
		? e.currentTarget
		: e.currentTarget.closest('.drag-list');
	target.appendChild(draggedEl);
	saveOrder();
};

// ── Order persistence ─────────────────────────────────────────────────────────

const saveOrder = () => {
	const order = {};
	document.querySelectorAll('.drag-list').forEach((list) => {
		order[list.dataset.column] = [...list.querySelectorAll('.drag-item')].map((i) => i.dataset.id);
	});
	apiFetch('save_order.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(order),
	}).catch((err) => console.error('saveOrder failed:', err));
};

// ── Delete item ───────────────────────────────────────────────────────────────

const deleteItem = (btn) => {
	const item = btn.closest('.drag-item');
	apiFetch('delete_item.php', {
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

// ── Auth redirect on 401 ─────────────────────────────────────────────────────

const apiFetch = (...args) =>
	fetch(...args).then((r) => {
		if (r.status === 401) {
			location.href = 'login.php';
		}
		return r;
	});

// ── Item dialog state ─────────────────────────────────────────────────────────

let quill = null;
let modalMode = null; // 'add' | 'edit'
let modalCol = null;
let modalColLabel = null;
let modalItemId = null;
let pendingFiles = []; // { file, previewUrl }
let existingAttachments = [];
let workspaceMembers = []; // populated once on first modal open

// ── Quill (lazy init) ─────────────────────────────────────────────────────────

const assignSelect = document.getElementById('assign-select');

const loadWorkspaceMembers = async () => {
	if (workspaceMembers.length > 0) return;
	const r = await apiFetch('get_workspace_members.php');
	const data = await r.json();
	workspaceMembers = data;
	assignSelect.innerHTML = '<option value="">Unassigned</option>';
	data.forEach((m) => {
		const opt = document.createElement('option');
		opt.value = m.id;
		opt.textContent = m.name;
		assignSelect.appendChild(opt);
	});
};

const initQuill = () => {
	if (quill) return;
	quill = new Quill('#quill-editor', {
		theme: 'snow',
		placeholder: 'Write something…',
		modules: {
			toolbar: [
				['bold', 'italic', 'underline', 'strike'],
				['blockquote', 'code-block'],
				[{ list: 'ordered' }, { list: 'bullet' }],
				['link'],
				['clean'],
			],
		},
	});
};

// ── Item dialog open / close ──────────────────────────────────────────────────

const itemDialog = document.getElementById('item-dialog');
const modalTitle = document.getElementById('modal-title');

const openModal = async (mode, col, colLabel, itemId = null) => {
	initQuill();
	modalMode = mode;
	modalCol = col;
	modalColLabel = colLabel;
	modalItemId = itemId;
	pendingFiles = [];
	existingAttachments = [];
	assignSelect.value = '';

	await loadWorkspaceMembers();

	if (mode === 'add') {
		modalTitle.textContent = `Add to "${colLabel}"`;
		quill.setContents([]);
		renderAttachmentList();
		itemDialog.showModal();
		setTimeout(() => quill.focus(), 50);
	} else {
		modalTitle.textContent = 'Edit Item';
		quill.setContents([]);
		renderAttachmentList();
		itemDialog.showModal();
		apiFetch(`get_item.php?id=${itemId}`)
			.then((r) => r.json())
			.then((data) => {
				quill.clipboard.dangerouslyPasteHTML(data.content || '');
				existingAttachments = data.attachments || [];
				assignSelect.value = data.assigned_to || '';
				renderAttachmentList();
				setTimeout(() => quill.focus(), 50);
			})
			.catch((err) => console.error('Failed to load item:', err));
	}
};

const closeModal = () => {
	itemDialog.close();
	pendingFiles.forEach((f) => {
		if (f.previewUrl) URL.revokeObjectURL(f.previewUrl);
	});
	pendingFiles = [];
	existingAttachments = [];
};

// Esc key fires 'cancel' on <dialog>; hook it to run our cleanup
itemDialog.addEventListener('cancel', (e) => {
	e.preventDefault();
	closeModal();
});

// Backdrop click: close when click lands outside the dialog box
itemDialog.addEventListener('click', (e) => {
	const r = itemDialog.getBoundingClientRect();
	if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
		closeModal();
	}
});

document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('cancel-btn').addEventListener('click', closeModal);

// ── File handling ─────────────────────────────────────────────────────────────

const fileDropZone = document.getElementById('file-drop-zone');
const fileInput = document.getElementById('file-input');
const attachmentsList = document.getElementById('attachments-list');

fileDropZone.addEventListener('dragover', (e) => {
	e.preventDefault();
	e.stopPropagation();
	fileDropZone.classList.add('drag-over');
});
fileDropZone.addEventListener('dragleave', () => fileDropZone.classList.remove('drag-over'));
fileDropZone.addEventListener('drop', (e) => {
	e.preventDefault();
	e.stopPropagation();
	fileDropZone.classList.remove('drag-over');
	handleFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => {
	handleFiles(fileInput.files);
	fileInput.value = '';
});

const handleFiles = (files) => {
	[...files].forEach((file) => {
		pendingFiles.push({ file, previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null });
	});
	renderAttachmentList();
};

const fileIcon = (mimeType) => {
	if (mimeType === 'application/pdf') return '📄';
	if (mimeType.includes('word')) return '📝';
	if (mimeType.includes('sheet') || mimeType.includes('excel')) return '📊';
	if (mimeType.includes('zip') || mimeType.includes('compressed')) return '🗜️';
	if (mimeType.startsWith('text/')) return '📃';
	return '📎';
};

const makeAttachmentEl = (name, mimeType, thumbUrl, onRemove) => {
	const div = document.createElement('div');
	div.className = 'attachment-item';

	if (thumbUrl) {
		const img = document.createElement('img');
		img.src = thumbUrl;
		img.className = 'attachment-thumb';
		div.appendChild(img);
	} else {
		const icon = document.createElement('span');
		icon.className = 'attachment-icon';
		icon.textContent = fileIcon(mimeType);
		div.appendChild(icon);
	}

	const nameEl = document.createElement('span');
	nameEl.className = 'attachment-name';
	nameEl.textContent = name;
	div.appendChild(nameEl);

	const removeBtn = document.createElement('button');
	removeBtn.className = 'attachment-remove';
	removeBtn.type = 'button';
	removeBtn.textContent = '×';
	removeBtn.addEventListener('click', onRemove);
	div.appendChild(removeBtn);

	return div;
};

const renderAttachmentList = () => {
	attachmentsList.innerHTML = '';

	existingAttachments.forEach((att) => {
		const el = makeAttachmentEl(
			att.original_name,
			att.mime_type,
			att.mime_type.startsWith('image/') ? att.url : null,
			() => {
				el.style.opacity = '0.5';
				fetch('delete_attachment.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ id: att.id }),
				})
					.then(() => {
						existingAttachments = existingAttachments.filter((a) => a.id !== att.id);
						el.remove();
					})
					.catch((err) => {
						console.error('Failed to delete attachment:', err);
						el.style.opacity = '';
					});
			},
		);
		attachmentsList.appendChild(el);
	});

	pendingFiles.forEach((item, idx) => {
		const el = makeAttachmentEl(item.file.name, item.file.type, item.previewUrl, () => {
			if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
			pendingFiles.splice(idx, 1);
			renderAttachmentList();
		});
		el.classList.add('pending');
		attachmentsList.appendChild(el);
	});
};

const uploadFiles = async (itemId) => {
	for (const item of pendingFiles) {
		const fd = new FormData();
		fd.append('file', item.file);
		fd.append('todo_id', itemId);
		await fetch('upload_file.php', { method: 'POST', body: fd });
		if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
	}
};

// ── Save item ─────────────────────────────────────────────────────────────────

const saveBtn = document.getElementById('save-btn');

saveBtn.addEventListener('click', async () => {
	const content = quill.root.innerHTML;
	const hasText = quill.getText().trim().length > 0;
	if (!hasText && pendingFiles.length === 0) return;

	saveBtn.disabled = true;
	try {
		const assignedTo = assignSelect.value ? parseInt(assignSelect.value) : null;
		const assignedMember = assignedTo ? workspaceMembers.find((m) => m.id === assignedTo) || null : null;

		if (modalMode === 'add') {
			const r = await apiFetch('add_item.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ col: modalCol, content, assigned_to: assignedTo }),
			});
			const data = await r.json();
			if (!data.id) throw new Error('Server error creating item');
			if (pendingFiles.length > 0) await uploadFiles(data.id);
			document
				.querySelector(`.drag-list[data-column="${modalCol}"]`)
				.appendChild(createItemEl(data.id, content, pendingFiles.length, data.assigned || null));
		} else {
			const r = await apiFetch('update_item.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: modalItemId, content, assigned_to: assignedTo }),
			});
			const data = await r.json();
			if (pendingFiles.length > 0) await uploadFiles(modalItemId);
			const card = document.querySelector(`.drag-item[data-id="${modalItemId}"]`);
			if (card) {
				card.querySelector('.item-content').innerHTML = content;
				updateAttachmentBadge(card, existingAttachments.length + pendingFiles.length);
				updateAssignedBadge(card, data.assigned || null);
			}
		}
		closeModal();
	} catch (err) {
		console.error('Save failed:', err);
	} finally {
		saveBtn.disabled = false;
	}
});

// ── DOM factory (items) ───────────────────────────────────────────────────────

const DRAG_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M9 20q-.825 0-1.412-.587T7 18t.588-1.412T9 16t1.413.588T11 18t-.587 1.413T9 20m6 0q-.825 0-1.412-.587T13 18t.588-1.412T15 16t1.413.588T17 18t-.587 1.413T15 20m-6-6q-.825 0-1.412-.587T7 12t.588-1.412T9 10t1.413.588T11 12t-.587 1.413T9 14m6 0q-.825 0-1.412-.587T13 12t.588-1.412T15 10t1.413.588T17 12t-.587 1.413T15 14M9 8q-.825 0-1.412-.587T7 6t.588-1.412T9 4t1.413.588T11 6t-.587 1.413T9 8m6 0q-.825 0-1.412-.587T13 6t.588-1.412T15 4t1.413.588T17 6t-.587 1.413T15 8"/></svg>`;

const updateAssignedBadge = (card, assigned) => {
	let badge = card.querySelector('.assigned-badge');
	if (assigned) {
		if (!badge) {
			badge = document.createElement('div');
			badge.className = 'assigned-badge';
			card.querySelector('.interactions').before(badge);
		}
		badge.textContent = assigned.initials;
		badge.style.background = assigned.color;
		badge.title = assigned.name;
	} else if (badge) {
		badge.remove();
	}
};

const updateAttachmentBadge = (card, count) => {
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

const createItemEl = (id, content, attachCount, assigned = null) => {
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

	if (assigned) {
		const ab = document.createElement('div');
		ab.className = 'assigned-badge';
		ab.textContent = assigned.initials;
		ab.style.background = assigned.color;
		ab.title = assigned.name;
		li.appendChild(ab);
	}

	const interactions = document.createElement('div');
	interactions.className = 'interactions';
	interactions.innerHTML = `
		<button class="edit-btn" title="Edit">&#9998;</button>
		<button class="delete-btn" title="Delete">&times;</button>
		<button class="drag-btn" title="Drag">${DRAG_ICON_SVG}</button>`;
	li.appendChild(interactions);

	attachItemListeners(li);
	return li;
};

const attachItemListeners = (item) => {
	const dragBtn = item.querySelector('.drag-btn');

	// Dragging is only allowed while the drag handle is held down
	dragBtn.addEventListener('mousedown', () => {
		item.draggable = true;
	});
	dragBtn.addEventListener('mouseup', () => {
		item.draggable = false;
	}); // no-drag click cleanup

	item.addEventListener('dragstart', dragStart);
	item.addEventListener('dragend', dragEnd); // dragEnd resets item.draggable = false
	item.addEventListener('dragover', allowDrop);
	item.addEventListener('drop', drop);

	item.querySelector('.delete-btn').addEventListener('click', (e) => deleteItem(e.currentTarget));

	item.querySelector('.edit-btn').addEventListener('click', () => {
		const list = item.closest('.drag-list');
		const wrapper = item.closest('.container');
		openModal('edit', list.dataset.column, wrapper.querySelector('h2').textContent, item.dataset.id);
	});
};

// ── Column management ─────────────────────────────────────────────────────────

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

	apiFetch('add_column.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ label }),
	})
		.then((r) => r.json())
		.then((data) => {
			if (!data.key) throw new Error('Failed to create column');
			colDialog.close();
			addColumnToDOM(data.key, data.label);
		})
		.catch((err) => console.error('Failed to add column:', err));
});

const deleteColumn = (wrapper) => {
	const key = wrapper.dataset.colKey;
	const label = wrapper.querySelector('h2').textContent;
	const itemCount = wrapper.querySelectorAll('.drag-item').length;
	const msg =
		itemCount > 0
			? `Delete "${label}" and its ${itemCount} item${itemCount > 1 ? 's' : ''}?`
			: `Delete column "${label}"?`;

	if (!confirm(msg)) return;

	apiFetch('delete_column.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ key }),
	})
		.then(() => {
			if (document.startViewTransition) {
				document.startViewTransition(() => wrapper.remove());
			} else {
				wrapper.remove();
			}
		})
		.catch((err) => console.error('Failed to delete column:', err));
};

const addColumnToDOM = (key, label) => {
	const wrapper = document.createElement('div');
	wrapper.className = 'container';
	wrapper.dataset.colKey = key;

	const colHeader = document.createElement('div');
	colHeader.className = 'col-header';
	const h2 = document.createElement('h2');
	h2.textContent = label;
	const deleteColBtn = document.createElement('button');
	deleteColBtn.className = 'delete-col-btn';
	deleteColBtn.title = 'Delete column';
	deleteColBtn.textContent = '×';
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

	document.getElementById('drag-lists').appendChild(wrapper);

	ul.addEventListener('dragenter', dragEnter);
	ul.addEventListener('dragleave', dragLeave);
	ul.addEventListener('dragover', allowDrop);
	ul.addEventListener('drop', drop);

	addBtn.addEventListener('click', () => openModal('add', key, label));
	deleteColBtn.addEventListener('click', () => deleteColumn(wrapper));
};

// ── Init ──────────────────────────────────────────────────────────────────────

document.querySelectorAll('.drag-list').forEach((list) => {
	list.addEventListener('dragenter', dragEnter);
	list.addEventListener('dragleave', dragLeave);
	list.addEventListener('dragover', allowDrop);
	list.addEventListener('drop', drop);
});

document.querySelectorAll('.drag-item').forEach((item) => attachItemListeners(item));

document.querySelectorAll('.add-btn').forEach((btn) => {
	btn.addEventListener('click', () => openModal('add', btn.dataset.col, btn.dataset.label));
});

document.querySelectorAll('.delete-col-btn').forEach((btn) => {
	btn.addEventListener('click', () => deleteColumn(btn.closest('.container')));
});
