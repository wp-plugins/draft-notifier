<?php
/*
Plugin Name: Draft Notifier
Plugin URI: http://www.blogwaffe.com:8000/2005/04/22/275/
Description: Draft Notifier sends a notification email to your blog's admin address when a post written by a level 1 user is saved or edited.  This provides notification that the level 1 user has a new draft which will need to be approved (published) by a higher level users.
Version: 0.1
Author: mdawaffe
Author URI: http://www.blogwaffe.com:8000/
*/

/*
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt
*/

function mda_draft_notifier($post_ID, $new_post = TRUE) {
	global $wpdb, $user_ID;

	//Bail if it's Admin that's editing.
	if ( 1 == $user_ID )
		return $post_ID;

	//There may be a way to grab this information without a DB query, but this was easy.
	$draft = $wpdb->get_row("SELECT post_author, post_content, post_title, user_login, user_email, user_level 
					FROM $wpdb->posts INNER JOIN $wpdb->users ON post_author = $wpdb->users.ID 
					WHERE $wpdb->posts.ID = $post_ID AND post_status = 'draft' AND user_level < '2'");

	//Don't send the notification if it's not a draft OR if it's a draft of a user with level > 1 (see SQL query)
	if ( !$draft )
		return $post_ID;

	$blogname = get_settings('blogname');
	$draft_note_mess  = "Draft written by $draft->user_login ($draft->user_email)";

	//if this is the first time the post has been saved we only have to worry about the author (not any subsequent editor)
	if ( $new_post === TRUE ) {
		$draft_note_from = "From: $draft->user_login <$draft->user_email>\n";
		$draft_note_subj = "[$blogname] New Draft: $draft->post_title";
		$draft_note_mess .=  "\n\r\n";
	//if this is not the first time the post has been saved, then lets grab some info about who's editing
	} else {
		//the editor is probably the original author, we already have that info
		if ( $user_ID == $draft->post_author ) {
			$editor =& $draft;
		//if the editor is not the original author, we need to grab new info
		} else {
			$editor = $wpdb->get_row("SELECT user_login, user_email FROM $wpdb->users WHERE ID = $user_ID");
		}
		$draft_note_from = "From: $editor->user_login <$editor->user_email>\n";
		$draft_note_subj = "[$blogname] Edited Draft: $draft->post_title";
		$draft_note_mess .=  " and edited by $editor->user_login ($editor->user_email)\n\r\n";
	}
	$draft_note_mess .= 'TITLE:  ' . stripslashes($draft->post_title) . "\n\r\n";
	$draft_note_mess .= stripslashes($draft->post_content);
	@wp_mail(get_settings('admin_email'), $draft_note_subj, $draft_note_mess, $draft_note_from);
	return $post_ID;
}

//Wrapper for mda_postman() on save_post
function mda_new_draft($post_ID) {
	mda_draft_notifier($post_ID, $new_post = TRUE);
	return $post_ID;
}

//Wrapper for mda_postman() on edit_post
function mda_edited_draft($post_ID) {
	mda_draft_notifier($post_ID, $new_post = FALSE);
	return $post_ID;
}

add_action('save_post', 'mda_new_draft');
add_action('edit_post', 'mda_edited_draft');
?>
