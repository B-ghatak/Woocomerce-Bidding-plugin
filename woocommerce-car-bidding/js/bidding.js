(function($) {
    'use strict';

    $(document).ready(function() {
        initBidding();
    });

    function initBidding() {
        $(document).on('click', '.place-bid-button', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const isLoggedIn = $button.data('logged-in') === true;
            const productId = $button.data('product-id');
            const salePrice = parseFloat($button.data('sale-price'));
            
            console.log('Button clicked:', {
                isLoggedIn,
                productId,
                salePrice
            });

            if (!isLoggedIn) {
                showLoginDialog();
                return;
            }
            
            showBidDialog(productId, salePrice);
        });
    }

    function showBidDialog(productId, salePrice) {
        const minimumBid = salePrice * 0.9;
        const currencySymbol = biddingAjax.currency_symbol;
        
        Swal.fire({
            title: 'Place Your Bid',
            html: `
                <div class="bid-popup">
                    <div class="bid-info">
                        <div class="ask-price-info">
                            <span>Ask Price:</span>
                            <span class="price">${currencySymbol}${salePrice.toLocaleString()}</span>
                        </div>
                        <div class="min-bid-info">
                            <span>Minimum Bid:</span>
                            <span class="price">${currencySymbol}${minimumBid.toLocaleString()}</span>
                        </div>
                      
                    </div>
                    <div class="bid-input-wrapper">
                        <label for="bid-amount">Enter your bid amount:</label>
                        <div class="input-group">
                            <span class="currency">${currencySymbol}</span>
                            <input 
                                type="number" 
                                id="bid-amount" 
                                class="swal2-input" 
                                min="${minimumBid}"
                                step="1"
                                placeholder="Enter amount"
                                value="${salePrice}"
                            >
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Place Bid',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#6200ee',
            cancelButtonColor: '#757575',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const bidAmount = parseFloat(document.getElementById('bid-amount').value);
                if (!bidAmount || isNaN(bidAmount)) {
                    Swal.showValidationMessage('Please enter a valid bid amount');
                    return false;
                }
                if (bidAmount < minimumBid) {
                    Swal.showValidationMessage(`Bid must be at least ${currencySymbol}${minimumBid.toLocaleString()}`);
                    return false;
                }
                return bidAmount;
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                submitBid(productId, result.value);
            }
        });
    }

    function submitBid(productId, bidAmount) {
        $.ajax({
            url: biddingAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'place_bid',
                product_id: productId,
                bid_amount: bidAmount,
                nonce: biddingAjax.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your bid has been placed successfully.',
                        icon: 'success',
                        confirmButtonColor: '#6200ee'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.data.message || 'An error occurred while placing your bid.',
                        icon: 'error',
                        confirmButtonColor: '#6200ee'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while placing your bid. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#6200ee'
                });
            }
        });
    }

    function showLoginDialog() {
        Swal.fire({
            title: 'Login Required',
            text: 'Please login to place a bid',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Login',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#6200ee',
            cancelButtonColor: '#757575'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = biddingAjax.loginUrl;
            }
        });
    }

    // Delete bid handler
    $(document).on('click', '.delete-bid', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const bidId = button.data('bid-id');
        const nonce = button.data('nonce');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6200ee',
            cancelButtonColor: '#757575',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: biddingAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_bid',
                        bid_id: bidId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Your bid has been deleted.',
                                icon: 'success',
                                confirmButtonColor: '#6200ee'
                            }).then(() => {
                                button.closest('tr').fadeOut(400, function() {
                                    $(this).remove();
                                    if ($('.woocommerce-orders-table tbody tr').length === 0) {
                                        location.reload();
                                    }
                                });
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.data.message,
                                icon: 'error',
                                confirmButtonColor: '#6200ee'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Something went wrong.',
                            icon: 'error',
                            confirmButtonColor: '#6200ee'
                        });
                    }
                });
            }
        });
    });
})(jQuery);

