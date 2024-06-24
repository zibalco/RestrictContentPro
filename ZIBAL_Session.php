<?php
if( !defined( 'ZIBAL_SESSION_COOKIE' ) )
    define( 'ZIBAL_SESSION_COOKIE', '_ZIBAL_Session' );

if ( !class_exists( 'Recursive_ArrayAccess' ) ) {
    class Recursive_ArrayAccess implements ArrayAccess {
        protected $container = array();
        protected $dirty = false;

        protected function __construct( $data = array() ) {
            foreach ( $data as $key => $value ) {
                $this[ $key ] = $value;
            }
        }

        public function __clone() {
            foreach ( $this->container as $key => $value ) {
                if ( $value instanceof self ) {
                    $this[ $key ] = clone $value;
                }
            }
        }

        public function toArray() {
            $data = $this->container;
            foreach ( $data as $key => $value ) {
                if ( $value instanceof self ) {
                    $data[ $key ] = $value->toArray();
                }
            }
            return $data;
        }

        #[\ReturnTypeWillChange]
        public function offsetExists( $offset ): bool {
            return isset( $this->container[ $offset ] );
        }

        #[\ReturnTypeWillChange]
        public function offsetGet( $offset ) {
            return isset( $this->container[ $offset ] ) ? $this->container[ $offset ] : null;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet( $offset, $data ): void {
            if ( is_array( $data ) ) {
                $data = new self( $data );
            }
            if ( $offset === null ) {
                $this->container[] = $data;
            } else {
                $this->container[ $offset ] = $data;
            }
            $this->dirty = true;
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset( $offset ): void {
            unset( $this->container[ $offset ] );
            $this->dirty = true;
        }
    }
}

if ( !class_exists( 'ZIBAL_Session' ) ) {
    final class ZIBAL_Session extends Recursive_ArrayAccess implements Iterator, Countable {
        protected $session_id;
        protected $expires;
        protected $exp_variant;
        private static $instance = false;

        public static function get_instance() {
            if ( !self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        protected function __construct() {
            if ( isset( $_COOKIE[ZIBAL_SESSION_COOKIE] ) ) {
                $cookie = stripslashes( $_COOKIE[ZIBAL_SESSION_COOKIE] );
                $cookie_crumbs = explode( '||', $cookie );
                $this->session_id = $cookie_crumbs[0];
                $this->expires = $cookie_crumbs[1];
                $this->exp_variant = $cookie_crumbs[2];
                if ( time() > $this->exp_variant ) {
                    $this->set_expiration();
                    delete_option( "_ZIBAL_Session_expires_{$this->session_id}" );
                    add_option( "_ZIBAL_Session_expires_{$this->session_id}", $this->expires, '', 'no' );
                }
            } else {
                $this->session_id = $this->generate_id();
                $this->set_expiration();
            }
            $this->read_data();
            $this->set_cookie();
        }

        protected function set_expiration() {
            $this->exp_variant = time() + (int) apply_filters( 'ZIBAL_Session_expiration_variant', 24 * 60 );
            $this->expires = time() + (int) apply_filters( 'ZIBAL_Session_expiration', 30 * 60 );
        }

        protected function set_cookie() {
            if( !headers_sent() )
                setcookie(ZIBAL_SESSION_COOKIE,$this->session_id.'||'.$this->expires.'||'.$this->exp_variant,$this->expires,COOKIEPATH,COOKIE_DOMAIN );
        }

        protected function generate_id() {
            require_once( ABSPATH . 'wp-includes/class-phpass.php');
            $hasher = new PasswordHash( 8, false );
            return md5( $hasher->get_random_bytes( 32 ) );
        }

        protected function read_data() {
            $this->container = get_option( "_ZIBAL_Session_{$this->session_id}", array() );
            return $this->container;
        }

        public function write_data() {
            $option_key = "_ZIBAL_Session_{$this->session_id}";
            if ( $this->dirty ) {
                if ( false === get_option( $option_key ) ) {
                    add_option( "_ZIBAL_Session_{$this->session_id}", $this->container, '', 'no' );
                    add_option( "_ZIBAL_Session_expires_{$this->session_id}", $this->expires, '', 'no' );
                } else {
                    delete_option( "_ZIBAL_Session_{$this->session_id}" );
                    add_option( "_ZIBAL_Session_{$this->session_id}", $this->container, '', 'no' );
                }
            }
        }

        public function json_out() {
            return json_encode( $this->container );
        }

        public function json_in( $data ) {
            $array = json_decode( $data );
            if ( is_array( $array ) ) {
                $this->container = $array;
                return true;
            }
            return false;
        }

        public function regenerate_id( $delete_old = false ) {
            if ( $delete_old ) {
                delete_option( "_ZIBAL_Session_{$this->session_id}" );
            }
            $this->session_id = $this->generate_id();
            $this->set_cookie();
        }

        public function session_started() {
            return !!self::$instance;
        }

        public function cache_expiration() {
            return $this->expires;
        }

        public function reset() {
            $this->container = array();
        }

        #[\ReturnTypeWillChange]
        public function current() {
            return current( $this->container );
        }

        #[\ReturnTypeWillChange]
        public function key() {
            return key( $this->container );
        }

        #[\ReturnTypeWillChange]
        public function next(): void {
            next( $this->container );
        }

        #[\ReturnTypeWillChange]
        public function rewind(): void {
            reset( $this->container );
        }

        #[\ReturnTypeWillChange]
        public function valid(): bool {
            return $this->offsetExists( $this->key() );
        }

        #[\ReturnTypeWillChange]
        public function count(): int {
            return count( $this->container );
        }
    }

    function ZIBAL_Session_cache_expire() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        return $ZIBAL_Session->cache_expiration();
    }

    function ZIBAL_Session_commit() {
        ZIBAL_Session_write_close();
    }

    function ZIBAL_Session_decode( $data ) {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        return $ZIBAL_Session->json_in( $data );
    }

    function ZIBAL_Session_encode() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        return $ZIBAL_Session->json_out();
    }

    function ZIBAL_Session_regenerate_id( $delete_old_session = false ) {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        $ZIBAL_Session->regenerate_id( $delete_old_session );
        return true;
    }

    function ZIBAL_Session_start() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        do_action( 'ZIBAL_Session_start' );
        return $ZIBAL_Session->session_started();
    }

    add_action( 'plugins_loaded', 'ZIBAL_Session_start' );

    function ZIBAL_Session_status() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        if ( $ZIBAL_Session->session_started() ) {
            return PHP_SESSION_ACTIVE;
        }
        return PHP_SESSION_NONE;
    }

    function ZIBAL_Session_unset() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        $ZIBAL_Session->reset();
    }

    function ZIBAL_Session_write_close() {
        $ZIBAL_Session = ZIBAL_Session::get_instance();
        $ZIBAL_Session->write_data();
        do_action( 'ZIBAL_Session_commit' );
    }

    add_action( 'shutdown', 'ZIBAL_Session_write_close' );

    function ZIBAL_Session_cleanup() {
        global $wpdb;
        if ( defined( 'ZIBAL_SETUP_CONFIG' ) ) {
            return;
        }
        if ( ! defined( 'ZIBAL_INSTALLING' ) ) {
            $expiration_keys = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '_ZIBAL_Session_expires_%'" );
            $now = time();
            $expired_sessions = array();
            foreach ( $expiration_keys as $expiration ) {
                if ( $expiration->option_value < $now ) {
                    $session_id = substr( $expiration->option_name, 22 );
                    $expired_sessions[] = $session_id;
                }
            }
            if ( $expired_sessions ) {
                $option_names = array();
                foreach ( $expired_sessions as $session_id ) {
                    $option_names[] = "'_ZIBAL_Session_{$session_id}'";
                    $option_names[] = "'_ZIBAL_Session_expires_{$session_id}'";
                }
                $option_names = implode( ',', $option_names );
                $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ({$option_names})" );
            }
        }
    }

    add_action( 'wp_scheduled_delete', 'ZIBAL_Session_cleanup' );
    register_deactivation_hook( __FILE__, 'ZIBAL_Session_cleanup' );

    function ZIBAL_Session_register_shutdown() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            ZIBAL_Session_write_close();
        }
    }

    add_action( 'shutdown', 'ZIBAL_Session_register_shutdown' );
}
?>
