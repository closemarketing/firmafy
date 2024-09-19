let uploadPDFBackgroundBtn = document.querySelector( '.js-firmafy-upload-file' );

if ( uploadPDFBackgroundBtn ) {

	uploadPDFBackgroundBtn.addEventListener( 'click', function( e ) {
		e.preventDefault();

		let input = document.querySelector( '#pdf_background' );

		wp.media.editor.send.attachment = function( props, attachment ) {
			input.value = attachment.url;
		};

		wp.media.editor.open();
	});

}