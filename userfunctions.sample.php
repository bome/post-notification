<?php
#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------


/**
 * About this file:
 * This is a sample for userfunctions which can be used in PN. You allways have to return
 * an array of the type: search=>replacement. Although you can use anything for replacement
 * it is recomended to use '@@searchstring'.
 * It is checkt for each function seperately. So you may want to comment or delete an unused
 * function.
 * To use this file you have to copy it to userfunctions.php
 */

/**
 * This function is called once per Post that is sent out. If the Post is sent out several times
 * then it will also be called several times. This happens if you have 20 subscribers but only send
 * 10 Mails in a batch.
 *
 * @param int $post_id the id of the Post
 *
 * @return array An array of replacements
 */
function post_notification_uf_perPost( $post_id ) {
	//This is the easiest way to create array with a low possibility of errors
	$replacements                 = array();
	$cats               = wp_get_post_categories( $post_id );
	$tags               = wp_get_post_tags( $post_id );
	$replacements['@@categories'] = wp_get_post_categories( $post_id );

	return $replacements;
}

/**
 * This function is called for every email sent out. It creates an array used for replacements.
 *
 * @param int $post_id The id of the Post
 * @param string $emailAddress The email address this Mail will be sent to.
 *
 * @return array An array of replacements
 */
function createReplacementsForEmail( $post_id, $emailAddress ) {
	$replacements = [];

	$replacements['@@lucky'] = ( mt_rand( 0, 1000 ) == 50 )
		? "Today is your lucky day"
		: "";

	return $replacements;
}
