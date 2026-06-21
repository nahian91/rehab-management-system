(function($) {
	"use strict";

	$(document).ready(function() {

		/* ==================================================
		    # Magnific popup init
		 ===============================================*/
		$(".popup-link").magnificPopup({ type: 'image' });

		$(".popup-gallery").magnificPopup({
			type: 'image',
			gallery: { enabled: true }
		});

		/* ==================================================
		    # Banner Carousel
		===============================================*/
		new Swiper(".banner-fade", {
			direction: "horizontal",
			loop: true,
			effect: "fade",
			fadeEffect: { crossFade: true },
			speed: 2000,
			autoplay: { delay: 5000, disableOnInteraction: false },
			pagination: { el: '.swiper-pagination', type: 'bullets', clickable: true },
			navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" }
		});

		/* ==================================================
		    # Testimonials
		===============================================*/
		new Swiper(".testimonial-style-one-carousel", {
			loop: true,
			autoplay: true,
			pagination: { el: '.swiper-pagination', clickable: true },
			navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" }
		});

		/* ==================================================
		    Carousels (Food / Brand / Product)
		====================================================*/
		new Swiper(".food-cat-carousel", {
			loop: true,
			slidesPerView: 1,
			spaceBetween: 30,
			autoplay: true,
			breakpoints: {
				768: { slidesPerView: 2 },
				992: { slidesPerView: 3 }
			}
		});
	}); // end doc ready

	

})(jQuery);


