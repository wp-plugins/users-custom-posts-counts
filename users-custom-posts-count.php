<?php
/*
Plugin Name: 	Users Custom Posts Counts
Plugin URI: 	http://www.bruno-carreco.com/plugins/wordpress/users-custom-posts-counts
Description:  	Simple plugin that adds a new column showing custom type posts counts on the users list. Users have the option to choose the post type to use. It works just like the Posts Column. You can normally sort and filter the new column.
Version: 		1.0
Author: 		Bruno Carre&ccedil;o
Author URI: 	http://www.bruno-carreco.com
License: 		GPL v2
*/

define("WP_DEBUG", false);

/**
 * Based on the function get_posts_by_author_sql() that retrieve the post SQL based on capability, author, and type.
 * Changed it to have the same behaviour with custom posts as with a normal 'post'
 *
 * @param string $post_type 	Supports 'post', 'custom_post_types' or 'page'.
 * @param bool $full 			Optional.  Returns a full WHERE statement instead of just an 'andalso' term.
 * @param int $post_author 		Optional.  Query posts having a single author ID.
 * @return string 				SQL	WHERE code that can be added to a query.
 */
function ucpc_get_posts_by_author_sql($post_type, $full = TRUE, $post_author = NULL) {
	global $user_ID, $wpdb;

	// Private posts
	if ($post_type == 'post') {
		$cap = 'read_private_posts';
	// Private pages
	} elseif ($post_type == 'page') {
		$cap = 'read_private_pages';
	// Private custom posts
	} else {
		$cap = 'read_private_pages';
	}

	if ($full) {
		if (is_null($post_author)) {
			$sql = $wpdb->prepare('WHERE post_type = %s AND ', $post_type);
		} else {
			$sql = $wpdb->prepare('WHERE post_author = %d AND post_type = %s AND ', $post_author, $post_type);
		}
	} else {
		$sql = '';
	}

	$sql .= "(post_status = 'publish'";

	if (current_user_can($cap)) {
		// Does the user have the capability to view private posts? Guess so.
		$sql .= " OR post_status = 'private'";
	} elseif (is_user_logged_in()) {
		// Users can view their own private posts.
		$id = (int) $user_ID;
		if (is_null($post_author) || !$full) {
			$sql .= " OR post_status = 'private' AND post_author = $id";
		} elseif ($id == (int)$post_author) {
			$sql .= " OR post_status = 'private'";
		} // else none
	} // else none

	$sql .= ')';

	return $sql;
}

/**
 * Adds a new column to the user listing.
 *
 * @param string $output 		Not used.
 * @param bool $column_name 	New column name.
 * @param int $user_id 			User ID.
 * @return string 				The html column.
 */
function ucpc_manage_users_custom_column($output = '', $column_name, $user_id) {
    global $wpdb;

    if( $column_name !== 'post_type_count' )
        return;

	//get the post type selected by the user	
	$options = get_option('ucpc_options');
	$options['post_type_label'] = get_post_type_object($options['post_type'])->label;		
		
    $where = ucpc_get_posts_by_author_sql( $options['post_type'], true, $user_id );
    $result = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );

    return '<a href="' . admin_url("edit.php?post_type=".$options['post_type']."&author=$user_id") . '" title="'.__($options['post_type_label']).'">' . $result . '</a>';
}
add_filter('manage_users_custom_column', 'ucpc_manage_users_custom_column', 10, 3);

/**
 * Renames the new user column.
 *
 * @param string $columns 	Columns array.
 * @return string 			Modified columns array.
 */
function ucpc_manage_users_columns($columns) {

	//get the post type selected by the user	
	$options = get_option('ucpc_options');
	$options['post_type_label'] = get_post_type_object($options['post_type'])->label;		
	
    $columns['post_type_count'] = ($options['post_type_label']?__($options['post_type_label']):__('New Custom Type? Please update UCPC options'));

    return $columns;
}
add_filter('manage_users_columns', 'ucpc_manage_users_columns');


// Add a new submenu under the settings menu
add_action('admin_menu', 'ucpc_admin');

function ucpc_admin() {
	add_options_page('User Custom Posts Count', 'User Custom Posts Count', 'manage_options', 'ucpc_options', 'ucpc_options');
}

// displays the options page content
function ucpc_options() { 

	$args=array(
	  'public'   => true,
	  '_builtin' => false
	);
	
	$post_types = get_post_types( $args, 'objects');
	
?>
	<div class="wrap" style="float:left;">
		<form method="post" id="ucpc_options" action="options.php">
			<?php
				//get defaults
				settings_fields('ucpc_options');
				$options = get_option('ucpc_options');						
			?>
			<h2><?php _e('User Custom Posts Count Options'); ?> </h2>
			<p> <?php _e('Please select the Post Type you want to use for the User Post Counts'); ?>:</p>			
			<?php _e('Post Type: '); ?>
			<select name="ucpc_options[post_type]" id="post_type">
				<?php
					foreach ($post_types as $post_type) {	 ?>						
						<option value="<?php echo $post_type->name; ?>" <?php echo ($options['post_type']==$post_type->name?'selected':'') ?> ><?php echo $post_type->label . ' ('. $post_type->name .')'; ?></option>
				<?php }	?>	
			</select>
			<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="Update Options" />
			</p>
		</form>
	</div>
	<div class="paypal" style="float:right; border: 2px dashed #ccc; padding: 2px 10px 2px 10px; margin-top: 75px; width: 200px; ">
		<h3><?php _e('Like this plugin?'); ?></h3>
		<p><?php _e('Why don\'t you buy me a coffee so I can keep my eyes open and do some more :)')?></p>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="NQL79HRYG6ZT4">
		<input type="image" src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
		<img alt="" border="0" src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/scr/pixel.gif" width="1" height="1">		
		</form>	
	</div>			

<?php
}

// registers the options
function register_ucpc_options() {
	register_setting( 'ucpc_options', 'ucpc_options' );
}
add_action('admin_init', 'register_ucpc_options' );

// set defaults on plugin activation
function ucpc_activation() {
	
	$args=array(
	  'public'   => true,
	  '_builtin' => false
	);	
	$post_types = get_post_types( $args, 'objects');	
	//gets the first custom taxonomy be default
	foreach ($post_types as $post_type) {	
		$def_ptype = $post_type->name;
		break;
	}
	$options = array();
	$options['post_type'] = $def_ptype;
		
	// set new option
	add_option('ucpc_options', $options, '', 'yes');
}
register_activation_hook(__FILE__, 'ucpc_activation');
?>