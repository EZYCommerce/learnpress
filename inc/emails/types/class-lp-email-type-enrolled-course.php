<?php
/**
 * LP_Email_Type_Enrolled_Course.
 *
 * @author  ThimPress
 * @package Learnpress/Classes
 * @extends LP_Email
 * @version 3.0.9
 * @editor tungnx
 * @modify 4.1.3 - send email on background
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class LP_Email_Type_Enrolled_Course extends LP_Email {
	/**
	 * LP_Email_Type_Enrolled_Course constructor.
	 */
	public function __construct() {
		parent::__construct();

		$variable_on_email_support = apply_filters(
			'lp/email/enrolled-course/variables-support',
			[
				'{{course_id}}',
				'{{course_name}}',
				'{{course_url}}',
				'{{user_id}}',
				'{{user_name}}',
				'{{user_email}}',
				'{{user_display_name}}',
			]
		);

		$this->support_variables = array_merge( $this->support_variables, $variable_on_email_support );
	}

	/**
	 * Trigger email.
	 * Receive 2 params: order_id, old_status
	 *
	 * @param array $params
	 * @author tungnx
	 * @since 4.1.1
	 */
	public function handle( array $params ) {
		if ( ! $this->enable ) {
			return;
		}
		$lp_db = LP_User_Items_DB::getInstance();

		try {
			if ( count( $params ) < 1 ) {
				throw new Exception( 'Invalid params to send email ' . __CLASS__ );
			}

			$order_id = $params[0] ?? 0;
			$order    = new LP_Order( $order_id );

			$user_course_status = $lp_db->get_status_by_order_id( $order_id );

			if ( LP_COURSE_ENROLLED != $user_course_status ) {
				return;
			}

			$course_ids = $order->get_item_ids();

			foreach ( $course_ids as $course_id ) {
				$user_ids = $order->get_user_id();

				if ( is_array( $user_ids ) ) {
					foreach ( $user_ids as $user_id ) {
						$this->send_mails( $order, $course_id, $user_id );
					}
				} else {
					$this->send_mails( $order, $course_id, $user_ids );
				}
			}
		} catch ( Throwable $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Set variables for content email.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @editor tungnx
	 * @since 4.1.1
	 */
	public function set_data_content( int $course_id, int $user_id ) {
		$user   = learn_press_get_user( $user_id );
		$course = learn_press_get_course( $course_id );

		$variables = [];

		if ( $course ) {
			$variables = array_merge(
				$variables,
				array(
					'{{course_id}}'   => $course->get_id(),
					'{{course_name}}' => $course->get_title(),
					'{{course_url}}'  => $course->get_permalink(),
				)
			);
		}

		if ( $user ) {
			$variables = array_merge(
				$variables,
				array(
					'{{user_id}}'           => $user->get_id(),
					'{{user_name}}'         => $user->get_username(),
					'{{user_email}}'        => $user->get_email(),
					'{{user_display_name}}' => $user->get_display_name(),
				)
			);
		}

		$variables_common = $this->get_common_variables( $this->email_format );
		$this->variables  = array_merge( $variables, $variables_common );
	}

	/**
	 * Send emails to user
	 *
	 * @param LP_Order $order
	 * @param int $course_id
	 * @param int $user_id
	 */
	public function send_mails( LP_Order $order, int $course_id, int $user_id ) {
		$this->set_data_content( $course_id, $user_id );

		if ( $this instanceof LP_Email_Enrolled_Course_User ) {
			$lp_user = new LP_User( $user_id );
			$this->set_receive( $lp_user->get_email() );
		} elseif ( $this instanceof LP_Email_Enrolled_Course_Instructor ) {
			$course           = new LP_Course( $course_id );
			$email_instructor = $course->get_author()->get_email();
			$this->set_receive( $email_instructor );
		}

		do_action( 'learnpress/email/user-enrolled-course/handle', $order, $course_id );

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
}
