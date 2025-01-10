<?php
class Car_Bidding_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'car_bidding';
    }

    public function get_title() {
        return __('Car Bidding', 'woocommerce-car-bidding');
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return ['general'];
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get product ID
        $product_id = get_the_ID();
        if (!$product_id) {
            echo 'No product found.';
            return;
        }
    
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) {
            echo 'Product not found.';
            return;
        }
    
        // Get the current highest bid
        $highest_bid = get_highest_bid($product_id);
        
        // Get the sale price (if available) or regular price
        $sale_price = $product->get_sale_price() ? $product->get_sale_price() : $product->get_regular_price();
    
        // Check if bid is closed
        $is_closed = get_post_meta($product_id, '_bid_closed', true);
        $winning_bid = get_post_meta($product_id, '_winning_bid', true);
    
        // Only show sold notice if both flags are set
        if ($is_closed && $winning_bid) {
            ?>
            <div class="elementor-car-bidding sold">
                <div class="sold-notice">
                    <h2 class="sold-title">CAR SOLD</h2>
                    <p style="font-size: 20px !important; font-weight: 500; color: red;" class="final-price car_sold">Bid Price: <?php echo wc_price($winning_bid); ?></p>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="elementor-car-bidding">
                <div class="bid-info">
                    <div class="ask-price-section">
                        <h3>Ask Price:</h3>
                        <p class="price ask-price"><?php echo wc_price($sale_price); ?></p>
                    </div>
                    
                    <div class="current-bid-section">
                        <h3>Current Bid:</h3>
                        <p class="current-bid">
                            <span class="bid-amount">
                                <?php 
                                if ($highest_bid) {
                                    echo wc_price($highest_bid);
                                } else {
                                    echo '<span class="no-bids">No bids yet</span>';
                                }
                                ?>
                            </span>
                        </p>
                    </div>
                    <button 
                        type="button"
                        class="place-bid-button elementor-button" 
                        data-product-id="<?php echo esc_attr($product_id); ?>"
                        data-sale-price="<?php echo esc_attr(floatval($sale_price)); ?>"
                        data-logged-in="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>"
                    >
                        Place A Bid
                    </button>
                </div>
            </div>
            <?php
        }
    }
   
}

