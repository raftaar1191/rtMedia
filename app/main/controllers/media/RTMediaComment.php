<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RTMediaComment
 *
 * @author udit
 */
class RTMediaComment {

	var $rtmedia_comment_model;

	public function __construct() {
		$this->rtmedia_comment_model = new RTMediaCommentModel();
	}

	static function comment_nonce_generator( $echo = true ) {
		if ( $echo ) {
			wp_nonce_field( 'rtmedia_comment_nonce','rtmedia_comment_nonce' );
		} else {
			$token = array(
				'action' => 'rtmedia_comment_nonce',
				'nonce' => wp_create_nonce( 'rtmedia_comment_nonce' ),
			);

			return wp_json_encode( $token );
		}
	}

	/**
	 * returns user_id of the current logged in user in wordpress
	 *
	 * @global WP_User $current_user
	 * @return int
	 */
	function get_current_id() {

		global $current_user;

		return $current_user->ID;

	}

	/**
	 * returns user_id of the current logged in user in wordpress
	 *
	 * @global WP_User $current_user
	 * @return string
	 */
	function get_current_author() {

		global $current_user;

		return $current_user->user_login;

	}

	function add( $attr ) {
		global $allowedtags;
       	do_action( 'rtmedia_before_add_comment', $attr );
		$defaults = array(
				'user_id'           => $this->get_current_id(),
				'comment_author'    => $this->get_current_author(),
				'comment_date'      => current_time( 'mysql' ),
		);
		$attr['comment_content'] = rtmedia_wp_kses_of_buddypress( $attr['comment_content'], $allowedtags );
		$params = wp_parse_args( $attr, $defaults );
		$id = $this->rtmedia_comment_model->insert( $params );
		global $rtmedia_points_media_id;
		$rtmedia_points_media_id = rtmedia_id( $params['comment_post_ID'] );
		$params['comment_id'] = $id;

		do_action( 'rtmedia_after_add_comment', $params );

	   	return $id;
	}

	function remove( $id ) {

		do_action( 'rtmedia_before_remove_comment', $id );

		$comment = '';
		if ( ! empty( $id ) ) {
			$comment = get_comment( $id );
		}

		if ( isset( $comment->comment_post_ID ) && isset( $comment->user_id ) ) {
			$model = new RTMediaModel();
			//get the current media from the comment_post_ID
			$media  = $model->get( array( 'media_id' => $comment->comment_post_ID ) );
			// if user is comment creator, or media uploader or admin, allow to delete
			if ( isset( $media[0]->media_author ) && ( is_rt_admin() || intval( $comment->user_id ) === get_current_user_id() || intval( $media[0]->media_author ) === get_current_user_id() ) ) {
				$comment_deleted = $this->rtmedia_comment_model->delete( $id );
				do_action( 'rtmedia_after_remove_comment', $id );

				return $comment_deleted;
			}
		}
		return false;
	}


	/**
	 * Helper function to check whether the shortcode should be rendered or not
	 *
	 * @return bool
	 */
	static function display_allowed() {
		global $rtmedia_query;
		$flag = ( ! (  is_home() || is_post_type_archive() || is_author()))
		&& is_user_logged_in()
		&& (is_rtmedia_upload_music_enabled() || is_rtmedia_upload_photo_enabled() || is_rtmedia_upload_video_enabled())
		 //added condition to disable upload when media is disabled in profile/group but user visits media tab
		&& ( ( isset( $rtmedia_query->is_upload_shortcode ) && true === $rtmedia_query->is_upload_shortcode )
				|| ( is_rtmedia_bp_profile() && is_rtmedia_profile_media_enable() )
				||  (is_rtmedia_bp_group() && is_rtmedia_group_media_enable()) );
		$flag = apply_filters( 'before_rtmedia_uploader_display', $flag );
		return $flag;
	}


	/**
	 * Render the uploader shortcode and attach the uploader panel
	 *
	 * @param mixed $attr
	 *
	 * @return string|void
	 */
	static function pre_comment_render( $attr ) {
		ob_start();
		if ( rtmedia_is_uploader_view_allowed( true, 'comment_media' ) ) {

			if ( isset( $attr['context'] ) && ! empty( $attr['context'] ) ) {
				$attr['context'] = 'profile';
			}

			$attr = apply_filters( 'rtmedia_media_comment_attributes', $attr );

			if ( self::display_allowed() ) {
				if ( ! _device_can_upload() ) {
					echo '<p>' . esc_html_e( 'The web browser on your device cannot be used to upload files.', 'buddypress-media' ) . '</p>';
					return;
				}
				$template = 'uploader';
				if( isset( $attr['upload_parent_id_type'] )  && isset( $attr['upload_parent_id'] ) ){
					$template = 'comment-media';
				}
				$view = new RTMediaUploadView( $attr );
				echo $view->render( $template );

			}
		} else {
			echo "<div class='rtmedia-upload-not-allowed'>" . wp_kses( apply_filters( 'rtmedia_upload_not_allowed_message', esc_html__( 'You are not allowed to upload/attach media.','buddypress-media' ), 'uploader_shortcode' ), RTMediaUpload::$wp_kses_allowed_tags ) . '</div>';
		}
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}


	/*
	 * add media upload in add comment section
	 * @param int $id ( media id or activity id )
	 * @param string $type ( media or activity )
	 *
	 * @return HTML
	*/
	static function add_uplaod_media_button( $id, $type, $context ){
		$attr = array(
			'comment' => true,
			'privacy' => 0,
			'upload_parent_id' => $id,
			'upload_parent_id_type' => $type,
			'upload_parent_id_context' => $context
		 );
		return RTMediaComment::pre_comment_render( $attr );
	}
}
