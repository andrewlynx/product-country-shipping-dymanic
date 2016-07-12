<?php
/*
Plugin Name: Product Country Shipping Dynamic
Description:
Version: 1
Author: Tarik A.
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

function iqxzvqhmye_add_meta_boxes() {
	add_meta_box( 'meta-box-iqxzvqhmye', 'Google shopping feeds', 'iqxzvqhmye_add_meta_boxes_callback', 'product' );
}
add_action( 'add_meta_boxes', 'iqxzvqhmye_add_meta_boxes' );

function iqxzvqhmye_add_meta_boxes_callback( $post ) {
	$hide_from_google_feeds= get_post_meta($post->ID, 'iqxzvqhmye_hide_from_google_feeds', true );
	?>
	<label>Hide from Google feeds <input name="iqxzvqhmye_hide_from_google_feeds" type="checkbox" <?php checked($hide_from_google_feeds,'yes') ?>  value="yes"></label> 
	<?php
}

function iqxzvqhmye_save_post( $post_id ) {
	if(isset($_POST['iqxzvqhmye_hide_from_google_feeds'])){
		update_post_meta($post_id, 'iqxzvqhmye_hide_from_google_feeds', $_POST['iqxzvqhmye_hide_from_google_feeds'] );
	}
}
add_action( 'save_post', 'iqxzvqhmye_save_post' );

function iqxzvqhmye_country_code(){
	
	$country_codes=get_option( 'woocommerce_specific_allowed_countries',array());
	
	$country_code = (isset($_COOKIE['iqxzvqhmye_country_ship_to'])) ? sanitize_text_field($_COOKIE['iqxzvqhmye_country_ship_to']) : null;
	
	//echo 'cookie:' . $country_code;
	
	if(empty($country_code)){
		$user_id = get_current_user_id();
		if($user_id){
			$country_code = get_user_meta( $user_id, 'iqxzvqhmye_country_ship_to', true );
		}
	}
	
	//echo 'user:' . $country_code;
	
	if(empty($country_code)){
		if (class_exists('WC_Geolocation')) {
			$user_location  = WC_Geolocation::geolocate_ip();
			$country_code = ! empty( $user_location['country'] ) ? $user_location['country'] : 'UK';
		}
	}
	
	//echo 'geo:' . $country_code;
	
	if(empty($country_code) || !in_array($country_code,$country_codes)){
		$country_code = 'UK';
	}
	
	//echo 'default:' . $country_code;
	
	return $country_code;
}

function iqxzvqhmye_woocommerce_edit_account_form() {
	
	$user_id = get_current_user_id();

	if ( !$user_id )
	return;

	$country_codes=get_option( 'woocommerce_specific_allowed_countries',array());
	$country_ship_to = get_user_meta( $user_id, 'iqxzvqhmye_country_ship_to', true );

	?>

	<fieldset>
		<legend><?php _e( 'Country ship to', 'woocommerce' ); ?></legend>
		<p class="form-row form-row-wide">
			<select name="iqxzvqhmye_country_ship_to_input" style="width:100%;" >
				<option value=""><?php _e( 'Choose a country&hellip;', 'woocommerce' ); ?></option>
				<?php
				foreach ( $country_codes as $country_code ) {
					echo '<option '.selected($country_ship_to,$country_code).' value="' . $country_code. '" ' . '>' . WC()->countries->countries[$country_code] . '</option>';
				}
				?>

			</select>
		</p>
	</fieldset>

	<?php
}
 
add_action( 'woocommerce_edit_account_form', 'iqxzvqhmye_woocommerce_edit_account_form',11 );


function iqxzvqhmye_woocommerce_add_to_cart(){
    
	$country_code=iqxzvqhmye_country_code();
	
    WC()->customer->set_country($country_code);
    WC()->customer->set_shipping_country($country_code);
}
add_action('woocommerce_add_to_cart' , 'iqxzvqhmye_woocommerce_add_to_cart'); 

function iqxzvqhmye_woocommerce_save_account_details( $user_id ) {
	if(isset($_POST[ 'iqxzvqhmye_country_ship_to_input' ])){
		setcookie('iqxzvqhmye_country_ship_to', null, -1, '/');
		update_user_meta( $user_id, 'iqxzvqhmye_country_ship_to', htmlentities( $_POST[ 'iqxzvqhmye_country_ship_to_input' ] ) );
	}
}
add_action( 'woocommerce_save_account_details', 'iqxzvqhmye_woocommerce_save_account_details' );

function iqxzvqhmye_prices($product_id,$country_code=false,$variation_id=false,$convert_price=true){
	
	$sku=get_post_meta($product_id, '_sku', true );

	if(is_numeric($sku)){
		
		$database_table=get_option('iqxzvqhmye_database_table_name','wp_product_country_shipping_dynamic');
		
		if(!$country_code){
			$country_code=iqxzvqhmye_country_code();
		}
		
		if($country_code=='GB'){
			$country_code='UK';
		}
		
		$output =array();
		
		global $wpdb;
		$query=$wpdb->get_row( "SELECT delivery_cost,delivery_time FROM ".$database_table." where product_id=".$sku." and country_code='".$country_code."'",ARRAY_A );
		
		if($query===null){
			return $query;
		}
		
		if($convert_price){
			$output['delivery_cost']=apply_filters('twaicejoop_prices',$query['delivery_cost'], $product_id); 
		}else{
			$output['delivery_cost']=$query['delivery_cost'];
		}
		
		$output['delivery_time']=$query['delivery_time'];
		
		if($variation_id){
			$output['product_id']=$variation_id;
		}else{
			$output['product_id']=$product_id;
		}
		
		return $output;
		
	}
	
	return null;
}

function iqxzvqhmye_woocommerce_get_price_html($price,$product){
	return str_replace( '</span>', ' '.get_woocommerce_currency().'<span>', $price );
}


function iqxzvqhmye_woocommerce_get_variation_price_html($price,$product) {
	return str_replace( '</span>', ' '.get_woocommerce_currency().'<span>', $price ); 
}

function iqxzvqhmye_woocommerce_shipping_init() {

	if ( ! class_exists( 'WC_Product_Country_Shipping_Dynamic_Method' ) ) {

		class WC_Product_Country_Shipping_Dynamic_Method extends WC_Shipping_Method {
			
			public function __construct() {
				$this->id                 = 'product_country_shipping_dynamic';
				$this->method_title       = __( 'Product Country Shipping Dynamic' );
				$this->method_description = __( 'Product Country Shipping Dynamic lets you charge a fixed price for shipping by country.' );
				$this->title       = __( 'Product Country Shipping Dynamic' );
				
				$this->init();
			}

			function init() {

				$this->init_form_fields(); 
				$this->init_settings(); 

				$this->enabled = $this->settings['enabled']; 
				
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function calculate_shipping( $package ) {
				
				
				$rate_cost=0;
				$shipping_allowed=true;
				

				foreach($package['contents'] as $content) {
					$product_id=$content['product_id'];
					$variation_id= $content['variation_id'];
					error_log($package['destination']['country']);
					$shipping_prices=iqxzvqhmye_prices($product_id,$package['destination']['country'],$variation_id);
					// $country_ship_to = iqxzvqhmye_country_code();
					if($shipping_prices===null){
						$shipping_allowed=false;
						break;
					}else{
						$shipping_prices=apply_filters( 'iqxzvqhmye_prices', $shipping_prices);
					}
					
					$rate_cost=$rate_cost+($shipping_prices['delivery_cost']*$content['quantity']);
				}
				
				if($shipping_allowed){
					$this->add_rate(array(
						'id'       => $this->id.'_standard',
						'label'    => $this->settings['method'],
						'cost'     => $rate_cost,
						'calc_tax' => 'per_order'
					));
				}

			
			}

			function is_available($package) {

				if ( 'no' == $this->enabled ) {
					return false;
				}

	            return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $this->enabled);
	        }
			
			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
						'type' 			=> 'checkbox',
						'label' 		=> __( 'Enable this shipping method', 'woocommerce' ),
						'default' 		=> 'no',
					),
			     'method' => array(
			          'title' => __( 'Shipping method label', 'woocommerce' ),
			          'type' => 'text',
			          'default' => 'Shipping'
			           ),
			     );
			}
		}
	}
}
add_action( 'woocommerce_shipping_init', 'iqxzvqhmye_woocommerce_shipping_init' );


function iqxzvqhmye_woocommerce_general_settings( $settings ) {

  $updated_settings = array();

  foreach ( $settings as $section ) {

    if ( isset( $section['id'] ) && 'general_options' == $section['id'] && isset( $section['type'] ) && 'sectionend' == $section['type'] ) {

      $updated_settings[] = array(
        'title'     => 'Shipping Table',
        'desc'     => 'Database table name for "shipping"',
        'id'       => 'iqxzvqhmye_database_table_name',
        'type'     => 'text',
        'class' => 'regular-text',
	     'default' => 'wp_product_country_shipping_dynamic',
      );
	  
	   $updated_settings[] = array(
        'title'     => 'Font-end Dropdown Menu',
        'desc'     => 'Show/hide dropdown menu in the top of pages.',
        'id'       => 'iqxzvqhmye_frontend_dropdown',
        'type'     => 'checkbox',
	    'default' => 'yes',
      );
    }

    $updated_settings[] = $section;

  }
  return $updated_settings;
}
add_filter( 'woocommerce_general_settings', 'iqxzvqhmye_woocommerce_general_settings' );
function iqxzvqhmye_wp_head()
{
    if( is_cart() || is_checkout() )
    {
    	echo '<style> #shipping_method .amount { float: right !important;} #shipping_method label { width: 90%; } </style>';
    }
	
	?>
	
	<style>
		.variation dt {margin-right: 5px;}
		.iqxzvqhmye {text-align: center;padding: 5px;margin: 0 auto;width: 350px;}
		.iqxzvqhmye .dd-options {height:250px;}
		.iqxzvqhmye .dd-selected-text, .iqxzvqhmye .dd-option-text {line-height: 32px !important;}
	</style>
	
	<?php
}
add_action( 'wp_head', 'iqxzvqhmye_wp_head' );

function iqxzvqhmye_render()
{
	$frontend_dropdown=get_option( 'iqxzvqhmye_frontend_dropdown','yes');
	
	if($frontend_dropdown=='no'){
		return false;
	}
	
	$country_codes=get_option( 'woocommerce_specific_allowed_countries',array());
	$country_ship_to = iqxzvqhmye_country_code();
	
	?>
	<div class="iqxzvqhmye">
		<select class="iqxzvqhmye-html-select" name="iqxzvqhmye_countries-ship_to"  >
			<option value=""><?php _e( 'Country ship to : ', 'woocommerce' ); ?></option>
			<?php
			foreach ( $country_codes as $country_code ) {
				echo '<option '.selected($country_ship_to,$country_code).' value="' . $country_code. '" ' . '>' . WC()->countries->countries[$country_code] . '</option>';
			}
			?>
		</select>
	</div>
	
	<?php
}
add_action( 'wp_head', 'iqxzvqhmye_render' ); 

function iqxzvqhmye_wp_footer()
{
	?>
	
 	<script>
    	jQuery(function(){
			var iqxzvqhmye_counter=0;
	    	jQuery('.iqxzvqhmye-html-select').ddslick({
			    onSelected: function(data){
					if(iqxzvqhmye_counter>0){
						console.log(data.selectedData.value);
						Cookies.set('iqxzvqhmye_country_ship_to', data.selectedData.value, { expires: 30, path: '/' });
						window.location.reload(false); 
			    	}
			    	iqxzvqhmye_counter++;
			    }   
			});
		});
    </script>
	
 	<script>
    	jQuery(window).load(function() {
	    	jQuery('#style').change(function(){
			   if(jQuery(this).val()){
				   jQuery('.summary .price').hide();
			   } else{
				   jQuery('.summary .price').show();
			   }
			});
		});
    </script>
	<?php
}
add_action( 'wp_footer', 'iqxzvqhmye_wp_footer' );

function iqxzvqhmye_default_checkout_country() {
  return iqxzvqhmye_country_code();
}
add_filter( 'default_checkout_country', 'iqxzvqhmye_default_checkout_country' );

function iqxzvqhmye_wp_enqueue_scripts() {
	wp_enqueue_script( 'iqxzvqhmye-ddslick', plugin_dir_url( __FILE__ ).'jquery.ddslick.min.js',array('jquery'));
	wp_enqueue_script( 'iqxzvqhmye-js-cookie', plugin_dir_url( __FILE__ ).'js.cookie.min.js',array('jquery'));
}
add_action( 'wp_enqueue_scripts', 'iqxzvqhmye_wp_enqueue_scripts' );

function iqxzvqhmye_woocommerce_shipping_methods( $methods ) {
	$methods[] = 'WC_Product_Country_Shipping_Dynamic_Method'; 
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'iqxzvqhmye_woocommerce_shipping_methods' );

function iqxzvqhmye_woocommerce_short_description($post_excerpt){

	$product_id=get_the_ID();
	// $variation_id=false;
	
	$shipping_settings=get_option('woocommerce_product_country_shipping_dynamic_settings');
	
	if($shipping_settings['enabled']!='no'){
			
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();
		// $quantity=1;
		
		// foreach ($items as $item) {

		// 	if($item['product_id']==$product_id){
		// 		$variation_id=(isset($item['variation_id'])) ? $item['variation_id'] : false;
		// 		$quantity=$item['quantity'];
		// 		break;
		// 	}
		// }

		$ouput=$post_excerpt;
		
		$currency=get_woocommerce_currency();
		$country_code=iqxzvqhmye_country_code();
		
		if($country_code){
				
			$shipping_allowed=true;
			
			$shipping_prices=iqxzvqhmye_prices($product_id,false);
			
			if($shipping_prices===null){
				$shipping_allowed=false;
			}else{
				$shipping_prices=apply_filters( 'iqxzvqhmye_prices', $shipping_prices,true);
			}
			
			$countries_ship_to= get_post_meta($product_id, 'iqxzvqhmy_countries_ship_to', true ); 
			if(isset($countries_ship_to['message']) && isset($countries_ship_to['number'])>0){
				$ouput=$ouput.'<p><strong>'.$countries_ship_to['message'].'</strong>: <span>'.$countries_ship_to['number'];
				
				if($shipping_allowed){
					$ouput=$ouput.' - Including '.WC()->countries->countries[$country_code];
				}else{
					$ouput=$ouput.' - Excluding '.WC()->countries->countries[$country_code];
				}
				
				$ouput=$ouput.'</span></p>';
			}
			
			if($shipping_allowed){
				
				$currency_symbol=get_woocommerce_currency_symbol();
			
				$rate_costs=$shipping_prices['delivery_cost']; 
				
				if(is_array($rate_costs)){
					foreach ($rate_costs as $key => $cost) {
						$rate_costs[$key]=$currency_symbol.$cost;
					}
					$rate_costs=implode(' - ',$rate_costs);
				}else{
					if($rate_costs>0){
						$rate_costs=$currency_symbol.$rate_costs; 
					}else{
						$rate_costs='FREE';
					}
				}
				
				$ouput=$ouput.'<p>';
					$ouput=$ouput.'<strong>'.$shipping_settings['method'].'</strong>: '.$rate_costs.'<br>';
				$ouput=$ouput.'</p>';
					
				if(strpos($shipping_prices['delivery_time'], '-') !== false){
						$ouput=$ouput.'<p><strong>Estimated Arrival</strong>: <span>'.$shipping_prices['delivery_time'].' Days</span></p>';
				}
				
				return $ouput;
			}
			
			$ouput=$ouput.'<p style="color:red"><span>Sorry, we currently do not ship this product to</span> <strong>'.WC()->countries->countries[$country_code].'</strong></p>';
			
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			
			echo '<style>.product-addtocart {display : none !important;}</style>';
			echo '<style>.summary .price {display : none !important;}</style>';
		}
		return $ouput;
	}	
	
	return $post_excerpt;
}
add_filter( 'woocommerce_short_description', 'iqxzvqhmye_woocommerce_short_description');

function iqxzvqhmye_woocommerce_get_item_data($item_data,$cart_item) {
	
	$shipping_settings=get_option('woocommerce_product_country_shipping_dynamic_settings');
	
	if($shipping_settings['enabled']!='no'){
		
		global $woocommerce;
		
		$product_id=$cart_item['product_id'];
		$variation_id=$cart_item['variation_id'];
		
		$items = $woocommerce->cart->get_cart();
		$quantity=1;
		
		foreach ($items as $item) {
			if($item['product_id']==$product_id && $item['variation_id']==$variation_id){
				$quantity=$item['quantity'];
				break;
			}
		}
		
		$currency=get_woocommerce_currency();
		$currency_symbol=get_woocommerce_currency_symbol();
	
		$shipping_allowed=true;
		
		$shipping_prices=iqxzvqhmye_prices($product_id,false,$variation_id);
			
		if($shipping_prices===null){
			$shipping_allowed=false;
		}else{
			$shipping_prices=apply_filters( 'iqxzvqhmye_prices', $shipping_prices);
		}
		
		if($shipping_allowed){
			
			$rate_cost=($shipping_prices['delivery_cost']*$quantity);
			
			$item_data[]=array(
				'key'=>$shipping_settings['method'],
				'value'=>$currency_symbol.$rate_cost.' '.$currency
			);
			
			$item_data = apply_filters('iqxzvqhmye_shipping', $item_data, $rate_cost, $quantity);			
			
			if(strpos($shipping_prices['delivery_time'], '-') !== false){
					$item_data[]=array(
						'key'=>'Estimated Arrival',
						'value'=>$shipping_prices['delivery_time'].' Days'
					);
			}
		}
	}
	
	return $item_data;
}
add_action( 'woocommerce_get_item_data', 'iqxzvqhmye_woocommerce_get_item_data',10,2 );

function iqxzvqhmy_add_meta_boxes() {
	add_meta_box( 'meta-box-iqxzvqhmy', __( 'Countries Ship to message', 'iqxzvqhmy' ), 'iqxzvqhmy_add_meta_boxes_callback', 'product' );
}
add_action( 'add_meta_boxes', 'iqxzvqhmy_add_meta_boxes' );

function iqxzvqhmy_add_meta_boxes_callback( $post ) {
	?>
	
	<table class="form-table">
		<tbody>
			
		<?php
			$countries_ship_to= get_post_meta($post->ID, 'iqxzvqhmy_countries_ship_to', true ); 
			if(!$countries_ship_to){
				$countries_ship_to=array(
						'message'=>'',
						'number'=>0,
					);
			}
		?>
		<tr>
			<th scope="row"><label>Text </label></th>
			<td>		
				<input  class="regular-text" placeholder="Message" value="<?php echo $countries_ship_to['message']; ?>" name="iqxzvqhmy_countries_ship_to[message]" type="text">
				<p class="description">
					If empty, default text will be : Number of Countries Ship to.
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label>Number </label></th>
			<td>		
				<input placeholder="Number" value="<?php echo $countries_ship_to['number']; ?>" name="iqxzvqhmy_countries_ship_to[number]" type="number" min="0">
				<p class="description">
					If set to 0 (zero) the text will be hidden.
				</p>
			</td>
		</tr>
		</tbody>
	</table>
	
	<?php
}

function iqxzvqhmy_save_post( $post_id ) {

	if(isset($_POST['iqxzvqhmy_countries_ship_to'])){
		update_post_meta($post_id, 'iqxzvqhmy_countries_ship_to', array(
			'message'=> empty($_POST['iqxzvqhmy_countries_ship_to']['messagetext']) ? 'Number of Countries Ship to' : sanitize_text_field($_POST['iqxzvqhmy_countries_ship_to']['message']),
			'number'=>(int)$_POST['iqxzvqhmy_countries_ship_to']['number'],
			)); 
	}
	
}
add_action( 'save_post', 'iqxzvqhmy_save_post' );
