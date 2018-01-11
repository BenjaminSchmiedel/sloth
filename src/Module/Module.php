<?php

namespace Sloth\Module;

use Sloth\Facades\View as View;
use Sloth\Utility\Utility;

class Module {
	public static $layotter = false;
	public static $json = false;
	private $view;
	private $viewVars = [];
	protected $viewPrefix = 'module';
	protected $render = true;
	protected $template;
	public static $ajax_url;
	protected $doing_ajax = false;
	protected $wrapInRow = false;

	protected function beforeRender() {

	}

	protected function beforeGetJSON() {
	}

	final private function getTemplate() {
		if ( is_null( $this->template ) ) {
			$class          = get_class( $this );
			$this->template = \Cake\Utility\Inflector::dasherize( preg_replace( '/Module$/',
				'',
				substr( strrchr( $class, "\\" ), 1 ) ) );
		}
		if ( ! strstr( $this->template, '.' ) ) {
			$this->template = $this->viewPrefix . '.' . $this->template;
		}
		$this->template = str_replace( '.', DS, ucfirst( $this->template ) );
	}

	final private function makeView() {
		$this->getTemplate();
		$this->view = View::make( $this->template );
	}

	final public function get_layotter_attributes() {
		$class = get_class( $this );

		return $class::$layotter;
	}

	/**
	 * render the view
	 */
	public function render() {
		if ( ! $this->doing_ajax ) {
			$this->set( $GLOBALS['sloth::plugin']->getContext() );
			$this->set( 'ajax_url', $this->getAjaxUrl() );
		}
		$this->beforeRender();
		$this->makeView();
		$vars   = array_merge( $GLOBALS['sloth::plugin']->getContext(), $this->viewVars );
		$output = $this->view->with( $vars )->render();
		if ( $this->render ) {
			if ( $this->wrapInRow ) {
				$output = View::make( 'Layotter.row' )->with( [ 'content' => $output ] )->render();
			}
			echo $output;
		}

		return $output;
	}

	final public function set( $key, $value = null ) {
		if ( is_array( $key ) ) {
			foreach ( $key as $k => $v ) {
				$this->set( $k, $v );
			}
		} else {
			$this->viewVars[ $key ] = $value;
		}
	}

	final protected function get( $k ) {
		if ( ! isset( $this->viewVars[ $k ] ) ) {
			$this->viewVars[ $k ] = null;
		}

		return $this->viewVars[ $k ];
	}

	final public function unset( $key ) {
		unset( $this->viewVars[ $key ] );
	}

	final protected function _get( $k ) {
		return $this->get( $k );
	}

	final public function getJSON() {
		$this->doing_ajax = true;
		$this->beforeRender();
		$this->beforeGetJSON();
		header( 'Content-Type: application/json' );
		echo json_encode( $this->viewVars, 1 );
		die();
	}

	final public function getAjaxUrl() {
		return str_replace( home_url(),
			'',
			\admin_url( 'admin-ajax.php?action=' . $this->getAjaxAction() ) );
	}

	final public function getAjaxAction() {
		return 'module_' . Utility::underscore( class_basename( $this ) );
	}
}
