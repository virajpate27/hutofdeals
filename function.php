<?php
/**
 * Enqueue script and styles for child theme
 */
function woodmart_child_enqueue_styles() {
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), woodmart_get_theme_info( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );


/*--------------------------------------------------------------
# Add Custom Product Label Field
--------------------------------------------------------------*/

// Add field in Product General Tab
add_action('woocommerce_product_options_general_product_data', function () {

    woocommerce_wp_text_input(array(
        'id'          => '_custom_product_label',
        'label'       => 'Custom Product Label',
        'placeholder' => 'e.g. Best Seller',
        'desc_tip'    => true,
        'description' => 'Enter custom label text',
    ));

});

// Save field value
add_action('woocommerce_process_product_meta', function ($post_id) {

    $label = isset($_POST['_custom_product_label'])
        ? sanitize_text_field($_POST['_custom_product_label'])
        : '';

    update_post_meta($post_id, '_custom_product_label', $label);

});


/*--------------------------------------------------------------
# Show Label Above Product Title
--------------------------------------------------------------*/

// Single Product Page
add_action('woocommerce_single_product_summary', function () {

    global $product;

    $label = get_post_meta($product->get_id(), '_custom_product_label', true);

    // Hide if empty
    if (empty($label)) {
        return;
    }

    echo '<div class="custom-product-label">' . esc_html($label) . '<span class="w-2 h-2 bg-green-500 rounded-full animate-ping"></span></div>';

}, 4); // Before title


// Shop / Archive Page
add_action('woocommerce_before_shop_loop_item_title', function () {

    global $product;

    $label = get_post_meta($product->get_id(), '_custom_product_label', true);

    // Hide if empty
    if (empty($label)) {
        return;
    }

    echo '<div class="custom-product-label shop-label">' . esc_html($label) . ' </div>';

}, 5);



// Action timer

function custom_auction_timer_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

    // Correct Meta Key
    $end_date = get_post_meta($product_id, 'woo_ua_auction_end_date', true);

    if (!$end_date) {
        return 'Auction timer not available.';
    }

    $timer_id = 'auction-timer-' . $product_id;

    ob_start();
    ?>

     <div class="auction-timer-main">
		<p class="timer-lable">
			Auction Ends In
		 </p> 
	  <div class="custom-auction-timer"
         id="<?php echo esc_attr($timer_id); ?>"
         data-end="<?php echo esc_attr($end_date); ?>">
      </div>	 
     </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {

        const timer = document.getElementById("<?php echo $timer_id; ?>");

        if (!timer) return;

        const endDate = new Date(timer.dataset.end).getTime();

        function updateTimer() {

            const now = new Date().getTime();
            const distance = endDate - now;

            if (distance <= 0) {
                timer.innerHTML = "Auction Ended";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            timer.innerHTML =
                days + ":" +
                hours + ":" +
                minutes + ":" +
                seconds + "";
        }

        updateTimer();
        setInterval(updateTimer, 1000);

    });
    </script>

    <?php

    return ob_get_clean();
}

add_shortcode('auction_timer', 'custom_auction_timer_shortcode');



// current bid

function custom_current_bid_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

    // Current Bid Meta Key
    $current_bid = get_post_meta($product_id, 'woo_ua_auction_current_bid', true);

    // Starting Price Fallback
    if (empty($current_bid)) {
        $current_bid = get_post_meta($product_id, 'woo_ua_opening_price', true);
    }

    if (empty($current_bid)) {
        return 'No bid available.';
    }

    ob_start();
    ?>

    <div class="custom-current-bid">

        <p class="bid-label">Current Bid:</span>

        <h3 class="bid-price">
            ₹<?php echo number_format((float)$current_bid, 2); ?>
        </h3>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('current_bid', 'custom_current_bid_shortcode');


//Highest Bidder 

function custom_highest_bidder_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

// Highest Bidder User ID
    $highest_bidder_id = get_post_meta($product_id, 'woo_ua_auction_current_bider', true);

    if (empty($highest_bidder_id)) {
        return 'No bidder yet.';
    }

    $user = get_user_by('id', $highest_bidder_id);

    if (!$user) {
        return 'Bidder not found.';
    }

    // Display Name
    $display_name = $user->display_name;

    ob_start();
    ?>

    <div class="custom-highest-bidder">

        <span class="bidder-label">Highest Bidder:</span>

        <span class="bidder-name">
            <?php echo esc_html($display_name); ?>
        </span>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('highest_bidder', 'custom_highest_bidder_shortcode');



//Top 5 Bidders List 

function custom_top_bidders_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => '',
        'limit' => 3
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

    global $wpdb;

    // Auction Bids Table
    $table_name = $wpdb->prefix . 'woo_ua_auction_log';

    // Get Top Bidders
    $results = $wpdb->get_results(

        $wpdb->prepare(

            "
            SELECT t1.userid,
                   t1.bid,
                   t1.date
            FROM $table_name t1

            INNER JOIN (

                SELECT userid,
                       MAX(bid) as highest_bid

                FROM $table_name

                WHERE auction_id = %d

                GROUP BY userid

            ) t2

            ON t1.userid = t2.userid
            AND t1.bid = t2.highest_bid

            WHERE t1.auction_id = %d

            ORDER BY t1.bid DESC

            LIMIT %d
            ",

            $product_id,
            $product_id,
            $atts['limit']
        )
    );

    if (empty($results)) {
        return 'No bids found.';
    }

    ob_start();
    ?>

    <div class="custom-top-bidders">

       
		
		<div class="bidlive-wrapper">

    <div class="bidlive-header">
        <h2 class="bidlive-title">Live Bid Updates</h2>
        <span class="bidlive-status">LIVE</span>
    </div>

			
     <?php
	
	  $rank = 1;
	
	 foreach ($results as $row) {
		  $user = get_user_by('id', $row->userid);
          
		 if (!$user) continue;
		 
		 // Mask Username
                $name = $user->display_name; 
		        // Format Date
                $formatted_date = date(
                    'M d, Y g:i a',
                    strtotime($row->date)
                );       
		 
                ?>
			
			<!-- Bid Item -->
    <div class="bidlive-card">
        <div class="bidlive-left">
            <h3 class="bidlive-name"> <?php echo esc_html($name); ?></h3>
            <span class="bidlive-time"><?php echo esc_html($formatted_date); ?></span>
        </div>

        <div class="bidlive-price">
             <?php echo wc_price($row->bid); ?>

        </div>
    </div>
		
    <?php

       $rank++;

	 }
	
	 ?>
			
</div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('top_bidders', 'custom_top_bidders_shortcode');

//Buy Now Price + Button

function custom_buy_now_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

    $product = wc_get_product($product_id);

    if (!$product) {
        return 'Product not found.';
    }

    // WooCommerce Product Price
    $buy_now_price = $product->get_price();

    if (empty($buy_now_price)) {
        return 'Buy Now not available.';
    }

    // Add To Cart URL
   $cart_url = wc_get_checkout_url() . '?add-to-cart=' . $product_id;
	
	// Auction End Date
$auction_end = get_post_meta(
    $product_id,
    'woo_ua_auction_end_date',
    true
);

// Highest Bidder (Winner)
$winner_user_id = get_post_meta(
    $product_id,
    'woo_ua_auction_current_bider',
    true
);

// Current User
$current_user_id = get_current_user_id();

// Auction Status
$is_ended = false;

if ($auction_end) {

    if (current_time('timestamp') >= strtotime($auction_end)) {

        $is_ended = true;
    }
}

// Winner Check
$is_winner = false;

if (
    is_user_logged_in()
    &&
    $current_user_id == $winner_user_id
) {

    $is_winner = true;
}

    ob_start();
    ?>

    <div class="custom-buy-now-wrapper">

       <div class="main-buynow">
    <div class="left-main">
       <p class="mlabel">Instant Purchase Option</p>
       <h3 class="price">
                <?php echo wc_price($buy_now_price); ?>
       </h3>
    </div>

    <div class="right-main">
      <?php if (!$is_ended) : ?>

    <!-- BEFORE AUCTION END -->

    <a href="<?php echo esc_url($cart_url); ?>"
       class="custom-buy-now-btn">

        Buy Now

    </a>

<?php else : ?>


    <?php if ($is_winner) : ?>

        <!-- WINNER USER -->

        <a href="<?php echo esc_url($cart_url); ?>"
           class="custom-buy-now-btn winner-btn">

            Claim Your Item

        </a>

    <?php else : ?>

        <!-- OTHER USERS -->

        <button class="custom-buy-now-btn disabled-buy-btn"
                disabled>

            Auction Closed

        </button>

    <?php endif; ?>


<?php endif; ?>
    </div>
</div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('buy_now_auction', 'custom_buy_now_shortcode');


/* =========================================================
   AJAX AUCTION BID BOX
   Ultimate Auction for WooCommerce
========================================================= */


/* =========================================================
   SHORTCODE
========================================================= */

function custom_ajax_bid_box_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    if (empty($atts['id'])) {
        return 'Auction ID missing.';
    }

    $product_id = $atts['id'];

    // Current Bid
    $current_bid = get_post_meta(
        $product_id,
        'woo_ua_auction_current_bid',
        true
    );

    // Opening Price Fallback
    if (empty($current_bid)) {

        $current_bid = get_post_meta(
            $product_id,
            'woo_ua_opening_price',
            true
        );
    }

    // Starting Bid
    $starting_bid = get_post_meta(
        $product_id,
        'woo_ua_opening_price',
        true
    );

    // Increment Amount
    $increment = 100;

    // Minimum Next Bid
    $minimum_bid = $current_bid + $increment;


    /* =========================================================
       USER LAST BID
    ========================================================= */

    global $wpdb;

    $table_name = $wpdb->prefix . 'woo_ua_auction_log';

    $user_last_bid = '';

    if (is_user_logged_in()) {

        $user_id = get_current_user_id();

        $user_last_bid = $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT bid
                FROM $table_name
                WHERE auction_id = %d
                AND userid = %d
                ORDER BY date DESC
                LIMIT 1
                ",

                $product_id,
                $user_id
            )
        );
    }
	
	
	// Auction End Date
$auction_end = get_post_meta(
    $product_id,
    'woo_ua_auction_end_date',
    true
);
	
// Auction Status
$is_ended = false;

if ($auction_end) {

    $current_time = current_time('timestamp');

    $end_time = strtotime($auction_end);

    if ($current_time >= $end_time) {

        $is_ended = true;
    }
}
	
	

    ob_start();
    ?>

    <div class="custom-auction-box"
         data-product-id="<?php echo esc_attr($product_id); ?>">

        <!-- BID INFO -->

        <div class="auction-bid-info">

            <!-- STARTING BID -->

            <div class="bid-info-item start-bid">

                <span class="label">
                    Starting Bid
                </span>

                <span class="value starting-bid">
                    <?php echo wc_price($starting_bid); ?>
                </span>

            </div>


            <!-- CURRENT BID -->

            <div class="bid-info-item current-bid">

                <span class="label">
                    Current Bid
                </span>

                <span class="value live-price">
                    <?php echo wc_price($current_bid); ?>
                </span>

            </div>


            <!-- YOUR BID -->

            <div class="bid-info-item your-bid">

                <span class="label">
                    Your Bid
                </span>

                <span class="value your-bid-preview">

                    <?php

                    if ($user_last_bid) {

                        echo wc_price($user_last_bid);

                    } else {

                        echo 'No Bid Yet';
                    }

                    ?>

                </span>

            </div>

        </div>


        <!-- BID CONTROLS -->

        <div class="auction-bid-controls">

            <button class="bid-minus">−</button>

            <input type="number"
                   class="bid-input"
                   value="<?php echo esc_attr($minimum_bid); ?>"
                   min="<?php echo esc_attr($minimum_bid); ?>"
                   step="<?php echo esc_attr($increment); ?>">

            <button class="bid-plus">+</button>

        </div>


        <!-- PLACE BID BUTTON -->

        <button class="place-bid-btn <?php echo $is_ended ? 'auction-ended-btn' : ''; ?>"

    <?php echo $is_ended ? 'disabled' : ''; ?>>

    <?php

    if ($is_ended) {

        echo 'Auction Ended';

    } else {

        echo 'Place Bid';
    }

    ?>

</button>


        <!-- POPUP -->

        <div class="auction-popup"></div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('ajax_bid_box', 'custom_ajax_bid_box_shortcode');



/* =========================================================
   AJAX PLACE BID
========================================================= */

function custom_ajax_place_bid() {

    if (!is_user_logged_in()) {

        wp_send_json(array(
            'success' => false,
            'message' => 'Please login to place bid.'
        ));
    }

    $product_id = intval($_POST['product_id']);
	// Check Auction End

$auction_end = get_post_meta(
    $product_id,
    'woo_ua_auction_end_date',
    true
);

if ($auction_end) {

    if (current_time('timestamp') >= strtotime($auction_end)) {

        wp_send_json(array(

            'success' => false,

            'message' => 'Auction has ended.'

        ));
    }
}
	
    $bid_amount = floatval($_POST['bid_amount']);
    $user_id    = get_current_user_id();

    // Current Bid
    $current_bid = get_post_meta(
        $product_id,
        'woo_ua_auction_current_bid',
        true
    );

    // Opening Price Fallback
    if (empty($current_bid)) {

        $current_bid = get_post_meta(
            $product_id,
            'woo_ua_opening_price',
            true
        );
    }

    // Increment
    $increment = 100;

    // Minimum Required
    $minimum_required = $current_bid + $increment;


    // VALIDATION

    if ($bid_amount < $minimum_required) {

        wp_send_json(array(
            'success' => false,
            'message' => 'Bid must be at least ' . wc_price($minimum_required)
        ));
    }


    // UPDATE CURRENT BID

    update_post_meta(
        $product_id,
        'woo_ua_auction_current_bid',
        $bid_amount
    );


    // UPDATE HIGHEST BIDDER

    update_post_meta(
        $product_id,
        'woo_ua_auction_current_bider',
        $user_id
    );


    // INSERT BID LOG

    global $wpdb;

    $table_name = $wpdb->prefix . 'woo_ua_auction_log';

    $wpdb->insert(
        $table_name,
        array(
            'userid'     => $user_id,
            'auction_id' => $product_id,
            'bid'        => $bid_amount,
            'date'       => current_time('mysql')
        )
    );


    // RESPONSE

    wp_send_json(array(

        'success' => true,

        'message' => 'Bid placed successfully!',

        'new_bid' => wc_price($bid_amount),

        'next_min_bid' => wc_price($bid_amount + $increment),

        'next_bid_value' => $bid_amount + $increment

    ));
}

add_action('wp_ajax_custom_ajax_place_bid', 'custom_ajax_place_bid');

add_action(
    'wp_ajax_nopriv_custom_ajax_place_bid',
    'custom_ajax_place_bid'
);



/* =========================================================
   JAVASCRIPT
========================================================= */

function custom_ajax_bid_scripts() {
?>

<script>

document.addEventListener("DOMContentLoaded", function() {

    document.querySelectorAll('.custom-auction-box').forEach(box => {

        const productId = box.dataset.productId;

        const input = box.querySelector('.bid-input');

        const plus = box.querySelector('.bid-plus');

        const minus = box.querySelector('.bid-minus');

        const placeBtn = box.querySelector('.place-bid-btn');

        const popup = box.querySelector('.auction-popup');

        const livePrice = box.querySelector('.live-price');

        const yourBid = box.querySelector('.your-bid-preview');

        const step = parseInt(input.step);


        // UPDATE YOUR BID PREVIEW

        function updateYourBid() {

            let amount = parseInt(input.value);

            yourBid.innerHTML =
                '₹' + amount.toLocaleString('en-IN');
        }


        // PLUS BUTTON

        plus.addEventListener('click', () => {

            input.value = parseInt(input.value) + step;

            updateYourBid();

        });


        // MINUS BUTTON

        minus.addEventListener('click', () => {

            let current = parseInt(input.value);

            let minAllowed = parseInt(input.min);

            if (current > minAllowed) {

                input.value = current - step;

                updateYourBid();
            }

        });


        // INPUT CHANGE

        input.addEventListener('input', updateYourBid);


        // PLACE BID

        placeBtn.addEventListener('click', () => {

            const bidAmount = input.value;

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },

                body:
                    'action=custom_ajax_place_bid' +
                    '&product_id=' + productId +
                    '&bid_amount=' + bidAmount
            })

            .then(response => response.json())

            .then(data => {

                popup.innerHTML = data.message;
               
                popup.classList.add('show');


                if (data.success) {

                    livePrice.innerHTML = data.new_bid;

                    yourBid.innerHTML = data.new_bid;

                    input.value = data.next_bid_value;

                    input.min = data.next_bid_value;
                }
				
				 // ERROR

             else { popup.classList.add('error-popup');}



                setTimeout(() => {

                    popup.classList.remove('show');
					popup.classList.remove('error-popup');
					location.reload();

                }, 1000);

            });

        });

    });

});

</script>

<?php
}

add_action('wp_footer', 'custom_ajax_bid_scripts');



//Product Gallery 

// Product Gallery Shortcode with Click Thumbnail Change Image
function custom_product_gallery_shortcode($atts) {

    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts);

    $product_id = $atts['id'];

    if (!$product_id) {
        return 'Product ID missing.';
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        return 'Product not found.';
    }

    // Featured Image
    $featured_image = get_the_post_thumbnail_url($product_id, 'large');

    ob_start();
    ?>

    <div class="custom-gallery-wrapper">

        <!-- Main Image -->
        <div class="custom-main-image-wrap">
            <img 
                id="custom-main-image-<?php echo $product_id; ?>" 
                src="<?php echo esc_url($featured_image); ?>" 
                class="custom-main-image"
            >
        </div>

        <!-- Gallery -->
        <div class="custom-gallery-thumbs">

            <!-- Featured Image Thumb -->
            <img 
                src="<?php echo esc_url($featured_image); ?>"
                class="custom-thumb active-thumb"
                onclick="changeProductImage('<?php echo $product_id; ?>', this)"
            >

            <?php

            $attachment_ids = $product->get_gallery_image_ids();

            if ($attachment_ids) {

                foreach ($attachment_ids as $attachment_id) {

                    $image_large = wp_get_attachment_image_url($attachment_id, 'large');

                    $image_thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');

                    ?>

                    <img 
                        src="<?php echo esc_url($image_thumb); ?>"
                        data-large="<?php echo esc_url($image_large); ?>"
                        class="custom-thumb"
                        onclick="changeProductImage('<?php echo $product_id; ?>', this)"
                    >

                    <?php
                }
            }

            ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('custom_product_gallery', 'custom_product_gallery_shortcode');
