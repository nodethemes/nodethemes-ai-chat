<?php
/**
 * Privacy: suggested policy text, personal data export and erase (by e-mail address).
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy.
 */
final class NTAC_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Suggested text for Settings → Privacy.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$t  = '<p>' . esc_html__( 'When you use the chat on this website (or write to us on Telegram or WhatsApp, if we offer it), your messages, the name and contact details you give us and the page you were on are stored on our website so we can answer you.', 'nodethemes-ai-chat' ) . '</p>';
		$t .= '<p>' . esc_html__( 'An AI assistant writes most answers. To do that, your messages are sent to our AI provider (OpenAI, Anthropic or Google, depending on our settings), which processes them under its own privacy terms. A member of our team can read the conversation and answer you.', 'nodethemes-ai-chat' ) . '</p>';
		/* translators: %d: days */
		$t .= '<p>' . esc_html( sprintf( __( 'Conversations are deleted automatically after %d days without activity.', 'nodethemes-ai-chat' ), (int) NTAC_Settings::get( 'retention' ) ) ) . '</p>';
		wp_add_privacy_policy_content( 'NodeThemes AI Chat', wp_kses_post( $t ) );
	}

	/**
	 * Register exporter.
	 *
	 * @param array $e Exporters.
	 * @return array
	 */
	public static function exporters( $e ) {
		$e['nodethemes-ai-chat'] = array(
			'exporter_friendly_name' => __( 'AI Chat conversations', 'nodethemes-ai-chat' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $e;
	}

	/**
	 * Register eraser.
	 *
	 * @param array $e Erasers.
	 * @return array
	 */
	public static function erasers( $e ) {
		$e['nodethemes-ai-chat'] = array(
			'eraser_friendly_name' => __( 'AI Chat conversations', 'nodethemes-ai-chat' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $e;
	}

	/**
	 * Conversations with this e-mail.
	 *
	 * @param string $email E-mail.
	 * @return int[]
	 */
	private static function ids( $email ) {
		global $wpdb;
		$t = NTAC_DB::t( 'conversations' );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE contact = %s", $email ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Export.
	 *
	 * @param string $email E-mail.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		unset( $page );
		$items = array();
		foreach ( self::ids( $email ) as $id ) {
			$c    = NTAC_DB::conv( $id );
			$text = '';
			foreach ( NTAC_DB::msgs( $id, 0, 1000, true ) as $m ) {
				$text .= '[' . $m->created . ' UTC] ' . $m->role . ': ' . $m->body . "\n";
			}
			$items[] = array(
				'group_id'    => 'ntac-chat',
				'group_label' => __( 'Chat conversations', 'nodethemes-ai-chat' ),
				'item_id'     => 'ntac-' . $id,
				'data'        => array(
					array(
						'name'  => __( 'Channel', 'nodethemes-ai-chat' ),
						'value' => NTAC_Channels::label( $c->channel ),
					),
					array(
						'name'  => __( 'Name', 'nodethemes-ai-chat' ),
						'value' => $c->name,
					),
					array(
						'name'  => __( 'Messages', 'nodethemes-ai-chat' ),
						'value' => $text,
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email E-mail.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$ids = self::ids( $email );
		foreach ( $ids as $id ) {
			NTAC_DB::conv_delete( $id );
		}
		return array(
			'items_removed'  => count( $ids ) > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
