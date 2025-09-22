(function($){
	$(function(){
		$(document).on('change', 'input.dq-shown-toggle', function(){
			var $cb = $(this);
			$.post(ajaxurl, {
				action: 'dq_toggle_shown',
				nonce: (window.DQOrder && DQOrder.toggleNonce) || '',
				post: parseInt($cb.data('post'), 10),
				set: parseInt($cb.data('set'), 10),
				value: $cb.is(':checked') ? 1 : 0
			});
		});
	});
})(jQuery);

(function($){
	$(function(){ /* logic handled via inline footer script for now */ });
})(jQuery);
