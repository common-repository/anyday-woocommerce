"use strict"; // Get the element

function clonePricetag(options, className) {
	var elem = document.querySelector('.' + className);

	if (elem) {

	  // Create a copy of it
	  var clone = elem; // Update the ID and add a class
	  clone.querySelector('anyday-price-tag').style.display = 'block'; // Inject it into the DOM

	  if (anyday.positionSelector) {
	    var holder = document.querySelector(anyday.positionSelector);

	    if(holder) {
	    	if(options && options.deletePrev) {
		    	var prevElement = holder.previousElementSibling;
		    	if(prevElement.classList.contains(ELEM_CLASS)) {
		    		prevElement.remove();
		    	}
		    }

	    	holder.before(clone);
	    }
	  }
	}
}

function variationProductNoPriceSelectedHandler() {
	var $variationsForm = jQuery('.variations_form');

	if($variationsForm.length) {
		setTimeout(function() { variationNoPriceSelectedElementHandler()} , 500);

		$variationsForm.on('woocommerce_variation_select_change', function() {
			setTimeout(function() { variationNoPriceSelectedElementHandler()} , 500);
		});
	}
}

function variationNoPriceSelectedElementHandler() {
	var variation_id = jQuery('input.variation_id').val();
	var variationData = jQuery('.variations_form.cart').data('product_variations');
	let limited = false;
	if (!isNaN(parseInt(anyday.limit))) {
		for (let i = 0; i < variationData.length; i++) {
			if (variationData[i].variation_id == variation_id) {
				if (anyday.limit > variationData[i].display_price) {
					limited = true;
				}
			}
		}
	}
	var variationPriceHolderEl = document.querySelector('.woocommerce-variation');
	var priceSelectedEl = document.querySelector('.anyday-price-tag-style-wrapper--price');
	var elementComputedDisplayStyle = variationPriceHolderEl.currentStyle ? variationPriceHolderEl.currentStyle.display : getComputedStyle(variationPriceHolderEl, null).display;
	if (!priceSelectedEl) return;
	if(limited || variationPriceHolderEl.innerHTML.length === 0 ||
    variationPriceHolderEl.style.display === 'none' ||
    elementComputedDisplayStyle === 'none' ||
		elementComputedDisplayStyle.length === 0
	) {
		priceSelectedEl.style.display = 'none';
	} else {
		priceSelectedEl.style.display = 'block';
	}
}

document.addEventListener("DOMContentLoaded", function() {
  clonePricetag({}, 'anyday-price-tag-style-wrapper--price');
  variationProductNoPriceSelectedHandler();
});
