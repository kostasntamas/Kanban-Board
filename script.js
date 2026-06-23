let draggedEl = null;

// --- Drag & Drop ---

const dragStart = (event) => {
	draggedEl = event.currentTarget;
	event.currentTarget.classList.add('dragging');
};

const dragEnd = (event) => {
	event.currentTarget.classList.remove('dragging');
	draggedEl = null;
};

const dragEnter = (event) => {
	event.currentTarget.classList.add('drop');
};

const dragLeave = (event) => {
	event.currentTarget.classList.remove('drop');
};

const allowDrop = (event) => {
	event.preventDefault();
};

const drop = (event) => {
	event.stopPropagation();
	event.preventDefault();
	document.querySelectorAll('.drag-list').forEach((col) => col.classList.remove('drop'));
	if (!draggedEl) return;

	const targetList = event.currentTarget.classList.contains('drag-list')
		? event.currentTarget
		: event.currentTarget.closest('.drag-list');

	targetList.appendChild(draggedEl);
	saveOrder();
};

// --- Persistence ---

const saveOrder = () => {
	const order = {};
	document.querySelectorAll('.drag-list').forEach((list) => {
		order[list.dataset.column] = [...list.querySelectorAll('.drag-item')].map((item) => item.dataset.id);
	});
	fetch('save_order.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(order),
	}).catch((err) => console.error('Failed to save order:', err));
};

// --- Add & Delete ---

const deleteItem = (btn) => {
	const item = btn.closest('.drag-item');
	fetch('delete_item.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ id: item.dataset.id }),
	})
		.then(() => item.remove())
		.catch((err) => console.error('Failed to delete:', err));
};

const addItem = (btn) => {
	const col = btn.dataset.col;
	const input = btn.previousElementSibling;
	const content = input.value.trim();
	if (!content) return;

	fetch('add_item.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ col, content }),
	})
		.then((r) => r.json())
		.then((data) => {
			const list = btn.closest('.wrapper').querySelector('.drag-list');
			list.appendChild(createItem(data.id, content));
			input.value = '';
		})
		.catch((err) => console.error('Failed to add:', err));
};

// --- Item factory ---

const attachItemListeners = (item) => {
	item.addEventListener('dragstart', dragStart);
	item.addEventListener('dragend', dragEnd);
	item.addEventListener('dragover', allowDrop);
	item.addEventListener('drop', drop);
	item.querySelector('.delete-btn').addEventListener('click', (e) => deleteItem(e.currentTarget));
};

const createItem = (id, content) => {
	const li = document.createElement('li');
	li.className = 'drag-item';
	li.draggable = true;
	li.dataset.id = id;
	li.innerHTML = `<span>${content}</span><button class="delete-btn">Delete</button>`;
	attachItemListeners(li);
	return li;
};

// --- Init ---

document.querySelectorAll('.drag-list').forEach((list) => {
	list.addEventListener('dragenter', dragEnter);
	list.addEventListener('dragleave', dragLeave);
	list.addEventListener('dragover', allowDrop);
	list.addEventListener('drop', drop);
});

document.querySelectorAll('.drag-item').forEach((item) => attachItemListeners(item));

document.querySelectorAll('.add-btn').forEach((btn) => {
	btn.addEventListener('click', () => addItem(btn));
});

document.querySelectorAll('.add-form input').forEach((input) => {
	input.addEventListener('keydown', (e) => {
		if (e.key === 'Enter') input.nextElementSibling.click();
	});
});
