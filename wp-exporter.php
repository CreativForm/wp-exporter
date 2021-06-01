<?php
/**
 * @link              http://cfgeoplugin.com/
 * @since             1.0.0
 * @package           WP_Exporter
 *
 * @wordpress-plugin
 * Plugin Name:       WP Exporter
 * Plugin URI:        https://linkedin.com/in/ivijanstefanstipic
 * Description:       Export posts, taxonomies and metaboxes from WordPress in WordPress eXtended RSS file
 * Version:           1.0.0
 * Author:            Ivijan-Stefan Stipic
 * Author URI:        https://linkedin.com/in/ivijanstefanstipic
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-export
 * Domain Path:       /languages
 * Network:           true
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
 
// If someone try to called this file directly via URL, abort.
if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
if ( ! defined( 'ABSPATH' ) ) { exit; }

if(!class_exists('WP_Exporter')) :
	class WP_Exporter
	{
		function __construct()
		{	
			/* Load plugin functionality
			===========================================================*/
			add_action ('admin_menu', function () {
				add_management_page(__('Advanced Export', 'wp-export'), __('Advanced Export', 'wp-export'), 'install_plugins', 'wp-export', array($this,'page'), '');
				add_action('admin_footer', array($this, 'javascript'), 999);
				
			});
			add_action('wp_ajax_wp-advanced-export', array($this, 'ajax'));
			
			/* Download file
			===========================================================*/
			if(isset($_POST['export_post_type']))
			{
				$post_type = (!empty($_POST['export_post_type']) 			? $_POST['export_post_type'] 		: 'post');
				$post_status = (!empty($_POST['export_post_status']) 		? $_POST['export_post_status'] 		: 'any');
				$taxonomy = (!empty($_POST['export_taxonomies']) 			? $_POST['export_taxonomies'] 		: 'category');
				$taxonomy_slug = (!empty($_POST['export_taxonomy_name']) 	? $_POST['export_taxonomy_name'] 	: NULL);
				
				$this->xml = $this->generate($post_type, $post_status, $taxonomy, $taxonomy_slug);
				
				if(is_wp_error($this->xml))
				{
					add_action( 'admin_notices', array($this, 'error_notice') );
				}
				else
				{
					
					$filename = strtr(sanitize_title(get_bloginfo('name')), '-', '_') . '_' . date('d-m-y-h-i-s') . '.xml';

					ob_end_clean();
					if(function_exists('header_remove')) header_remove();
					
					$now = gmdate('D, d M Y H:i:s');
					header('Expires: Tue, 03 Jul 2001 06:00:00 GMT');
					header('Cache-Control: max-age=0, post-check=0, pre-check=0, no-cache, must-revalidate, proxy-revalidate');
					header("Last-Modified: {$now} GMT");
					header('Pragma: public');
					header('Content-Description: File Transfer');
					header('Content-Encoding: UTF-8');
					header('Content-type: "text/xml"; charset=UTF-8');
					header('Content-Disposition: attachment; filename=' . $filename);
					header('Content-Transfer-Encoding: binary');
					echo $this->xml;
					exit();
				}
			}
			
		}
		
		/* Error notice
		===========================================================*/
		function error_notice (){
			$class = 'notice notice-error error';
			$message = $this->xml->get_error_message();

			printf( '<div id="message" class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
		}
		
		/* Tools Page
		===========================================================*/
		function page()
		{
?>
<div class="wrap">
	<h1><?php _e('Advanced Export','wp-export'); ?></h1>
    <p><?php _e('When you click the button below WordPress will create an XML file for you to save to your computer.','wp-export'); ?></p>
    <p><?php _e('This format, which we call WordPress eXtended RSS or WXR, will contain your posts, pages, comments, custom fields, categories, and tags.','wp-export'); ?></p>
    <p><?php _e('Once you’ve saved the download file, you can use the Import function in another WordPress installation to import the content from this site.','wp-export'); ?></p>
    <h3><?php _e('Choose what to export','wp-export'); ?></h3>
    <form method="post" action="<?php echo admin_url('tools.php?page=wp-export'); ?>" id="wp-export">
	<table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e('Post type:','wp-export'); ?></th>
            <td>
            	<select name="export_post_type">
                	<option value="" selected>— <?php _e('Select','wp-export'); ?> —</option>
            	<?php 
                	foreach(get_post_types(array(
						'public'   => true,
						'_builtin' => false
					), 'objects', 'or') as $post_type => $post_type_object) :
				?>
                	<option value="<?php echo $post_type; ?>"><?php echo $post_type_object->label; ?></option>
                <?php endforeach; ?>
            	</select>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row"><?php _e('Post Status:','wp-export'); ?></th>
            <td>
                <select name="export_post_status">
                    <option value="all" selected><?php _e('Any','wp-export'); ?></option>
                    <option value="publish"><?php _e('Published','wp-export'); ?></option>
                    <option value="future"><?php _e('Scheduled','wp-export'); ?></option>
                    <option value="draft"><?php _e('Draft','wp-export'); ?></option>
                    <option value="pending"><?php _e('Pending','wp-export'); ?></option>
                    <option value="private"><?php _e('Private','wp-export'); ?></option>
                    <option value="trash"><?php _e('Trash','wp-export'); ?></option>
                </select>
            </td>
        </tr>
        
        <tr valign="top" id="show_export_taxonomies" style="display:none;">
            <th scope="row"><?php _e('Taxonomy:','wp-export'); ?></th>
            <td id="export_taxonomies">
            	<?php _e('( none )', 'wp-export'); ?>
        	</td>
        </tr>
        
        <tr valign="top" id="show_taxonomy_name" style="display:none;">
            <th scope="row"><?php _e('Taxonomy name:','wp-export'); ?></th>
            <td id="export_taxonomy_name">
            	<?php _e('( none )', 'wp-export'); ?>
            </td>
        </tr>
    </table>
    
    <?php submit_button(__('Download Export File','wp-export')); ?>
    </form>
</div>
		<?php }
		
		/* JavaScript for the Tools page
		===========================================================*/
		public function javascript()
		{
			$nonce = wp_create_nonce( 'wp-export-ajax-nonce' );
		?>
<script>
(function($){
	$(document).on('select change', 'select[name^="export_post_type"]', function(){
		var $this = $(this),
			$val = $this.val();
		
		$('#show_export_taxonomies').show();
		$('#export_taxonomies').html('<?php _e('Loading...', 'wp-export'); ?>');
		$('#show_taxonomy_name').hide();
			
		$.ajax({
			method : 'POST',
			data: {
				'wp-export-nonce' : '<?php echo $nonce; ?>',
				'export-post-type' : $val,
				'export-type' : 'get-taxonomies'
			},
			cache : false,
			async : true,
			url: '<?php echo admin_url('admin-ajax.php?action=wp-advanced-export'); ?>', 
		}).done(function(data){
			$('#export_taxonomies').html(data);
			if($val.length <= 0)
			{
				$('#show_taxonomy_name, #show_export_taxonomies').hide();
			}
		});
	}).on('select change', 'select[name^="export_taxonomies"]', function(){
		var $this = $(this),
			$val = $this.val();
			
		$('#show_taxonomy_name').show();
		$('#export_taxonomy_name').html('<?php _e('Loading...', 'wp-export'); ?>');
		
		$.ajax({
			method : 'POST',
			data: {
				'wp-export-nonce' : '<?php echo $nonce; ?>',
				'taxonomy' : $val,
				'export-type' : 'get-taxonomy-names'
			},
			cache : false,
			async : true,
			url: '<?php echo admin_url('admin-ajax.php?action=wp-advanced-export'); ?>', 
		}).done(function(data){
			$('#export_taxonomy_name').html(data);
			if($val.length <= 0)
			{
				$('#show_taxonomy_name').hide();
			}
			else
			{
				$('#show_taxonomy_name').show();
			}
		});
	});
}(jQuery || window.jQuery))
</script>
		<?php }
		
		/* Ajax functions
		===========================================================*/
		public function ajax()
		{
			if(wp_verify_nonce( $_POST['wp-export-nonce'], 'wp-export-ajax-nonce' ) !== false)
			{
				if($_POST['export-type'] == 'get-taxonomies')
				{ 
					if($get_object_taxonomies = get_object_taxonomies($_POST['export-post-type'], 'objects')) : 
						?>
						<select name="export_taxonomies">
							<option value="" selected>— <?php _e('Select','wp-export'); ?> —</option>
						<?php 
							foreach($get_object_taxonomies as $taxonomy_type => $taxonomy_object) :
						?>
							<option value="<?php echo $taxonomy_type; ?>"><?php echo $taxonomy_object->label; ?></option>
						<?php endforeach; ?>
						</select>
						<?php
					else :
						_e('( none )', 'wp-export');
					endif;
				}
				else if($_POST['export-type'] == 'get-taxonomy-names')
				{
					if($terms = get_terms(array(
						'taxonomy' => $_POST['taxonomy']
					))) :
						if(empty($terms)): _e('( none )', 'wp-export'); else :
						if(is_wp_error($terms)){ echo $terms->get_error_message(); wp_die();}
						?>
						<select name="export_taxonomy_name">
							<option value="" selected><?php _e('All','wp-export'); ?></option>
						<?php 
							foreach($terms as $i => $term) :
						?>
							<option value="<?php echo $term->slug; ?>"><?php echo $term->name; ?></option>
						<?php endforeach; ?>
						</select>
						<?php
						endif;
					else :
						_e('( none )', 'wp-export');
					endif;
				}
			}
			wp_die();
		}
		
		/* Generate XML by rules
		===========================================================*/
		private function generate($post_type = 'post', $post_status = 'any', $taxonomy = 'category', $taxonomy_slug=NULL)
		{
			global $wp_rewrite, $wp_version;
			// Load pluggable functions.
			if(!function_exists('get_userdata'))
			{
				require( ABSPATH . WPINC . '/pluggable.php' );
				require( ABSPATH . WPINC . '/pluggable-deprecated.php' );
			}
			// Load XML template
			$template = $this->get_template();
			
			// Save global site informations
			$template = strtr($template, array(
				'{FULL-DATE-TIME}'		=> date('Y-m-d H:i'),
				'{SITE-TITLE}'			=> get_bloginfo('name'),
				'{SITE-URL}'			=> get_bloginfo('url'),
				'{SITE-DESCRIPTION}'	=> get_bloginfo('description'),
				'{SITE-PUBLISH-DATE}'	=> date('D, j M Y H:i:s O'),
				'{SITE-LANGUAGE}'		=> get_bloginfo('language'),
				'{WP-VERSION}'			=> get_bloginfo('version')
			));
			
			// Get loop templates
			$template_category = $this->get_loop($template, 'LOOP-CATEGORY-TERM');
			$template_author = $this->get_loop($template, 'LOOP-AUTHOR');
			$template_term = $this->get_loop($template, 'LOOP-TERM');
			$template_item = $this->get_loop($template, 'LOOP-ITEM');
			$template_tag = $this->get_loop($template, 'LOOP-TAG');
			
			$terms = $authors = $items = $tags = $terms_category = array();
			
			if($taxonomy_slug)
			{
				$categories=get_term_by('slug', $taxonomy_slug, $taxonomy);
				if(is_wp_error($categories)) return $categories;
				$categories = array($categories);
			}
			else
			{
				$categories = get_terms(array(
					'taxonomy'		=> $taxonomy
				));
				if(is_wp_error($categories)) return $categories;
			}
			
			foreach( $categories as $category )
			{
				// Save terms to array for later array insertation
				if($category->taxonomy == 'tag' || $category->taxonomy == 'post_tag')
				{
					$tags[$category->term_id]=strtr($template_tag, array(
						'{TERM-ID}'				=> $category->term_id,
						'{TERM-SLUG}'			=> $category->slug,
						'{TERM-NAME}'			=> $category->name,
						'{TERM-DESCRIPTION}'	=> $category->description,
					));
				}
				else if($category->taxonomy == 'category')
				{
					$terms_category[$category->term_id]=strtr($template_category, array(
						'{TERM-ID}'				=> $category->term_id,
						'{TERM-SLUG}'			=> $category->slug,
						'{TERM-PARENT}'			=> (!empty($category->parent) ? (is_numeric($category->parent) ? get_term_by('id', $category->parent, $taxonomy)->slug : $category->parent) : ''),
						'{TERM-NAME}'			=> $category->name,
						'{TERM-DESCRIPTION}'	=> $category->description,
					));
				} else {
					$terms[$category->term_id]=strtr($template_term, array(
						'{TERM-ID}'				=> $category->term_id,
						'{TERM-SLUG}'			=> $category->slug,
						'{TERM-PARENT}'			=> (!empty($category->parent) ? (is_numeric($category->parent) ? get_term_by('id', $category->parent, $taxonomy)->slug : $category->parent) : ''),
						'{TERM-NAME}'			=> $category->name,
						'{TERM-DESCRIPTION}'	=> $category->description,
					));
				}
				
				// Get posts
				if($posts = get_posts(array(
					'numberposts'	=> -1,
					'post_type'		=> $post_type,
					'post_status'	=> $post_status
				)))
				{
					if(is_wp_error($posts)) return $posts;
					
					foreach($posts as $i=>$post)
					{
						// Globals
						$get_post_author = $post->post_author;
						$attachment = '';

						// Avoid same posts
						if(isset($items[$post->ID])) continue;
						setup_postdata( $post );

						// transfer teplate
						$template_item_loop = $template_item;

						// Save authors to array for later array insetration
						if(function_exists('get_userdata'))
						{
							$user_info = get_userdata($post->post_author);
							if(!is_wp_error($user_info) && !empty($user_info))
							{
								$get_post_author = $user_info->user_login;
								
								if(!isset($authors[$post->post_author]))
								{
									$authors[$post->post_author]=strtr($template_author, array(
										'{AUTHOR-ID}'			=> $user_info->ID,
										'{AUTHOR-USERNAME}'		=> $user_info->user_login,
										'{AUTHOR-EMAIL}'		=> $user_info->user_email,
										'{AUTHOR-DISPLAY-NAME}'	=> (method_exists($user_info, 'display_name') ? $user_info->display_name : join(' ', array_filter(array($user_info->first_name, $user_info->last_name)))),
										'{AUTHOR-FIRST-NAME}'	=> $user_info->first_name,
										'{AUTHOR-LAST-NAME}'	=> $user_info->last_name,
									));
								}
							}
						}
 
						// Add categories to loop
						$cats = array();
						$template_item_category = $this->get_loop($template_item_loop, 'LOOP-CATEGORY');
						$wp_get_post_categories = wp_get_post_categories($post->ID);
						if(!is_wp_error($wp_get_post_categories) && !empty($wp_get_post_categories))
						{
							foreach($wp_get_post_categories as $cat)
							{
								$cat = get_category( $cat );
								if(!is_wp_error($cat) && !empty($cat))
								{
									$cats[$cat->term_id]=strtr($template_item_category, array(
										'{CATEGORY-TAXONOMY}'	=> $cat->taxonomy,
										'{CATEGORY-SLUG}'		=> $cat->slug,
										'{CATEGORY-NAME}'		=> $cat->name
									));
								}
							}
						}
					
						// Add tags to loop
						$get_the_tags = get_the_tags($post->ID);
						if(!is_wp_error($get_the_tags) && !empty($get_the_tags))
						{
							foreach($get_the_tags as $t)
							{
								$tags[$t->term_id]=strtr($template_tag, array(
									'{TERM-ID}'				=> $t->term_id,
									'{TERM-SLUG}'			=> $t->slug,
									'{TERM-NAME}'			=> $t->name,
									'{TERM-DESCRIPTION}'	=> $t->description,
								));
								$cats[$t->term_id]=strtr($template_item_category, array(
									'{CATEGORY-TAXONOMY}'	=> $t->taxonomy,
									'{CATEGORY-SLUG}'		=> $t->slug,
									'{CATEGORY-NAME}'		=> $t->name
								));
							}
						}
						
						//Add all other taxonomies to loop
						$get_the_terms = get_the_terms($post->ID, $taxonomy);
						if(!is_wp_error($get_the_terms) && !empty($get_the_terms))
						{
							foreach($get_the_terms as $tr)
							{
								$cats[$tr->term_id]=strtr($template_item_category, array(
									'{CATEGORY-TAXONOMY}'	=> $tr->taxonomy,
									'{CATEGORY-SLUG}'		=> $tr->slug,
									'{CATEGORY-NAME}'		=> $tr->name
								));
							}
						}
						
						$template_item_loop = $this->replace_loop($template_item_loop, 'LOOP-CATEGORY', join("\n", $cats));
						$cats = NULL;

						// Add all postmeta
						$meta = array();
						$template_item_postmeta = $this->get_loop($template_item_loop, 'LOOP-POSTMETA');
						$get_post_meta=get_post_meta($post->ID, '', true);
						if(!is_wp_error($get_post_meta) && !empty($get_post_meta))
						{
							foreach($get_post_meta as $post_meta_key=>$post_meta_val)
							{
								if($post_meta_key == '_thumbnail_id')
								{
									$attachment = wp_get_attachment_url($post_meta_val[0]);
								}
								
								$meta[$post_meta_key]=strtr($template_item_postmeta, array(
									'{POSTMETA-KEY}'	=> $post_meta_key,
									'{POSTMETA-VALUE}'	=> $post_meta_val[0],
								));
							}
						}
						$template_item_loop = $this->replace_loop($template_item_loop, 'LOOP-POSTMETA', join("\n", $meta));
						$meta = NULL;
						
						$wp_rewrite->use_trailing_slashes = NULL;
						
						// Save posts to array for the later insertation
						$items[$post->ID]=strtr($template_item_loop, array(
							'{ITEM-TITLE}'			=> $post->post_title,
							'{ITEM-LINK}'			=> get_permalink($post->ID),
							'{ITEM-DATE-PUBLISH}'	=> date('D, j M Y H:i:s O', strtotime($post->post_date)),
							'{ITEM-AUTHOR}'			=> $get_post_author,
							'{ITEM-PERMIT-LINK}'	=> $post->guid,
							'{ITEM-CONTENT}'		=> $post->post_content,
							'{ITEM-EXCERPT}'		=> $post->post_excerpt,
							'{ITEM-ID}'				=> $post->ID,
							'{ITEM-DESCRIPTION}'	=> '',
							'{ITEM-DATE-EDIT}'		=> $post->post_modified,
							'{ITEM-DATE-ADD}'		=> $post->post_date_gmt,
							'{ITEM-COMMENT-STATUS}'	=> $post->comment_status,
							'{ITEM-PING-STATUS}'	=> $post->ping_status,
							'{ITEM-SLUG}'			=> $post->post_name,
							'{ITEM-STATUS}'			=> $post->post_status,
							'{ITEM-PARENT}'			=> $post->post_parent,
							'{ITEM-ORDER}'			=> $post->menu_order,
							'{ITEM-TYPE}'			=> $post->post_type,
							'{ITEM-PASSWORD}'		=> $post->post_password,
							'{ITEM-STICKY}'			=> (is_sticky($post->ID) ? '1' : '0'),
							'{ITEM-ATTACHMENT-URL}'	=> $attachment
						));
					}
					wp_reset_postdata();				
				}
			}
			
			// Replace category loops
			$template = $this->replace_loop($template, 'LOOP-CATEGORY-TERM', join("\n", $terms_category)); $terms_category = NULL;
			// Replace terms loops
			$template = $this->replace_loop($template, 'LOOP-TERM', join("\n", $terms)); $terms = NULL;
			// Replace tags loops
			$template = $this->replace_loop($template, 'LOOP-TAG', join("\n", $tags)); $tags = NULL;
			// Replace author loops
			$template = $this->replace_loop($template, 'LOOP-AUTHOR', join("\n", $authors)); $authors = NULL;
			// Replace items loops
			$template = $this->replace_loop($template, 'LOOP-ITEM', join("\n", $items)); $items = NULL;
			
			return $template;
		}
		
		/* Get template
		===========================================================*/
		private function get_template()
		{
			return file_get_contents(dirname(__FILE__) . '/xml-template.tpl');
		}
		
		/* Parse loop from the template
		===========================================================*/
		private function get_loop($str, $name)
		{
			if(preg_match('/\{'.$name.'\}(.*?)\{\/'.$name.'\}/s', $str, $match))
			{
				return $match[1];
			}
			return NULL;
		}
		
		/* Replace loop in the template
		===========================================================*/
		private function replace_loop($str, $name, $replace)
		{
			if(preg_match('/\{'.$name.'\}(.*?)\{\/'.$name.'\}/s', $str, $match))
			{
				return str_replace($match[0], $replace, $str);
			}
			return NULL;
		}
	}
	
	/* Initialize plugin
	===========================================================*/
	 
	
	add_action('init', function(){
		if(is_admin() && current_user_can( 'manage_options' ) ) new WP_Exporter();
	}, 999);
endif;