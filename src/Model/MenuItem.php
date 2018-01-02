<?php

namespace Sloth\Model;

use Illuminate\Support\Arr;
use Corcel\Model\Page;
use Corcel\Model\CustomLink;
use Corcel\Model\Taxonomy;
use Corcel\Model\Term;

/**
 * Class MenuItem
 *
 * @package Corcel\Model
 * @author  Junior Grossi <juniorgro@gmail.com>
 */
class MenuItem extends Post {
	/**
	 * @var string
	 */
	protected $postType = 'nav_menu_item';

	/**
	 * @var array
	 */
	private $instanceRelations = [
		'post'     => Post::class,
		'page'     => Page::class,
		'custom'   => CustomLink::class,
		'category' => Taxonomy::class,
	];

	private function get_wp_post_classes() {
		$post = get_post( $this->ID );

		$post->type             = $this->_menu_item_type;
		$post->menu_item_parent = $this->_menu_item_menu_item_parent;
		$post->object_id        = $this->_menu_item_object_id;
		$post->object           = $this->_menu_item_object;
		$post->target           = $this->_menu_item_target;
		$post->classes          = unserialize( $this->_menu_item_classes );

		$items = [ $post ];

		\_wp_menu_item_classes_by_context( $items );
		$post = reset( $items );

		return $post;
	}

	/**
	 * @return Post|Page|CustomLink|Taxonomy
	 */
	public function parent() {
		if ( $className = $this->getClassName() ) {
			return ( new $className )->newQuery()
			                         ->find( $this->meta->_menu_item_menu_item_parent );
		}

		return null;
	}

	/**
	 * @return Post|Page|CustomLink|Taxonomy
	 */
	public function instance() {
		if ( $className = $this->getClassName() ) {
			return ( new $className )->newQuery()
			                         ->find( $this->meta->_menu_item_object_id );
		}

		return null;
	}

	/**
	 * @return string
	 */
	private function getClassName() {
		return Arr::get(
			$this->instanceRelations,
			$this->meta->_menu_item_object
		);
	}

	public function getUrlAttribute() {
		switch ( $this->_menu_item_type ) {
			case 'taxonomy':
				$tax = $this->instance()->toArray();

				return \get_term_link( (int) $tax['term_taxonomy_id'], $tax['taxonomy'] );
				break;
			case 'custom':
				return ( $this->_menu_item_url );
				break;
			/* @TODO
			case 'post_type_archive':
			 * return \get_post_type_archive_link();
			 * break;
			 * */
			case 'post_type':
				return \get_permalink( $this->instance()->ID );
				break;
		}
	}

	public function getTitleAttribute() {
		switch ( $this->_menu_item_type ) {
			case 'taxonomy':
				$tax = $this->instance()->toArray();

				return \get_term_field( 'name', (int) $tax['term_taxonomy_id'], $tax['taxonomy'], 'raw' );
				break;
			/* @TODO
			case 'post_type_archive':
			 * return \get_post_type_archive_link();
			 * break;
			 * */
			default:
				return $this->instance()->post_title;
				break;
		}
	}

	public function getCurrentAttribute() {
		$post = $this->get_wp_post_classes();

		return $post->current;
	}

	public function getCurrentItemParentAttribute() {
		$post = $this->get_wp_post_classes();

		return $post->current_item_parent;
	}

	public function getCurrentItemAncestorAttribute() {
		$post = $this->get_wp_post_classes();

		return $post->current_item_ancestor;
	}

	public function getClassesAttribute() {
		$post = $this->get_wp_post_classes();

		$classes = $post->classes;

		if ( $post->current ) {
			$classes[] = 'current';
			$classes[] = 'active';
		}

		if ( $post->current_item_parent ) {
			$classes[] = 'current_item_parent';
		}

		if ( $post->current_item_ancestor ) {
			$classes[] = 'current_item_ancestor';
		}

		return trim( implode( ' ', array_filter( $classes ) ) );
	}
}
