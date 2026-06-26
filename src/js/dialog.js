import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { apiFetch } from './api.js';
import { createItemEl, updateAttachmentBadge, updateAssignedBadges } from './dom.js';
import { saveOrder } from './drag.js';
import { ATTACHMENT_ICON_SVG, ZIP_ICON_SVG, PDF_ICON_SVG, DOCS_ICON_SVG, SHEET_ICON_SVG } from './icons.js';

let quill = null;
let modalMode = null;
let modalCol = null;
let modalColLabel = null;
let modalItemId = null;
let pendingFiles = [];
let existingAttachments = [];
let workspaceMembers = [];
let selectedAssignees = [];

// ── Column selector ───────────────────────────────────────────────────────────

const colSelect = document.getElementById('col-select');

const populateColSelect = (currentCol) => {
	const cols = window.__KANBAN_COLUMNS__ || [];
	colSelect.innerHTML = '';
	cols.forEach((c) => {
		const opt = document.createElement('option');
		opt.value = c.key;
		opt.textContent = c.label;
		if (c.key === currentCol) opt.selected = true;
		colSelect.appendChild(opt);
	});
};

// ── Multiselect assignees ─────────────────────────────────────────────────────

const chipsContainer = document.getElementById('assign-chips');
const searchInput = document.getElementById('assign-search');
const dropdown = document.getElementById('assign-dropdown');
const control = document.getElementById('assign-control');

const loadWorkspaceMembers = async () => {
	if (workspaceMembers.length > 0) return;
	const r = await apiFetch('api/get_workspace_members.php');
	workspaceMembers = await r.json();
};

const renderChips = () => {
	chipsContainer.innerHTML = '';
	selectedAssignees.forEach((m) => {
		const chip = document.createElement('span');
		chip.className = 'multiselect-chip';
		chip.innerHTML = `<span class="chip-avatar" style="background:${m.color}">${m.initials}</span>${m.name}<button type="button" class="chip-remove" data-id="${m.id}">&times;</button>`;
		chipsContainer.appendChild(chip);
	});
	searchInput.placeholder = selectedAssignees.length ? '' : 'Add assignee...';
};

const renderDropdown = () => {
	const filter = searchInput.value.toLowerCase();
	const selectedIds = new Set(selectedAssignees.map((m) => m.id));
	dropdown.innerHTML = '';

	const filtered = workspaceMembers.filter((m) => m.name.toLowerCase().includes(filter));
	if (filtered.length === 0) {
		const empty = document.createElement('div');
		empty.className = 'multiselect-empty';
		empty.textContent = 'No members found';
		dropdown.appendChild(empty);
		return;
	}

	filtered.forEach((m) => {
		const row = document.createElement('div');
		row.className = 'multiselect-option' + (selectedIds.has(m.id) ? ' selected' : '');
		row.dataset.id = m.id;
		row.innerHTML = `<span class="chip-avatar" style="background:${m.color}">${m.initials}</span><span>${m.name}</span>${selectedIds.has(m.id) ? '<span class="check-mark">&#10003;</span>' : ''}`;
		dropdown.appendChild(row);
	});
};

const showDropdown = () => {
	renderDropdown();
	dropdown.classList.remove('hidden');
};

const hideDropdown = () => {
	dropdown.classList.add('hidden');
};

const toggleAssignee = (id) => {
	const idx = selectedAssignees.findIndex((m) => m.id === id);
	if (idx >= 0) {
		selectedAssignees.splice(idx, 1);
	} else {
		const member = workspaceMembers.find((m) => m.id === id);
		if (member) selectedAssignees.push(member);
	}
	searchInput.value = '';
	renderChips();
	renderDropdown();
};

control.addEventListener('click', (e) => {
	if (e.target.closest('.chip-remove')) {
		toggleAssignee(parseInt(e.target.closest('.chip-remove').dataset.id));
		return;
	}
	searchInput.focus();
	showDropdown();
});

searchInput.addEventListener('input', () => {
	showDropdown();
});

searchInput.addEventListener('focus', () => {
	showDropdown();
});

dropdown.addEventListener('mousedown', (e) => {
	e.preventDefault();
	const option = e.target.closest('.multiselect-option');
	if (option) toggleAssignee(parseInt(option.dataset.id));
});

document.addEventListener('click', (e) => {
	if (!e.target.closest('#assign-multiselect')) hideDropdown();
});

searchInput.addEventListener('keydown', (e) => {
	if (e.key === 'Backspace' && !searchInput.value && selectedAssignees.length) {
		selectedAssignees.pop();
		renderChips();
		renderDropdown();
	}
});

// ── Quill (lazy init) ─────────────────────────────────────────────────────────

const initQuill = () => {
	if (quill) return;
	quill = new Quill('#quill-editor', {
		theme: 'snow',
		placeholder: 'Write something…',
		modules: {
			toolbar: [
				['bold', 'italic', 'underline', 'strike'],
				['blockquote', 'code-block'],
				[{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
				['link'],
				['clean'],
			],
		},
	});
};

// ── Item dialog open / close ──────────────────────────────────────────────────

const itemDialog = document.getElementById('item-dialog');
const modalTitle = document.getElementById('modal-title');

export const openModal = async (mode, col, colLabel, itemId = null) => {
	initQuill();
	modalMode = mode;
	modalCol = col;
	modalColLabel = colLabel;
	modalItemId = itemId;
	pendingFiles = [];
	existingAttachments = [];
	selectedAssignees = [];

	populateColSelect(col);
	await loadWorkspaceMembers();
	renderChips();

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
		apiFetch(`api/get_item.php?id=${itemId}`)
			.then((r) => r.json())
			.then((data) => {
				quill.clipboard.dangerouslyPasteHTML(data.content || '');
				existingAttachments = data.attachments || [];
				selectedAssignees = (data.assignees || []).map((a) => {
					const member = workspaceMembers.find((m) => m.id === a.id);
					return member || a;
				});
				populateColSelect(data.col);
				renderChips();
				renderAttachmentList();
				setTimeout(() => quill.focus(), 50);
			})
			.catch((err) => console.error('Failed to load item:', err));
	}
};

export const closeModal = () => {
	itemDialog.close();
	hideDropdown();
	pendingFiles.forEach((f) => {
		if (f.previewUrl) URL.revokeObjectURL(f.previewUrl);
	});
	pendingFiles = [];
	existingAttachments = [];
	selectedAssignees = [];
};

itemDialog.addEventListener('cancel', (e) => {
	e.preventDefault();
	closeModal();
});

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
	if (mimeType === 'application/pdf') return `${PDF_ICON_SVG}`;
	if (mimeType.includes('word')) return `${DOCS_ICON_SVG}`;
	if (mimeType.includes('sheet') || mimeType.includes('excel')) return `${SHEET_ICON_SVG}`;
	if (mimeType.includes('zip') || mimeType.includes('compressed')) return `${ZIP_ICON_SVG}`;
	if (mimeType.startsWith('text/')) return '📃';
	return `${ATTACHMENT_ICON_SVG}`;
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
		icon.innerHTML = fileIcon(mimeType);
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
				fetch('api/delete_attachment.php', {
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
		await fetch('api/upload_file.php', { method: 'POST', body: fd });
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
		const assignedTo = selectedAssignees.map((m) => m.id);
		const targetCol = colSelect.value;

		if (modalMode === 'add') {
			const r = await apiFetch('api/add_item.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ col: targetCol, content, assigned_to: assignedTo }),
			});
			const data = await r.json();
			if (!data.id) throw new Error('Server error creating item');
			if (pendingFiles.length > 0) await uploadFiles(data.id);
			document
				.querySelector(`.drag-list[data-column="${targetCol}"]`)
				.appendChild(createItemEl(data.id, content, pendingFiles.length, data.assigned || []));
		} else {
			const r = await apiFetch('api/update_item.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: modalItemId, content, assigned_to: assignedTo, col: targetCol }),
			});
			const data = await r.json();
			if (pendingFiles.length > 0) await uploadFiles(modalItemId);
			const card = document.querySelector(`.drag-item[data-id="${modalItemId}"]`);
			if (card) {
				card.querySelector('.item-content').innerHTML = content;
				updateAttachmentBadge(card, existingAttachments.length + pendingFiles.length);
				updateAssignedBadges(card, data.assigned || []);

				if (data.moved) {
					const targetList = document.querySelector(`.drag-list[data-column="${data.col}"]`);
					if (targetList) {
						targetList.appendChild(card);
						saveOrder();
					}
				}
			}
		}
		closeModal();
	} catch (err) {
		console.error('Save failed:', err);
	} finally {
		saveBtn.disabled = false;
	}
});

// ── Keep columns list in sync when columns are added/removed ──────────────────

export const addColumnOption = (key, label) => {
	window.__KANBAN_COLUMNS__ = window.__KANBAN_COLUMNS__ || [];
	window.__KANBAN_COLUMNS__.push({ key, label });
};

export const removeColumnOption = (key) => {
	window.__KANBAN_COLUMNS__ = (window.__KANBAN_COLUMNS__ || []).filter((c) => c.key !== key);
};
