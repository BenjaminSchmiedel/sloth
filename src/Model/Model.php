<?php

namespace Sloth\Model;

use Corcel\Model\Attachment;
use Corcel\Model\Post as Corcel;
use PostTypes\PostType;
use Sloth\Field\Image;

class Model extends Corcel {
	protected $names = [];
	protected $options = [];
	protected $labels = [];
	public static $layotter = false;
	public $register = true;

	public function __construct( array $attributes = [] ) {
		if ( $this->postType == null ) {
			$reflection     = new \ReflectionClass( $this );
			$this->postType = strtolower( $reflection->getShortName() );
		}
		if ( $this->icon == null ) {
			$this->icon = 'admin-post';
		}
		if ( is_array( $this->labels ) && count( $this->labels ) ) {
			foreach ( $this->labels as &$label ) {
				$label = __( $label );
			}
		}
		parent::__construct( $attributes );
	}

	public function register() {

		global $wp_post_types;

		if ( ! $this->register ) {
			return false;
		}

		if ( \post_type_exists( $this->getPostType() ) ) {

			$post_type_object = get_post_type_object( $this->getPostType() );
			$this->labels     = array_merge( (array) \get_post_type_labels( $post_type_object ), $this->labels );

			$post_type_object->remove_supports();
			$post_type_object->remove_rewrite_rules();
			$post_type_object->unregister_meta_boxes();
			$post_type_object->remove_hooks();
			$post_type_object->unregister_taxonomies();

			unset( $wp_post_types[ $this->getPostType() ] );

			/**
			 * Fires after a post type was unregistered.
			 *
			 * @param string $post_type Post type identifier.
			 */
			do_action( 'unregistered_post_type', $this->getPostType() );
		}

		$names   = array_merge( $this->names, [ 'name' => $this->getPostType() ] );
		$options = $this->options;
		if ( isset( $this->icon ) ) {
			$options = array_merge( $this->options,
				[ 'menu_icon' => 'dashicons-' . preg_replace( '/^dashicons-/', '', $this->icon ) ] );
		}
		$labels = $this->labels;

		$pt = new PostType( $names, $options, $labels );

		# fix for newer version of jjgrainger/PostTypes
		if ( method_exists( $pt, 'register' ) ) {
			$pt->register();
		}
		if ( method_exists( $pt, 'registerPostType' ) ) {
			$pt->registerPostType();
		}
	}

	/**
	 * @return string
	 */
	public
	function getPostType() {
		return $this->postType;
	}

	public
	function getPermalinkAttribute() {
		return \get_permalink( $this->ID );
	}

	final public function init() {
		// fix post_type
		$object = get_post_type_object( $this->postType );
		foreach ( $this->options as $key => $option ) {
			if ( $object ) {
				$object->{$key} = $option;
			}
		}
	}

	/**
	 * @return string
	 */
	public function getContentAttribute() {
		$post_content = $this->getAttribute( 'post_content' );
		if ( ! is_null( $post_content ) ) {
			$post_content = \apply_filters( 'the_content', $post_content );
		}

		return (string) $post_content;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __isset( $key ) {
		return $this->acf->boolean( $key );
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( function_exists( 'acf_maybe_get_field' ) ) {
			$acf = acf_maybe_get_field( $key, $this->getAttribute( 'ID' ), false );

			if ( $acf && $acf['type'] === 'image' ) {
				$attachement = Attachment::find( parent::__get( $key ) );

				return new Image( $attachement->url );
			}
		}

		$value = parent::__get( $key );

		return $value;
	}

	public function toArray() {
		$array = parent::toArray();

		foreach ( $this->getMutatedAttributes() as $key ) {
			if ( ! array_key_exists( $key, $array ) ) {
				$array[ $key ] = $this->{$key};
			}
		}
		if ( isset( $this->hidden ) && is_array( $this->hidden ) ) {
			foreach ( $this->hidden as $k ) {
				unset( $array[ $k ] );
			}
		}

		return $array;
	}
}
