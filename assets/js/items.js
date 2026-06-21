/**
 * Food Ordering System - Frontend Logic
 */
jQuery(document).ready(function($) {
    // 1. INITIALIZE DATA FROM LOCALIZED OBJECT (fd_vars)
    let cart = JSON.parse(localStorage.getItem('fd_cart_save')) || [];
    
    const config = {
        deliveryFee: parseFloat(fd_vars.delivery_charge) || 0,
        collectionFee: parseFloat(fd_vars.collection_fee) || 0,
        serviceFee: parseFloat(fd_vars.service_fee) || 0,
        bagFee: parseFloat(fd_vars.bag_fee) || 0,
        deliveryDiscount: parseFloat(fd_vars.delivery_discount) || 0,
        collectionDiscount: parseFloat(fd_vars.collection_discount) || 0,
        currency: fd_vars.currency,
        checkoutUrl: fd_vars.checkout_url
    };

    /**
     * Helper: Update the price shown on the "Add to Order" button
     */
    function updateButtonPrice($card) {
        const basePrice = parseFloat($card.data('base-price')) || 0;
        const qty = parseInt($card.find('.fd-item-qty').text()) || 0;
        const $selectedVariant = $card.find('.fd-variant-radio:checked');
        const variantPrice = parseFloat($selectedVariant.data('vprice')) || 0;
        
        const calcQty = qty > 0 ? qty : 1;
        const total = (basePrice + variantPrice) * calcQty;

        $card.find('.btn-total-display').text(total.toFixed(2));
    }

    /**
     * Core: Update Cart Sidebar UI & Calculations
     */
    function updateCart() {
        const container = $('#fd-cart-list');
        container.empty();
        let subtotal = 0, count = 0;

        if (cart.length === 0) {
            container.html('<div style="color:#bbb; text-align:center; padding: 40px 0;">Your cart is empty</div>');
            $('#fd-checkout-trigger').css({'opacity': '0.5', 'pointer-events': 'none'});
            $('#fd-mobile-trigger').fadeOut();
        } else {
            $('#fd-checkout-trigger').css({'opacity': '1', 'pointer-events': 'auto'});
            $('#fd-mobile-trigger').css('display', 'flex').fadeIn();
        }

        cart.forEach((item, index) => {
            const itemUnitPrice = parseFloat(item.price) + (parseFloat(item.vPrice) || 0);
            const rowTotal = itemUnitPrice * item.qty;
            subtotal += rowTotal; 
            count += item.qty;

            container.append(`
                <div class="fd-cart-item" style="padding:12px 0; border-bottom:1px solid #eee;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <div style="font-weight:700; font-size:15px;">${item.name}</div>
                            ${item.vName ? `<div style="font-size:11px; color:#d63638; font-weight:700; text-transform:uppercase;">${item.vName} (+${config.currency}${item.vPrice.toFixed(2)})</div>` : ''}
                        </div>
                        <button class="fd-delete" data-index="${index}" style="color:#ccc; background:none; border:none; cursor:pointer;">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px;">
                        <div style="display:flex; align-items:center; gap:10px; background:#f5f5f5; padding:3px 10px; border-radius:8px;">
                            <button class="fd-minus" data-index="${index}" style="border:none; background:none; cursor:pointer; font-weight:bold;">-</button>
                            <span style="font-weight:800; font-size:14px;">${item.qty}</span>
                            <button class="fd-plus" data-index="${index}" style="border:none; background:none; cursor:pointer; font-weight:bold;">+</button>
                        </div>
                        <div style="font-weight:700; color:#1a1a1a;">${config.currency}${rowTotal.toFixed(2)}</div>
                    </div>
                </div>
            `);
        });

        let isDel = $('input[name="order_type"]:checked').val() === 'delivery';
        let activeFee = isDel ? config.deliveryFee : config.collectionFee;
        let activeDiscPercent = isDel ? config.deliveryDiscount : config.collectionDiscount;
        let tip = parseFloat($('#fd-tip-amount').val()) || 0;
        
        let discountVal = (subtotal * activeDiscPercent) / 100;
        let orderTotal = Math.max(0, subtotal - discountVal);
        
        $('#br-subtotal').text(subtotal.toFixed(2));
        $('#lbl-discount-text').text(isDel ? `Delivery Discount (${activeDiscPercent}%)` : `Collection Discount (${activeDiscPercent}%)`);
        $('#br-discount-value').text(discountVal.toFixed(2));
        $('#br-order-total').text(orderTotal.toFixed(2));
        $('#lbl-fee-name').text(isDel ? 'Delivery' : 'Collection');
        $('#lbl-fee-val').text(activeFee.toFixed(2));

        let totalDue = subtotal > 0 ? (orderTotal + config.serviceFee + activeFee + config.bagFee + tip) : 0;
        $('#fd-total-due, #m-total').text(totalDue.toFixed(2));
        $('#m-count').text(count);
        
        localStorage.setItem('fd_cart_save', JSON.stringify(cart));
    }

    // 2. EVENT HANDLERS
    function closeDrawers() { $('.fd-cat-drawer, .fd-cart-sidebar, #fd-overlay').removeClass('active'); }
    
    $('#fd-open-cats').on('click', function() { $('#fd-cat-drawer, #fd-overlay').addClass('active'); });
    $('#fd-mobile-trigger').on('click', function() { $('#fd-cart-sidebar, #fd-overlay').addClass('active'); });
    $('#fd-close-cats, .fd-close-cart, #fd-overlay').on('click', function() { closeDrawers(); });

    $(document).on('click', '.fd-item-plus', function() {
        let s = $(this).siblings('.fd-item-qty');
        s.text(parseInt(s.text()) + 1);
        updateButtonPrice($(this).closest('.fd-food-card'));
    });

    $(document).on('click', '.fd-item-minus', function() {
        let s = $(this).siblings('.fd-item-qty');
        let val = parseInt(s.text());
        if (val > 0) s.text(val - 1);
        updateButtonPrice($(this).closest('.fd-food-card'));
    });

    $(document).on('change', '.fd-variant-radio', function() {
        updateButtonPrice($(this).closest('.fd-food-card'));
    });

    $(document).on('click', '.order-btn', function() {
        const $btn = $(this);
        const $card = $btn.closest('.fd-food-card');
        const $qtyElem = $card.find('.fd-item-qty');
        const qty = parseInt($qtyElem.text());

        if (qty <= 0) {
            $qtyElem.fadeOut(100).fadeIn(100);
            return;
        }

        const name = $btn.data('name');
        const basePrice = parseFloat($card.data('base-price'));
        const $variantInput = $card.find('.fd-variant-radio:checked');
        const vName = $variantInput.val() || '';
        const vPrice = parseFloat($variantInput.data('vprice')) || 0;

        const exist = cart.find(i => i.name === name && i.vName === vName);
        if (exist) {
            exist.qty += qty;
        } else {
            cart.push({ name, price: basePrice, qty, vName, vPrice });
        }

        const originalHtml = $btn.html();
        $btn.html('Added!').prop('disabled', true).css('background', '#10b981');
        
        setTimeout(() => {
            $qtyElem.text(0);
            updateButtonPrice($card);
            $btn.html(originalHtml).prop('disabled', false).css('background', '');
        }, 800);

        updateCart();
    });

    $(document).on('click', '.fd-plus', function() { 
        cart[$(this).data('index')].qty += 1; 
        updateCart(); 
    });

    $(document).on('click', '.fd-minus', function() {
        const idx = $(this).data('index');
        if (cart[idx].qty > 1) { cart[idx].qty -= 1; } else { cart.splice(idx, 1); }
        updateCart();
    });

    $(document).on('click', '.fd-delete', function() { 
        cart.splice($(this).data('index'), 1); 
        updateCart(); 
    });

    $('input[name="order_type"], #fd-tip-amount').on('change input', updateCart);

    $('#fd-menu-search').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        $('.fd-food-card').each(function() { 
            $(this).toggle($(this).data('title').indexOf(val) > -1); 
        });
    });

    $(document).on('click', '#fd-checkout-trigger', function(e) {
        e.preventDefault();
        if (cart.length === 0) return;
        localStorage.setItem('fd_scheduled_time', $('#fd-scheduled-time').val());
        localStorage.setItem('fd_kitchen_notes', $('#fd-kitchen-notes').val());
        localStorage.setItem('fd_order_type', $('input[name="order_type"]:checked').val());
        window.location.href = config.checkoutUrl;
    });

    // Initial Load
    updateCart();
    $('.fd-food-card').each(function() { updateButtonPrice($(this)); });
});