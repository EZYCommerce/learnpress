<?php
class LP_Reset_Data {
	public static function init() {
		$ajax_events = array(
			'search-courses',
			'search-users',
			'reset-course-users' => 'ajax_reset_course_users',
			'reset-user-courses' => 'ajax_reset_user_courses',
			'reset-user-item'    => 'ajax_reset_user_item',
		);

		foreach ( $ajax_events as $action => $callback ) {
			if ( is_numeric( $action ) ) {
				$action = $callback;
			}

			$actions = LP_Request::parse_action( $action );
			$method  = $actions['action'];

			if ( ! is_callable( $callback ) ) {
				$callback = array( __CLASS__, $callback );

				if ( ! is_callable( $callback ) ) {
					$method   = preg_replace( '/-/', '_', $method );
					$callback = array( __CLASS__, $method );
				}
			}
			LP_Request::register_ajax( "rs-{$action}", $callback );
		}
	}

	public static function ajax_reset_user_item() {
		$user_id = LP_Request::get_string( 'user_id' );
		$item_id = LP_Request::get_int( 'item_id' );

		if ( ! is_numeric( $user_id ) ) {
			$user_email = get_user_by( 'email', $user_id );
			$user_login = get_user_by( 'login', $user_id );

			if ( $user_email ) {
				$user_id = $user_email->ID;
			} elseif ( $user_login ) {
				$user_id = $user_login->ID;
			}
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"
			SELECT user_item_id
			FROM {$wpdb->learnpress_user_items}
			WHERE user_id = %d AND item_id = %d
		",
			$user_id,
			$item_id
		);

		$user_item_ids = $wpdb->get_col( $query );
		if ( $user_item_ids ) {
			$query   = "
				SELECT DISTINCT parent_id AS parent, item_id
				FROM {$wpdb->learnpress_user_items}
				WHERE user_item_id IN(" . join( ',', $user_item_ids ) . ')
			';
			$parents = $wpdb->get_results( $query );

			$format = array_fill( 0, sizeof( $user_item_ids ), '%d' );
			$query  = $wpdb->prepare(
				"
				DELETE
				FROM {$wpdb->learnpress_user_itemmeta}
				WHERE learnpress_user_item_id IN(" . join( ',', $format ) . ')
			',
				$user_item_ids
			);
			$wpdb->query( $query );

			$query = $wpdb->prepare(
				"
				DELETE
				FROM {$wpdb->learnpress_user_items}
				WHERE user_id = %d AND item_id = %d
			",
				$user_id,
				$item_id
			);

			$wpdb->query( $query );

			if ( $parents ) {
				foreach ( $parents as $parent ) {
					$retaken_items = learn_press_get_user_item_meta( $parent->parent, '_retaken_items', true );
					if ( $retaken_items ) {
						if ( ! isset( $retaken_items[ $parent->item_id ] ) ) {
							continue;
						}

						unset( $retaken_items[ $parent->item_id ] );
						learn_press_update_user_item_meta( $parent->parent, '_retaken_items', $retaken_items );
					}
				}
			}

			echo __( 'Item progress deleted', 'learnpress' );
		} else {
			echo __( 'No data found', 'learnpress' );
		}
		// LP_Debug::rollbackTransaction();
		die();
	}

	public static function remove_item_data() {

	}

	public static function search_courses() {
		global $wpdb;

		$s     = LP_Request::get_string( 's' );
		$where = '';

		$query = $wpdb->prepare(
			"
			SELECT ID as id, post_title AS title, 'students', '' AS status
			FROM {$wpdb->posts}
			WHERE post_type = %s AND post_title LIKE %s
			{$where}
		",
			LP_COURSE_CPT,
			'%' . $wpdb->esc_like( $s ) . '%'
		);

		$courses = array();
		$rows    = $wpdb->get_results( $query );

		if ( $rows ) {
			$course_ids = wp_list_pluck( $rows, 'id' );
			$format     = array_fill( 0, sizeof( $course_ids ), '%d' );
			$args       = $course_ids;
			$args[]     = LP_COURSE_CPT;
			$query      = $wpdb->prepare( "SELECT item_id FROM {$wpdb->learnpress_user_items} WHERE item_id IN(" . join( ',', $format ) . ') AND item_type = %s', $args );

			$item_ids = $wpdb->get_col( $query );

			if ( $item_ids ) {
				for ( $n = sizeof( $rows ), $i = $n - 1; $i >= 0; $i -- ) {
					if ( ! in_array( $rows[ $i ]->id, $item_ids ) ) {
						unset( $rows[ $i ] );
					}
				}
			} else {
				$rows = array();
			}

			if ( $rows ) {
				foreach ( $rows as $k => $row ) {
					$course = learn_press_get_course( $row->id );
					if ( $row->students = $course->count_in_order() ) {
						$courses[] = $row;
					}
				}
			}
		}

		learn_press_send_json( $courses );
	}

	public static function search_users() {
		global $wpdb;

		$s     = LP_Request::get_string( 's' );
		$query = $wpdb->prepare(
			"
			SELECT ID AS id, user_login AS username, user_email AS email, '' AS status
			FROM {$wpdb->users}
			WHERE user_login LIKE %s
				OR user_email LIKE %s
		",
			'%' . $wpdb->esc_like( $s ) . '%',
			'%' . $wpdb->esc_like( $s ) . '%'
		);

		$users = array();

		$rows = $wpdb->get_results( $query );
		if ( $rows ) {
			$user_ids = wp_list_pluck( $rows, 'id' );
			$format   = array_fill( 0, sizeof( $user_ids ), '%d' );
			$args     = $user_ids;
			$args[]   = LP_COURSE_CPT;
			$query    = $wpdb->prepare( "SELECT user_id, item_id FROM {$wpdb->learnpress_user_items} WHERE user_id IN(" . join( ',', $format ) . ') AND item_type = %s', $args );

			$items = $wpdb->get_results( $query );
			if ( $items ) {
				$uids = wp_list_pluck( $items, 'user_id' );
				for ( $n = sizeof( $rows ), $i = $n - 1; $i >= 0; $i -- ) {

					if ( ! in_array( $rows[ $i ]->id, $uids ) ) {
						unset( $rows[ $i ] );
						continue;
					}

					if ( empty( $rows[ $i ]->courses ) ) {
						$rows[ $i ]->courses = array();
					}
					foreach ( $items as $item ) {
						if ( $item->user_id == $rows[ $i ]->id ) {
							$rows[ $i ]->courses[ $item->item_id ] = array(
								'url'   => get_the_permalink( $item->item_id ),
								'id'    => $item->item_id,
								'title' => get_the_title( $item->item_id ),
							);
						}
					}
				}
			} else {
				$rows = array();
			}

			if ( $rows ) {
				foreach ( $rows as $k => $row ) {
					$users[] = $row;
					if ( sizeof( $users ) > 100 ) {
						break;
					}
				}
			}
		}

		learn_press_send_json( $users );
	}

	public static function ajax_reset_course_users() {
		$course_id = LP_Request::get_int( 'id' );

		$ids = self::reset_course_users( $course_id );
		learn_press_send_json( array( 'id' => $ids ) );
		die();
	}

	public static function ajax_reset_user_courses() {
		$user_id   = LP_Request::get_int( 'user_id' );
		$course_id = LP_Request::get_int( 'course_id' );

		$user = learn_press_get_user($user_id);
		$user_course_data = $user->get_course_data( $course_id );

		// Set status, start_time, end_time of course to enrolled.
		$user_course_data->set_status( LP_COURSE_ENROLLED )
		                 ->set_start_time( current_time( 'mysql', true ) )
		                 ->set_end_time( '' )
		                 ->set_graduation( 'in-progress' )
		                 ->update();

		// Remove items' course user learned.
		$filter_remove            = new LP_User_Items_Filter();
		$filter_remove->parent_id = $user_course_data->get_user_item_id();
		$filter_remove->user_id   = $user_course_data->get_user_id();
		$filter_remove->limit     = - 1;
		LP_User_Items_DB::getInstance()->remove_items_of_user_course( $filter_remove );

		die();
	}

	public static function reset_course_users( $course_id, $user_id = 0 ) {
		global $wpdb;

		$user_item_courses = self::get_user_item_courses( $course_id, $user_id );
		if ( ! $user_item_courses ) {
			return false;
		}

		try {
			// Delete course items
			foreach ( $user_item_courses as $course_item ) {
				$course_items = self::get_user_items_by_parent( $course_item->user_item_id );
				if ( ! $course_items ) {
					continue;
				}
				$user_item_ids = wp_list_pluck( $course_items, 'user_item_id' );

				self::delete_user_items_by_id( $user_item_ids );
			}

			// Delete course
			$user_item_ids = wp_list_pluck( $user_item_courses, 'user_item_id' );
			self::delete_user_items_by_id( $user_item_ids );
		} catch ( Exception $ex ) {
			// LP_Debug::rollbackTransaction();
		}

		$removed = false;

		$user_item_courses = self::get_user_item_courses( $course_id );
		if ( ! $user_item_courses ) {
			$removed = $course_id;
		}

		return $removed;
	}

	public static function get_user_item_courses( $course_id, $user_id = 0 ) {
		global $wpdb;

		$where = '';
		if ( $user_id ) {
			$where = $wpdb->prepare( 'AND user_id = %d', $user_id );
		}

		$query = $wpdb->prepare(
			"
			SELECT *
			FROM {$wpdb->learnpress_user_items}
			WHERE item_type = %s
			AND item_id = %d
			$where
		",
			LP_COURSE_CPT,
			$course_id
		);

		echo "$query\n";

		return $wpdb->get_results( $query );
	}

	public static function delete_user_items_by_id( $ids ) {
		settype( $ids, 'array' );

		global $wpdb;

		// Delete meta
		$format = array_fill( 0, sizeof( $ids ), '%d' );
		$query  = $wpdb->prepare( "DELETE FROM {$wpdb->learnpress_user_itemmeta} WHERE learnpress_user_item_id IN(" . join( ',', $format ) . ')', $ids );
		$wpdb->query( $query );
		echo "$query\n";

		// Delete items
		$query = $wpdb->prepare( "DELETE FROM {$wpdb->learnpress_user_items} WHERE user_item_id IN(" . join( ',', $format ) . ')', $ids );
		$wpdb->query( $query );

		echo "$query\n";

	}

	public static function get_user_items_by_parent( $parent_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"
			SELECT *
			FROM {$wpdb->learnpress_user_items}
			WHERE parent_id = %d
		",
			$parent_id
		);

		echo "$query\n";

		return $wpdb->get_results( $query );
	}
}
LP_Reset_Data::init();
