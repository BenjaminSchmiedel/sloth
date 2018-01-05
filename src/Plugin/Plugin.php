<?php

namespace Sloth\Plugin;

use Corcel\Model\Menu;
use Sloth\Facades\Configure;
use Sloth\Facades\View;

use PostTypes\PostType;

use Sloth\Core\Sloth;

use Brain\Hierarchy\Finder\FoldersTemplateFinder;
use \Brain\Hierarchy\QueryTemplate;
use Sloth\Utility\Utility;

class Plugin extends \Singleton {
	public $current_theme_path;
	private $container;
	private $modules = [];

	public function __construct() {
		$this->container = $GLOBALS['sloth']->container;
		$this->loadControllers();
		$this->loadModels();
		$this->loadTaxonomies();
		#\Route::instance()->boot();

		$this->fixPagination();

		/**
		 * set current_theme_path
		 */
		$this->current_theme_path = realpath( get_template_directory() );
		/**
		 * tell container about current theme path
		 */
		$this->container->addPath( 'theme', $this->current_theme_path );

		/**
		 * tell ViewFinder about current theme's view path
		 */
		$this->container['view.finder']->addLocation( $this->current_theme_path . DS . 'View' );

		/**
		 * tell ViewFinder about sloths's view path
		 */

		$this->container['view.finder']->addLocation( dirname( __DIR__ ) . DS . '_view' );

		/*
		 * we need the possibility to use @extends in twig, so we resolve all subdirectories of Layouts
		 */
		/* $twigLoader = $this->container['twig.loader'];

		if ( is_dir( $this->current_theme_path . DS . 'views' . DS . 'partials' ) ) {
			$twigLoader->addPath( $this->current_theme_path . DS . 'views' . DS . 'partials', 'partials' );
		}
				$dirs = array_filter( glob( $this->current_theme_path . DS . 'View' . DS . '*' ), 'is_dir' );

		foreach ( $dirs as $dir ) {
			$this->container['view.finder']->addNamespace( basename( $dir ), $dir );
		}*/


		/*
		 * Update Twig Loaded registered paths.
		 */
		$this->container['twig.loader']->setPaths( $this->container['view.finder']->getPaths() );

		/*
		 * include theme's config
		 */
		$theme_config = $this->current_theme_path . DS . 'config.php';
		if ( file_exists( $theme_config ) ) {
			include_once $theme_config;
		}

		$this->addFilters();
	}

	private function loadControllers() {
		foreach ( glob( \get_template_directory() . DS . 'Controller' . DS . '*Controller.php' ) as $file ) {
			include( $file );
		}
	}

	public function loadModels() {

		foreach ( glob( DIR_APP . 'Model' . DS . '*.php' ) as $file ) {
			$model_name = 'App\Model\\' . basename( $file, '.php' );
			if ( ! class_exists( $model_name ) ) {
				include( $file );
				$classes    = get_declared_classes();
				$model_name = array_pop( $classes );
			}

			$model = new $model_name;
			$model->register();

			$post_type = $model->getPostType();

			if ( $model::$layotter !== false ) {
				$this->container['layotter']->enable_for_post_type( $post_type );
				if ( is_array( $model::$layotter ) ) {
					isset( $model::$layotter['allowed_row_layouts'] ) ? $this->container['layotter']->set_layouts_for_post_type( $post_type,
						$model::$layotter['allowed_row_layouts'] ) : null;
				}
			} else {
				$this->container['layotter']->disable_for_post_type( $post_type );
			}
		}
	}


	public function loadTaxonomies() {

		foreach ( glob( DIR_APP . 'Taxonomy' . DS . '*.php' ) as $file ) {
			include( $file );
			$classes       = get_declared_classes();
			$taxonomy_name = array_pop( $classes );

			$taxonomy = new $taxonomy_name;
			$taxonomy->register();
		}
	}

	public function loadModules() {
		foreach ( glob( get_template_directory() . DS . 'Module' . DS . '*Module.php' ) as $file ) {
			include( $file );
			$classes     = get_declared_classes();
			$module_name = array_pop( $classes );

			if ( is_array( $module_name::$layotter ) && class_exists( '\Layotter' ) ) {

				$class_name = substr( strrchr( $module_name, "\\" ), 1 );

				eval(
				"class $class_name extends \Sloth\Module\LayotterElement {
					static \$module = '$module_name';
				}"
				);
				\Layotter::register_element( strtolower( substr( strrchr( $module_name, "\\" ), 1 ) ), $class_name );
			}

			if ( $module_name::$json ) {
				$action = 'module_' . Utility::underscore( class_basename( $module_name ) );
				#$reflect = new ReflectionClass($object);
				add_action( 'wp_ajax_nopriv_' . $action,
					[ new $module_name, 'getJSON' ] );
				add_action( 'wp_ajax_' . $action,
					[ new $module_name, 'getJSON' ] );
				$module_name::$ajax_url = str_replace( home_url(), '', \admin_url( 'admin-ajax.php?action=' . $action ) );
			}

			$this->modules[] = $module_name;

		}
	}

	private function addFilters() {

		add_filter( 'network_admin_url', [ $this, 'fix_network_admin_url' ] );
		add_action( 'init', [ $this, 'loadModules' ], 20 );
		add_action( 'init', [ $this, 'register_menus' ], 20 );

		add_action( 'admin_init', [ $this, 'auto_sync_acf_fields' ] );
		/**
		 * For now we give up Controllers an Routing
		 */
		#add_action( 'init', [ Sloth::getInstance(), 'setRouter' ], 20 );
		# add_action( 'template_redirect', [ Sloth::getInstance(), 'dispatchRouter' ], 20 );

		add_action( 'template_redirect', [ $this, 'getTemplate' ], 20 );

		if ( getenv( 'FORCE_SSL' ) ) {
			add_action( 'template_redirect', [ $this, 'force_ssl' ], 30 );
		}

		$this->container['layotter']->addFilters();
	}

	public function plugin() {
		// rewrite the upload directory
		add_filter(
			'upload_dir',
			function ( $uploads_array ) {
				$fixed_uploads_array = [];
				foreach ( $uploads_array as $part => $value ) {
					if ( in_array( $part, [ 'path', 'url', 'basedir', 'baseurl' ] ) ) {
						$fixed_uploads_array[ $part ] = str_replace( WP_PATH . '/..', '', $value );
					} else {
						$fixed_uploads_array[ $part ] = $value;
					}
				}

				return $fixed_uploads_array;
			}
		);
	}

	public function fix_network_admin_url( $url ) {
		$url_info = parse_url( $url );

		if ( ! preg_match( '/^\/cms/', $url_info['path'] ) ) {
			$url = $url_info['scheme'] . '://' . $url_info['host'] . '/cms' . $url_info['path'];
			if ( isset( $url_info['query'] ) && ! empty( $url_info['query'] ) ) {
				$url .= '?' . $url_info['query'];
			}
		}

		return $url;
	}

	public function force_ssl() {
		if ( getenv( 'FORCE_SSL' ) && ! is_ssl() ) {
			wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301 );
			exit();
		}
	}

	/**
	 * @return array
	 */
	public function getContext() {
		global $post;

		return [
			'post'     => $post,
			'wp_title' => wp_title( '', false ),
			'site'     => [
				'url'         => home_url(),
				'rdf'         => get_bloginfo( 'rdf_url' ),
				'rss'         => get_bloginfo( 'rss_url' ),
				'rss2'        => get_bloginfo( 'rss2_url' ),
				'atom'        => get_bloginfo( 'atom_url' ),
				'language'    => get_bloginfo( 'language' ),
				'charset'     => get_bloginfo( 'charset' ),
				'pingback'    => $this->pingback_url = get_bloginfo( 'pingback_url' ),
				'admin_email' => get_bloginfo( 'admin_email' ),
				'name'        => get_bloginfo( 'name' ),
				'title'       => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
			],
			'globals'  => [
				'home_url'   => home_url( '/' ),
				'theme_url'  => get_template_directory_uri(),
				'images_url' => get_template_directory_uri() . '/assets/img',
			],
		];
	}

	public function getTemplate() {

		$this->fixPagination();
		//@TODO: fix for older themes structure
		if ( ! is_dir( $this->current_theme_path . DS . 'View' . DS . 'Layout' ) ) {
			return;
		}
		global $post;

		$post = is_object( $post ) ? $post : new \StdClass;

		$layoutPaths = [];
		foreach ( $this->container['view.finder']->getPaths() as $path ) {
			$layoutPaths[] = $path . DS . 'Layout';
		}
		$finder = new FoldersTemplateFinder( $layoutPaths, [ 'twig' ] );

		$queryTemplate = new QueryTemplate( $finder );

		$view = View::make( 'Layout.' . basename( $queryTemplate->findTemplate(), '.twig' ) );
		if ( isset( $post->post_content ) ) {
			$post->content = apply_filters( 'the_content', $post->post_content );
		}
		echo $view
			->with(
				$this->getContext()
			)
			->render();
	}

	public function auto_sync_acf_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) || WP_ENV != 'development' ) {
			return false;
		}

		// vars
		$groups = acf_get_field_groups();
		$sync   = [];

		// bail early if no field groups
		if ( empty( $groups ) ) {
			return;
		}

		// find JSON field groups which have not yet been imported
		foreach ( $groups as $group ) {

			// vars
			$local    = acf_maybe_get( $group, 'local', false );
			$modified = acf_maybe_get( $group, 'modified', 0 );
			$private  = acf_maybe_get( $group, 'private', false );

			// ignore DB / PHP / private field groups
			if ( $local !== 'json' || $private ) {

				// do nothing

			} else if ( ! $group['ID'] ) {

				$sync[ $group['key'] ] = $group;

			} else if ( $modified && $modified > get_post_modified_time( 'U', true, $group['ID'], true ) ) {

				$sync[ $group['key'] ] = $group;
			}
		}

		// bail if no sync needed
		if ( empty( $sync ) ) {
			return;
		}

		if ( ! empty( $sync ) ) { //if( ! empty( $keys ) ) {

			// vars
			$new_ids = [];

			foreach ( $sync as $key => $v ) { //foreach( $keys as $key ) {

				// append fields
				if ( acf_have_local_fields( $key ) ) {

					$sync[ $key ]['fields'] = acf_get_local_fields( $key );

				}
				// import
				$field_group = acf_import_field_group( $sync[ $key ] );
			}
		}
	}

	/**
	 * register menus for the theme
	 */
	public function register_menus() {
		$menus = Configure::read( 'theme.menus' );
		if ( $menus && is_array( $menus ) ) {
			foreach ( $menus as $menu => $title ) {
				\register_nav_menu( $menu, __( $title ) );
			}
		}
	}

	public function register_image_sizes() {
		$image_sizes = Configure::read( 'theme.image-sizes' );
		if ( $image_sizes && is_array( $image_sizes ) ) {
			foreach ( $image_sizes as $name => $options ) {
				$options = array_merge( [
					'width'  => 800,
					'height' => 600,
					'crop'   => false,
				],
					$options );
				\add_image_size( $name, $options['width'], $options['height'], $options['crop'] );
			}
		}
	}

	protected function fixPagination() {
		/**
		 * hand current page from get to Illuminate
		 */
		if ( isset( $_GET['page'] ) ) {
			$currentPage = $_GET['page'];
			\Illuminate\Pagination\Paginator::currentPageResolver( function () use ( $currentPage ) {
				return $currentPage;
			} );
		}
		global $wp_query;
		/**
		 * hand current page from wp_query to Illuminate
		 */
		if ( isset( $wp_query->query['page'] ) ) {
			$currentPage = $wp_query->query['page'];
			\Illuminate\Pagination\Paginator::currentPageResolver( function () use ( $currentPage ) {
				return $currentPage;
			} );
		}
	}
}
