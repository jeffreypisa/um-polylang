<?php
defined( 'ABSPATH' ) || exit;

/**
 * The "Ultimate Member - Polylang" extension initialization.
 *
 * Get an instance this way: UM()->Polylang()
 *
 * @package um_ext\um_polylang
 */
class UM_Polylang {

	/**
	 * Cached request language for the current page load.
	 *
	 * @since 1.2.3
	 *
	 * @var string|null
	 */
	protected $request_language = null;


	/**
	 * An instance of the class.
	 *
	 * @var UM_Polylang
	 */
	private static $instance;


	/**
	 * Creates an instance of the class.
	 *
	 * @return UM_Polylang
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Class constructor.
	 */
	public function __construct() {

                add_action( 'wp', array( $this, 'prime_request_language' ), 1 );

		$this->core();
		if ( UM()->is_request( 'admin' ) ) {
			$this->admin();
			$this->posts();
		} elseif ( UM()->is_request( 'frontend' ) ) {
			$this->front();
		}

		// Extensions.
		if ( defined( 'um_account_tabs_version' ) ) {
			require_once um_polylang_path . 'includes/extensions/account-tabs.php';
		}
		if ( defined( 'um_profile_tabs_version' ) ) {
			require_once um_polylang_path . 'includes/extensions/profile-tabs.php';
		}
	}


	/**
	 * Subclass that extends wp-admin features.
	 *
	 * @since 1.1.0
	 *
	 * @return um_ext\um_polylang\admin\Admin()
	 */
	public function admin() {
		if ( empty( UM()->classes['um_polylang_admin'] ) ) {
			require_once um_polylang_path . 'includes/admin/class-init.php';
			UM()->classes['um_polylang_admin'] = new um_ext\um_polylang\admin\Init();
		}
		return UM()->classes['um_polylang_admin'];
	}


	/**
	 * Common functionality.
	 *
	 * @since 1.2.2
	 *
	 * @return um_ext\um_polylang\core\Init()
	 */
	public function core() {
		if ( empty( UM()->classes['um_polylang_core'] ) ) {
			require_once um_polylang_path . 'includes/core/class-init.php';
			UM()->classes['um_polylang_core'] = new um_ext\um_polylang\core\Init();
		}
		return UM()->classes['um_polylang_core'];
	}


	/**
	 * Front-end functionality.
	 *
	 * @since 1.2.2
	 *
	 * @return um_ext\um_polylang\front\Init()
	 */
	public function front() {
		if ( empty( UM()->classes['um_polylang_front'] ) ) {
			require_once um_polylang_path . 'includes/front/class-init.php';
			UM()->classes['um_polylang_front'] = new um_ext\um_polylang\front\Init();
		}
		return UM()->classes['um_polylang_front'];
	}


	/**
	 * Subclass that creates translated posts and forms.
	 *
	 * @since 1.1.1
	 *
	 * @return um_ext\um_polylang\core\Posts()
	 */
	public function posts() {
		if ( empty( UM()->classes['um_polylang_posts'] ) ) {
			require_once um_polylang_path . 'includes/core/class-posts.php';
			UM()->classes['um_polylang_posts'] = new um_ext\um_polylang\core\Posts();
		}
		return UM()->classes['um_polylang_posts'];
	}


	/**
	 * Subclass that do actions on installation.
	 *
	 * @since 1.1.0
	 *
	 * @return um_ext\um_polylang\core\Setup()
	 */
	public function setup() {
		if ( empty( UM()->classes['um_polylang_setup'] ) ) {
			require_once um_polylang_path . 'includes/core/class-setup.php';
			UM()->classes['um_polylang_setup'] = new um_ext\um_polylang\core\Setup();
		}
		return UM()->classes['um_polylang_setup'];
	}


	/**
	 * Returns the current language.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $field Optional, the language field to return (@see PLL_Language), defaults to `'slug'`.
	 * @return string|int|bool|string[]|PLL_Language The requested field or object for the current language, `false` if the field isn't set.
	 */
	public function get_current( $field = 'slug' ) {

                $lang = '';

                if ( isset( $_GET['lang'] ) ) {
                        $lang = sanitize_key( $_GET['lang'] );
                }

                if ( empty( $lang ) || 'all' === $lang ) {
                        $request_lang = $this->get_request_language();

                        if ( $request_lang ) {
                                $lang = $request_lang;
                        }
                }

               if ( empty( $lang ) || 'all' === $lang ) {
                       $lang = pll_current_language();
               } else {
                        $current_lang = pll_current_language();

                        if ( $current_lang && $current_lang !== $lang ) {
                                $this->log_debug(
                                        'Override Polylang current language with request language.',
                                        array(
                                                'polylang' => $current_lang,
                                                'request'  => $lang,
                                        )
                                );
                        }
                }

               if ( empty( $lang ) || 'all' === $lang ) {
                       $referer_lang = $this->detect_language_from_referer();
                       if ( $referer_lang ) {
                               $lang = $referer_lang;
                       }
               }

               if ( empty( $lang ) || 'all' === $lang ) {
                       $lang = pll_default_language();
               }

                if ( empty( $lang ) || 'all' === $lang ) {
                        $locale = determine_locale();
                        $lang   = substr( $locale, 0, 2 );
                }
                $language = PLL()->model->get_language( $lang );

                return is_object( $language ) ? $language->get_prop( $field ) : $lang;
        }


        /**
         * Determine and cache the current request language once the main query is available.
         *
         * @since 1.2.3
         */
        public function prime_request_language() {
                if ( null !== $this->request_language && '' !== $this->request_language ) {
                        return;
                }

                $language = '';

                if ( function_exists( 'get_queried_object_id' ) ) {
                        $post_id = get_queried_object_id();

                        if ( $post_id ) {
                                $language = pll_get_post_language( $post_id, 'slug' );
                        }
                }

                if ( empty( $language ) ) {
                        global $post;

                        if ( $post instanceof \WP_Post ) {
                                $language = pll_get_post_language( $post->ID, 'slug' );
                        }
                }

                if ( empty( $language ) ) {
                        $language = $this->detect_language_from_request();
                }

                if ( empty( $language ) ) {
                        $language = pll_current_language();
                }

                if ( empty( $language ) || 'all' === $language ) {
                        $language = '';
                }

                $this->request_language = $language;

                if ( $language ) {
                        $permalinks = UM()->Polylang()->core()->permalinks();

                        if ( method_exists( $permalinks, 'sync_current_language_permalinks' ) ) {
                                $permalinks->sync_current_language_permalinks( $language );
                        }
                }
        }


        /**
         * Return the detected language for the current request.
         *
         * @since 1.2.3
         *
         * @return string Language slug when detected, otherwise an empty string.
         */
        public function get_request_language() {
                if ( null === $this->request_language ) {
                        $this->request_language = $this->detect_language_from_request();

                        if ( empty( $this->request_language ) || 'all' === $this->request_language ) {
                                $this->request_language = '';
                        }
                }

                return $this->request_language ? $this->request_language : '';
        }


        /**
         * Try to determine the current language from the referring URL.
         *
         * @since 1.2.3
         *
         * @return string Language slug if detected, otherwise an empty string.
         */
        protected function detect_language_from_referer() {
                $referer = '';

                if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
                        $referer = wp_unslash( $_REQUEST['_wp_http_referer'] );
                } elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                        $referer = wp_unslash( $_SERVER['HTTP_REFERER'] );
                }

                return $this->detect_language_from_url( $referer );
        }


        /**
         * Try to determine the current language from the current request.
         *
         * @since 1.2.3
         *
         * @return string Language slug if detected, otherwise an empty string.
         */
        protected function detect_language_from_request() {
                $language = '';

                if ( function_exists( 'get_queried_object_id' ) ) {
                        $post_id = get_queried_object_id();
                        if ( $post_id ) {
                                $language = pll_get_post_language( $post_id, 'slug' );

                                if ( $language ) {
                                        return $language;
                                }
                        }
                }

                global $post;

                if ( $post instanceof \WP_Post ) {
                        $language = pll_get_post_language( $post->ID, 'slug' );

                        if ( $language ) {
                                return $language;
                        }
                }

                $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

                if ( empty( $request_uri ) ) {
                        return pll_default_language();
                }

                $request_uri = strtok( $request_uri, '#' );

                $url = home_url( $request_uri );

                $language = $this->detect_language_from_url( $url );

                if ( empty( $language ) ) {
                        $language = pll_default_language();
                }

                return $language;
        }


        /**
         * Extract the language from a provided URL.
         *
         * @since 1.2.3
         *
         * @param string $url URL to inspect.
         * @return string Language slug if detected, otherwise an empty string.
         */
        protected function detect_language_from_url( $url ) {
                if ( empty( $url ) ) {
                        return '';
                }

                $url = esc_url_raw( $url );

                $post_id = url_to_postid( $url );
                if ( $post_id ) {
                        $language = pll_get_post_language( $post_id, 'slug' );
                        if ( $language ) {
                                return $language;
                        }
                }

                $path = wp_parse_url( $url, PHP_URL_PATH );
                if ( empty( $path ) ) {
                        return '';
                }

                $segments  = array_filter( explode( '/', trim( $path, '/' ) ) );
                $languages = pll_languages_list();

                foreach ( $segments as $segment ) {
                        $segment = sanitize_key( $segment );
                        if ( in_array( $segment, $languages, true ) ) {
                                return $segment;
                        }
                }

                return '';
        }


	/**
	 * Returns the default language.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $field Optional, the language field to return (@see PLL_Language), defaults to `'slug'`.
	 * @return string|int|bool|string[]|PLL_Language The requested field or object for the default language, `false` if the field isn't set.
	 */
	public function get_default( $field = 'slug' ) {
		return pll_default_language( $field );
	}


	/**
	 * Returns the list of available languages.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function get_languages_list() {
		return pll_languages_list();
	}


	/**
	 * Returns an object with the language details.
	 *
	 * @since 1.2.2
	 *
	 * @param string $lang Language code.
	 * @return object Language info.
	 */
	public function get_translation( $lang ) {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$translations = wp_get_available_translations();

		switch( $lang ) {
			case 'ca': $locale = 'en_CA'; break;
			case 'en': $locale = 'en_GB'; break;
			case 'us': $locale = 'en_US'; break;
			case 'ar': $locale = 'es_AR'; break;
			case 'co': $locale = 'es_CO'; break;
			case 'mx': $locale = 'es_MX'; break;
			case 'br': $locale = 'pt_BR'; break;
			default: $locale = $lang . '_' . strtoupper( $lang ); break;
		}

		if ( array_key_exists( $lang, $translations ) ) {
			$translation = $translations[ $lang ];
		} elseif ( array_key_exists( $locale, $translations ) ) {
			$translation = $translations[ $locale ];
		} else {
			foreach ( $translations as $t ) {
				if ( in_array( $lang, $t['iso'], true ) ) {
					$translation = $t;
					break;
				}
			}
		}
		return empty( $translation ) ? false : (object) $translation;
	}


	/**
	 * Check if Polylang is active.
	 *
	 * @since   1.0.0
	 * @version 1.1.0 Check for the PLL function.
	 *
	 * @return boolean
	 */
        public function is_active() {
                if ( function_exists( 'um_polylang_is_polylang_active' ) ) {
                        return um_polylang_is_polylang_active();
                }

                return function_exists( 'PLL' ) && ( defined( 'POLYLANG_VERSION' ) || defined( 'POLYLANG_PRO_VERSION' ) );
        }


        /**
         * Check if the default language is chosen.
         *
         * @since 1.0.0
         *
         * @return boolean
         */
        public function is_default() {
                $default = $this->get_default();
                $current = $this->get_current();

                if ( $current ) {
                        return $current === $default;
                }

                $request = $this->get_request_language();

                if ( $request ) {
                        return $request === $default;
                }

                return true;
        }

        /**
         * Log debugging information when debugging is enabled.
         *
         * @since 1.2.3
         *
         * @param string $message Debug message.
         * @param array  $context Additional context to include in the log entry.
         */
        public function log_debug( $message, array $context = array() ) {
                $enabled = apply_filters( 'um_polylang_enable_debug', defined( 'WP_DEBUG' ) && WP_DEBUG );

                if ( ! $enabled ) {
                        return;
                }

                if ( ! empty( $context ) ) {
                        $message .= ' ' . wp_json_encode( $context );
                }

                error_log( '[UM Polylang] ' . $message );
        }

}
