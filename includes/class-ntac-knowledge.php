<?php
/**
 * Knowledge base: questions and answers the assistant may use.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge.
 */
final class NTAC_Knowledge {

	/**
	 * Entries.
	 *
	 * @param string $status active|pending|off|all.
	 * @param string $search Search text.
	 * @return array
	 */
	public static function all( $status = 'active', $search = '' ) {
		global $wpdb;
		$t     = NTAC_DB::t( 'knowledge' );
		$where = '1=1';
		$vals  = array();
		if ( 'all' !== $status ) {
			$where .= ' AND status = %s';
			$vals[] = $status;
		}
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (question LIKE %s OR answer LIKE %s)';
			array_push( $vals, $like, $like );
		}
		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT 1000"; // phpcs:ignore WordPress.DB
		return (array) ( $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ) ) : $wpdb->get_results( $sql ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Count by status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public static function count( $status ) {
		global $wpdb;
		$t = NTAC_DB::t( 'knowledge' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * One entry.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = NTAC_DB::t( 'knowledge' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Add an entry.
	 *
	 * @param string $q      Question.
	 * @param string $a      Answer.
	 * @param string $source manual|learned.
	 * @param string $status active|pending.
	 * @param int    $conv   Source conversation.
	 * @return int
	 */
	public static function add( $q, $a, $source = 'manual', $status = 'active', $conv = 0 ) {
		global $wpdb;
		$q = NTAC_Util::cut( sanitize_textarea_field( $q ), 1000, '' );
		$a = NTAC_Util::cut( sanitize_textarea_field( $a ), 4000, '' );
		if ( '' === trim( $q ) || '' === trim( $a ) ) {
			return 0;
		}
		$now = NTAC_Util::now();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			NTAC_DB::t( 'knowledge' ),
			array(
				'question' => $q,
				'answer'   => $a,
				'source'   => 'learned' === $source ? 'learned' : 'manual',
				'status'   => in_array( $status, array( 'active', 'pending', 'off' ), true ) ? $status : 'active',
				'conv_id'  => (int) $conv,
				'created'  => $now,
				'updated'  => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an entry.
	 *
	 * @param int   $id   Id.
	 * @param array $data question, answer, status.
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$d = array( 'updated' => NTAC_Util::now() );
		if ( isset( $data['question'] ) ) {
			$d['question'] = NTAC_Util::cut( sanitize_textarea_field( $data['question'] ), 1000, '' );
		}
		if ( isset( $data['answer'] ) ) {
			$d['answer'] = NTAC_Util::cut( sanitize_textarea_field( $data['answer'] ), 4000, '' );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'pending', 'off' ), true ) ) {
			$d['status'] = $data['status'];
		}
		$wpdb->update( NTAC_DB::t( 'knowledge' ), $d, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( NTAC_DB::t( 'knowledge' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Words of a text (any language), lower case.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function words( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$text = str_replace( array( 'ي', 'ك', '‌' ), array( 'ی', 'ک', ' ' ), $text ); // Arabic/Persian letter variants and zero-width non-joiner.
		preg_match_all( '/[\p{L}\p{N}]{2,}/u', $text, $m );
		$stop = array( 'the', 'and', 'for', 'you', 'your', 'are', 'can', 'what', 'how', 'is', 'do', 'does', 'to', 'of', 'in', 'on', 'a', 'an', 'it', 'my', 'me', 'we', 'our', 'with', 'have', 'has', 'هست', 'است', 'که', 'از', 'به', 'با', 'را', 'این', 'آن', 'برای', 'چه', 'من', 'شما', 'یک', 'هم' );
		return array_values( array_diff( array_unique( $m[0] ), $stop ) );
	}

	/**
	 * Entries relevant to the customer's text. Small knowledge bases are sent whole.
	 *
	 * @param string $text   Customer text (recent messages).
	 * @param int    $budget Max characters.
	 * @return array
	 */
	public static function relevant( $text, $budget = 9000 ) {
		$rows = self::all( 'active' );
		if ( ! $rows ) {
			return array();
		}
		$q      = self::words( $text );
		$scored = array();
		$total  = 0;
		foreach ( $rows as $r ) {
			$qw    = self::words( $r->question );
			$aw    = self::words( $r->answer );
			$score = 0;
			foreach ( $q as $w ) {
				foreach ( $qw as $x ) {
					if ( $w === $x ) {
						$score += 3;
					} elseif ( NTAC_Util::len( $w ) > 3 && ( 0 === strpos( $x, $w ) || 0 === strpos( $w, $x ) ) ) {
						$score += 1;
					}
				}
				if ( in_array( $w, $aw, true ) ) {
					++$score;
				}
			}
			$r->score = $score;
			$scored[] = $r;
			$total   += NTAC_Util::len( $r->question ) + NTAC_Util::len( $r->answer ) + 20;
		}
		usort(
			$scored,
			function ( $a, $b ) {
				return $b->score === $a->score ? (int) $b->id - (int) $a->id : $b->score - $a->score;
			}
		);
		$out  = array();
		$used = 0;
		foreach ( $scored as $r ) {
			if ( $total > $budget && 0 === $r->score && count( $out ) >= 6 ) {
				break;
			}
			$len = NTAC_Util::len( $r->question ) + NTAC_Util::len( $r->answer ) + 20;
			if ( $used + $len > $budget ) {
				continue;
			}
			$out[] = $r;
			$used += $len;
		}
		return $out;
	}

	/**
	 * Count that entries were used in an answer.
	 *
	 * @param array $ids Ids.
	 */
	public static function touch( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( ! $ids ) {
			return;
		}
		$t = NTAC_DB::t( 'knowledge' );
		$wpdb->query( "UPDATE {$t} SET uses = uses + 1 WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB
	}
}
