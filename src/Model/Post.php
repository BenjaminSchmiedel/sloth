<?php
/**
 * User: Kremer
 * Date: 02.01.18
 * Time: 12:40
 */

namespace Sloth\Model;

use Corcel\Model\Post as CorcelPost;
use Sloth\Facades\Configure;

class Post extends CorcelPost {

	/**
	 * @return string
	 */
	public function getContentAttribute() {

		return apply_filters( 'the_content', $this->post_content );
	}
}
