jQuery(document).ready(function ($) {

	$('#active').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').hide();
		$('#threadlog tbody .active').show();
	});

	$('#closed').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').hide();
		$('#threadlog tbody .closed').show();
	});

	$('#need-replies').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').hide();
		$('#threadlog .needs-reply').show();
	});

	$('#closed').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').hide();
		$('#threadlog tbody .closed').show();
	});

	$('#show-all').on('click', function(e) {
		e.preventDefault();
		$('#threadlog tbody tr').show();
	});

});