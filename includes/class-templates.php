<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Templates {

	/**
	 * Return all built-in reminder templates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all() {
		return array(
			array(
				'id'             => 'birthday',
				'name'           => __( 'Birthday', 'remindmii' ),
				'category'       => 'birthday',
				'icon'           => '🎂',
				'description'    => __( 'Reminder for a birthday.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( "Someone's Birthday", 'remindmii' ),
					'description'         => __( 'Remember to send a message or gift.', 'remindmii' ),
					'is_recurring'        => true,
					'recurrence_interval' => 'yearly',
				),
			),
			array(
				'id'             => 'anniversary',
				'name'           => __( 'Anniversary', 'remindmii' ),
				'category'       => 'anniversary',
				'icon'           => '💕',
				'description'    => __( 'Reminder for an anniversary.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Anniversary', 'remindmii' ),
					'description'         => __( 'Celebrate the occasion!', 'remindmii' ),
					'is_recurring'        => true,
					'recurrence_interval' => 'yearly',
				),
			),
			array(
				'id'             => 'subscription',
				'name'           => __( 'Subscription renewal', 'remindmii' ),
				'category'       => 'subscription',
				'icon'           => '💳',
				'description'    => __( 'Remind yourself before a subscription renews.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Subscription renewal', 'remindmii' ),
					'description'         => __( 'Check if you still need this subscription.', 'remindmii' ),
					'is_recurring'        => true,
					'recurrence_interval' => 'yearly',
				),
			),
			array(
				'id'             => 'monthly_subscription',
				'name'           => __( 'Monthly subscription', 'remindmii' ),
				'category'       => 'subscription',
				'icon'           => '📅',
				'description'    => __( 'Remind yourself before a monthly charge.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Monthly subscription', 'remindmii' ),
					'description'         => '',
					'is_recurring'        => true,
					'recurrence_interval' => 'monthly',
				),
			),
			array(
				'id'             => 'gift_card',
				'name'           => __( 'Gift card expiry', 'remindmii' ),
				'category'       => 'gift_card',
				'icon'           => '🎁',
				'description'    => __( 'Remind yourself to use a gift card before it expires.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Gift card expiry', 'remindmii' ),
					'description'         => __( 'Use before it expires!', 'remindmii' ),
					'is_recurring'        => false,
					'recurrence_interval' => '',
				),
			),
			array(
				'id'             => 'voucher',
				'name'           => __( 'Voucher / coupon expiry', 'remindmii' ),
				'category'       => 'voucher',
				'icon'           => '🎟️',
				'description'    => __( 'Remind yourself before a voucher expires.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Voucher expiry', 'remindmii' ),
					'description'         => __( 'Do not forget to use this!', 'remindmii' ),
					'is_recurring'        => false,
					'recurrence_interval' => '',
				),
			),
			array(
				'id'             => 'bill_payment',
				'name'           => __( 'Bill / invoice', 'remindmii' ),
				'category'       => 'finance',
				'icon'           => '🧾',
				'description'    => __( 'Reminder to pay a bill.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Pay bill', 'remindmii' ),
					'description'         => '',
					'is_recurring'        => true,
					'recurrence_interval' => 'monthly',
				),
			),
			array(
				'id'             => 'meeting',
				'name'           => __( 'Meeting / appointment', 'remindmii' ),
				'category'       => 'event',
				'icon'           => '📆',
				'description'    => __( 'Reminder for a meeting or appointment.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Meeting', 'remindmii' ),
					'description'         => '',
					'is_recurring'        => false,
					'recurrence_interval' => '',
				),
			),
			array(
				'id'             => 'holiday',
				'name'           => __( 'Holiday / travel', 'remindmii' ),
				'category'       => 'event',
				'icon'           => '✈️',
				'description'    => __( 'Prepare for an upcoming trip.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Holiday / travel preparation', 'remindmii' ),
					'description'         => __( 'Pack bags, check passport, book transfers.', 'remindmii' ),
					'is_recurring'        => false,
					'recurrence_interval' => '',
				),
			),
			array(
				'id'             => 'medication',
				'name'           => __( 'Medication', 'remindmii' ),
				'category'       => 'health',
				'icon'           => '💊',
				'description'    => __( 'Regular medication reminder.', 'remindmii' ),
				'default_fields' => array(
					'title'               => __( 'Take medication', 'remindmii' ),
					'description'         => '',
					'is_recurring'        => true,
					'recurrence_interval' => 'daily',
				),
			),
		);
	}

	/**
	 * Get unique categories from templates.
	 *
	 * @return array<int, string>
	 */
	public static function get_categories() {
		$cats = array();

		foreach ( self::get_all() as $t ) {
			if ( ! in_array( $t['category'], $cats, true ) ) {
				$cats[] = $t['category'];
			}
		}

		return $cats;
	}
}
