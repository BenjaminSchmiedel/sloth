<?php

namespace Sloth\Pagination;

use Illuminate\Support\ServiceProvider;
use Sloth\Paginaton\Paginator;

class PaginationServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		\Illuminate\Pagination\AbstractPaginator::viewFactoryResolver( function () {
			return $GLOBALS['sloth']->container['view'];
		} );

		\Illuminate\Pagination\AbstractPaginator::$defaultView       = 'pagination.default';
		\Illuminate\Pagination\AbstractPaginator::$defaultSimpleView = 'pagination.default';

		\Illuminate\Pagination\AbstractPaginator::currentPathResolver( function () {
			return '';
		} );

	}

}