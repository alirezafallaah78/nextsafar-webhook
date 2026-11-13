<?php

namespace NextSafar\MetaBoxes;

class VisaMetaBox {

    /* ═══════════════════════════════════════════════════════
       Visa Types — انواع ویزا
       ═══════════════════════════════════════════════════════ */
    const VISA_TYPES = [
        // ─── توریستی ───
        'توریستی',
        'توریستی سینگل (یکبار ورود)',
        'توریستی مولتی (چندبار ورود)',
        'توریستی الکترونیکی (e-Visa)',

        // ─── تجاری ───
        'تجاری',
        'تجاری سینگل',
        'تجاری مولتی',

        // ─── کاری و تحصیلی ───
        'کاری',
        'تحصیلی',
        'کارآموزی',

        // ─── پزشکی ───
        'درمانی (پزشکی)',
        'همراه بیمار',

        // ─── ترانزیت ───
        'ترانزیت',
        'ترانزیت فرودگاهی',

        // ─── سایر انواع ───
        'با دعوت‌نامه',
        'بدون دعوت‌نامه',
        'فوری (اکسپرس)',
        'عادی',
        'لیبل (برچسب)',
        'خدمات سفارت',
        'خانوادگی',
        'سرمایه‌گذاری',
    ];

    /* ═══════════════════════════════════════════════════════
       Durations — مدت زمان ویزا
       ═══════════════════════════════════════════════════════ */
    const VISA_DURATIONS = [
        '7 روزه', '10 روزه', '14 روزه', '15 روزه', '20 روزه', '21 روزه',
        '30 روزه', '45 روزه', '58 روزه', '60 روزه', '90 روزه',
        '3 ماهه', '6 ماهه',
        '1 ساله', '2 ساله', '3 ساله', '5 ساله', '10 ساله',
    ];

    /* ═══════════════════════════════════════════════════════
       Persons — انواع اشخاص
       ═══════════════════════════════════════════════════════ */
    const VISA_PERSONS = [
        'بزرگسال',
        'کودک (زیر 12 سال)',
        'نوزاد (زیر 2 سال)',
        'سالمند (بالای 65 سال)',
        'دانشجویی',
        'خانوادگی (زوجین)',
    ];

    /* ═══════════════════════════════════════════════════════
       Currencies — واحدهای پولی
       ═══════════════════════════════════════════════════════ */
    const VISA_CURRENCIES = [
        'USD' => 'دلار آمریکا',
        'EUR' => 'یورو',
        'GBP' => 'پوند انگلیس',
        'TRY' => 'لیر ترکیه',
        'CNY' => 'یوان چین',
        'IRR' => 'ریال ایران',
        'JPY' => 'ین ژاپن',
        'AED' => 'درهم امارات',
        'CAD' => 'دلار کانادا',
        'AUD' => 'دلار استرالیا',
    ];

    /* ═══════════════════════════════════════════════════════
       Registration
       ═══════════════════════════════════════════════════════ */
    public static function register() {
        add_meta_box('nextsafar_visa_banner', '🏞️ عکس بنر ویزا', [__CLASS__, 'render_banner'], 'visa', 'side', 'default');
        add_meta_box('nextsafar_visa_prices', '💰 قیمت‌های مختلف ویزا', [__CLASS__, 'render_prices'], 'visa', 'normal', 'high');
        add_meta_box('nextsafar_visa_info', '📋 اطلاعات ویزا', [__CLASS__, 'render_info'], 'visa', 'normal', 'default');
        add_meta_box('nextsafar_visa_documents', '📄 مدارک مورد نیاز ویزا', [__CLASS__, 'render_documents'], 'visa', 'normal', 'default');
        add_meta_box('nextsafar_visa_description', '📝 توضیحات ویزا', [__CLASS__, 'render_description'], 'visa', 'normal', 'default');
    }

    public static function register_hooks() {
        add_action('save_post_visa', [__CLASS__, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);

        // ✅ ادیتور کلاسیک برای ویزا: metaboxهای این پلاگین (به‌خصوص wp_editor)
        //    داخل iframe گوتنبرگ درست کار نمی‌کنند.
        add_filter('use_block_editor_for_post_type', [__CLASS__, 'maybe_disable_block_editor'], 10, 2);
    }

    public static function maybe_disable_block_editor($use_block_editor, $post_type) {
        return ($post_type === 'visa') ? false : $use_block_editor;
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen    = function_exists('get_current_screen') ? get_current_screen() : null;
        $post_type = $screen ? $screen->post_type : ($GLOBALS['post_type'] ?? '');
        if ($post_type !== 'visa') {
            return;
        }

        wp_enqueue_media();

        // ✅ نسخه‌گذاری بر اساس زمان تغییر فایل → کش مرورگر بعد از آپدیت، فایل قدیمی را نشان نمی‌دهد
        $css_ver = @filemtime(NEXTSAFAR_PATH . 'assets/admin/css/metaboxes/visa.css') ?: NEXTSAFAR_VERSION;
        $js_ver  = @filemtime(NEXTSAFAR_PATH . 'assets/admin/js/metaboxes/visa.js') ?: NEXTSAFAR_VERSION;

        wp_enqueue_style(
            'nextsafar-visa-admin',
            NEXTSAFAR_URL . 'assets/admin/css/metaboxes/visa.css',
            [],
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-visa-admin',
            NEXTSAFAR_URL . 'assets/admin/js/metaboxes/visa.js',
            ['jquery'],
            NEXTSAFAR_VERSION,
            true
        );
    }

    /* ═══════════════════════════════════════════════════════
       Banner Image
       ═══════════════════════════════════════════════════════ */
    public static function render_banner($post) {
        wp_nonce_field('nextsafar_visa_meta', 'visa_meta_nonce');

        $banner = get_post_meta($post->ID, '_visa_banner', true);
        ?>
        <div class="ns-visa-banner-field">
            <input type="hidden" name="visa_banner" id="ns-visa-banner-input" value="<?= esc_attr($banner); ?>">

            <div class="ns-visa-banner-preview" id="ns-visa-banner-preview">
                <?php if ($banner) : ?>
                    <img src="<?= esc_url($banner); ?>" style="max-width:100%; height:auto; border-radius:6px;">
                <?php else : ?>
                    <div class="ns-visa-banner-placeholder">🏞️ بنری انتخاب نشده</div>
                <?php endif; ?>
            </div>

            <div class="ns-visa-banner-buttons" style="margin-top:10px;">
                <button type="button" class="button button-primary ns-visa-banner-upload">📁 انتخاب بنر</button>
                <button type="button" class="button ns-visa-banner-remove" <?= $banner ? '' : 'style="display:none;"'; ?>>✕ حذف</button>
            </div>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════════════════════
       Prices — همه فیلدها SELECT به‌جز قیمت
       ═══════════════════════════════════════════════════════ */
    public static function render_prices($post) {
        wp_nonce_field('nextsafar_visa_meta', 'visa_meta_nonce');

        $visa_prices = get_post_meta($post->ID, '_visa_prices', true);
        if (!is_array($visa_prices)) $visa_prices = [];
        ?>
        <div class="nextsafar-visa-metabox">
            <div class="ns-visa-prices-header" aria-hidden="true">
                <span>نوع ویزا</span>
                <span>مدت</span>
                <span>شخص</span>
                <span>قیمت</span>
                <span>واحد پول</span>
                <span></span>
            </div>

            <div class="ns-visa-prices-wrapper" id="visa-prices-wrapper">
                <?php foreach ($visa_prices as $index => $price_item) : ?>
                    <?= self::price_row_html((int) $index, is_array($price_item) ? $price_item : []); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="ns-btn-add" id="add-visa-price">➕ افزودن قیمت جدید</button>

            <!-- ✅ قالب ردیف جدید برای JS — دقیقاً همان HTML ردیف‌های بالا (منبع واحد حقیقت) -->
            <script type="text/html" id="tmpl-ns-visa-price-row">
                <?= self::price_row_html('__INDEX__', []); ?>
            </script>
        </div>
        <?php
    }

    /**
     * ✅ ساخت HTML یک ردیف قیمت — هم برای ردیف‌های ذخیره‌شده و هم قالب ردیف جدید
     */
    private static function price_row_html(int|string $index, array $item): string {
        $i = (string) $index;

        $html  = '<div class="ns-visa-price-group">';
        $html .= '<select name="visa_prices[' . $i . '][type]" aria-label="نوع ویزا">'
               . self::select_options(self::VISA_TYPES, (string) ($item['type'] ?? ''), '-- نوع ویزا --') . '</select>';
        $html .= '<select name="visa_prices[' . $i . '][duration]" aria-label="مدت ویزا">'
               . self::select_options(self::VISA_DURATIONS, (string) ($item['duration'] ?? ''), '-- مدت --') . '</select>';
        $html .= '<select name="visa_prices[' . $i . '][person]" aria-label="نوع شخص">'
               . self::select_options(self::VISA_PERSONS, (string) ($item['person'] ?? ''), '-- شخص --') . '</select>';
        $html .= '<input type="text" inputmode="decimal" name="visa_prices[' . $i . '][price]" placeholder="قیمت" aria-label="قیمت" value="'
               . esc_attr((string) ($item['price'] ?? '')) . '">';
        $html .= '<select name="visa_prices[' . $i . '][currency]" aria-label="واحد پول">'
               . self::currency_options(self::VISA_CURRENCIES, (string) ($item['currency'] ?? '')) . '</select>';
        $html .= '<button type="button" class="ns-remove-visa-price" title="حذف ردیف">✕</button>';
        $html .= '</div>';

        return $html;
    }

    /* ═══════════════════════════════════════════════════════
       Info
       ═══════════════════════════════════════════════════════ */
    public static function render_info($post) {
        $visa_issue  = get_post_meta($post->ID, '_visa_issue', true);
        $visa_expiry = get_post_meta($post->ID, '_visa_expiry', true);
        ?>
        <div class="nextsafar-visa-metabox">
            <div class="ns-visa-info-grid">
                <div class="ns-field">
                    <label for="visa_issue">⏱️ زمان اخذ ویزا:</label>
                    <input type="text" name="visa_issue" id="visa_issue" value="<?= esc_attr($visa_issue); ?>"
                           class="ns-input" placeholder="مثلاً ۱۰ روز کاری">
                </div>

                <div class="ns-field">
                    <label for="visa_expiry">📅 اعتبار ویزا پس از صدور:</label>
                    <input type="text" name="visa_expiry" id="visa_expiry" value="<?= esc_attr($visa_expiry); ?>"
                           class="ns-input" placeholder="مثلاً ۹۰ روز">
                </div>
            </div>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════════════════════
       Documents
       ═══════════════════════════════════════════════════════ */
    public static function render_documents($post) {
        $fields = self::get_visa_doc_fields();
        $saved_data = get_post_meta($post->ID, '_visa_docs', true);
        if (!is_array($saved_data)) $saved_data = [];
        ?>
        <div class="nextsafar-visa-metabox">
            <div class="ns-visa-docs-grid">
                <?php foreach ($fields as $field) :
                    $checked = !empty($saved_data[$field]['checked']) ? 'checked' : '';
                    $text = $saved_data[$field]['text'] ?? '';
                    ?>
                    <div class="ns-visa-doc-item">
                        <label class="ns-visa-doc-label">
                            <input type="checkbox" name="visa_docs[<?= esc_attr($field); ?>][checked]"
                                   value="1" <?= $checked; ?>>
                            <span><?= esc_html($field); ?></span>
                        </label>
                        <input type="text" name="visa_docs[<?= esc_attr($field); ?>][text]"
                               value="<?= esc_attr($text); ?>"
                               placeholder="توضیحات..."
                               class="ns-visa-doc-text">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public static function render_description($post) {
        $content = get_post_meta($post->ID, '_visa_description', true);
        wp_editor($content, 'visa_description', [
            'textarea_name' => 'visa_description',
            'textarea_rows' => 10,
            'media_buttons' => true,
            'tinymce'       => true,
            'quicktags'     => true,
        ]);
    }

    /* ═══════════════════════════════════════════════════════
       Save
       ═══════════════════════════════════════════════════════ */
    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!isset($_POST['visa_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['visa_meta_nonce'])), 'nextsafar_visa_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (get_post_type($post_id) !== 'visa') return;

        // Banner
        if (isset($_POST['visa_banner'])) {
            $banner = esc_url_raw(wp_unslash($_POST['visa_banner']));
            if ($banner !== '') {
                update_post_meta($post_id, '_visa_banner', $banner);
            } else {
                delete_post_meta($post_id, '_visa_banner');
            }
        }

        // Prices — فقط ردیف‌های دارای قیمت ذخیره می‌شوند
        if (isset($_POST['visa_prices']) && is_array($_POST['visa_prices'])) {
            $prices = [];

            foreach (wp_unslash($_POST['visa_prices']) as $item) {
                if (!is_array($item)) continue;

                $row = [
                    'type'     => sanitize_text_field($item['type'] ?? ''),
                    'duration' => sanitize_text_field($item['duration'] ?? ''),
                    'person'   => sanitize_text_field($item['person'] ?? ''),
                    'price'    => sanitize_text_field($item['price'] ?? ''),
                    'currency' => sanitize_text_field($item['currency'] ?? ''),
                ];

                if (trim($row['price']) === '') continue; // ✅ حذف ردیف بدون قیمت

                $prices[] = $row;
            }

            if (!empty($prices)) {
                update_post_meta($post_id, '_visa_prices', $prices);
            } else {
                delete_post_meta($post_id, '_visa_prices');
            }
        } else {
            delete_post_meta($post_id, '_visa_prices');
        }

        // Info
        if (isset($_POST['visa_issue'])) {
            update_post_meta($post_id, '_visa_issue', sanitize_text_field(wp_unslash($_POST['visa_issue'])));
        }
        if (isset($_POST['visa_expiry'])) {
            update_post_meta($post_id, '_visa_expiry', sanitize_text_field(wp_unslash($_POST['visa_expiry'])));
        }

        // Description
        if (isset($_POST['visa_description'])) {
            update_post_meta($post_id, '_visa_description', wp_kses_post(wp_unslash($_POST['visa_description'])));
        }

        // Documents
        $fields = self::get_visa_doc_fields();
        $docs   = (isset($_POST['visa_docs']) && is_array($_POST['visa_docs'])) ? wp_unslash($_POST['visa_docs']) : [];
        $data   = [];

        foreach ($fields as $field) {
            $data[$field] = [
                'checked' => !empty($docs[$field]['checked']),
                'text'    => sanitize_text_field($docs[$field]['text'] ?? ''),
            ];
        }

        update_post_meta($post_id, '_visa_docs', $data);
    }

    public static function get_visa_doc_fields() {
        return [
            'پاسپورت', 'عکس ۳ در ۴', 'شناسنامه', 'کارت ملی', 'بلیط پرواز',
            'ووچر هتل', 'بیمه مسافرتی', 'فرم اطلاعات', 'تمکن مالی', 'برنامه سفر',
            'آدرس میزبان', 'نامه اشتغال به کار', 'اصل و ترجمه سند ملکی', 'انگشت نگاری',
            'ضمانت بازگشت', 'پاسپورت قدیمی', 'گواهی اشتغال به تحصیل', 'دعوت نامه',
            'کارت پایان خدمت', 'واکسن', 'مدارک همسر (خانم)', 'رضایت‌نامه محضری',
        ];
    }

    /* ═══════════════════════════════════════════════════════
       Helper Functions
       ═══════════════════════════════════════════════════════ */

    private static function select_options(array $options, string $current, string $placeholder = '-- انتخاب کنید --'): string {
        $html = '<option value="">' . esc_html($placeholder) . '</option>';

        // مقدارهای قدیمی که در لیست جدید نیستند، حفظ و نمایش داده می‌شوند
        if ($current !== '' && !in_array($current, $options, true)) {
            $html .= '<option value="' . esc_attr($current) . '" selected>' . esc_html($current) . ' (فعلی)</option>';
        }

        foreach ($options as $opt) {
            $html .= '<option value="' . esc_attr($opt) . '"' . selected($current, $opt, false) . '>' . esc_html($opt) . '</option>';
        }

        return $html;
    }

    private static function currency_options(array $currencies, string $current): string {
        $html = '<option value="">-- واحد پول --</option>';

        // ✅ حفظ واحد پول قدیمیِ خارج از لیست (مثل کدهای نسخه قبلی)
        if ($current !== '' && !isset($currencies[$current])) {
            $html .= '<option value="' . esc_attr($current) . '" selected>' . esc_html($current) . ' (فعلی)</option>';
        }

        foreach ($currencies as $code => $name) {
            $html .= '<option value="' . esc_attr($code) . '"' . selected($current, $code, false) . '>' . esc_html($name) . ' (' . esc_html($code) . ')</option>';
        }

        return $html;
    }

    public static function get_min_price(int $post_id): array {
        $prices = get_post_meta($post_id, '_visa_prices', true);
        if (!is_array($prices)) return ['price_min' => null, 'currency' => ''];

        $min = null;
        $cur = '';

        foreach ($prices as $p) {
            $val = self::to_number((string) ($p['price'] ?? ''));
            if ($val > 0 && ($min === null || $val < $min)) {
                $min = $val;
                $cur = sanitize_text_field($p['currency'] ?? '');
            }
        }

        return ['price_min' => $min, 'currency' => $cur];
    }

    private static function to_number(string $str): float {
        $str = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $str);
        $str = str_replace(['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9'], $str);
        $str = str_replace(['٫', '٬'], ['.', ''], $str); // ممیز و جداکننده هزارگان فارسی/عربی
        $str = preg_replace('/[^\d.]/', '', $str);
        return (float) $str;
    }

    /* ═══════════════════════════════════════════════════════
       REST — خروجی کامل برای فرانت‌اند (Next.js)
       ═══════════════════════════════════════════════════════ */
    public static function register_rest_fields() {
        register_rest_field('visa', 'visa_info', [
            'get_callback' => function ($post) {
                $id  = (int) $post['id'];
                $min = self::get_min_price($id);

                // ✅ لیست کامل قیمت‌ها (نه فقط حداقل قیمت)
                $raw_prices = get_post_meta($id, '_visa_prices', true);
                if (!is_array($raw_prices)) $raw_prices = [];

                $prices = array_values(array_map(function ($p) {
                    return [
                        'type'      => (string) ($p['type'] ?? ''),
                        'duration'  => (string) ($p['duration'] ?? ''),
                        'person'    => (string) ($p['person'] ?? ''),
                        'price'     => self::to_number((string) ($p['price'] ?? '')),
                        'price_raw' => (string) ($p['price'] ?? ''),
                        'currency'  => (string) ($p['currency'] ?? ''),
                    ];
                }, $raw_prices));

                // ✅ مدارک مورد نیاز (فقط تیک‌خورده‌ها)
                $raw_docs  = get_post_meta($id, '_visa_docs', true);
                $documents = [];
                if (is_array($raw_docs)) {
                    foreach ($raw_docs as $name => $d) {
                        if (!empty($d['checked'])) {
                            $documents[] = ['name' => (string) $name, 'note' => (string) ($d['text'] ?? '')];
                        }
                    }
                }

                return [
                    'price_min'   => $min['price_min'],
                    'currency'    => $min['currency'] ?: null,
                    'prices'      => $prices,
                    'issue'       => get_post_meta($id, '_visa_issue', true) ?: null,
                    'expiry'      => get_post_meta($id, '_visa_expiry', true) ?: null,
                    'banner'      => get_post_meta($id, '_visa_banner', true) ?: null,
                    'documents'   => $documents,
                    'description' => get_post_meta($id, '_visa_description', true) ?: null,
                ];
            },
            'update_callback' => null,
            'schema' => [
                'description' => 'اطلاعات ویزا برای فرانت‌اند',
                'type'        => 'object',
                'context'     => ['view', 'edit'],
            ],
        ]);
    }
}