<?php
/**
 * Admin screens: Inbox, Knowledge, Settings.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class NTAC_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_ntac_kb_save', array( __CLASS__, 'kb_save' ) );
		add_action( 'admin_post_ntac_kb_action', array( __CLASS__, 'kb_action' ) );
		add_filter( 'plugin_action_links_' . NTAC_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Is this one of our screens?
	 *
	 * @return bool
	 */
	public static function our_screen() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $s && false !== strpos( (string) $s->id, 'ntac' );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( __( 'AI Chat', 'nodethemes-ai-chat' ), __( 'AI Chat', 'nodethemes-ai-chat' ), NTAC_Rest::inbox_cap(), 'ntac-inbox', array( __CLASS__, 'page_inbox' ), 'dashicons-format-chat', 58 );
		add_submenu_page( 'ntac-inbox', __( 'Inbox', 'nodethemes-ai-chat' ), __( 'Inbox', 'nodethemes-ai-chat' ), NTAC_Rest::inbox_cap(), 'ntac-inbox', array( __CLASS__, 'page_inbox' ) );
		add_submenu_page( 'ntac-inbox', __( 'Knowledge', 'nodethemes-ai-chat' ), __( 'Knowledge', 'nodethemes-ai-chat' ), 'manage_options', 'ntac-knowledge', array( __CLASS__, 'page_knowledge' ) );
		add_submenu_page( 'ntac-inbox', __( 'Settings', 'nodethemes-ai-chat' ), __( 'Settings', 'nodethemes-ai-chat' ), 'manage_options', 'ntac-settings', array( __CLASS__, 'page_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu_badge' ), 999 );
	}

	/**
	 * "Needs you" count next to the menu (added after registration so page hooks stay stable).
	 */
	public static function menu_badge() {
		global $menu;
		$n = NTAC_DB::counts()['needs'];
		if ( ! $n || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'ntac-inbox' === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	}

	/**
	 * Plugins list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=ntac-settings' ) ) . '">' . esc_html__( 'Settings', 'nodethemes-ai-chat' ) . '</a>' );
		if ( ! NTAC_Pro::has_code() ) {
			$links[] = '<a href="' . esc_url( NTAC_Pro::url( 'plugins' ) ) . '" target="_blank" rel="noopener" style="color:#7c3aed;font-weight:600">' . esc_html__( 'Get Pro', 'nodethemes-ai-chat' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Styles and scripts.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'ntac' ) ) {
			return;
		}
		wp_enqueue_style( 'ntac-admin', NTAC_URL . 'assets/admin.css', array(), NTAC_VERSION );
		wp_enqueue_script( 'ntac-admin', NTAC_URL . 'assets/admin.js', array(), NTAC_VERSION, true );
		wp_localize_script(
			'ntac-admin',
			'ntacAdmin',
			array(
				'rest'    => esc_url_raw( rest_url( 'ntac/v1/admin/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'ntac_admin' ),
				'pro'     => NTAC_Pro::active(),
				'proUrl'  => NTAC_Pro::url( 'inbox' ),
				'i18n'    => array(
					'empty'      => __( 'No conversations yet. Open your website and send a test message in the chat bubble.', 'nodethemes-ai-chat' ),
					'pick'       => __( 'Select a conversation.', 'nodethemes-ai-chat' ),
					'customer'   => __( 'Customer', 'nodethemes-ai-chat' ),
					'ai'         => __( 'AI', 'nodethemes-ai-chat' ),
					'admin'      => __( 'You', 'nodethemes-ai-chat' ),
					'system'     => __( 'Note', 'nodethemes-ai-chat' ),
					'reply'      => __( 'Write your answer… (Enter = send, Shift+Enter = new line)', 'nodethemes-ai-chat' ),
					'send'       => __( 'Send', 'nodethemes-ai-chat' ),
					'aiOn'       => __( 'AI answers: ON', 'nodethemes-ai-chat' ),
					'aiOff'      => __( 'AI answers: OFF', 'nodethemes-ai-chat' ),
					'aiPaused'   => __( 'AI paused after your reply until', 'nodethemes-ai-chat' ),
					'done'       => __( 'Mark as handled', 'nodethemes-ai-chat' ),
					'del'        => __( 'Delete', 'nodethemes-ai-chat' ),
					'delAsk'     => __( 'Delete this conversation and all its messages?', 'nodethemes-ai-chat' ),
					'needs'      => __( 'Needs you', 'nodethemes-ai-chat' ),
					'failed'     => __( 'Not delivered', 'nodethemes-ai-chat' ),
					'learn'      => __( 'Save as knowledge', 'nodethemes-ai-chat' ),
					'learnPro'   => __( 'Save as knowledge (Pro)', 'nodethemes-ai-chat' ),
					'noChannel'  => __( 'This channel is off, so replies cannot be sent from here.', 'nodethemes-ai-chat' ),
					'all'        => __( 'All', 'nodethemes-ai-chat' ),
					'filterNeed' => __( 'Needs you', 'nodethemes-ai-chat' ),
					'unread'     => __( 'Unread', 'nodethemes-ai-chat' ),
					'search'     => __( 'Search name or message', 'nodethemes-ai-chat' ),
					'page'       => __( 'Page', 'nodethemes-ai-chat' ),
					'back'       => __( 'Back', 'nodethemes-ai-chat' ),
					'testing'    => __( 'Testing…', 'nodethemes-ai-chat' ),
					'loading'    => __( 'Loading…', 'nodethemes-ai-chat' ),
					'viaApp'     => __( 'sent from the app', 'nodethemes-ai-chat' ),
					'viaTg'      => __( 'sent from Telegram', 'nodethemes-ai-chat' ),
				),
			)
		);
	}

	/**
	 * Flash message (set by the save handlers).
	 */
	public static function flash() {
		$k = 'ntac_flash_' . get_current_user_id();
		$f = get_transient( $k );
		if ( is_array( $f ) ) {
			delete_transient( $k );
			echo '<div class="ntac-flash ' . esc_attr( $f[0] ) . '">' . esc_html( $f[1] ) . '</div>';
		}
	}

	/**
	 * Page header.
	 *
	 * @param string $title Title.
	 * @param string $sub   Subtitle.
	 */
	public static function hero( $title, $sub ) {
		$pro = NTAC_Pro::active();
		echo '<div class="ntac-hero"><div><h1>' . esc_html( $title ) . ( $pro ? ' <span class="ntac-protag on">PRO</span>' : '' ) . '</h1><p>' . esc_html( $sub ) . '</p></div><div class="ntac-hero-r">NodeThemes AI Chat v' . esc_html( NTAC_VERSION ) . '</div></div>';
	}

	/**
	 * Admin notices: free monthly allowance.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) || NTAC_Pro::active() ) {
			return;
		}
		$s = get_current_screen();
		if ( ! $s || ! ( self::our_screen() || in_array( $s->id, array( 'dashboard', 'plugins' ), true ) ) ) {
			return;
		}
		$u     = NTAC_Pro::usage();
		$limit = NTAC_Pro::limit();
		if ( $limit <= 0 ) {
			return;
		}
		if ( (int) $u['ai'] >= $limit ) {
			$msg = sprintf(
				/* translators: 1: limit, 2: missed messages */
				_n( 'NodeThemes AI Chat: the %1$d free AI replies of this month are used up. %2$d message was not answered by the AI and is waiting in your inbox.', 'NodeThemes AI Chat: the %1$d free AI replies of this month are used up. %2$d messages were not answered by the AI and are waiting in your inbox.', max( 1, (int) $u['missed'] ), 'nodethemes-ai-chat' ),
				$limit,
				(int) $u['missed']
			);
			echo '<div class="notice notice-warning"><p><strong>' . esc_html( $msg ) . '</strong> <a class="button button-primary" style="margin-left:6px" href="' . esc_url( NTAC_Pro::url( 'limit' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get unlimited replies with Pro', 'nodethemes-ai-chat' ) . '</a></p></div>';
		} elseif ( (int) $u['ai'] >= $limit * 0.8 && self::our_screen() ) {
			/* translators: 1: used, 2: limit */
			echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'NodeThemes AI Chat: %1$d of %2$d free AI replies used this month.', 'nodethemes-ai-chat' ), (int) $u['ai'], $limit ) ) . ' <a href="' . esc_url( NTAC_Pro::url( 'limit80' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Pro has no limit', 'nodethemes-ai-chat' ) . '</a></p></div>';
		}
	}

	/* ------------------------------------------------------------------ Inbox */

	/**
	 * Inbox page (the list and the thread are drawn by admin.js).
	 */
	public static function page_inbox() {
		echo '<div class="wrap ntac-wrap">';
		self::hero( __( 'Inbox', 'nodethemes-ai-chat' ), __( 'Every conversation from your website chat (and Telegram / WhatsApp with Pro). Conversations marked "Needs you" are questions the assistant passed to you.', 'nodethemes-ai-chat' ) );
		self::setup_checklist();
		$open = isset( $_GET['conv'] ) ? absint( $_GET['conv'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div id="ntac-inbox" class="ntac-inbox" data-open="' . esc_attr( $open ) . '"><p>' . esc_html__( 'Loading…', 'nodethemes-ai-chat' ) . '</p></div>';
		echo '<noscript><p>' . esc_html__( 'The inbox needs JavaScript.', 'nodethemes-ai-chat' ) . '</p></noscript>';
		echo '</div>';
	}

	/**
	 * First steps until everything is set up.
	 */
	private static function setup_checklist() {
		$key  = NTAC_Settings::has_key( NTAC_Settings::get( 'provider' ) );
		$biz  = '' !== trim( (string) NTAC_Settings::get( 'business' ) );
		$conv = NTAC_DB::counts()['total'] > 0;
		if ( $key && $biz && $conv ) {
			return;
		}
		$steps = array(
			array( $key, __( 'Add your AI API key and press "Test connection"', 'nodethemes-ai-chat' ), admin_url( 'admin.php?page=ntac-settings&tab=ai' ) ),
			array( $biz, __( 'Describe your business: what you sell, prices, delivery, opening hours, contact', 'nodethemes-ai-chat' ), admin_url( 'admin.php?page=ntac-settings&tab=business' ) ),
			array( $conv, __( 'Open your website and send a test message in the chat bubble', 'nodethemes-ai-chat' ), home_url( '/' ) ),
		);
		echo '<div class="ntac-card"><h2>' . esc_html__( 'Get started in 3 steps', 'nodethemes-ai-chat' ) . '</h2><ul class="ntac-steps">';
		foreach ( $steps as $s ) {
			echo '<li class="' . ( $s[0] ? 'done' : '' ) . '"><a href="' . esc_url( $s[2] ) . '"' . ( false === strpos( $s[2], 'wp-admin' ) ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $s[1] ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	/* ------------------------------------------------------------------ Knowledge */

	/**
	 * Knowledge page.
	 */
	public static function page_knowledge() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only view parameters.
		$tab    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
		$tab    = in_array( $tab, array( 'active', 'pending', 'off' ), true ) ? $tab : 'active';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$edit   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		// phpcs:enable
		$rows    = NTAC_Knowledge::all( $tab, $search );
		$editing = $edit ? NTAC_Knowledge::get( $edit ) : null;
		$post    = esc_url( admin_url( 'admin-post.php' ) );
		$base    = admin_url( 'admin.php?page=ntac-knowledge' );

		echo '<div class="wrap ntac-wrap">';
		self::hero( __( 'Knowledge', 'nodethemes-ai-chat' ), __( 'Questions and answers the assistant may use. Write them the way you would answer a customer. The assistant never answers with facts that are not here or in your business description.', 'nodethemes-ai-chat' ) );
		self::flash();

		echo '<div class="ntac-cols"><div>';
		echo '<div class="ntac-card"><h2>' . esc_html( $editing ? __( 'Edit entry', 'nodethemes-ai-chat' ) : __( 'Add a question and answer', 'nodethemes-ai-chat' ) ) . '</h2>';
		echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		wp_nonce_field( 'ntac_kb' );
		echo '<input type="hidden" name="action" value="ntac_kb_save"><input type="hidden" name="id" value="' . esc_attr( $editing ? $editing->id : 0 ) . '"><input type="hidden" name="back" value="' . esc_attr( $tab ) . '">';
		echo '<p><label for="ntac-q"><strong>' . esc_html__( 'Question', 'nodethemes-ai-chat' ) . '</strong></label><textarea id="ntac-q" name="question" rows="2" required placeholder="' . esc_attr__( 'e.g. Do you deliver on weekends?', 'nodethemes-ai-chat' ) . '">' . esc_textarea( $editing ? $editing->question : '' ) . '</textarea></p>';
		echo '<p><label for="ntac-a"><strong>' . esc_html__( 'Answer', 'nodethemes-ai-chat' ) . '</strong></label><textarea id="ntac-a" name="answer" rows="5" required placeholder="' . esc_attr__( 'e.g. Yes, on Saturdays from 10:00 to 16:00. Sunday deliveries are not possible.', 'nodethemes-ai-chat' ) . '">' . esc_textarea( $editing ? $editing->answer : '' ) . '</textarea></p>';
		echo '<p><button class="ntac-btn primary" type="submit">' . esc_html( $editing ? __( 'Save changes', 'nodethemes-ai-chat' ) : __( 'Add to knowledge', 'nodethemes-ai-chat' ) ) . '</button>';
		if ( $editing ) {
			echo ' <a class="ntac-btn" href="' . esc_url( add_query_arg( 'status', $tab, $base ) ) . '">' . esc_html__( 'Cancel', 'nodethemes-ai-chat' ) . '</a>';
		}
		echo '</p></form></div>';

		echo '<div class="ntac-card"><h2>' . esc_html__( 'Learning from your answers', 'nodethemes-ai-chat' ) . '</h2>';
		if ( NTAC_Pro::active() ) {
			echo '<p class="ntac-help">' . esc_html__( 'When you answer a customer, the assistant turns your reply into a general question and answer (without the customer\'s personal details) and puts it under "Waiting for approval". Approve it and the assistant uses it from then on.', 'nodethemes-ai-chat' ) . '</p>';
		} else {
			echo '<p class="ntac-help">' . esc_html__( 'With Pro, every answer you give a customer can become a new entry here automatically (you approve it first), so the assistant gets better every week.', 'nodethemes-ai-chat' ) . '</p>' . NTAC_Pro::lock( __( 'Learn from my answers', 'nodethemes-ai-chat' ), 'learn' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></div><div>';

		$counts = array(
			'active'  => NTAC_Knowledge::count( 'active' ),
			'pending' => NTAC_Knowledge::count( 'pending' ),
			'off'     => NTAC_Knowledge::count( 'off' ),
		);
		$labels = array(
			'active'  => __( 'In use', 'nodethemes-ai-chat' ),
			'pending' => __( 'Waiting for approval', 'nodethemes-ai-chat' ),
			'off'     => __( 'Switched off', 'nodethemes-ai-chat' ),
		);
		echo '<div class="ntac-card"><div class="ntac-tabs">';
		foreach ( $labels as $k => $l ) {
			echo '<a class="ntac-tab' . ( $k === $tab ? ' on' : '' ) . '" href="' . esc_url( add_query_arg( 'status', $k, $base ) ) . '">' . esc_html( $l ) . ' <span class="ntac-count">' . (int) $counts[ $k ] . '</span></a>';
		}
		echo '</div>';
		echo '<form method="get" class="ntac-inline"><input type="hidden" name="page" value="ntac-knowledge"><input type="hidden" name="status" value="' . esc_attr( $tab ) . '"><label class="screen-reader-text" for="ntac-kbs">' . esc_html__( 'Search', 'nodethemes-ai-chat' ) . '</label><input id="ntac-kbs" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search', 'nodethemes-ai-chat' ) . '"><button class="ntac-btn" type="submit">' . esc_html__( 'Search', 'nodethemes-ai-chat' ) . '</button></form>';

		if ( ! $rows ) {
			echo '<p class="ntac-help">' . esc_html( 'pending' === $tab ? __( 'Nothing is waiting for approval.', 'nodethemes-ai-chat' ) : __( 'No entries yet.', 'nodethemes-ai-chat' ) ) . '</p>';
		}
		foreach ( $rows as $r ) {
			echo '<div class="ntac-kb"><div class="ntac-kb-q" dir="auto">' . esc_html( $r->question ) . '</div><div class="ntac-kb-a" dir="auto">' . esc_html( $r->answer ) . '</div><div class="ntac-kb-m">';
			echo '<span class="ntac-chip">' . esc_html( 'learned' === $r->source ? __( 'Learned from your reply', 'nodethemes-ai-chat' ) : __( 'Written by you', 'nodethemes-ai-chat' ) ) . '</span> ';
			/* translators: %d: number of answers */
			echo '<span class="ntac-help">' . esc_html( sprintf( _n( 'used in %d answer', 'used in %d answers', (int) $r->uses, 'nodethemes-ai-chat' ), (int) $r->uses ) ) . '</span>';
			echo '<span class="ntac-kb-act">';
			$act = function ( $do, $label, $cls = '' ) use ( $r, $tab, $post ) {
				echo '<form method="post" action="' . $post . '" class="ntac-inline-form">'; // phpcs:ignore WordPress.Security.EscapeOutput
				wp_nonce_field( 'ntac_kb' );
				echo '<input type="hidden" name="action" value="ntac_kb_action"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . (int) $r->id . '"><input type="hidden" name="back" value="' . esc_attr( $tab ) . '"><button type="submit" class="ntac-btn sm ' . esc_attr( $cls ) . '"' . ( 'delete' === $do ? ' data-confirm="' . esc_attr__( 'Delete this entry?', 'nodethemes-ai-chat' ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
			};
			if ( 'pending' === $tab ) {
				$act( 'approve', __( 'Approve', 'nodethemes-ai-chat' ), 'primary' );
			}
			echo '<a class="ntac-btn sm" href="' . esc_url( add_query_arg( array( 'status' => $tab, 'edit' => $r->id ), $base ) ) . '">' . esc_html__( 'Edit', 'nodethemes-ai-chat' ) . '</a>';
			if ( 'active' === $tab ) {
				$act( 'off', __( 'Switch off', 'nodethemes-ai-chat' ) );
			} elseif ( 'off' === $tab ) {
				$act( 'approve', __( 'Use again', 'nodethemes-ai-chat' ) );
			}
			$act( 'delete', 'pending' === $tab ? __( 'Reject', 'nodethemes-ai-chat' ) : __( 'Delete', 'nodethemes-ai-chat' ), 'danger' );
			echo '</span></div></div>';
		}
		echo '</div></div></div></div>';
	}

	/**
	 * Save a knowledge entry.
	 */
	public static function kb_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nodethemes-ai-chat' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ntac_kb' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$q  = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
		$a  = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';
		$bk = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'active';
		if ( '' === trim( $q ) || '' === trim( $a ) ) {
			set_transient( 'ntac_flash_' . get_current_user_id(), array( 'err', __( 'Please fill in both the question and the answer.', 'nodethemes-ai-chat' ) ), 60 );
		} elseif ( $id ) {
			NTAC_Knowledge::update(
				$id,
				array(
					'question' => $q,
					'answer'   => $a,
				)
			);
			set_transient( 'ntac_flash_' . get_current_user_id(), array( 'ok', __( 'Entry saved.', 'nodethemes-ai-chat' ) ), 60 );
		} else {
			NTAC_Knowledge::add( $q, $a );
			$bk = 'active';
			set_transient( 'ntac_flash_' . get_current_user_id(), array( 'ok', __( 'Added. The assistant uses it from now on.', 'nodethemes-ai-chat' ) ), 60 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ntac-knowledge&status=' . $bk ) );
		exit;
	}

	/**
	 * Approve / switch off / delete.
	 */
	public static function kb_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nodethemes-ai-chat' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ntac_kb' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$bk = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'active';
		if ( 'delete' === $do ) {
			NTAC_Knowledge::delete( $id );
		} elseif ( 'approve' === $do ) {
			NTAC_Knowledge::update( $id, array( 'status' => 'active' ) );
		} elseif ( 'off' === $do ) {
			NTAC_Knowledge::update( $id, array( 'status' => 'off' ) );
		}
		set_transient( 'ntac_flash_' . get_current_user_id(), array( 'ok', __( 'Done.', 'nodethemes-ai-chat' ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=ntac-knowledge&status=' . $bk ) );
		exit;
	}

	/* ------------------------------------------------------------------ Settings */

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	public static function tabs() {
		$t = array(
			'ai'       => __( 'AI', 'nodethemes-ai-chat' ),
			'business' => __( 'Business & answers', 'nodethemes-ai-chat' ),
			'widget'   => __( 'Website chat', 'nodethemes-ai-chat' ),
			'channels' => __( 'Telegram & WhatsApp', 'nodethemes-ai-chat' ),
			'notify'   => __( 'Notifications', 'nodethemes-ai-chat' ),
			'privacy'  => __( 'Privacy & data', 'nodethemes-ai-chat' ),
			'usage'    => __( 'Usage', 'nodethemes-ai-chat' ),
		);
		return (array) apply_filters( 'ntac_settings_tabs', $t );
	}

	/**
	 * A settings row.
	 *
	 * @param string $label Label.
	 * @param string $for   Field id.
	 * @param string $html  Field HTML (already escaped).
	 * @param string $help  Help text.
	 */
	public static function row( $label, $for, $html, $help = '' ) {
		echo '<div class="ntac-row"><div class="ntac-row-l"><label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label></div><div>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
		if ( '' !== $help ) {
			echo '<p class="ntac-help">' . esc_html( $help ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Yes/no checkbox.
	 *
	 * @param string $k     Key.
	 * @param string $label Label.
	 * @return string
	 */
	public static function check( $k, $label ) {
		return '<label class="ntac-check"><input type="checkbox" id="ntac-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="yes"' . checked( 'yes', NTAC_Settings::get( $k ), false ) . '> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * Text input.
	 *
	 * @param string $k           Key.
	 * @param string $placeholder Placeholder.
	 * @param string $type        Type.
	 * @return string
	 */
	public static function input( $k, $placeholder = '', $type = 'text' ) {
		return '<input type="' . esc_attr( $type ) . '" id="ntac-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) NTAC_Settings::get( $k ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( 'number' === $type ? ' min="0" step="any" class="ntac-short"' : '' ) . '>';
	}

	/**
	 * Textarea.
	 *
	 * @param string $k           Key.
	 * @param int    $rows        Rows.
	 * @param string $placeholder Placeholder.
	 * @return string
	 */
	public static function textarea( $k, $rows, $placeholder = '' ) {
		return '<textarea id="ntac-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" rows="' . (int) $rows . '" placeholder="' . esc_attr( $placeholder ) . '" dir="auto">' . esc_textarea( (string) NTAC_Settings::get( $k ) ) . '</textarea>';
	}

	/**
	 * Select.
	 *
	 * @param string $k       Key.
	 * @param array  $options value => label.
	 * @return string
	 */
	public static function select( $k, $options ) {
		$h = '<select id="ntac-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">';
		foreach ( $options as $v => $l ) {
			$h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) NTAC_Settings::get( $k ), (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	/**
	 * Settings page.
	 */
	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ai'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'ai';
		}
		echo '<div class="wrap ntac-wrap">';
		self::hero( __( 'Settings', 'nodethemes-ai-chat' ), __( 'Connect your AI, tell the assistant about your business and choose where it answers.', 'nodethemes-ai-chat' ) );
		self::flash();
		echo '<nav class="ntac-tabs" aria-label="' . esc_attr__( 'Settings sections', 'nodethemes-ai-chat' ) . '">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="ntac-tab' . ( $k === $tab ? ' on' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=ntac-settings&tab=' . $k ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		if ( has_action( 'ntac_settings_tab_' . $tab ) ) {
			do_action( 'ntac_settings_tab_' . $tab );
		} elseif ( 'channels' === $tab ) {
			self::tab_channels_locked();
		} elseif ( 'usage' === $tab ) {
			self::tab_usage();
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ntac-card">';
			wp_nonce_field( 'ntac_save_settings' );
			echo '<input type="hidden" name="action" value="ntac_save_settings"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
			call_user_func( array( __CLASS__, 'tab_' . $tab ) );
			echo '<p class="ntac-savebar"><button type="submit" class="ntac-btn primary lg">' . esc_html__( 'Save settings', 'nodethemes-ai-chat' ) . '</button></p></form>';
		}
		echo '<div class="ntac-support">' . esc_html__( 'Need help?', 'nodethemes-ai-chat' ) . ' ' . esc_html__( 'Email', 'nodethemes-ai-chat' ) . ' <a href="mailto:nodethemes.com@gmail.com">nodethemes.com@gmail.com</a> &middot; WhatsApp <a href="https://wa.me/447441429969" target="_blank" rel="noopener">+44 7441 429969</a> &middot; Telegram <a href="https://t.me/nodethemes" target="_blank" rel="noopener">@nodethemes</a></div>';
		echo '</div>';
	}

	/**
	 * Tab: AI.
	 */
	private static function tab_ai() {
		$cur = NTAC_Settings::get( 'provider' );
		echo '<h2>' . esc_html__( 'AI provider', 'nodethemes-ai-chat' ) . '</h2><p class="ntac-help">' . esc_html__( 'The assistant uses your own API key, so you pay the AI provider directly for what you use (a typical chat reply costs a fraction of a cent with the small models). Keys are stored encrypted. You can also put them in wp-config.php: define( \'NTAC_OPENAI_KEY\', \'...\' ); (or NTAC_ANTHROPIC_KEY / NTAC_GEMINI_KEY).', 'nodethemes-ai-chat' ) . '</p>';
		foreach ( NTAC_Settings::providers() as $id => $p ) {
			$has = NTAC_Settings::has_key( $id );
			echo '<div class="ntac-prov' . ( $id === $cur ? ' on' : '' ) . '" data-prov="' . esc_attr( $id ) . '">';
			echo '<h3><label><input type="radio" name="provider" value="' . esc_attr( $id ) . '"' . checked( $id, $cur, false ) . '> ' . esc_html( $p['label'] ) . '</label> ' . ( $has ? '<span class="ntac-badge ok">' . esc_html__( 'key saved', 'nodethemes-ai-chat' ) . '</span>' : '' ) . '</h3>';
			if ( NTAC_Settings::key_in_config( $id ) ) {
				echo '<p class="ntac-help">' . esc_html__( 'The key comes from wp-config.php.', 'nodethemes-ai-chat' ) . '</p>';
			} else {
				echo '<div class="ntac-inline"><label class="screen-reader-text" for="ntac-key-' . esc_attr( $id ) . '">' . esc_html__( 'API key', 'nodethemes-ai-chat' ) . '</label><input type="password" autocomplete="off" id="ntac-key-' . esc_attr( $id ) . '" name="key_' . esc_attr( $id ) . '" class="ntac-key" value="' . esc_attr( $has ? NTAC_Settings::mask( NTAC_Settings::api_key( $id ) ) : '' ) . '" placeholder="' . esc_attr__( 'Paste your API key', 'nodethemes-ai-chat' ) . '">';
				if ( $has ) {
					echo '<label class="ntac-check"><input type="checkbox" name="remove_key_' . esc_attr( $id ) . '" value="1"> ' . esc_html__( 'Remove', 'nodethemes-ai-chat' ) . '</label>';
				}
				echo '</div>';
			}
			$models = NTAC_Settings::suggested_models( $id );
			echo '<div class="ntac-inline"><label for="ntac-model_' . esc_attr( $id ) . '">' . esc_html__( 'Model', 'nodethemes-ai-chat' ) . '</label><input type="text" id="ntac-model_' . esc_attr( $id ) . '" name="model_' . esc_attr( $id ) . '" list="ntac-models-' . esc_attr( $id ) . '" value="' . esc_attr( NTAC_Settings::model( $id ) ) . '" class="ntac-model"><datalist id="ntac-models-' . esc_attr( $id ) . '">';
			foreach ( $models as $m ) {
				echo '<option value="' . esc_attr( $m ) . '">';
			}
			echo '</datalist><button type="button" class="ntac-btn sm ntac-load-models">' . esc_html__( 'Load my models', 'nodethemes-ai-chat' ) . '</button><button type="button" class="ntac-btn sm ntac-test">' . esc_html__( 'Test connection', 'nodethemes-ai-chat' ) . '</button><span class="ntac-test-out" role="status"></span></div>';
			echo '<p class="ntac-help">' . esc_html( $p['steps'] ) . ' <a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a key', 'nodethemes-ai-chat' ) . '</a></p>';
			echo '</div>';
		}
		echo '<p class="ntac-help">' . esc_html__( 'Tip: fast, low-cost models (gpt-4o-mini, claude-haiku, gemini-flash) are ideal for chat.', 'nodethemes-ai-chat' ) . '</p>';
		self::row( __( 'Daily budget (USD)', 'nodethemes-ai-chat' ), 'ntac-daily_budget', self::input( 'daily_budget', '3', 'number' ), __( 'Estimated cost limit per day. When it is reached, messages go to your inbox instead of the AI. 0 = no limit.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Daily AI requests', 'nodethemes-ai-chat' ), 'ntac-daily_cap', self::input( 'daily_cap', '500', 'number' ), __( 'Protects you from abuse. 0 = no limit.', 'nodethemes-ai-chat' ) );
	}

	/**
	 * Tab: business.
	 */
	private static function tab_business() {
		echo '<h2>' . esc_html__( 'What should the assistant know?', 'nodethemes-ai-chat' ) . '</h2>';
		self::row( __( 'Assistant name', 'nodethemes-ai-chat' ), 'ntac-assistant_name', self::input( 'assistant_name', NTAC_Settings::assistant_name() ) );
		self::row( __( 'About your business', 'nodethemes-ai-chat' ), 'ntac-business', self::textarea( 'business', 12, __( "What you sell or do, for whom, where.\nPrices and packages.\nDelivery / shipping areas, times and costs.\nOpening hours, address, phone.\nReturns, guarantees, payment methods.\nAnything customers often ask.", 'nodethemes-ai-chat' ) ), __( 'The more facts you write here, the more questions the assistant can answer. It never invents prices or policies that are not written here or in Knowledge.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Your instructions (prompt)', 'nodethemes-ai-chat' ), 'ntac-instructions', self::textarea( 'instructions', 6, __( "e.g. Call customers by their first name.\nAlways suggest our free consultation call.\nNever discuss competitors.", 'nodethemes-ai-chat' ) ), __( 'How the assistant should behave, in your own words.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Always pass to me', 'nodethemes-ai-chat' ), 'ntac-handoff_topics', self::textarea( 'handoff_topics', 3, __( 'refunds, complaints, wholesale prices, problems with an order', 'nodethemes-ai-chat' ) ), __( 'Topics the assistant never answers itself. It also passes every question it cannot answer from your information.', 'nodethemes-ai-chat' ) );
		self::row(
			__( 'Reply language', 'nodethemes-ai-chat' ),
			'ntac-lang',
			self::select(
				'lang',
				array(
					'auto'   => __( 'The customer\'s language (recommended)', 'nodethemes-ai-chat' ),
					'site'   => __( 'The site language', 'nodethemes-ai-chat' ),
					'custom' => __( 'Always this language:', 'nodethemes-ai-chat' ),
				)
			) . ' ' . self::input( 'lang_custom', 'English' )
		);
		self::row(
			__( 'Tone', 'nodethemes-ai-chat' ),
			'ntac-tone',
			self::select(
				'tone',
				array(
					'friendly'     => __( 'Friendly', 'nodethemes-ai-chat' ),
					'professional' => __( 'Professional', 'nodethemes-ai-chat' ),
					'short'        => __( 'Short and direct', 'nodethemes-ai-chat' ),
				)
			)
		);
		self::row( __( 'Honesty', 'nodethemes-ai-chat' ), 'ntac-disclose_first', self::check( 'disclose_first', __( 'In Telegram / WhatsApp, the first reply says it is the AI assistant', 'nodethemes-ai-chat' ) ), __( 'The assistant always answers honestly when someone asks if they are talking to a bot, and never claims to be a person. In the website chat the notice line under the header says it.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Message when the AI cannot answer', 'nodethemes-ai-chat' ), 'ntac-offline_msg', self::textarea( 'offline_msg', 2, NTAC_Settings::text( 'offline_msg' ) ), __( 'Sent (at most once every 6 hours per conversation) when the AI is unavailable or the monthly free replies are used up.', 'nodethemes-ai-chat' ) );
	}

	/**
	 * Tab: widget.
	 */
	private static function tab_widget() {
		echo '<h2>' . esc_html__( 'Chat bubble on your website', 'nodethemes-ai-chat' ) . '</h2>';
		self::row( __( 'Show the chat', 'nodethemes-ai-chat' ), 'ntac-widget_on', self::check( 'widget_on', __( 'Show the chat bubble on the website', 'nodethemes-ai-chat' ) ) );
		self::row( __( 'Title', 'nodethemes-ai-chat' ), 'ntac-widget_title', self::input( 'widget_title', NTAC_Settings::text( 'widget_title' ) ) );
		self::row( __( 'Subtitle', 'nodethemes-ai-chat' ), 'ntac-widget_sub', self::input( 'widget_sub', NTAC_Settings::text( 'widget_sub' ) ) );
		self::row( __( 'Welcome message', 'nodethemes-ai-chat' ), 'ntac-welcome', self::textarea( 'welcome', 2, NTAC_Settings::text( 'welcome' ) ) );
		self::row( __( 'Notice line', 'nodethemes-ai-chat' ), 'ntac-notice', self::textarea( 'notice', 2, NTAC_Settings::text( 'notice' ) ), __( 'Shown at the top of the chat. Keep it honest: say that an AI assistant answers.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Color', 'nodethemes-ai-chat' ), 'ntac-color', '<input type="color" id="ntac-color" name="color" value="' . esc_attr( (string) NTAC_Settings::get( 'color' ) ) . '">' );
		self::row(
			__( 'Position', 'nodethemes-ai-chat' ),
			'ntac-position',
			self::select(
				'position',
				array(
					'right' => __( 'Bottom right', 'nodethemes-ai-chat' ),
					'left'  => __( 'Bottom left', 'nodethemes-ai-chat' ),
				)
			)
		);
		self::row( __( 'Phones', 'nodethemes-ai-chat' ), 'ntac-mobile', self::check( 'mobile', __( 'Show on phones', 'nodethemes-ai-chat' ) ) );
		self::row( __( 'Ask name and e-mail', 'nodethemes-ai-chat' ), 'ntac-ask_contact', self::check( 'ask_contact', __( 'Ask for name and e-mail before the first message (optional fields)', 'nodethemes-ai-chat' ) ) );
		self::row( __( 'Hide on pages', 'nodethemes-ai-chat' ), 'ntac-hide_ids', self::input( 'hide_ids', '12, 45' ), __( 'Page or post IDs, separated by commas.', 'nodethemes-ai-chat' ) );
		echo '<p class="ntac-help">' . esc_html__( 'Works with page caching plugins: the chat loads messages through the REST API (/wp-json/ntac/v1/), which must not be cached or blocked. No cookies need to be excluded from the cache.', 'nodethemes-ai-chat' ) . '</p>';
	}

	/**
	 * Tab: notifications.
	 */
	private static function tab_notify() {
		echo '<h2>' . esc_html__( 'When a customer needs you', 'nodethemes-ai-chat' ) . '</h2>';
		self::row( __( 'E-mail', 'nodethemes-ai-chat' ), 'ntac-notify_email_on', self::check( 'notify_email_on', __( 'Send me an e-mail when a question is passed to me', 'nodethemes-ai-chat' ) ) . ' ' . self::input( 'notify_email', (string) get_option( 'admin_email' ), 'email' ), __( 'At most one e-mail per conversation every 15 minutes.', 'nodethemes-ai-chat' ) );
		self::row( __( 'After you reply', 'nodethemes-ai-chat' ), 'ntac-pause_after_human', self::input( 'pause_after_human', '60', 'number' ) . ' ' . esc_html__( 'minutes', 'nodethemes-ai-chat' ), __( 'When you answer a customer yourself, the AI stays quiet in that conversation for this long, so it does not interrupt you. 0 = the AI answers the next message.', 'nodethemes-ai-chat' ) );
		if ( ! NTAC_Pro::active() ) {
			echo '<p>' . NTAC_Pro::lock( __( 'Get questions on Telegram and answer them there', 'nodethemes-ai-chat' ), 'notify' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Tab: privacy.
	 */
	private static function tab_privacy() {
		echo '<h2>' . esc_html__( 'Privacy & data', 'nodethemes-ai-chat' ) . '</h2>';
		self::row( __( 'Keep conversations', 'nodethemes-ai-chat' ), 'ntac-retention', self::input( 'retention', '180', 'number' ) . ' ' . esc_html__( 'days', 'nodethemes-ai-chat' ), __( 'Conversations without activity for this long are deleted automatically. 0 = keep forever.', 'nodethemes-ai-chat' ) );
		self::row( __( 'Uninstall', 'nodethemes-ai-chat' ), 'ntac-remove_data', self::check( 'remove_data', __( 'Delete all conversations, knowledge and settings when the plugin is deleted', 'nodethemes-ai-chat' ) ) );
		echo '<p class="ntac-help">' . esc_html__( 'Customer messages are sent to the AI provider you chose to write the answers. A suggested paragraph for your privacy policy is in Settings → Privacy → Policy guide.', 'nodethemes-ai-chat' ) . '</p>';
	}

	/**
	 * Tab: channels (free = locked).
	 */
	private static function tab_channels_locked() {
		echo '<div class="ntac-grid">';
		$cards = array(
			array( 'Telegram', __( 'Your own Telegram bot answers customers. With Telegram Business (Premium) the assistant can even answer in your personal account, and you can jump in at any time.', 'nodethemes-ai-chat' ) ),
			array( 'WhatsApp Cloud API', __( 'The official WhatsApp Business API from Meta. Safe and reliable, set up with your Meta developer account.', 'nodethemes-ai-chat' ) ),
			array( 'WhatsApp (QR code)', __( 'Connect your normal WhatsApp (or WhatsApp Business app) by scanning a QR code, through a small gateway you run on your own server. Unofficial: read the risks in the guide.', 'nodethemes-ai-chat' ) ),
		);
		foreach ( $cards as $c ) {
			echo '<div class="ntac-card"><h2>' . esc_html( $c[0] ) . ' <span class="ntac-protag">PRO</span></h2><p class="ntac-help">' . esc_html( $c[1] ) . '</p>' . NTAC_Pro::lock( __( 'Unlock', 'nodethemes-ai-chat' ), 'channel' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		NTAC_Pro::upsell_box( __( 'NodeThemes AI Chat Pro', 'nodethemes-ai-chat' ) );
	}

	/**
	 * Tab: usage.
	 */
	private static function tab_usage() {
		$u     = NTAC_Pro::usage();
		$limit = NTAC_Pro::limit();
		$today = NTAC_AI::usage_today();
		$c     = NTAC_DB::counts();
		echo '<div class="ntac-stats">';
		echo '<div class="ntac-stat"><span>' . esc_html__( 'AI replies this month', 'nodethemes-ai-chat' ) . '</span><b>' . (int) $u['ai'] . '</b><i>' . esc_html( $limit ? sprintf( /* translators: %d: limit */ __( 'of %d free', 'nodethemes-ai-chat' ), $limit ) : __( 'unlimited (Pro)', 'nodethemes-ai-chat' ) ) . '</i></div>';
		echo '<div class="ntac-stat"><span>' . esc_html__( 'Not answered (limit)', 'nodethemes-ai-chat' ) . '</span><b class="' . ( $u['missed'] ? 'bad' : '' ) . '">' . (int) $u['missed'] . '</b><i>' . esc_html__( 'this month', 'nodethemes-ai-chat' ) . '</i></div>';
		echo '<div class="ntac-stat"><span>' . esc_html__( 'AI cost today (estimate)', 'nodethemes-ai-chat' ) . '</span><b>$' . esc_html( number_format( (float) $today['cost'], 3 ) ) . '</b><i>' . esc_html( sprintf( /* translators: %d: requests */ __( '%d requests', 'nodethemes-ai-chat' ), (int) $today['calls'] ) ) . '</i></div>';
		echo '<div class="ntac-stat"><span>' . esc_html__( 'Conversations', 'nodethemes-ai-chat' ) . '</span><b>' . (int) $c['total'] . '</b><i>' . esc_html( sprintf( /* translators: %d: count */ __( '%d need you', 'nodethemes-ai-chat' ), (int) $c['needs'] ) ) . '</i></div>';
		echo '</div>';
		if ( $limit ) {
			echo '<div class="ntac-card"><p>' . esc_html__( 'The free plugin includes 50 AI replies per calendar month. After that, messages still arrive in your inbox and you get an e-mail, but the AI does not answer them until the next month. Pro has no monthly limit.', 'nodethemes-ai-chat' ) . '</p><p><a class="ntac-btn primary" target="_blank" rel="noopener" href="' . esc_url( NTAC_Pro::url( 'usage' ) ) . '">' . esc_html__( 'See Pro', 'nodethemes-ai-chat' ) . '</a></p></div>';
		}
	}
}
