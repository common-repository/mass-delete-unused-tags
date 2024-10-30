<?php
/**
 Plugin Name: Mass delete unused tags
 Plugin URI: https://www.mijnpress.nl
 Description: Deletes all unused tags, handy tool if you want to start over with a quick clean blog.
 Version: 3.1.0
 Author: Ramon Fincken
 Author URI: https://www.mijnpress.nl
 */

function plugin_mass_delete_unused_tags_init() {
	global $current_user, $wpdb;

	// Get tags
	$taxonomy = 'post_tag';
	// Code adapted from http://www.catswhocode.com/blog/wordpress-10-life-saving-sql-queries
	$sql = 'SELECT wt.term_id FROM '.$wpdb->terms.' wt INNER JOIN '.$wpdb->term_taxonomy.' wtt ON wt.term_id=wtt.term_id WHERE wtt.taxonomy=\''.$taxonomy.'\' AND wtt.count=0';
	$all_tags = $wpdb->get_results($sql,ARRAY_A);
	
	// Settings
	$limit = 50;
	$timeout = 4; // For refresh

	// Hash based on userid, userlevel and ip
	wp_get_current_user();
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
	$hash = md5($current_user->ID.$current_user->user_level.$ip);
	$url  = 'plugins.php?page=plugin_mass_delete_unused_tags&hash='.$hash;
	$stop =  false;
	if(count($all_tags) > 0)
	{
		$validated = false;
		if(isset($_POST['plugin_tag_action']) && isset($_POST['plugin_tag_validate']) && $_POST['plugin_tag_validate'] == 'yes')
		{
			check_admin_referer( 'mass-delete-submit' );
			
			$validated = true;
		}
		if(isset($_GET['hash']) && $_GET['hash'] == $hash)
		{
			$validated = true;
		}
		
		if ($validated) {
			$sql_sub = 'SELECT wt.term_id FROM '.$wpdb->terms.' wt INNER JOIN '.$wpdb->term_taxonomy.' wtt ON wt.term_id=wtt.term_id WHERE wtt.taxonomy=\''.$taxonomy.'\' AND wtt.count=0 LIMIT '.$limit;
			$tags = $wpdb->get_results($sql_sub,OBJECT);			
			
			$i = 0;
			echo 'Deleted ids: ';
			foreach($tags as $tag) {
				wp_delete_term($tag->term_id, $taxonomy);
				echo $tag->term_id.', ';
				$i++;
			}

			echo '<br/><br/>Deleted '.$i.' tags in this page load. Please stand by if the page needs refreshing<br/>';

			if($i >= $limit)
			{
				echo '<br/><br/><meta http-equiv="refresh" content="'.$timeout.';url='.$url.'" />';
				echo '<strong><u>Not done yet</u>!</strong><br/><a href="'.$url.'">Refreshing page! Is this taking more then '.(2*$timeout). ' seconds, please click here</a>';

				die();
			}
			else
			{
				echo '<br/>Removed all unused tags';
				$stop =  true;
			}
		}

	}

	if ($all_tags && !$stop) {
		echo ' Found '.count($all_tags) . ' unused tags';
		?>

<h4>By clicking the button you will delete ALL unused terms</h4>
<form action="plugins.php?page=plugin_mass_delete_unused_tags" method="post"><input
	type="radio" name="plugin_tag_validate" id="plugin_tag_validate_no"
	value="no" checked="checked" /><label for="plugin_tag_validate_no">&nbsp;NO!</label><br />

<input type="radio" name="plugin_tag_validate"
	id="plugin_tag_validate_yes" value="yes" /><label
	for="plugin_tag_validate_yes">&nbsp;Yes, delete all unused terms (select me to
proceed)</label><br />
<br />

<br />
Note: Staggered delete of (<?php echo $limit; ?>) terms at a time. Page
will auto refresh untill all unused tags are deleted. <br />
<?php
wp_nonce_field( 'mass-delete-submit' );
?>
<input type="submit" name="plugin_tag_action" value="<?php _e("Delete Terms") ?>" onclick="javascript:return(confirm('<?php _e("Are you sure you want to delete these terms? There is NO undo!")?>'))" />

</form>
<?php
	} else {
		echo '<p>' . __('No tags are unused at the moment.') . '</p>';
	}
}


function plugin_mass_delete_unused_tags_menu() {
	if (is_admin()) {
		add_submenu_page("plugins.php", "Delete all unused tags", "Delete all unused tags", 'manage_options', 'plugin_mass_delete_unused_tags', 'plugin_mass_delete_unused_tags_init');
	}
}

// Admin menu items
add_action( 'admin_menu', 'plugin_mass_delete_unused_tags_menu' );
