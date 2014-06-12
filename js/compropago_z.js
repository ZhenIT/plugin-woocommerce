jQuery(document).ready(function($) {
	$.fancybox.open({
		modal: true,
		overlayShow: true,
		hideOnOverlayClick: false,
		hideOnContentClick: false,
		enableEscapeButton: false,
		showCloseButton: false,
		href : gateway_compropago,
		type : 'iframe',
		padding : 5
	});
	$("#payment_btn").click(function(event) {
		event.preventDefault();
		$.fancybox.open({
			modal: true,
			overlayShow: true,
			hideOnOverlayClick: false,
			hideOnContentClick: false,
			enableEscapeButton: false,
			showCloseButton: false,
			href : gateway_compropago,
			type : 'iframe',
			padding : 5
		});
	});
});