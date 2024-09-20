// Handle the upload of the PDF background.
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

// Handle the upload of the PDF logo.
let uploadPDFLogoBtn = document.querySelector( '.js-firmafy-upload-logo-file' );

if ( uploadPDFLogoBtn ) {

	uploadPDFLogoBtn.addEventListener( 'click', function( e ) {
		e.preventDefault();

		let input = document.querySelector( '#pdf_logo' );

		wp.media.editor.send.attachment = function( props, attachment ) {
			input.value = attachment.url;
		};

		wp.media.editor.open();
	});

}