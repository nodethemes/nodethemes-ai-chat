<?php
/**
 * Channels: where conversations come from and how replies are delivered.
 * The free plugin has the website live chat; the Pro add-on registers Telegram and WhatsApp.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base channel.
 */
abstract class NTAC_Channel {

	/**
	 * Channel id (stored on the conversation).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Deliver a text.
	 *
	 * @param object $conv Conversation.
	 * @param string $text Text.
	 * @param array  $opts humanize: array( read, typing ) seconds for channels that wait on their side.
	 * @return array ok, ext_id, error.
	 */
	abstract public function send( $conv, $text, $opts = array() );

	/**
	 * Show "typing…" (best effort).
	 *
	 * @param object $conv Conversation.
	 */
	public function typing( $conv ) {}

	/**
	 * Mark the customer's messages as read (best effort).
	 *
	 * @param object $conv     Conversation.
	 * @param array  $messages Customer messages being answered.
	 */
	public function mark_read( $conv, $messages ) {}

	/**
	 * Does the remote side do the reading/typing pauses itself (so PHP does not wait)?
	 *
	 * @return bool
	 */
	public function remote_humanize() {
		return false;
	}

	/**
	 * Seconds a typing indicator lasts on this channel (0 = no indicator).
	 *
	 * @return int
	 */
	public function typing_ttl() {
		return 0;
	}

	/**
	 * Can replies be sent right now?
	 *
	 * @return bool
	 */
	public function can_send() {
		return true;
	}

	/**
	 * Longest message the channel accepts.
	 *
	 * @return int
	 */
	public function max_len() {
		return 4000;
	}

	/**
	 * Style hint for the AI (formatting the channel supports).
	 *
	 * @return string
	 */
	public function style() {
		return 'Plain text. Short paragraphs. No markdown headings or tables.';
	}
}

/**
 * Website live chat. Replies are stored and picked up by the visitor's browser.
 */
class NTAC_Channel_Web extends NTAC_Channel {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'web';
	}

	/**
	 * Nothing to deliver: the browser polls for new messages.
	 *
	 * @param object $conv Conversation.
	 * @param string $text Text.
	 * @param array  $opts Options.
	 * @return array
	 */
	public function send( $conv, $text, $opts = array() ) {
		NTAC_DB::conv_meta( $conv->id, array( 'typing_until' => 0 ) );
		return array(
			'ok'     => true,
			'ext_id' => '',
			'error'  => '',
		);
	}

	/**
	 * Typing flag read by the browser.
	 *
	 * @param object $conv Conversation.
	 */
	public function typing( $conv ) {
		NTAC_DB::conv_meta( $conv->id, array( 'typing_until' => time() + 12 ) );
	}

	/**
	 * How long the typing indicator lasts.
	 *
	 * @return int
	 */
	public function typing_ttl() {
		return 10;
	}

	/**
	 * Style.
	 *
	 * @return string
	 */
	public function style() {
		return 'Plain text for a small chat window. Short paragraphs, simple "-" lists if needed, no markdown headings, tables or bold. Full https links are fine.';
	}
}

/**
 * Registry.
 */
final class NTAC_Channels {

	/**
	 * Instances.
	 *
	 * @var NTAC_Channel[]|null
	 */
	private static $channels = null;

	/**
	 * Hooks.
	 */
	public static function init() {}

	/**
	 * All registered channels.
	 *
	 * @return NTAC_Channel[]
	 */
	public static function all() {
		if ( null === self::$channels ) {
			$list           = apply_filters( 'ntac_channels', array( 'web' => new NTAC_Channel_Web() ) );
			self::$channels = array();
			foreach ( (array) $list as $id => $c ) {
				if ( $c instanceof NTAC_Channel ) {
					self::$channels[ $id ] = $c;
				}
			}
		}
		return self::$channels;
	}

	/**
	 * One channel.
	 *
	 * @param string $id Id.
	 * @return NTAC_Channel|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Labels (known even when the Pro add-on is off, for old conversations).
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'web'      => __( 'Live chat', 'nodethemes-ai-chat' ),
			'telegram' => __( 'Telegram bot', 'nodethemes-ai-chat' ),
			'tgbiz'    => __( 'Telegram Business', 'nodethemes-ai-chat' ),
			'wa_cloud' => __( 'WhatsApp (Cloud API)', 'nodethemes-ai-chat' ),
			'wa_qr'    => __( 'WhatsApp (QR)', 'nodethemes-ai-chat' ),
		);
	}

	/**
	 * Label.
	 *
	 * @param string $id Id.
	 * @return string
	 */
	public static function label( $id ) {
		$l = self::labels();
		return isset( $l[ $id ] ) ? $l[ $id ] : $id;
	}
}
