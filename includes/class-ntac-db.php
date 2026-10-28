<?php
/**
 * Tables and data access.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conversations, messages and knowledge.
 */
final class NTAC_DB {

	/**
	 * Table names.
	 *
	 * @param string $t conversations|messages|knowledge.
	 * @return string
	 */
	public static function t( $t ) {
		global $wpdb;
		return $wpdb->prefix . 'ntac_' . $t;
	}

	/**
	 * Create or update the tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$conv = self::t( 'conversations' );
		$msg  = self::t( 'messages' );
		$kb   = self::t( 'knowledge' );
		dbDelta(
			"CREATE TABLE {$conv} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel varchar(20) NOT NULL DEFAULT 'web',
			ext_id varchar(191) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			contact varchar(190) NOT NULL DEFAULT '',
			ai_on tinyint(1) NOT NULL DEFAULT 1,
			paused_until datetime NULL,
			needs_admin tinyint(1) NOT NULL DEFAULT 0,
			handled_id bigint(20) unsigned NOT NULL DEFAULT 0,
			unread int(10) unsigned NOT NULL DEFAULT 0,
			last_preview varchar(255) NOT NULL DEFAULT '',
			last_at datetime NOT NULL,
			last_customer_at datetime NULL,
			notified_at datetime NULL,
			offline_at datetime NULL,
			meta longtext NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY channel_ext (channel,ext_id),
			KEY last_at (last_at)
		) $c;"
		);
		dbDelta(
			"CREATE TABLE {$msg} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conv_id bigint(20) unsigned NOT NULL,
			role varchar(10) NOT NULL DEFAULT 'customer',
			body text NOT NULL,
			ext_id varchar(100) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'ok',
			error varchar(255) NOT NULL DEFAULT '',
			meta text NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conv_id (conv_id,id),
			KEY ext_id (ext_id)
		) $c;"
		);
		dbDelta(
			"CREATE TABLE {$kb} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question text NOT NULL,
			answer text NOT NULL,
			source varchar(10) NOT NULL DEFAULT 'manual',
			status varchar(10) NOT NULL DEFAULT 'active',
			uses int(10) unsigned NOT NULL DEFAULT 0,
			conv_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;"
		);
		update_option( 'ntac_db_version', NTAC_DB_VERSION, false );
	}

	/**
	 * Run install() after an update that changed the tables.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'ntac_db_version' ) !== NTAC_DB_VERSION ) {
			self::install();
		}
	}

	/* ------------------------------------------------------------------ conversations */

	/**
	 * Decode a conversation row.
	 *
	 * @param object|null $row Row.
	 * @return object|null
	 */
	private static function conv_row( $row ) {
		if ( ! $row ) {
			return null;
		}
		$m          = json_decode( (string) $row->meta, true );
		$row->meta  = is_array( $m ) ? $m : array();
		$row->id    = (int) $row->id;
		$row->ai_on = (int) $row->ai_on;
		return $row;
	}

	/**
	 * Conversation by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function conv( $id ) {
		global $wpdb;
		$t = self::t( 'conversations' );
		return self::conv_row( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Conversation by channel + external id.
	 *
	 * @param string $channel Channel.
	 * @param string $ext_id  External id.
	 * @return object|null
	 */
	public static function conv_by_ext( $channel, $ext_id ) {
		global $wpdb;
		$t = self::t( 'conversations' );
		return self::conv_row( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE channel = %s AND ext_id = %s", $channel, $ext_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Find or create a conversation.
	 *
	 * @param string $channel Channel.
	 * @param string $ext_id  External id.
	 * @param array  $data    name, contact, meta.
	 * @return object|null
	 */
	public static function conv_open( $channel, $ext_id, $data = array() ) {
		global $wpdb;
		foreach ( array( 'name', 'contact' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$data[ $k ] = NTAC_Util::cut( sanitize_text_field( (string) $data[ $k ] ), 180, '' );
			}
		}
		$c = self::conv_by_ext( $channel, $ext_id );
		if ( $c ) {
			$upd = array();
			if ( ! empty( $data['name'] ) && $data['name'] !== $c->name ) {
				$upd['name'] = $data['name'];
			}
			if ( ! empty( $data['contact'] ) && $data['contact'] !== $c->contact ) {
				$upd['contact'] = $data['contact'];
			}
			if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
				$upd['meta'] = array_merge( $c->meta, $data['meta'] );
			}
			if ( $upd ) {
				self::conv_update( $c->id, $upd );
				$c = self::conv( $c->id );
			}
			return $c;
		}
		$now = NTAC_Util::now();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			self::t( 'conversations' ),
			array(
				'channel' => $channel,
				'ext_id'  => $ext_id,
				'name'    => isset( $data['name'] ) ? NTAC_Util::cut( (string) $data['name'], 180, '' ) : '',
				'contact' => isset( $data['contact'] ) ? NTAC_Util::cut( (string) $data['contact'], 180, '' ) : '',
				'meta'    => wp_json_encode( isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array() ),
				'last_at' => $now,
				'created' => $now,
			)
		);
		if ( ! $wpdb->insert_id ) {
			// Lost a race with a parallel request: read the row it created.
			return self::conv_by_ext( $channel, $ext_id );
		}
		do_action( 'ntac_conversation_created', (int) $wpdb->insert_id );
		return self::conv( (int) $wpdb->insert_id );
	}

	/**
	 * Update a conversation.
	 *
	 * @param int   $id   Id.
	 * @param array $data Columns.
	 */
	public static function conv_update( $id, $data ) {
		global $wpdb;
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}
		$wpdb->update( self::t( 'conversations' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Merge keys into a conversation's meta.
	 *
	 * @param int   $id   Id.
	 * @param array $meta Keys.
	 */
	public static function conv_meta( $id, $meta ) {
		$c = self::conv( $id );
		if ( $c ) {
			self::conv_update( $id, array( 'meta' => array_merge( $c->meta, $meta ) ) );
		}
	}

	/**
	 * Delete a conversation and its messages.
	 *
	 * @param int $id Id.
	 */
	public static function conv_delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::t( 'messages' ), array( 'conv_id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( self::t( 'conversations' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Conversation list for the inbox.
	 *
	 * @param array $args filter (all|needs|ai_off), search, limit.
	 * @return array
	 */
	public static function conv_list( $args = array() ) {
		global $wpdb;
		$a     = wp_parse_args( $args, array( 'filter' => 'all', 'search' => '', 'limit' => 60 ) );
		$t     = self::t( 'conversations' );
		$where = '1=1';
		$vals  = array();
		if ( 'needs' === $a['filter'] ) {
			$where .= ' AND needs_admin = 1';
		} elseif ( 'ai_off' === $a['filter'] ) {
			$where .= ' AND ai_on = 0';
		} elseif ( 'unread' === $a['filter'] ) {
			$where .= ' AND unread > 0';
		} elseif ( in_array( $a['filter'], array_keys( NTAC_Channels::labels() ), true ) ) {
			$where .= ' AND channel = %s';
			$vals[] = $a['filter'];
		}
		if ( '' !== $a['search'] ) {
			$like   = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$where .= ' AND (name LIKE %s OR contact LIKE %s OR last_preview LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		$vals[] = max( 1, min( 200, (int) $a['limit'] ) );
		$sql    = "SELECT * FROM {$t} WHERE {$where} ORDER BY last_at DESC LIMIT %d"; // phpcs:ignore WordPress.DB
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $vals ) ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'conv_row' ), (array) $rows );
	}

	/**
	 * Counters for the menu badge and filters.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;
		$t = self::t( 'conversations' );
		$r = $wpdb->get_row( "SELECT COUNT(*) AS total, SUM(needs_admin) AS needs, SUM(unread > 0) AS unread FROM {$t}" ); // phpcs:ignore WordPress.DB
		return array(
			'total'  => $r ? (int) $r->total : 0,
			'needs'  => $r ? (int) $r->needs : 0,
			'unread' => $r ? (int) $r->unread : 0,
		);
	}

	/* ------------------------------------------------------------------ messages */

	/**
	 * Add a message and update the conversation summary.
	 *
	 * @param int    $conv_id Conversation.
	 * @param string $role    customer|ai|admin|system.
	 * @param string $body    Text.
	 * @param array  $extra   ext_id, status, error, meta.
	 * @return int Message id (0 when it was a duplicate).
	 */
	public static function msg_add( $conv_id, $role, $body, $extra = array() ) {
		global $wpdb;
		$ext = isset( $extra['ext_id'] ) ? (string) $extra['ext_id'] : '';
		if ( '' !== $ext ) {
			$t   = self::t( 'messages' );
			$dup = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE conv_id = %d AND ext_id = %s LIMIT 1", $conv_id, $ext ) ); // phpcs:ignore WordPress.DB
			if ( $dup ) {
				return 0;
			}
		}
		$now = NTAC_Util::now();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			self::t( 'messages' ),
			array(
				'conv_id' => (int) $conv_id,
				'role'    => $role,
				'body'    => (string) $body,
				'ext_id'  => $ext,
				'status'  => isset( $extra['status'] ) ? $extra['status'] : 'ok',
				'error'   => isset( $extra['error'] ) ? NTAC_Util::cut( (string) $extra['error'], 250, '' ) : '',
				'meta'    => isset( $extra['meta'] ) ? wp_json_encode( $extra['meta'] ) : null,
				'created' => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $id && 'system' !== $role ) {
			$t   = self::t( 'conversations' );
			$pre = NTAC_Util::cut( preg_replace( '/\s+/u', ' ', (string) $body ), 120 );
			if ( 'customer' === $role ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET last_at = %s, last_customer_at = %s, last_preview = %s, unread = unread + 1 WHERE id = %d", $now, $now, $pre, $conv_id ) ); // phpcs:ignore WordPress.DB
			} else {
				$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET last_at = %s, last_preview = %s WHERE id = %d", $now, $pre, $conv_id ) ); // phpcs:ignore WordPress.DB
			}
		}
		return $id;
	}

	/**
	 * Update a message.
	 *
	 * @param int   $id   Id.
	 * @param array $data Columns.
	 */
	public static function msg_update( $id, $data ) {
		global $wpdb;
		$wpdb->update( self::t( 'messages' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Messages of a conversation.
	 *
	 * @param int  $conv_id Conversation.
	 * @param int  $after   Only ids greater than this.
	 * @param int  $limit   Max rows (newest).
	 * @param bool $public  Hide system notes.
	 * @return array
	 */
	public static function msgs( $conv_id, $after = 0, $limit = 200, $public = false ) {
		global $wpdb;
		$t    = self::t( 'messages' );
		$role = $public ? " AND role <> 'system'" : '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE conv_id = %d AND id > %d{$role} ORDER BY id DESC LIMIT %d", $conv_id, $after, $limit ) ); // phpcs:ignore WordPress.DB
		return array_reverse( (array) $rows );
	}

	/**
	 * A message by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function msg( $id ) {
		global $wpdb;
		$t = self::t( 'messages' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Customer messages not answered yet.
	 *
	 * @param object $conv Conversation.
	 * @return array
	 */
	public static function pending( $conv ) {
		global $wpdb;
		$t = self::t( 'messages' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE conv_id = %d AND id > %d AND role = 'customer' ORDER BY id ASC", $conv->id, (int) $conv->handled_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Conversations whose customer messages are still waiting (for the safety-net sweep).
	 *
	 * @param int $older_than Seconds since the last customer message.
	 * @param int $limit      Max.
	 * @return int[]
	 */
	public static function waiting( $older_than, $limit = 5 ) {
		global $wpdb;
		$c    = self::t( 'conversations' );
		$m    = self::t( 'messages' );
		$time = gmdate( 'Y-m-d H:i:s', time() - $older_than );
		$from = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$sql  = "SELECT c.id FROM {$c} c WHERE c.ai_on = 1 AND c.last_customer_at IS NOT NULL AND c.last_customer_at < %s AND c.last_customer_at > %s
			AND EXISTS (SELECT 1 FROM {$m} m WHERE m.conv_id = c.id AND m.role = 'customer' AND m.id > c.handled_id) ORDER BY c.last_customer_at ASC LIMIT %d";
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $time, $from, $limit ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete conversations without activity for N days.
	 *
	 * @param int $days Days.
	 * @return int Deleted conversations.
	 */
	public static function purge( $days ) {
		global $wpdb;
		if ( $days <= 0 ) {
			return 0;
		}
		$c   = self::t( 'conversations' );
		$m   = self::t( 'messages' );
		$cut = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$c} WHERE last_at < %s LIMIT 500", $cut ) ) ); // phpcs:ignore WordPress.DB
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', $ids );
		$wpdb->query( "DELETE FROM {$m} WHERE conv_id IN ({$in})" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$c} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB
		return count( $ids );
	}
}
