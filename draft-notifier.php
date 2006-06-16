<?php
/*
Plugin Name: Draft Notifier
Plugin URI: http://blogwaffe.com/2005/04/22/275/
Description: Draft Notifier sends a notification email to your blog's admin address when a post is written by a Contributor or when such a post is edited.
Version: 1.1
Author: Michael D. Adams
Author URI: http://blogwaffe.com/
*/

/*
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt
*/

function mda_draft_notifier( $post_ID, $new_post = true ) {
	global $wpdb, $user_ID;

	$draft = get_post( $post_ID );
	$author = new WP_User( $draft->post_author );
	if ( $author->has_cap( 'publish_posts' ) )
		return $post_ID;

	//the editor is probably the original author, we already have that info
	//if the editor is not the original author, we need to grab new info
	if ( $user_ID == $author->id )
		$editor =& $author;
	else
		$editor = new WP_User( $user_ID );

	//Don't send the notification if it's not a draft
	if ( 'draft' != $draft->post_status )
		return $post_ID;

	$blogname = get_settings( 'blogname' );
	$blog_url = get_settings( 'siteurl' );
	$draft_note_mess  = "Draft written by {$author->data->user_login} ({$author->data->user_email})";
	if ( $editor->id != $author->id )
		$draft_note_mess .=  " and edited by {$editor->data->user_login} ({$editor->data->user_email})";

	//if this is the first time the post has been saved we only have to worry about the author (not any subsequent editor)
	if ( $new_post === true ) {
		$draft_note_from = "From: {$author->data->user_login} <{$author->data->user_email}>\n";
		$draft_note_subj = "[$blogname] New Draft: $draft->post_title";
	//if this is not the first time the post has been saved, then lets grab some info about who's editing
	} else {
		$draft_note_from = "From: {$editor->data->user_login} <{$editor->data->user_email}>\n";
		$draft_note_subj = "[$blogname] Edited Draft: $draft->post_title";
	}
	$draft_note_mess .= "\n\r\nTITLE: " . stripslashes($draft->post_title) . "\n\r\n";
	$draft_note_mess .= stripslashes($draft->post_content) . "\n\r\n";
	$draft_note_mess .= "To edit or approve this draft visit $blog_url/wp-admin/post.php?action=edit&post=$draft->ID";
	@wp_mail( get_settings( 'admin_email' ), $draft_note_subj, $draft_note_mess, $draft_note_from );
	return $post_ID;
}

//Wrapper for mda_postman() on save_post
function mda_dn_new_draft( $post_ID ) {
	mda_draft_notifier( $post_ID, $new_post = true );
	return $post_ID;
}

//Wrapper for mda_postman() on edit_post
function mda_dn_edited_draft( $post_ID ) {
	mda_draft_notifier( $post_ID, $new_post = false );
	remove_action( 'save_post', 'mda_dn_new_draft' );
	return $post_ID;
}

function mda_dn_insert_post( $post_ID ) {
	add_action( 'save_post', 'mda_dn_new_draft' );
}

add_action( 'edit_post', 'mda_dn_edited_draft' );
add_action( 'save_post', 'mda_dn_new_draft' );
add_action( 'wp_insert_post', 'mda_dn_insert_post' );
?>
