// in JS
document.getElementById('firmafy_template').addEventListener('change', function(e) {
	// AJAX request.
	let firmafy_template = document.getElementById('firmafy_template').value;
	let post_id = this.getAttribute('data-post-id');

	fetch( ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=firmafy_product_fields&nonce=' + ajaxAction.nonce + '&firmafy_template=' + firmafy_template + '&post_id=' + post_id,
	})
	.then((resp) => resp.json())
	.then( function(data) {
		//RESPONSE
		document.getElementById('firmafy-table-fields').innerHTML = data.data;
	})
	.catch(err => console.log(err));
});
