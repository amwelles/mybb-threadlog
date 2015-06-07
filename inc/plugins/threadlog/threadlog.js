jQuery(document).ready(function ($) {

	$('#need-replies').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').hide();
		$('#threadlog .needs-reply').show();
	});

	$('#show-all').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').show();
	});

});