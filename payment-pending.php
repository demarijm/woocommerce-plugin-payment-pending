<?php

/**
 * Plugin Name: payment-pending
 *
 * @package WooCommerce\Admin
 */

/**
 * Register the JS and CSS.
 */
function add_extension_register_script()
{
	if (
		!method_exists('Automattic\WooCommerce\Admin\Loader', 'is_admin_or_embed_page') ||
		!\Automattic\WooCommerce\Admin\Loader::is_admin_or_embed_page()
	) {
		return;
	}


	$script_path       = '/build/index.js';
	$script_asset_path = dirname(__FILE__) . '/build/index.asset.php';
	$script_asset      = file_exists($script_asset_path)
		? require($script_asset_path)
		: array('dependencies' => array(), 'version' => filemtime($script_path));
	$script_url = plugins_url($script_path, __FILE__);

	wp_register_script(
		'payment-pending',
		$script_url,
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);

	wp_register_style(
		'payment-pending',
		plugins_url('/build/index.css', __FILE__),
		// Add any dependencies styles may have, such as wp-components.
		array(),
		filemtime(dirname(__FILE__) . '/build/index.css')
	);

	wp_enqueue_script('payment-pending');
	wp_enqueue_style('payment-pending');
}

add_action('admin_enqueue_scripts', 'add_extension_register_script');


// Adding the query argument 
function apply_currency_arg( $args ) {
    $currency = 'USD';
 
    if ( isset( $_GET['currency'] ) ) {
        $currency = sanitize_text_field( wp_unslash( $_GET['currency'] ) );
    }
 
    $args['currency'] = $currency;
 
    return $args;
}
 
add_filter( 'woocommerce_analytics_orders_query_args', 'apply_currency_arg' );
add_filter( 'woocommerce_analytics_orders_stats_query_args', 'apply_currency_arg' );


// Adding a join for the orders table, order stats, and orders chart
function add_join_subquery( $clauses ) {
    global $wpdb;
 
    $clauses[] = "JOIN {$wpdb->postmeta} currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = currency_postmeta.post_id";
 
    return $clauses;
}
 
add_filter( 'woocommerce_analytics_clauses_join_orders_subquery', 'add_join_subquery' );
add_filter( 'woocommerce_analytics_clauses_join_orders_stats_total', 'add_join_subquery' );
add_filter( 'woocommerce_analytics_clauses_join_orders_stats_interval', 'add_join_subquery' );

// Adding a where clause for the orders table, order stats, and orders chart
function add_where_subquery( $clauses ) {
    $currency = 'USD';
 
    if ( isset( $_GET['currency'] ) ) {
        $currency = sanitize_text_field( wp_unslash( $_GET['currency'] ) );
    }
 
    $clauses[] = "AND currency_postmeta.meta_key = '_order_currency' AND currency_postmeta.meta_value = '{$currency}'";
 
    return $clauses;
}
 
add_filter( 'woocommerce_analytics_clauses_where_orders_subquery', 'add_where_subquery' );
add_filter( 'woocommerce_analytics_clauses_where_orders_stats_total', 'add_where_subquery' );
add_filter( 'woocommerce_analytics_clauses_where_orders_stats_interval', 'add_where_subquery' );

// // finally, a select clause for the orders table, order stats, and orders chart
// function add_where_subquery( $clauses ) {
//     $currency = 'USD';
 
//     if ( isset( $_GET['currency'] ) ) {
//         $currency = sanitize_text_field( wp_unslash( $_GET['currency'] ) );
//     }
 
//     $clauses[] = "AND currency_postmeta.meta_key = '_order_currency' AND currency_postmeta.meta_value = '{$currency}'";
 
//     return $clauses;
// }
 
// add_filter( 'woocommerce_analytics_clauses_where_orders_subquery', 'add_where_subquery' );
// add_filter( 'woocommerce_analytics_clauses_where_orders_stats_total', 'add_where_subquery' );
// add_filter( 'woocommerce_analytics_clauses_where_orders_stats_interval', 'add_where_subquery' );


// Adding a select fragment to the report SQL
function add_access_expires_select( $report_columns, $context, $table_name ) {
	if ($context !== 'downloads') {
		return $report_columns;
	}
	$report_columns['access_expires'] = 
		'product_permissions.access_expires AS access_expires';
	return $report_columns;
}



// Add a UI dropdown to get currencies

function add_currency_settings() {
    $currencies = array(
        array(
            'label' => __( 'United States Dollar', 'dev-blog-example' ),
            'value' => 'USD',
        ),
        array(
            'label' => __( 'New Zealand Dollar', 'dev-blog-example' ),
            'value' => 'NZD',
        ),
        array(
            'label' => __( 'Mexican Peso', 'dev-blog-example' ),
            'value' => 'MXN',
        ),
    );
 
    $data_registry = Automattic\WooCommerce\Blocks\Package::container()->get(
        Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class
    );
 
    $data_registry->add( 'multiCurrency', $currencies );
}
 
add_action( 'init', 'add_currency_settings' );

/**
 * Register a WooCommerce Admin page.
 */
function add_extension_register_page()
{
	if (!function_exists('wc_admin_register_page')) {
		return;
	}

	wc_admin_register_page(array(
		'id'       => 'pending-report',
		'title'    => __('Pending Order Report', 'pending-order-report'),
		'parent'   => 'woocommerce',
		'path'     => '/pending-report',
		'nav_args' => array(
			'order'  => 10,
			'parent' => 'woocommerce',
		),
	));
}

add_action('admin_menu', 'add_extension_register_page');




// Testing, you can delete this section
function cw_add_order_profit_column_header($columns)
{
    $new_columns = array();
    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ('order_total' === $column_name) {
            $new_columns['order_profit'] = __('Profit', 'my-textdomain');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'cw_add_order_profit_column_header');

function cw_add_order_profit_column_content( $column ) {
    global $post;
    if ( 'order_profit' === $column ) {
        $order    = wc_get_order( $post->ID );
        $currency = is_callable( array( $order, 'get_currency' ) ) ? $order->get_currency() : $order->order_currency;
        $profit   = '';
        $cost     = sv_helper_get_order_meta( $order, '_wc_cog_order_total_cost' );
        $total    = (float) $order->get_total();
               if ( '' !== $cost || false !== $cost ) {
            $cost   = (float) $cost;
            $profit = $total - $cost;
        }
        echo wc_price( $profit, array( 'currency' => $currency ) );
    }
}
add_action( 'manage_shop_order_posts_custom_column', 'cw_add_order_profit_column_content' );

function cw_add_order_profit_column_style() {
    $css = '.widefat .column-order_date, .widefat .column-order_profit { width: 9%; }';
    wp_add_inline_style( 'woocommerce_admin_styles', $css );
}
add_action( 'admin_print_styles', 'cw_add_order_profit_column_style' );