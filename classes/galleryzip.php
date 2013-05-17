<?php
class GalleryZip
{
	const SESSION_KEY = 'gallery-zip';

	// one of a predefined sizes (thumbnail, medium, large or full) or a
	// custom size registered with add_image_size
	const IMAGE_SIZE  = 'full';

	private static $instance = null;

	public static $images = array();

	public static function get_instance() {
		if ( ! session_id() )
			session_start();

		if ( null === self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

	private final function __construct() {
		if ( ! session_id() )
			session_start();

		remove_shortcode( 'gallery' );
		add_shortcode( 'gallery', array( __CLASS__, 'gallery_zip_shortcode' ) );
	}

	public static function gallery_zip_shortcode( $atts ) {
		$post  = get_post();

		require_once ABSPATH . 'wp-includes/media.php';
		self::get_gallery_images_from_shortcode( $post->ID, $atts );
		$output = gallery_shortcode( $post->ID, $atts );

		$gallery_id = ( isset( self::$images[$post->ID] ) ) ?
			count( self::$images[$post->ID] ) - 1 : 0;

		$output .= sprintf( '<div><a href="#" gallery-id="%d" post-id="%d" class="gallery-zip">%s</a></div>', $gallery_id, $post->ID, __( 'Get as Zip' ) );

		return $output;
	}

	protected static function get_gallery_images_from_shortcode( $post_id, $atts ) {
		// use the post ID if the attribute 'ids' is not set or empty
		$id = ( ! isset( $atts['ids'] ) || empty( $atts['ids'] ) ) ?
			(int) $post_id : $atts['ids'];

		$exclude = ( isset( $atts['exclude'] ) && ! empty( $atts['exclude'] ) ) ?
			$atts['exclude'] : '';

		if ( ! isset( self::$images[$post_id] ) || ! is_array( self::$images[$post_id] ) )
			self::$images[$post_id] = array();

		$images = self::get_gallery_images( $id, $exclude );

		array_push( self::$images[$post_id], $images );

		return $images;
	}

	protected static function get_gallery_images( $id, $exclude ) {
		$images     = array();
		$query_args = array(
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => -1,
		);

		// handle gallery WP3.5+
		// if $id contains an comma, it is a list of post IDs
		if ( false !== strpos( $id, ',' ) ) {
			$query_args['include'] = $id;
		} elseif ( ! empty( $exclude ) ) {
			// handle excluding posts
			$query_args['post_parent'] = $id;
			$query_args['exclude']     = $exclude;
		} else {
			// handle gallery before WP3.5
			$query_args['post_parent'] = $id;
		}

		$attachments = get_posts( $query_args );

		$img_sizes = array_merge( array( 'full' ), get_intermediate_image_sizes() );

		$img_size = ( in_array( self::IMAGE_SIZE, $img_sizes ) ) ?
				self::IMAGE_SIZE : 'full';

		foreach ( $attachments as $key => $post ) {
			$img = wp_get_attachment_image_src( $post->ID, $img_size, false, false );
			$images[] = sprintf( '%s/%s', dirname( get_attached_file( $post->ID ) ), basename( $img[0] ) );
		}

		return $images;
	}

	public static function save_in_session() {
		$_SESSION[self::SESSION_KEY] = self::$images;
		session_write_close();
	}

	public static function get_images_ajax_callback( $post_id = 0, $gallery_id = 0 ) {
		if ( ! session_id() )
			session_start();

		if ( empty( self::$images ) )
			self::$images = $_SESSION[self::SESSION_KEY];

		return ( isset( self::$images[$post_id][$gallery_id] ) ) ?
			self::$images[$post_id][$gallery_id] : array();
	}

}