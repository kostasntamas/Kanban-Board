export const apiFetch = (...args) =>
	fetch(...args).then((r) => {
		if (r.status === 401) {
			location.href = 'login.php';
		}
		return r;
	});
