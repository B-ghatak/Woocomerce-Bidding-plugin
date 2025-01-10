jQuery(function($) {
    // Close bidding
    $(document).on('click', '.close-bidding', function() {
        if (!confirm('Are you sure you want to close bidding for this product?')) return;
        
        const button = $(this);
        const productId = button.data('product-id');
        
        $.ajax({
            url: adminBidding.ajaxurl,
            type: 'POST',
            data: {
                action: 'close_bidding',
                product_id: productId,
                nonce: adminBidding.nonce
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Replace close button with restart button
                    const restartButton = $('<button>', {
                        class: 'button button-secondary restart-bidding',
                        'data-product-id': productId,
                        'data-nonce': adminBidding.restartNonce,
                        text: 'Restart Bidding'
                    });
                    
                    // Update the UI
                    button.closest('p').html(`
                        <strong>Bidding Closed</strong> - Winning Bid: ${response.data.winning_bid}<br><br>
                        ${restartButton.prop('outerHTML')}
                    `);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Error closing bidding. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Restart bidding
    $(document).on('click', '.restart-bidding', function() {
        if (!confirm('Are you sure you want to restart bidding for this product?')) return;
        
        const button = $(this);
        const productId = button.data('product-id');
        const nonce = button.data('nonce');
        
        $.ajax({
            url: adminBidding.ajaxurl,
            type: 'POST',
            data: {
                action: 'restart_bidding',
                product_id: productId,
                nonce: nonce
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Replace restart button with close button
                    const closeButton = $('<button>', {
                        class: 'button button-primary close-bidding',
                        'data-product-id': productId,
                        text: 'Close Bidding'
                    });
                    
                    // Clear the bids table and update UI
                    $('.widefat tbody').empty();
                    $('.widefat').after('<p>No bids placed yet.</p>');
                    $('.widefat').remove();
                    
                    button.closest('p').html(closeButton);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Error restarting bidding. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});

