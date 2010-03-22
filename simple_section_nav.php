<?php
/**
 Plugin Name: Simple Section Navigation Widget
 Plugin URI: http://www.cmurrayconsulting.com/software/wordpress-simple-section-navigation/
 Description: Adds a <strong>widget</strong> for <strong>section (or top level page) based navigation</strong>... essential for <strong>CMS</strong> implementations! The <strong>title of the widget is the top level page</strong> within the current page hierarchy. Shows all page siblings (except on the top level page), all parents and grandparents (and higher), the siblings of all parents and grandparents (up to top level page), and any immediate children of the current page. Can also be called by a function inside template files. May <strong>exclude any pages or sections</strong>. Uses standard WordPress navigation classes for easy styling. 
 Version: 2.0.1
 Author: Jacob M Goldman (C. Murray Consulting)
 Author URI: http://www.cmurrayconsulting.com

    Plugin: Copyright 2009 C. Murray Consulting  (email : jake@cmurrayconsulting.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * fld_checked_output() will return string 'checked="checked"' if evaluates true, else returns false
 * 
 * @param mixed $fldValue is the value to evaulate if true; ideally boolean 
 * @return string checked='checked' or false
 */
function fld_checked_output($fldValue = false) {
	if (!$fldValue) return false;
	echo ' checked="checked" ';
	return true; 
}

class SimpleSectionNav extends WP_Widget
{
	function SimpleSectionNav() {
		$widget_ops = array('classname' => 'simple-section-nav', 'description' => __( "Shows page ancestory (parents, grandparents, etc), siblings of ancestory and current page, and immediate children of the current page beneath the current top level page.") );
		$this->WP_Widget('simple-section-nav', __('Simple Section Navigation'), $widget_ops);
	}

    function widget($args, $instance) {
		extract($args);
		
		if (is_front_page() && !$instance['show_on_home']) return false;	//if we're on the home page and we haven't chosen to show this anyways, leave
  		if (is_search() || is_404()) return false; //doesn't apply to search or 404 page
		
		if (is_page()) {
			global $post;	//make the post global so we can talk to it in a widget or sidebar
			_get_post_ancestors($post);   //workaround for occassional problems
		} else {
			$post_page = get_option("page_for_posts");
			if ($post_page) $post = get_page($post_page); //treat the posts page as the current page if applicable
			elseif ($instance['show_on_home']) $sub_front_page = true;	//if want to show on home, and home is the posts page
			else return false;
		}
		
		/*
		//unnecessary now with defaults?
		if (!$sortby = $instance['sort_by']) $sortby = 'menu_order';
		*/
		
		if (is_front_page() || isset($sub_front_page)) {
			echo $before_widget;  
			echo $before_title.get_bloginfo('name').$after_title;
			echo "<ul>";
			wp_list_pages(array('title_li'=>'', 'depth'=>1, 'sort_column'=>$instance['sort_by'], 'exclude'=>$instance['exclude']));
			echo "</ul>";  
			echo $after_widget;
			
			return true; 
	  	}
		
		//get the list of excluded pages, and add a comma to the end so we can precisely search for matching page id later
		$excluded = explode(',', $instance['exclude']);
		//do not display widget if this page is in the excluded list, and user choose to not show section navigation for excluded pages 
		if (in_array($post->ID,$excluded) && $instance['hide_on_excluded']) return false;
		
		$post_ancestors = (isset($post->ancestors)) ? $post->ancestors : get_post_ancestors($post); //get the current page's ancestors either from existing value or by executing function
		$top_page = $post_ancestors ? end($post_ancestors) : $post->ID; //get the top page id
		if (in_array($top_page,$excluded)) return false; //if the top level page is in the excluded list, cancel function
		
		//initialize default variables
		$pagelist = "";
		$thedepth = 0;
		
		if(!$instance['show_all']) {	
			//exclude pages not in direct hierarchy
			foreach ($post_ancestors as $theid) {
				$pageset = get_pages(array('child_of'=>$theid, 'parent'=>$theid));
				foreach ($pageset as $apage) {
				 	if(!in_array($apage->ID,$post_ancestors) && $apage->ID != $post->ID) {
						$excludeset = get_pages(array('child_of'=>$apage->ID, 'parent'=>$apage->ID));
						foreach ($excludeset as $expage) $pagelist = $pagelist.$expage->ID.",";
					}
				}
			}
			
			$thedepth = count($post_ancestors)+1; //prevents improper grandchildren from showing
		}		
		
		$children = wp_list_pages(array('title_li'=>'', 'echo'=>0, 'depth'=>$thedepth, 'child_of'=>$top_page, 'sort_column'=>$instance['sort_by'], 'exclude'=>($pagelist.$instance['exclude'])));	//get the list of pages, including only those in our page list
		if(!$children && !$instance['show_empty']) return false; 	//if there are no pages in this section, and use hasnt chosen to display widget anyways, leave the function
		
		$sect_title = get_the_title($top_page);
		if ($instance['a_heading']) {
			$headclass = ($post->ID == $top_page) ? "current_page_item" : "current_page_ancestor";
			$sect_title = '<a href="'.get_permalink($top_page).'" id="toppage-'.$top_page.'" class="'.$headclass.'">'.$sect_title.'</a>';	
		}
	  	
		echo $before_widget;  
		echo $before_title.$sect_title.$after_title;
		echo "<ul>";  
		echo $children;
		echo "</ul>";  
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['show_all'] = ($new_instance['show_all']) ? true : false;
		$instance['exclude'] = str_replace(" ","",$new_instance['exclude']); //remove spaces from list
		$instance['hide_on_excluded'] = ($new_instance['hide_on_excluded']) ? true : false;
		$instance['show_on_home'] = ($new_instance['show_on_home']) ? true : false;
		$instance['show_empty'] = ($new_instance['show_empty']) ? true : false;
		$instance['sort_by'] = $new_instance['sort_by'];
		$instance['a_heading'] = ($new_instance['a_heading']) ? true : false;
		return $instance;
	}

	function form($instance){
		$instance = wp_parse_args((array) $instance, array('show_all' => false, 'exclude' => '', 'hide_on_excluded' => true, 'show_on_home' => false, 'show_empty' => false, 'sort_by' => 'menu_order', 'a_heading' => false)); //defaults
	?>
		<p>
			<label for="<?php echo $this->get_field_id('sort_by'); ?>"><?php echo __('Sort pages by:'); ?></label>
			<select name="<?php echo $this->get_field_name('sort_by'); ?>" id="<?php echo $this->get_field_id('sort_by'); ?>" class="widefat">
			<?php
				$sort_by_opts = array('menu_order' => 'Page order', 'post_title' => 'Page title', 'ID' => 'Page ID');
				foreach($sort_by_opts as $key => $sort_opt) {
					echo '<option value="'.$key.'"';
					if ($instance['sort_by'] == $key) echo ' selected="selected"';
					echo ">$sort_opt</option>\n";
				}
			?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('exclude'); ?>"><?php echo __('Exclude:'); ?></label> 
			<input type="text" id="<?php echo $this->get_field_id('exclude'); ?>" name="<?php echo $this->get_field_name('exclude'); ?>" value="<?php echo esc_html($instance['exclude']); ?>" size="7" class="widefat" /><br />
			<small>Page IDs, separated by commas.</small>			
		</p>
		<p> 
			<input type="checkbox" id="<?php echo $this->get_field_id('show_on_home'); ?>" name="<?php echo $this->get_field_name('show_on_home'); ?>"<?php fld_checked_output($instance['show_on_home']); ?> />
			<label for="<?php echo $this->get_field_id('show_on_home'); ?>"><?php echo __('Show on home page'); ?></label>
			<br /> 
			<input type="checkbox" id="<?php echo $this->get_field_id('a_heading'); ?>" name="<?php echo $this->get_field_name('a_heading'); ?>"<?php fld_checked_output($instance['a_heading']); ?>/>
			<label for="<?php echo $this->get_field_id('a_heading'); ?>"><?php echo __('Link heading (top level page)'); ?></label>
			<br />
			<input type="checkbox" id="<?php echo $this->get_field_id('show_all'); ?>" name="<?php echo $this->get_field_name('show_all'); ?>"<?php fld_checked_output($instance['show_all']); ?>/>
			<label for="<?php echo $this->get_field_id('show_all'); ?>"><?php echo __('Show all pages in section'); ?></label>
			<br />
			<input type="checkbox" id="<?php echo $this->get_field_id('show_empty'); ?>" name="<?php echo $this->get_field_name('show_empty'); ?>"<?php fld_checked_output($instance['show_empty']); ?>/>
			<label for="<?php echo $this->get_field_id('show_empty'); ?>"><?php echo __('Output even if empty section'); ?></label>
			<br />
			<input type="checkbox" id="<?php echo $this->get_field_id('hide_on_excluded'); ?>" name="<?php echo $this->get_field_name('hide_on_excluded'); ?>"<?php fld_checked_output($instance['hide_on_excluded']); ?>/>
			<label for="<?php echo $this->get_field_id('hide_on_excluded'); ?>"><?php echo __('No nav on excluded pages'); ?></label> 			
		</p>
		<p><small><a href="http://www.cmurrayconsulting.com/software/wordpress-simple-section-navigation/" target="_blank">Help &amp; Support</a></small></p>
	<?php
	}
}

add_action('widgets_init', create_function('', 'return register_widget("SimpleSectionNav");'));

/**
 * Display section based navigation
 * 
 * Arguments include: 'show_all' (boolean), 'exclude' (comma delimited list of page IDs),
 * 'show_on_home' (boolean), 'show_empty' (boolean), sort_by (any valid page sort string),
 * 'a_heading' (boolean), 'before_widget' (string), 'after_widget' (strong)
 *
 * @param array|string $args Optional. Override default arguments.
 * @param NULL deprecated - so pre 2.0 implementations don't break site 
 * @return string HTML content, if not displaying.
 */
function simple_section_nav($args='',$deprecated=NULL) {
	if (!is_null($deprecated)) {
		echo 'The section navigation has been upgrade from 1.x to 2.0; this template needs to be updated to reflect major changes to the plug-in.';
		return false;
	}
	$args = wp_parse_args($args, array('show_all' => false, 'exclude' => '', 'hide_on_excluded' => true, 'show_on_home' => false, 'show_empty' => false, 'sort_by' => 'menu_order', 'a_heading' => false, 'before_widget'=>'<div>','after_widget'=>'</div>', 'before_title'=>'<h2 class="widgettitle">', 'after_title'=>'</h2>')); //defaults
	the_widget('SimpleSectionNav',$args,array('before_widget'=>$args['before_widget'],'after_widget'=>$args['after_widget'],'before_title'=>$args['before_title'],'after_title'=>$args['after_title']));
}

//********************//
//action link for help//
//********************//

add_filter('plugin_action_links', 'simple_section_nav_actlinks', 10, 2);

function simple_section_nav_actlinks($links, $file) {
	$thisFile = basename(__FILE__);
    if (basename($file) == $thisFile) {
        $l = '<a href="http://www.cmurrayconsulting.com/software/wordpress-simple-section-navigation/" target="_blank">Help & Support</a>';
        array_unshift($links, $l);
    }
    return $links;
}

//********************//
//upgrade from pre 2.0//
//********************//

function simple_section_nav_activate() 
{
	if (get_option('ssn_sortby') === false) return false;	//if not upgrading, leave
	
	$show_all = (get_option('ssn_show_all')) ? 1 : 0;
	$exclude =  str_replace(" ","",get_option('ssn_exclude'));
	$hide_on_excluded = (get_option('ssn_hide_on_excluded')) ? 1 : 0;
	$show_on_home = (get_option('ssn_show_on_home')) ? 1 : 0;
	$show_empty = (get_option('ssn_show_empty')) ? 1 : 0;
	$a_heading = (get_option('ssn_a_heading')) ? 1 : 0;
	
	$settings = array('show_all'=>$show_all, 'exclude'=>$exclude, 'hide_on_excluded'=>$hide_on_excluded,'show_on_home'=>$show_on_home,'show_empty'=>$show_empty,'sort_by'=>get_option('ssn_sortby'),'a_heading'=>$a_heading);
	wp_convert_widget_settings('simple-section-nav','widget_simple-section-nav',$settings);
	/*	
	//delete old settings
	//will leave for a future update just in case people want to roll back
	delete_option('ssn_show_all');
	delete_option('ssn_exclude');
	delete_option('ssn_hide_on_excluded');
	delete_option('ssn_show_on_home');
	delete_option('ssn_show_empty');
	delete_option('ssn_sortby');
	delete_option('ssn_a_heading');
	*/
}
register_activation_hook(__FILE__, 'simple_section_nav_activate');
?>