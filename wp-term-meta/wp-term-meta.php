<?php
/*
Plugin Name: WP Term Meta
Description: Lightweight term meta that doesn't depend on new tables or pollute the options table.
Author: Ben Doherty @ Oomph, Inc.
Version: 0.0.1
Author URI: http://www.oomphinc.com/thinking/author/bdoherty/
License: GPLv2 or later

		Copyright © 2015 Oomph, Inc. <http://oomphinc.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class WP_Term_Meta {
	const post_type = '_term_meta';

	static function init() {
		register_post_type( self::post_type, array( 'public' => false ) );
	}

	private static function _post( $term_id, $taxonomy = null ) {
		// Allow first argument to be term object instead
		if( is_object( $term_id ) ) {
			$taxonomy = $term_id->taxonomy;
			$term_id = $term_id->term_id;
		}

		// Get or create the post whose name is _meta_{$taxonomy}_{$term_id}.
		//
		// This naming format is used to take advantage of the index on post_name
		// so we can quickly pull the meta-containing post object from a known term object.
		//
		// Conversely, if we need to do a search on meta (which is non-optimal,) we can
		// easily extract the correct taxonomy and term from the post_name
		$post_name = sanitize_key( '_meta_' . $taxonomy . '_' . $term_id );

		$post_query = new WP_Query( array(
			'post_type' => self::post_type,
			'post_status' => 'draft',
			'posts_per_page' => 1,
			'name' => $post_name
		) );

		if( $post_query->have_posts() ) {
			$post = $post_query->next_post();
		}
		else {
			$post_id = wp_insert_post( array(
				'post_name' => $post_name,
				'post_type' => self::post_type
			) );

			$post = get_post( $post_id );
		}

		return $post;
	}

	/**
	 * Update a term meta
	 */
	static function update( $term_id, $taxonomy, $key_or_array = null, $value = null ) {
		// Allow first argument to be term object instead, which then the second and
		// third arguments become the key and possible value instead
		if( is_object( $term_id ) ) {
			$value = $key_or_array;
			$key_or_array = $taxonomy;
		}

		$post = self::_post( $term_id, $taxonomy );

		if( $post ) {
			if( is_string( $key_or_array ) ) {
				$key_or_array = array( $key_or_array => $value );
			}

			foreach( $key_or_array as $key => $value ) {
				if( isset( $value ) ) {
					update_post_meta( $post->ID, $key, $value );
				}
				else {
					delete_post_meta( $post->ID, $key );
				}
			}

			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Get a term meta, or all, term meta.
	 *
	 * @return mixed|null
	 */
	static function get( $term_id, $taxonomy = null, $key = null ) {
		if( is_object( $term_id ) ) {
			$key = $taxonomy;
		}

		$post = self::_post( $term_id, $taxonomy );

		if( $post ) {
			if( empty( $key ) ) {
				$custom = get_post_custom( $post->ID );

				// Flatten down to key-value instead of key-value-array
				$values = array();
				foreach( $custom as $key => $meta_values ) {
					$values[$key] = $meta_values[0];
				}

				return $values;
			}

			return get_post_meta( $post->ID, $key, true );
		}
	}

	/**
	 * Delete a term meta value.
	 *
	 * @param int|object $term_id (or term object)
	 * @param string $taxonomy or $key
	 * @param string $key optional
	 */
	static function delete( $term_id, $taxonomy, $key = null ) {
		self::update( $term_id, $taxonomy, $key );
	}
}
add_action( 'init', array( 'WP_Term_Meta', 'init' ) );
