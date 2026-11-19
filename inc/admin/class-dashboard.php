<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NextSafar_Dashboard {

	/**
	 * 🔐 تنظیمات Google Search Console
	 * اطلاعات حساس از ثابت‌های wp-config.php خوانده می‌شوند.
	 */
	private function get_gsc_config() {
		if ( ! defined( 'NEXTSAFAR_GSC_CLIENT_EMAIL' ) || ! defined( 'NEXTSAFAR_GSC_PRIVATE_KEY' ) ) {
			return array(
				'site_url'    => '',
				'credentials' => array(),
			);
		}
		return array(
			'site_url'    => defined( 'NEXTSAFAR_GSC_SITE_URL' ) ? NEXTSAFAR_GSC_SITE_URL : '',
			'credentials' => array(
				'type'           => 'service_account',
				'project_id'     => defined( 'NEXTSAFAR_GSC_PROJECT_ID' ) ? NEXTSAFAR_GSC_PROJECT_ID : '',
				'private_key_id' => defined( 'NEXTSAFAR_GSC_PRIVATE_KEY_ID' ) ? NEXTSAFAR_GSC_PRIVATE_KEY_ID : '',
				'private_key'    => defined( 'NEXTSAFAR_GSC_PRIVATE_KEY' ) ? NEXTSAFAR_GSC_PRIVATE_KEY : '',
				'client_email'   => defined( 'NEXTSAFAR_GSC_CLIENT_EMAIL' ) ? NEXTSAFAR_GSC_CLIENT_EMAIL : '',
				'client_id'      => defined( 'NEXTSAFAR_GSC_CLIENT_ID' ) ? NEXTSAFAR_GSC_CLIENT_ID : '',
				'auth_uri'       => 'https://accounts.google.com/o/oauth2/auth',
				'token_uri'      => 'https://oauth2.googleapis.com/token',
			),
		);
	}

	public function init() {
		add_action( 'wp_ajax_nextsafar_get_recent_comments', array( $this, 'ajax_recent_comments' ) );
		add_action( 'wp_ajax_nextsafar_get_admin_logins', array( $this, 'ajax_admin_logins' ) );
		add_action( 'wp_ajax_nextsafar_get_calendar_events', array( $this, 'ajax_get_calendar_events' ) );
		add_action( 'wp_ajax_nextsafar_get_yoast_stats', array( $this, 'ajax_yoast_stats' ) );
		add_action( 'wp_ajax_nextsafar_get_weather', array( $this, 'ajax_get_weather' ) );
		add_action( 'wp_ajax_nextsafar_get_search_console_data', array( $this, 'ajax_get_search_console_data' ) );

		add_action( 'wp_login', array( $this, 'track_admin_login' ), 10, 2 );
	}

	/* =========================================================
	   🔐 احراز هویت با Service Account JWT
	========================================================= */

	    private function get_google_access_token() {
        $config = $this->get_gsc_config();
        $json   = $config['credentials'] ?? array();

        if ( empty( $json['client_email'] ) || empty( $json['private_key'] ) ) {
            return new WP_Error(
                'invalid_key',
                'کلیدهای Search Console در wp-config.php تعریف نشده‌اند. باید NEXTSAFAR_GSC_CLIENT_EMAIL و NEXTSAFAR_GSC_PRIVATE_KEY (و NEXTSAFAR_GSC_SITE_URL) را define کنید.'
            );
        }

        $id        = md5( $json['client_email'] );
        $cache_key = 'nextsafar_google_token_' . $id;
        $fail_key  = 'nextsafar_google_token_fail_' . $id;

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // ✅ کش خطا (۱۰ دقیقه) — جلوگیری از تلاش مجدد ۱۰ ثانیه‌ای در هر بارگذاری داشبورد
        $last_error = get_transient( $fail_key );
        if ( false !== $last_error ) {
            return new WP_Error( 'gsc_cached_error', $last_error );
        }

        $now         = time();
        $header_json = json_encode( array(
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => isset( $json['private_key_id'] ) ? $json['private_key_id'] : '',
        ) );
        $header = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), base64_encode( $header_json ) );

        $claim_json = json_encode( array(
            'iss'   => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ) );
        $claim = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), base64_encode( $claim_json ) );

        $signature_input = $header . '.' . $claim;

        if ( ! function_exists( 'openssl_sign' ) ) {
            return $this->gsc_token_fail( $fail_key, 'openssl_missing', 'تابع openssl فعال نیست' );
        }

        $sign_result = @openssl_sign( $signature_input, $signature, $json['private_key'], 'SHA256' );
        if ( ! $sign_result ) {
            return $this->gsc_token_fail(
                $fail_key,
                'sign_failed',
                'امضای دیجیتال ناموفق: ' . openssl_error_string() . ' — مقدار NEXTSAFAR_GSC_PRIVATE_KEY را بررسی کنید.'
            );
        }

        $signature_b64 = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), base64_encode( $signature ) );
        $jwt           = $signature_input . '.' . $signature_b64;

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
            'timeout' => 10, // ✅ کاهش از ۲۰
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->gsc_token_fail( $fail_key, 'token_http', 'اتصال به Google ناموفق: ' . $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            $err_msg = isset( $data['error_description'] ) ? $data['error_description'] : 'دریافت Token ناموفق';
            return $this->gsc_token_fail( $fail_key, 'token_failed', $err_msg );
        }

        set_transient( $cache_key, $data['access_token'], 55 * MINUTE_IN_SECONDS );
        delete_transient( $fail_key );
        return $data['access_token'];
    }

    /** ✅ ثبت خطای توکن برای ۱۰ دقیقه */
    private function gsc_token_fail( $fail_key, $code, $message ) {
        set_transient( $fail_key, $message, 10 * MINUTE_IN_SECONDS );
        return new WP_Error( $code, $message );
    }

	/* =========================================================
	   🔍 کلاینت مشترک Search Console (نمودار + ویجت فرصت‌ها)
	   خروجی: آرایه داده Google یا WP_Error
	========================================================= */

	public function query_search_console( array $body ) {
		$config = $this->get_gsc_config();
		$site   = $config['site_url'];

		if ( '' === $site ) {
			return new WP_Error( 'no_site', 'NEXTSAFAR_GSC_SITE_URL در wp-config.php تعریف نشده است.' );
		}

		$access_token = $this->get_google_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$url      = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 10,
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== $code ) {
			$msg = 'خطای ناشناخته از Google (HTTP ' . $code . ')';
			if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
				$msg = $data['error']['message'];
			} elseif ( ! is_array( $data ) ) {
				$msg = 'پاسخ غیر JSON از سرور: ' . substr( $raw, 0, 200 );
			}

			if ( 403 === $code || false !== stripos( $msg, 'permission' ) ) {
				$msg .= ' 💡 Service Account را در Search Console → Settings → Users and permissions اضافه کنید.';
			} elseif ( 404 === $code || false !== stripos( $msg, 'notFound' ) ) {
				$msg .= ' 💡 Property یافت نشد؛ NEXTSAFAR_GSC_SITE_URL باید دقیقاً مطابق Property ثبت‌شده باشد (حتی اسلش آخر).';
			} elseif ( false !== stripos( $msg, 'quota' ) ) {
				$msg .= ' 💡 سهمیه API Google تمام شده است؛ چند دقیقه صبر کنید.';
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[NextSafar GSC] HTTP ' . $code . ': ' . $msg );
			}
			return new WP_Error( 'gsc_http', $msg );
		}

		return is_array( $data ) ? $data : new WP_Error( 'bad_json', 'پاسخ نامعتبر از Google' );
	}

	/* =========================================================
	   🔍 Search Console Data — نمودار با تب‌های زمانی
	========================================================= */

	public function ajax_get_search_console_data() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$range = isset( $_POST['date_range'] ) ? sanitize_text_field( $_POST['date_range'] ) : '28d';
		switch ( $range ) {
			case '24h':
				$days_back = 2; $days_start = 3;  $label = '۲ روز اخیر';
				break;
			case '7d':
				$days_back = 2; $days_start = 9;  $label = '۷ روز اخیر';
				break;
			case '28d':
				$days_back = 2; $days_start = 30; $label = '۲۸ روز اخیر';
				break;
			case '3m':
				$days_back = 2; $days_start = 92; $label = '۳ ماه اخیر';
				break;
			default:
				$days_back = 2; $days_start = 30; $label = '۲۸ روز اخیر';
		}

		$config    = $this->get_gsc_config();
		$cache_key = 'nextsafar_gsc_' . $range . '_' . md5( $config['site_url'] );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            wp_send_json_success( array( 'data' => $cached, 'range_label' => $label ) );
            return;
        }

        // ✅ کش خطا (۱۰ دقیقه) — داشبورد در هر بارگذاری معطل گوگل نمی‌ماند
        $err_key    = $cache_key . '_err';
        $last_error = get_transient( $err_key );
        if ( false !== $last_error ) {
            wp_send_json_error( array( 'message' => $last_error, 'cached_error' => true ) );
            return;
        }

		$data = $this->query_search_console( array(
			'startDate'  => date( 'Y-m-d', strtotime( "-{$days_start} days" ) ),
			'endDate'    => date( 'Y-m-d', strtotime( "-{$days_back} days" ) ),
			'dimensions' => array( 'date' ),
			'rowLimit'   => 100,
		) );
        if ( is_wp_error( $data ) ) {
            $msg = 'خطای Search Console: ' . $data->get_error_message();
            set_transient( $err_key, $msg, 10 * MINUTE_IN_SECONDS );
            wp_send_json_error( array( 'message' => $msg ) );
            return;
        }

		$chart        = array();
		$clicks       = 0;
		$impressions  = 0;
		$position_sum = 0;

		if ( isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
			usort( $data['rows'], function ( $a, $b ) {
				return strcmp( $a['keys'][0], $b['keys'][0] );
			} );
			foreach ( $data['rows'] as $row ) {
				$chart[] = array(
					'date'        => $row['keys'][0],
					'clicks'      => (int) $row['clicks'],
					'impressions' => (int) $row['impressions'],
					'ctr'         => isset( $row['ctr'] ) ? round( (float) $row['ctr'] * 100, 2 ) : 0,
					'position'    => isset( $row['position'] ) ? round( (float) $row['position'], 1 ) : 0,
				);
				$clicks       += (int) $row['clicks'];
				$impressions  += (int) $row['impressions'];
				$position_sum += isset( $row['position'] ) ? (float) $row['position'] : 0;
			}
		}

		$row_count = count( $chart );
		$avg_ctr   = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0;
		$avg_pos   = $row_count > 0 ? round( $position_sum / $row_count, 1 ) : 0;

		$result = array(
			'chart'  => $chart,
			'totals' => array(
				'clicks'      => number_format_i18n( $clicks ),
				'impressions' => number_format_i18n( $impressions ),
				'ctr'         => $avg_ctr . '%',
				'position'    => $avg_pos,
			),
		);

		$cache_time = '24h' === $range ? 30 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS;
		set_transient( $cache_key, $result, $cache_time );
		wp_send_json_success( array( 'data' => $result, 'range_label' => $label ) );
	}

	/* =========================================================
	   📅 Calendar Events (تعطیلات ۱۴۰۵ تا ۱۴۰۹)
	========================================================= */

	public function ajax_get_calendar_events() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$jalali_year = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : 1404;
		$cache_key   = 'nextsafar_calendar_holidays_' . $jalali_year;
		$cached      = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'holidays' => $cached ) );
			return;
		}

		$holidays          = array();
		$official_holidays = array(
			1405 => array(
				'1/1' => 'نوروز + عید فطر', '1/2' => 'عید نوروز', '1/3' => 'عید نوروز', '1/4' => 'عید نوروز',
				'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت', '1/24' => 'شهادت امام جعفر صادق (ع)',
				'3/3' => 'شهادت امام محمد باقر (ع)', '3/6' => 'عید قربان', '3/14' => 'رحلت امام خمینی (ره) + عید غدیر',
				'3/15' => 'قیام ۱۵ خرداد', '4/3' => 'تاسوعای حسینی', '4/4' => 'عاشورای حسینی',
				'4/13' => 'تعطیلی خاص', '4/14' => 'تعطیلی خاص', '4/15' => 'تعطیلی خاص', '4/16' => 'تعطیلی خاص',
				'5/13' => 'اربعین حسینی', '5/21' => 'رحلت پیامبر اکرم (ص) و شهادت امام حسن مجتبی (ع)',
				'5/22' => 'شهادت امام رضا (ع)', '5/30' => 'شهادت امام حسن عسکری (ع)',
				'6/8' => 'ولادت پیامبر اکرم (ص) و ولادت امام جعفر صادق (ع)', '8/22' => 'شهادت حضرت فاطمه زهرا (س)',
				'10/2' => 'ولادت امام علی (ع) ـ روز پدر', '10/16' => 'مبعث پیامبر اکرم (ص)',
				'11/4' => 'ولادت امام مهدی (عج) ـ نیمه شعبان', '11/22' => 'پیروزی انقلاب اسلامی ایران',
				'12/9' => 'شهادت امام علی (ع)', '12/19' => 'عید سعید فطر', '12/20' => 'تعطیل عید فطر',
				'12/29' => 'روز ملی شدن صنعت نفت ایران',
			),
			1406 => array(
				'1/1' => 'نوروز', '1/2' => 'عید نوروز', '1/3' => 'عید نوروز', '1/4' => 'عید نوروز',
				'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت', '1/14' => 'شهادت امام جعفر صادق (ع)',
				'2/24' => 'شهادت امام محمد باقر (ع)', '2/27' => 'عید قربان', '3/4' => 'عید غدیر',
				'3/14' => 'رحلت امام خمینی (ره)', '3/15' => 'قیام ۱۵ خرداد', '3/24' => 'تاسوعای حسینی',
				'3/25' => 'عاشورای حسینی', '5/3' => 'اربعین حسینی', '5/11' => 'رحلت پیامبر اکرم (ص) و شهادت امام حسن مجتبی (ع)',
				'5/12' => 'شهادت امام رضا (ع)', '5/20' => 'شهادت امام حسن عسکری (ع)', '5/29' => 'ولادت پیامبر اکرم (ص) و ولادت امام جعفر صادق (ع)',
				'8/12' => 'شهادت حضرت فاطمه زهرا (س)', '9/21' => 'ولادت امام علی (ع) ـ روز پدر',
				'10/5' => 'مبعث پیامبر اکرم (ص)', '10/23' => 'ولادت امام مهدی (عج) ـ نیمه شعبان',
				'11/22' => 'پیروزی انقلاب اسلامی', '11/28' => 'شهادت امام علی (ع)', '12/8' => 'عید سعید فطر',
				'12/9' => 'تعطیل عید فطر', '12/29' => 'روز ملی شدن صنعت نفت ایران',
			),
			1407 => array(
				'1/1' => 'نوروز', '1/2' => 'عید نوروز', '1/3' => 'نوروز + شهادت امام جعفر صادق (ع)', '1/4' => 'عید نوروز',
				'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت', '2/13' => 'شهادت امام محمد باقر (ع)',
				'2/16' => 'عید قربان', '2/24' => 'عید غدیر', '3/13' => 'تاسوعای حسینی',
				'3/14' => 'رحلت امام خمینی (ره) + عاشورای حسینی', '3/15' => 'قیام ۱۵ خرداد', '4/23' => 'اربعین حسینی',
				'4/31' => 'رحلت پیامبر اکرم (ص) و شهادت امام حسن مجتبی (ع)', '5/1' => 'شهادت امام رضا (ع)',
				'5/9' => 'شهادت امام حسن عسکری (ع)', '5/18' => 'ولادت پیامبر اکرم (ص) و ولادت امام جعفر صادق (ع)',
				'7/1' => 'شهادت حضرت فاطمه زهرا (س)', '9/10' => 'ولادت امام علی (ع) ـ روز پدر',
				'9/24' => 'مبعث پیامبر اکرم (ص)', '10/12' => 'ولادت امام مهدی (عج) ـ نیمه شعبان',
				'11/17' => 'شهادت امام علی (ع)', '11/22' => 'پیروزی انقلاب اسلامی', '11/27' => 'عید سعید فطر',
				'11/28' => 'تعطیل عید فطر', '12/21' => 'شهادت امام جعفر صادق (ع)', '12/29' => 'روز ملی شدن صنعت نفت ایران',
			),
			1408 => array(
				'1/1' => 'نوروز', '1/2' => 'عید نوروز', '1/3' => 'عید نوروز', '1/4' => 'عید نوروز',
				'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت', '2/2' => 'شهادت امام محمد باقر (ع)',
				'2/5' => 'عید قربان', '2/13' => 'عید غدیر', '3/3' => 'تاسوعای حسینی',
				'3/4' => 'عاشورای حسینی', '3/14' => 'رحلت امام خمینی (ره)', '3/15' => 'قیام ۱۵ خرداد',
				'4/13' => 'اربعین حسینی', '4/21' => 'رحلت پیامبر اکرم (ص) و شهادت امام حسن مجتبی (ع)',
				'4/22' => 'شهادت امام رضا (ع)', '4/30' => 'شهادت امام حسن عسکری (ع)',
				'5/8' => 'ولادت پیامبر اکرم (ص) و ولادت امام جعفر صادق (ع)', '7/21' => 'شهادت حضرت فاطمه زهرا (س)',
				'8/30' => 'ولادت امام علی (ع) ـ روز پدر', '9/14' => 'مبعث پیامبر اکرم (ص)',
				'10/2' => 'ولادت امام مهدی (عج) ـ نیمه شعبان', '11/7' => 'شهادت امام علی (ع)',
				'11/17' => 'عید سعید فطر', '11/18' => 'تعطیل عید فطر', '11/22' => 'پیروزی انقلاب اسلامی',
				'12/11' => 'شهادت امام جعفر صادق (ع)', '12/29' => 'روز ملی شدن صنعت نفت ایران',
			),
			1409 => array(
				'1/1' => 'نوروز', '1/2' => 'عید نوروز', '1/3' => 'عید نوروز', '1/4' => 'عید نوروز',
				'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت', '1/22' => 'شهادت امام جعفر صادق (ع)',
				'1/25' => 'عید قربان', '2/2' => 'عید غدیر', '2/22' => 'تاسوعای حسینی',
				'2/23' => 'عاشورای حسینی', '3/14' => 'رحلت امام خمینی (ره)', '3/15' => 'قیام ۱۵ خرداد',
				'4/1' => 'اربعین حسینی', '4/9' => 'رحلت پیامبر اکرم (ص) و شهادت امام حسن مجتبی (ع)',
				'4/10' => 'شهادت امام رضا (ع)', '4/18' => 'ولادت امام حسن عسکری (ع)',
				'4/27' => 'ولادت پیامبر اکرم (ص) و ولادت امام جعفر صادق (ع)', '7/9' => 'شهادت حضرت فاطمه زهرا (س)',
				'8/18' => 'ولادت امام علی (ع) ـ روز پدر', '9/2' => 'مبعث پیامبر اکرم (ص)',
				'9/20' => 'ولادت امام مهدی (عج) ـ نیمه شعبان', '10/25' => 'شهادت امام علی (ع)',
				'11/5' => 'عید سعید فطر', '11/6' => 'تعطیل عید فطر', '11/22' => 'پیروزی انقلاب اسلامی',
				'11/29' => 'شهادت امام جعفر صادق (ع)', '12/29' => 'روز ملی شدن صنعت نفت ایران',
			),
		);

		$default_holidays = array(
			'1/1' => 'عید نوروز', '1/2' => 'عید نوروز', '1/3' => 'عید نوروز', '1/4' => 'عید نوروز',
			'1/12' => 'روز جمهوری اسلامی ایران', '1/13' => 'روز طبیعت',
			'3/14' => 'رحلت امام خمینی (ره)', '3/15' => 'قیام ۱۵ خرداد',
			'11/22' => 'پیروزی انقلاب اسلامی', '12/29' => 'روز ملی شدن صنعت نفت ایران',
		);

		$source = isset( $official_holidays[ $jalali_year ] ) ? $official_holidays[ $jalali_year ] : $default_holidays;
		foreach ( $source as $date => $title ) {
			$holidays[ $date ] = array( 'title' => $title, 'type' => 'shamsi' );
		}

		set_transient( $cache_key, $holidays, WEEK_IN_SECONDS );
		wp_send_json_success( array( 'holidays' => $holidays ) );
	}

	/* =========================================================
	   🎯 Yoast SEO Stats
	========================================================= */

	public function get_yoast_stats() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return array( 'available' => false );
		}

		$cache_key = 'nextsafar_yoast_stats_data';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$stats = array(
			'available'           => true,
			'version'             => WPSEO_VERSION,
			'method'              => '',
			'total_posts'         => 0,
			'posts_without_focus' => 0,
			'posts_with_focus'    => 0,
			'posts_with_good_seo' => 0,
			'posts_with_poor_seo' => 0,
			'readability_good'    => 0,
			'readability_poor'    => 0,
			'avg_score'           => 0,
		);

		$post_types = array( 'post', 'page' );
		foreach ( array( 'hotel', 'restaurant', 'destination', 'hospital', 'airport', 'travelguide', 'travelnews', 'visa' ) as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$post_types[] = $pt;
			}
		}

		$table_name   = $wpdb->prefix . 'yoast_indexable';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $table_exists ) {
			$types_placeholder = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COUNT(*) as total_posts,
					SUM(CASE WHEN primary_focus_keyword IS NULL OR primary_focus_keyword = '' THEN 1 ELSE 0 END) as no_keyword,
					SUM(CASE WHEN primary_focus_keyword IS NOT NULL AND primary_focus_keyword != '' THEN 1 ELSE 0 END) as has_keyword,
					SUM(CASE WHEN primary_focus_keyword_score >= 70 THEN 1 ELSE 0 END) as good_seo,
					SUM(CASE WHEN primary_focus_keyword_score > 0 AND primary_focus_keyword_score < 70 THEN 1 ELSE 0 END) as poor_seo,
					AVG(CASE WHEN primary_focus_keyword_score > 0 THEN primary_focus_keyword_score ELSE NULL END) as avg_score,
					SUM(CASE WHEN readability_score >= 60 THEN 1 ELSE 0 END) as readability_good,
					SUM(CASE WHEN readability_score > 0 AND readability_score < 60 THEN 1 ELSE 0 END) as readability_poor
				FROM {$table_name}
				WHERE object_type = 'post'
					AND object_sub_type IN ($types_placeholder)
					AND post_status = 'publish'",
				...$post_types
			) );

			if ( $row && (int) $row->total_posts > 0 ) {
				$stats['method']              = 'indexable_table';
				$stats['total_posts']         = (int) $row->total_posts;
				$stats['posts_without_focus'] = (int) $row->no_keyword;
				$stats['posts_with_focus']    = (int) $row->has_keyword;
				$stats['posts_with_good_seo'] = (int) $row->good_seo;
				$stats['posts_with_poor_seo'] = (int) $row->poor_seo;
				$stats['readability_good']    = (int) $row->readability_good;
				$stats['readability_poor']    = (int) $row->readability_poor;
				$stats['avg_score']           = round( (float) $row->avg_score );
				set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );
				return $stats;
			}
		}

		$stats['method'] = 'post_meta_query';
		$types_sql       = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";
		$row = $wpdb->get_row(
			"SELECT
				COUNT(DISTINCT p.ID) as total_posts,
				SUM(CASE WHEN fk.meta_value IS NULL OR fk.meta_value = '' THEN 1 ELSE 0 END) as no_keyword,
				SUM(CASE WHEN fk.meta_value IS NOT NULL AND fk.meta_value != '' THEN 1 ELSE 0 END) as has_keyword,
				SUM(CASE WHEN CAST(ld.meta_value AS UNSIGNED) >= 70 THEN 1 ELSE 0 END) as good_seo,
				SUM(CASE WHEN CAST(ld.meta_value AS UNSIGNED) > 0 AND CAST(ld.meta_value AS UNSIGNED) < 70 THEN 1 ELSE 0 END) as poor_seo,
				AVG(CASE WHEN CAST(ld.meta_value AS UNSIGNED) > 0 THEN CAST(ld.meta_value AS UNSIGNED) ELSE NULL END) as avg_score,
				SUM(CASE WHEN CAST(cs.meta_value AS UNSIGNED) >= 60 THEN 1 ELSE 0 END) as readability_good,
				SUM(CASE WHEN CAST(cs.meta_value AS UNSIGNED) > 0 AND CAST(cs.meta_value AS UNSIGNED) < 60 THEN 1 ELSE 0 END) as readability_poor
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} fk ON p.ID = fk.post_id AND fk.meta_key = '_yoast_wpseo_focuskw'
			LEFT JOIN {$wpdb->postmeta} ld ON p.ID = ld.post_id AND ld.meta_key = '_yoast_wpseo_linkdex'
			LEFT JOIN {$wpdb->postmeta} cs ON p.ID = cs.post_id AND cs.meta_key = '_yoast_wpseo_content_score'
			WHERE p.post_type IN ($types_sql)
				AND p.post_status = 'publish'"
		);

		if ( $row ) {
			$stats['total_posts']         = (int) $row->total_posts;
			$stats['posts_without_focus'] = (int) $row->no_keyword;
			$stats['posts_with_focus']    = (int) $row->has_keyword;
			$stats['posts_with_good_seo'] = (int) $row->good_seo;
			$stats['posts_with_poor_seo'] = (int) $row->poor_seo;
			$stats['readability_good']    = (int) $row->readability_good;
			$stats['readability_poor']    = (int) $row->readability_poor;
			$stats['avg_score']           = round( (float) $row->avg_score );
		}

		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );
		return $stats;
	}

	public function ajax_yoast_stats() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$cache_key = 'nextsafar_yoast_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'html' => $cached ) );
			return;
		}

		$stats = $this->get_yoast_stats();
		if ( ! $stats['available'] ) {
			$html = '<div class="widget-empty"><div class="widget-empty-icon">⚠️</div><p>افزونه Yoast SEO نصب نیست</p></div>';
			wp_send_json_success( array( 'html' => $html ) );
			return;
		}

		$total_scored      = $stats['posts_with_good_seo'] + $stats['posts_with_poor_seo'];
		$good_pct          = $total_scored > 0 ? round( ( $stats['posts_with_good_seo'] / $total_scored ) * 100 ) : 0;
		$bad_pct           = $total_scored > 0 ? round( ( $stats['posts_with_poor_seo'] / $total_scored ) * 100 ) : 0;
		$readability_total = $stats['readability_good'] + $stats['readability_poor'];
		$readability_pct   = $readability_total > 0 ? round( ( $stats['readability_good'] / $readability_total ) * 100 ) : 0;
		$score_class       = $stats['avg_score'] >= 70 ? 'good' : ( $stats['avg_score'] >= 40 ? 'ok' : 'bad' );

		ob_start();
		?>
		<div class="yoast-stats-container">
			<div class="yoast-overview-score <?php echo esc_attr( $score_class ); ?>">
				<div class="yoast-score-number"><?php echo esc_html( $stats['avg_score'] ); ?></div>
				<div class="yoast-score-label">میانگین امتیاز SEO</div>
			</div>
			<div class="yoast-stats-grid">
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo number_format_i18n( $stats['total_posts'] ); ?></strong><span>کل مطالب</span></div></div>
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo number_format_i18n( $stats['posts_with_good_seo'] ); ?></strong><span>سئو خوب (۷۰+)</span></div></div>
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo number_format_i18n( $stats['posts_with_poor_seo'] ); ?></strong><span>سئو ضعیف</span></div></div>
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo number_format_i18n( $stats['posts_without_focus'] ); ?></strong><span>بدون کلمه کلیدی</span></div></div>
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo esc_html( $readability_pct ); ?>٪</strong><span>خوانایی خوب</span></div></div>
				<div class="yoast-stat-item"><div class="yoast-stat-info"><strong><?php echo esc_html( $stats['version'] ); ?></strong><span>نسخه یواست</span></div></div>
			</div>
			<div class="yoast-progress-section">
				<div class="yoast-progress-label"><span>وضعیت کلی SEO</span><strong><?php echo esc_html( $good_pct ); ?>٪</strong></div>
				<div class="yoast-progress-bar">
					<div class="yoast-progress-fill yoast-progress-good" style="width: <?php echo esc_attr( $good_pct ); ?>%"></div>
					<div class="yoast-progress-fill yoast-progress-bad" style="width: <?php echo esc_attr( $bad_pct ); ?>%"></div>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		set_transient( $cache_key, $html, 15 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/* =========================================================
	   🌤 Weather
	========================================================= */

	private function get_weather_cities() {
		$cached = get_transient( 'nextsafar_ops_weather_cities' );
		if ( false !== $cached ) { return $cached; }

		$flags = array(
			'ایران' => '🇮🇷', 'ترکیه' => '🇹🇷', 'امارات' => '🇦🇪', 'گرجستان' => '🇬🇪',
			'ارمنستان' => '🇦🇲', 'قطر' => '🇶🇦', 'عمان' => '🇴🇲', 'عراق' => '🇮🇶',
			'آذربایجان' => '🇦🇿', 'مالزی' => '🇲🇾', 'تایلند' => '🇹🇭', 'چین' => '🇨🇳',
		);

		$cities = array();
		$ids = get_posts( array(
			'post_type'      => 'destination',
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'fields'         => 'ids',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_geo_lat', 'value' => '', 'compare' => '!=' ),
				array( 'key' => '_geo_lng', 'value' => '', 'compare' => '!=' ),
			),
		) );

		foreach ( $ids as $pid ) {
			$lat = (float) get_post_meta( $pid, '_geo_lat', true );
			$lng = (float) get_post_meta( $pid, '_geo_lng', true );
			if ( ! $lat || ! $lng ) { continue; }
			$name = trim( (string) get_post_meta( $pid, '_geo_city', true ) );
			if ( '' === $name ) { $name = get_the_title( $pid ); }
			if ( '' === $name ) { continue; }
			$exists = false;
			foreach ( $cities as $c ) { if ( $c['name'] === $name ) { $exists = true; break; } }
			if ( $exists ) { continue; }
			$country = trim( (string) get_post_meta( $pid, '_geo_country', true ) );
			$cities[] = array(
				'name' => $name,
				'lat'  => $lat,
				'lon'  => $lng,
				'flag' => isset( $flags[ $country ] ) ? $flags[ $country ] : '🌍',
			);
			if ( count( $cities ) >= 8 ) { break; }
		}

		if ( count( $cities ) < 4 ) {
			$defaults = array(
				array( 'name' => 'تهران', 'lat' => 35.6892, 'lon' => 51.3890, 'flag' => '🇮🇷' ),
				array( 'name' => 'مشهد', 'lat' => 36.2605, 'lon' => 59.6168, 'flag' => '🇮🇷' ),
				array( 'name' => 'استانبول', 'lat' => 41.0082, 'lon' => 28.9784, 'flag' => '🇹🇷' ),
				array( 'name' => 'دبی', 'lat' => 25.2048, 'lon' => 55.2708, 'flag' => '🇦🇪' ),
				array( 'name' => 'تفلیس', 'lat' => 41.7151, 'lon' => 44.8271, 'flag' => '🇬🇪' ),
			);
			foreach ( $defaults as $d ) {
				if ( count( $cities ) >= 8 ) { break; }
				$exists = false;
				foreach ( $cities as $c ) { if ( $c['name'] === $d['name'] ) { $exists = true; break; } }
				if ( ! $exists ) { $cities[] = $d; }
			}
		}

		set_transient( 'nextsafar_ops_weather_cities', $cities, HOUR_IN_SECONDS );
		return $cities;
	}

	public function ajax_get_weather() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$cache_key = 'nextsafar_weather_data';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'html' => $cached ) );
			return;
		}

		$cities = $this->get_weather_cities();

		$lats = implode( ',', array_column( $cities, 'lat' ) );
		$lons = implode( ',', array_column( $cities, 'lon' ) );
		$url  = sprintf(
			'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current=temperature_2m,weather_code&timezone=auto',
			$lats,
			$lons
		);

		$response     = wp_remote_get( $url, array( 'timeout' => 10 ) );
		$weather_data = array();

		if ( is_wp_error( $response ) ) {
			foreach ( $cities as $city ) {
				$weather_data[] = array( 'name' => $city['name'], 'flag' => $city['flag'], 'temp' => '—', 'icon' => '❓', 'desc' => 'خطا', 'error' => true );
			}
		} else {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$results = isset( $body[0] ) ? $body : array( $body );
			foreach ( $cities as $idx => $city ) {
				$data = isset( $results[ $idx ] ) ? $results[ $idx ] : null;
				if ( $data && isset( $data['current']['temperature_2m'] ) ) {
					$temp = round( $data['current']['temperature_2m'] );
					$code = $data['current']['weather_code'] ?? 0;
					$info = $this->get_weather_info( $code );
					$weather_data[] = array( 'name' => $city['name'], 'flag' => $city['flag'], 'temp' => $temp, 'icon' => $info['icon'], 'desc' => $info['desc'], 'error' => false );
				} else {
					$weather_data[] = array( 'name' => $city['name'], 'flag' => $city['flag'], 'temp' => '—', 'icon' => '❓', 'desc' => 'خطا', 'error' => true );
				}
			}
		}

		ob_start();
		?>
		<div class="weather-grid">
			<?php foreach ( $weather_data as $w ) : ?>
				<div class="weather-item <?php echo $w['error'] ? 'weather-error' : ''; ?>">
					<div class="weather-city"><span class="weather-flag"><?php echo esc_html( $w['flag'] ); ?></span><?php echo esc_html( $w['name'] ); ?></div>
					<div class="weather-info">
						<span class="weather-icon"><?php echo esc_html( $w['icon'] ); ?></span>
						<span class="weather-temp"><?php echo esc_html( $w['temp'] ); ?>°</span>
						<span class="weather-desc"><?php echo esc_html( $w['desc'] ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="weather-footer"><small>🕐 به‌روزرسانی هر ۳۰ دقیقه</small></div>
		<?php
		$html = ob_get_clean();
		set_transient( $cache_key, $html, 30 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'html' => $html ) );
	}

	private function get_weather_info( $code ) {
		$map = array(
			0  => array( 'icon' => '☀️', 'desc' => 'آفتابی' ),
			1  => array( 'icon' => '🌤️', 'desc' => 'عمدتاً آفتابی' ),
			2  => array( 'icon' => '⛅', 'desc' => 'نیمه ابری' ),
			3  => array( 'icon' => '☁️', 'desc' => 'ابری' ),
			45 => array( 'icon' => '🌫️', 'desc' => 'مه' ),
			48 => array( 'icon' => '🌫️', 'desc' => 'مه یخی' ),
			51 => array( 'icon' => '🌦️', 'desc' => 'نم‌نم' ),
			53 => array( 'icon' => '🌦️', 'desc' => 'نم‌نم متوسط' ),
			55 => array( 'icon' => '🌧️', 'desc' => 'نم‌نم شدید' ),
			61 => array( 'icon' => '🌧️', 'desc' => 'باران خفیف' ),
			63 => array( 'icon' => '🌧️', 'desc' => 'باران' ),
			65 => array( 'icon' => '🌧️', 'desc' => 'باران شدید' ),
			71 => array( 'icon' => '🌨️', 'desc' => 'برف خفیف' ),
			73 => array( 'icon' => '🌨️', 'desc' => 'برف' ),
			75 => array( 'icon' => '❄️', 'desc' => 'برف شدید' ),
			80 => array( 'icon' => '🌦️', 'desc' => 'رگبار' ),
			81 => array( 'icon' => '🌧️', 'desc' => 'رگبار متوسط' ),
			82 => array( 'icon' => '⛈️', 'desc' => 'رگبار شدید' ),
			95 => array( 'icon' => '⛈️', 'desc' => 'رعد و برق' ),
			96 => array( 'icon' => '⛈️', 'desc' => 'رعد و برق با تگرگ' ),
			99 => array( 'icon' => '⛈️', 'desc' => 'رعد و برق شدید' ),
		);
		return $map[ $code ] ?? array( 'icon' => '🌡️', 'desc' => 'نامشخص' );
	}

	/* =========================================================
	   💬 Recent Comments
	========================================================= */

	public function ajax_recent_comments() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$cache_key = 'nextsafar_recent_comments';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'html' => $cached, 'cached' => true ) );
			return;
		}

        $comments = get_comments( array(
            'number'  => 24,
            'status'  => 'all',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ) );

        // ✅ حذف اسپم و زباله (status=all آن‌ها را هم می‌آورد)
        $comments = array_values( array_filter( $comments, function ( $c ) {
            return in_array( (string) $c->comment_approved, array( '0', '1' ), true );
        } ) );
        $comments = array_slice( $comments, 0, 8 );

		if ( empty( $comments ) ) {
			$empty_html = '<div class="widget-empty"><div class="widget-empty-icon">💬</div><p>هنوز کامنتی ثبت نشده</p></div>';
			set_transient( $cache_key, $empty_html, 3 * MINUTE_IN_SECONDS );
			wp_send_json_success( array( 'html' => $empty_html ) );
			return;
		}

		$html = '<ul class="widget-list">';
		foreach ( $comments as $comment ) {
			$status_class = '0' === $comment->comment_approved ? 'status-pending' : 'status-approved';
			$status_text  = '0' === $comment->comment_approved ? 'در انتظار' : 'تایید شده';
			$post_title   = get_the_title( $comment->comment_post_ID );
			$author       = $comment->comment_author;
			$date         = human_time_diff( strtotime( $comment->comment_date_gmt ), current_time( 'timestamp', true ) ) . ' پیش';

			$html .= '
			<li class="widget-list-item">
				<div class="widget-list-icon">💬</div>
				<div class="widget-list-content">
					<div class="widget-list-title">' . esc_html( $author ) . '</div>
					<div class="widget-list-meta">روی «' . esc_html( $post_title ) . '» • ' . $date . '</div>
					<span class="widget-list-status ' . $status_class . '">' . $status_text . '</span>
				</div>
			</li>';
		}
		$html .= '</ul>';

		set_transient( $cache_key, $html, 3 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/* =========================================================
	   🔐 Admin Logins
	========================================================= */

    public function track_admin_login( $user_login, $user ) {
        if ( ! $user instanceof WP_User ) {
            return;
        }
        if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'edit_posts' ) ) {
            return;
        }

        $logins = get_option( 'nextsafar_admin_logins', array() );
        if ( ! is_array( $logins ) ) {
            $logins = array();
        }

        array_unshift( $logins, array(
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'time'         => current_time( 'mysql' ),
        ) );

        // ✅ autoload=false — option پرتغییر نباید در alloptions هر ریکوئست لود شود
        update_option( 'nextsafar_admin_logins', array_slice( $logins, 0, 20 ), false );
        if ( function_exists( 'wp_set_option_autoload' ) ) {
            wp_set_option_autoload( 'nextsafar_admin_logins', false );
        }

        // ✅ کلید واقعی کش ویجت _html است — هر دو پاک شوند
        delete_transient( 'nextsafar_admin_logins' );
        delete_transient( 'nextsafar_admin_logins_html' );
    }

	public function ajax_admin_logins() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$cache_key = 'nextsafar_admin_logins_html';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'html' => $cached, 'cached' => true ) );
			return;
		}

		$logins = get_option( 'nextsafar_admin_logins', array() );
		if ( empty( $logins ) ) {
			$empty_html = '<div class="widget-empty"><div class="widget-empty-icon">🔐</div><p>لاگینی ثبت نشده</p></div>';
			set_transient( $cache_key, $empty_html, 5 * MINUTE_IN_SECONDS );
			wp_send_json_success( array( 'html' => $empty_html ) );
			return;
		}

		$logins = array_slice( $logins, 0, 8 );
		$html   = '';
		foreach ( $logins as $login ) {
			$initials = '';
			foreach ( explode( ' ', $login['display_name'] ) as $part ) {
				if ( ! empty( $part ) ) {
					$initials .= mb_substr( $part, 0, 1 );
				}
			}
			$initials = mb_strtoupper( mb_substr( $initials, 0, 2 ) );
			$time     = human_time_diff( strtotime( $login['time'] ), current_time( 'timestamp' ) ) . ' پیش';

			$html .= '
			<div class="login-item">
				<div class="login-avatar">' . esc_html( $initials ) . '</div>
				<div class="login-info">
					<div class="login-name">' . esc_html( $login['display_name'] ) . '</div>
					<div class="login-time">' . $time . '</div>
				</div>
			</div>';
		}

		set_transient( $cache_key, $html, 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'html' => $html ) );
	}
}