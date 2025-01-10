<?php
/*
Plugin Name: WooCommerce Car Bidding
Description: Adds bidding functionality for car products with Elementor integration
Version: 1.1
Author: Biplob Ghatak
Author URI: https://biplobghatak.com
*/

if (!defined('ABSPATH')) {
    exit;
}

// Fix 404 error by flushing rewrite rules on activation
register_activation_hook(__FILE__, 'car_bidding_activate');
function car_bidding_activate() {
    register_bid_history_endpoint();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'car_bidding_deactivate');
function car_bidding_deactivate() {
    flush_rewrite_rules();
}

// Create custom table for bids
register_activation_hook(__FILE__, 'create_bids_table');
function create_bids_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_bids';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        bid_amount decimal(10,2) NOT NULL,
        bid_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Override WooCommerce sale price with highest bid
add_filter('woocommerce_product_get_sale_price', 'override_sale_price_with_bid', 10, 2);
add_filter('woocommerce_product_get_price', 'override_sale_price_with_bid', 10, 2);
function override_sale_price_with_bid($price, $product) {
    if (has_term('cars', 'product_cat', $product->get_id())) {
        $highest_bid = get_highest_bid($product->get_id());
        if ($highest_bid) {
            return $highest_bid;
        }
    }
    return $price;
}

// Helper function to get highest bid
function get_highest_bid($product_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(bid_amount) FROM {$wpdb->prefix}product_bids WHERE product_id = %d",
        $product_id
    ));
}


function handle_not_logged_in() {
    wp_send_json_error(array(
        'message' => 'Please log in to place a bid',
        'redirect' => wp_login_url(wp_get_referer())
    ));
}


function handle_place_bid() {
    check_ajax_referer('place-bid-nonce', 'nonce');

    if (!isset($_POST['product_id']) || !isset($_POST['bid_amount'])) {
        wp_send_json_error(array('message' => 'Invalid request: Missing required parameters'));
        return;
    }
    
    $product_id = intval($_POST['product_id']);
    $bid_amount = floatval($_POST['bid_amount']);
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error(array('message' => 'User not logged in'));
        return;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(array('message' => 'Invalid product'));
        return;
    }
    
    $sale_price = $product->get_sale_price() ? $product->get_sale_price() : $product->get_regular_price();
    $minimum_bid = $sale_price * 0.9;
    
    if ($bid_amount < $minimum_bid) {
        wp_send_json_error(array('message' => 'Bid amount is below the minimum allowed'));
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_bids';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'product_id' => $product_id,
            'user_id' => $user_id,
            'bid_amount' => $bid_amount,
            'bid_date' => current_time('mysql')
        ),
        array('%d', '%d', '%f', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error(array('message' => 'Failed to place bid'));
        return;
    }
    
    // Clear WooCommerce price cache
    wc_delete_product_transients($product_id);
    
    wp_send_json_success(array(
        'message' => 'Bid placed successfully',
        'new_price' => wc_price($bid_amount)
    ));
}

add_action('wp_ajax_place_bid', 'handle_place_bid');
add_action('wp_ajax_nopriv_place_bid', 'handle_not_logged_in');

// My Account page integration
add_action('init', 'register_bid_history_endpoint');
function register_bid_history_endpoint() {
    add_rewrite_endpoint('bid-history', EP_ROOT | EP_PAGES);
}

add_filter('woocommerce_account_menu_items', 'add_bid_history_menu_item');
function add_bid_history_menu_item($items) {
    $items['bid-history'] = 'My Bids';
    return $items;
}

add_action('woocommerce_account_bid-history_endpoint', 'bid_history_content'); // Changed function call here


// Elementor Widget Integration
add_action('elementor/widgets/widgets_registered', 'register_car_bidding_widget');
function register_car_bidding_widget() {
    require_once(__DIR__ . '/widgets/car-bidding-widget.php');
    \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Car_Bidding_Widget());
}

// Enqueue scripts
add_action('wp_enqueue_scripts', 'enqueue_bidding_scripts');
function enqueue_bidding_scripts() {
    // Enqueue SweetAlert2
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);
    wp_enqueue_style('sweetalert2', 'https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui@4/material-ui.css', array(), null);
    
    // Enqueue our custom script
    wp_enqueue_script(
        'car-bidding', 
        plugins_url('js/bidding.js', __FILE__), 
        array('jquery', 'sweetalert2'), 
        time(), // Use time() for development to prevent caching
        true
    );

    // Localize script
    wp_localize_script('car-bidding', 'biddingAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('place-bid-nonce'),
        'loginUrl' => wp_login_url(get_permalink()),
        'currency_symbol' => get_woocommerce_currency_symbol()
    ));

    // Add custom styles
    wp_add_inline_style('sweetalert2', '
        .swal2-popup {
            padding: 2em;
        }
        .bid-popup {
            padding: 20px;
        }
        .bid-info {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .ask-price-info, .min-bid-info {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 16px;
        }
        .price {
            font-weight: bold;
            color: #2c3338;
        }
        .bid-input-wrapper {
            text-align: left;
        }
        .bid-input-wrapper label {
            display: block;
            margin-bottom: 10px;
            color: #4a5568;
        }
        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .currency {
            background: #f7fafc;
            padding: 8px 12px;
            border-right: 1px solid #e2e8f0;
            color: #4a5568;
        }
        #bid-amount {
            flex: 1;
            border: none !important;
            margin: 0 !important;
            padding: 8px 12px !important;
            width: auto !important;
        }
        .place-bid-button {
            background-color: #6200ee;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .place-bid-button:hover {
            background-color: #5000ca;
        }
    ');
}

// Remove regular price display if there's a bid
add_filter('woocommerce_get_price_html', 'modify_price_display', 10, 2);
function modify_price_display($price_html, $product) {
    if (has_term('cars', 'product_cat', $product->get_id())) {
        $highest_bid = get_highest_bid($product->get_id());
        $ask_price = $product->get_regular_price();
        
        if ($highest_bid) {
            return sprintf(
                '<div class="product-bid-price">
                    <span class="ask-price">Ask Price: %s</span>
                    <span class="current-bid">Current Bid: %s</span>
                </div>',
                wc_price($ask_price),
                wc_price($highest_bid)
            );
        } else {
            return sprintf(
                '<div class="product-bid-price">
                    <span class="ask-price">Ask Price: %s</span>
                    <span class="current-bid">No bids yet</span>
                </div>',
                wc_price($ask_price)
            );
        }
    }
    return $price_html;
}

// Update product price meta for sorting and filtering
add_action('woocommerce_before_product_object_save', 'update_product_price_meta');
function update_product_price_meta($product) {
    if (has_term('cars', 'product_cat', $product->get_id())) {
        $highest_bid = get_highest_bid($product->get_id());
        if ($highest_bid) {
            update_post_meta($product->get_id(), '_price', $highest_bid);
        }
    }
}

// Add custom CSS for price display
add_action('wp_head', 'add_bid_price_styles');
function add_bid_price_styles() {
    ?>
    <style>
        .product-bid-price {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .product-bid-price .ask-price {
            font-weight: bold;
            color: #333;
        }
        
        .product-bid-price .current-bid {
            color: #4054b2;
            font-weight: bold;
        }
    </style>
    <?php
}

// Add AJAX login handler
add_action('wp_ajax_nopriv_ajax_login', 'ajax_login');
function ajax_login() {
    check_ajax_referer('ajax-login-nonce', 'security');

    $info = array();
    $info['user_login'] = $_POST['username'];
    $info['user_password'] = $_POST['password'];
    $info['remember'] = true;

    $user_signon = wp_signon($info, false);
    if (is_wp_error($user_signon)) {
        wp_send_json_error(array('message' => 'Invalid login credentials'));
    } else {
        wp_send_json_success(array('message' => 'Login successful'));
    }
}

// Add bid closing functionality for admin
add_action('wp_ajax_close_bidding', 'close_bidding');
// Update the close_bidding function
function close_bidding() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
        return;
    }

    $product_id = intval($_POST['product_id']);
    check_ajax_referer('close-bidding-nonce', 'nonce');

    $highest_bid = get_highest_bid($product_id);
    
    // Update post meta
    update_post_meta($product_id, '_bid_closed', true);
    update_post_meta($product_id, '_winning_bid', $highest_bid);
    
    // Clear caches
    wc_delete_product_transients($product_id);
    clean_post_cache($product_id);
    
    wp_send_json_success(array(
        'message' => 'Bidding closed successfully',
        'winning_bid' => wc_price($highest_bid)
    ));
}

// Add meta box for bids in product admin
add_action('add_meta_boxes', function() {
    add_meta_box(
        'product_bids',
        'Product Bids',
        'render_bids_meta_box',
        'product',
        'normal',
        'high'
    );
});

function render_bids_meta_box($post) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_bids';
    $bids = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE product_id = %d ORDER BY bid_amount DESC",
        $post->ID
    ));

    echo '<h4>Bids for this product:</h4>';
    
    $is_closed = get_post_meta($post->ID, '_bid_closed', true);
    $winning_bid = get_post_meta($post->ID, '_winning_bid', true);

    if (!empty($bids)) {
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>User</th><th>Email</th><th>Mobile Number</th><th>Bid Amount</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($bids as $bid) {
            $user_info = get_userdata($bid->user_id);
            $user_email = esc_html($user_info->user_email);
            $mobile_number = get_user_meta($bid->user_id, 'mobile_number', true); // Get mobile number from WordPress login
            echo '<tr>';
            echo '<td>' . esc_html($user_info->display_name) . '</td>';
            echo '<td>' . $user_email . '</td>';
            echo '<td>' . esc_html($mobile_number) . '</td>'; // Use WordPress login mobile number
            echo '<td>' . wc_price($bid->bid_amount) . '</td>';
            echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($bid->bid_date)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No bids placed yet.</p>';
    }

    if ($is_closed) {
        echo '<p>';
        echo '<strong>Bidding Closed</strong> - Winning Bid: ' . wc_price($winning_bid) . '<br><br>';
        echo '<button class="button button-secondary restart-bidding" 
            data-product-id="' . esc_attr($post->ID) . '" 
            data-nonce="' . wp_create_nonce('restart-bid-nonce') . '">
            Restart Bidding</button>';
        echo '</p>';
    } else {
        echo '<p><button class="button button-primary close-bidding" 
            data-product-id="' . esc_attr($post->ID) . '">Close Bidding</button></p>';
    }
}


// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');
function enqueue_admin_scripts($hook) {
    if ('post.php' != $hook || get_post_type() != 'product') {
        return;
    }
    
    wp_enqueue_script('admin-bidding', plugins_url('js/admin-bidding.js', __FILE__), array('jquery'), time(), true);
    wp_localize_script('admin-bidding', 'adminBidding', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('close-bidding-nonce'),
        'restartNonce' => wp_create_nonce('restart-bid-nonce')
    ));
}

// Add restart bid functionality
add_action('wp_ajax_restart_bidding', 'restart_bidding');
function restart_bidding() {
    if (!current_user_can('manage_options') && !is_product_author()) {
        wp_send_json_error(array('message' => 'Permission denied'));
        return;
    }

    $product_id = intval($_POST['product_id']);
    check_ajax_referer('restart-bid-nonce', 'nonce');

    global $wpdb;
    
    // Delete all bid meta
    delete_post_meta($product_id, '_bid_closed');
    delete_post_meta($product_id, '_winning_bid');
    delete_post_meta($product_id, '_price');
    delete_post_meta($product_id, '_sale_price');
    
    // Clear product cache
    wc_delete_product_transients($product_id);
    clean_post_cache($product_id);
    wp_cache_delete($product_id, 'post_meta');
    
    // Clear all bids from the database
    $table_name = $wpdb->prefix . 'product_bids';
    $wpdb->delete(
        $table_name,
        array('product_id' => $product_id),
        array('%d')
    );
    
    // Force refresh of product data
    $product = wc_get_product($product_id);
    if ($product) {
        $product->save();
    }
    
    wp_send_json_success(array('message' => 'Bidding restarted successfully'));
}

// Add AJAX handler for clearing bids
add_action('wp_ajax_clear_bids', 'clear_bids');
function clear_bids() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }

    $product_id = intval($_POST['product_id']);
    check_ajax_referer('restart-bid-nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'product_bids';
    
    // Clear all bids for this product
    $wpdb->delete(
        $table_name,
        array('product_id' => $product_id),
        array('%d')
    );

    wp_send_json_success();
}

// Add delete bid functionality
add_action('wp_ajax_delete_bid', 'delete_bid');
function delete_bid() {
    global $wpdb;
    $bid_id = intval($_POST['bid_id']);
    $user_id = get_current_user_id();
    
    check_ajax_referer('delete-bid-nonce', 'nonce');
    
    $bid = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}product_bids WHERE id = %d",
        $bid_id
    ));
    
    if (!$bid || ($bid->user_id != $user_id && !current_user_can('manage_options'))) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    $wpdb->delete(
        $wpdb->prefix . 'product_bids',
        array('id' => $bid_id),
        array('%d')
    );
    
    wp_send_json_success(array('message' => 'Bid deleted successfully'));
}

// Helper function to check if user is product author
function is_product_author($product_id = null) {
    if (!$product_id) {
        $product_id = get_the_ID();
    }
    $product = wc_get_product($product_id);
    return $product && get_current_user_id() == $product->get_author();
}

// Modify the existing bid history content
function bid_history_content() { //Updated function
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Get user's bids
    $bids = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, p.post_title, p.post_author 
        FROM {$wpdb->prefix}product_bids b 
        JOIN {$wpdb->prefix}posts p ON b.product_id = p.ID 
        WHERE b.user_id = %d
        ORDER BY b.bid_date DESC",
        $user_id
    ));

    // Get cars the user is selling
    $cars_for_sale = $wpdb->get_results($wpdb->prepare(
        "SELECT p.* 
        FROM {$wpdb->prefix}posts p 
        WHERE p.post_author = %d 
        AND p.post_type = 'product' 
        AND EXISTS (
            SELECT 1 FROM {$wpdb->prefix}term_relationships tr 
            JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
            JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id 
            WHERE tr.object_id = p.ID AND t.slug = 'cars'
        )",
        $user_id
    ));

    // Output tabs
    ?>
    <div class="bid-history-tabs">
        <nav class="bid-tabs-nav">
            <a href="#my-bids" class="active">My Bids</a>
            <a href="#my-cars">My Cars for Sale</a>
        </nav>

        <div class="bid-tabs-content">
            <!-- My Bids Tab -->
            <div id="my-bids" class="bid-tab-panel active">
                <?php if (!empty($bids)): ?>
                    <table class="woocommerce-orders-table">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Bid Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bids as $bid): 
                                $highest_bid = get_highest_bid($bid->product_id);
                                $status = ($highest_bid == $bid->bid_amount) ? 'Highest Bid' : 'Outbid';
                            ?>
                                <tr>
                                    <td><a href="<?php echo get_permalink($bid->product_id); ?>"><?php echo $bid->post_title; ?></a></td>
                                    <td><?php echo wc_price($bid->bid_amount); ?></td>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($bid->bid_date)); ?></td>
                                    <td><?php echo $status; ?></td>
                                    <td>
                                        <button class="button delete-bid" 
                                            data-bid-id="<?php echo $bid->id; ?>" 
                                            data-nonce="<?php echo wp_create_nonce('delete-bid-nonce'); ?>">
                                            Delete Bid
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You haven't placed any bids yet.</p>
                <?php endif; ?>
            </div>

            <!-- My Cars for Sale Tab -->
            <div id="my-cars" class="bid-tab-panel">
                <?php if (!empty($cars_for_sale)): ?>
                    <table class="woocommerce-orders-table">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Ask Price</th>
                                <th>Current Bid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cars_for_sale as $car): 
                                $product = wc_get_product($car->ID);
                                $highest_bid = get_highest_bid($car->ID);
                                $is_closed = get_post_meta($car->ID, '_bid_closed', true);
                            ?>
                                <tr>
                                    <td><a href="<?php echo get_permalink($car->ID); ?>"><?php echo $car->post_title; ?></a></td>
                                    <td><?php echo wc_price($product->get_regular_price()); ?></td>
                                    <td><?php echo $highest_bid ? wc_price($highest_bid) : 'No bids'; ?></td>
                                    <td><?php echo $is_closed ? 'Closed' : 'Active'; ?></td>
                                    <td>
                                        <?php if (!$is_closed): ?>
                                            <button class="button close-bidding" 
                                                data-product-id="<?php echo $car->ID; ?>" 
                                                data-nonce="<?php echo wp_create_nonce('close-bidding-nonce'); ?>">
                                                Close Bidding
                                            </button>
                                        <?php else: ?>
                                            <button class="button restart-bidding" 
                                                data-product-id="<?php echo $car->ID; ?>" 
                                                data-nonce="<?php echo wp_create_nonce('restart-bid-nonce'); ?>">
                                                Restart Bidding
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You don't have any cars listed for sale.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'woocommerce_after_single_product_summary', 'add_place_bid_button' );
function add_place_bid_button() {
    global $product;
    $product_id = $product->get_id();
    $settings = get_option( 'place_bid_settings' ); // Assuming you have settings stored in the database

    //This is where the button will be added.
    ?>
    <button 
        class="place-bid-button elementor-button" 
        data-product="<?php echo esc_attr($product_id); ?>"
        data-logged-in="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>"
        data-nonce="<?php echo wp_create_nonce('place-bid-nonce'); ?>"
        style="background-color: <?php echo esc_attr($settings['button_background_color']); ?>"
    >
        <?php echo esc_html($settings['button_text']); ?>
    </button>
    <?php
}

