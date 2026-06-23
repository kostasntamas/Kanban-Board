let dragged = null;

document.querySelectorAll('.drag-list').forEach((list) => {
	list.addEventListener('dragstart', (e) => {
		dragged = e.target.closest('.drag-item');
		if (!dragged) return;

		dragged.classList.add('dragging');
		e.dataTransfer.effectAllowed = 'move';
	});

	list.addEventListener('dragend', () => {
		document.querySelectorAll('.drag-item').forEach((el) => el.classList.remove('dragging', 'drag-over'));

		dragged = null;
	});

	list.addEventListener('dragover', (e) => {
		e.preventDefault();
		if (!dragged) return;

		const target = e.target.closest('.drag-item');
		if (!target || target === dragged) return;

		const items = [...list.querySelectorAll('.drag-item')];

		if (items.indexOf(dragged) < items.indexOf(target)) {
			target.after(dragged);
		} else {
			target.before(dragged);
		}
	});

	list.addEventListener('dragleave', (e) => {
		const t = e.target.closest('.drag-item');
		if (t) t.classList.remove('drag-over');
	});
});
