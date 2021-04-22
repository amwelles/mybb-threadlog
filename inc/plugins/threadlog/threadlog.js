/* authored by Autumn Welles <http://autumnwelles.com/> */
jQuery(document).ready(function ($) {

	$('#active').on('click', function(e) {
		e.preventDefault();
		$('#threadlog .threadlogrow').hide();
		$('#threadlog .active').show();
	});

	$('#closed').on('click', function(e) {
		e.preventDefault();
		$('#threadlog .threadlogrow').hide();
		$('#threadlog .closed').show();
	});

	$('#need-replies').on('click', function(e) {
		e.preventDefault();
		$('#threadlog .threadlogrow').hide();
		$('#threadlog .needs-reply').show();
	});

	$('#closed').on('click', function(e) {
		e.preventDefault();
		$('#threadlog .threadlogrow').hide();
		$('#threadlog .closed').show();
	});

	$('#show-all').on('click', function(e) {
		e.preventDefault();
		$('#threadlog .threadlogrow').show();
	});

});
