/**
 * NextSafar News Admin JavaScript
 * Handles: Terminal UI for manual sync, AJAX interactions, Keyword management, Gemini test
 */
(function ($) {
    'use strict';

    // ✅ گارد یک‌بار اجرا (اگر اسکریپت دو بار لود شد، eventها دوبل bind نشوند)
    if (window.__nextsafarNewsAdminInit) return;
    window.__nextsafarNewsAdminInit = true;

    const NewsAdmin = {
        syncInProgress: false,

        cfg() {
            return window.nextsafarNews || {};
        },

        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Manual sync
            $('#start-news-sync').on('click', () => this.startSync());

            // AI
            $('#reset-ai-btn').on('click', () => this.resetAi());
            $('#test-gemini-btn').on('click', () => this.testGemini());

            // Sources
            $('#refresh-sources-btn').on('click', () => this.loadSources());
            $('#reset-sources-btn').on('click', () => this.resetSources());

            // Keywords
            $('#add-keyword-btn').on('click', () => this.addKeyword());
            $(document).on('click', '.delete-keyword-btn', (e) => this.deleteKeyword(e));

            // History
            $('#refresh-history-btn').on('click', () => this.loadHistory());

            // ✅ Hours grid — رفتار native چک‌باکس + همگام‌سازی کلاس (بدون preventDefault شکننده)
            $(document).on('change', '.ns-hour-cell input[type="checkbox"]', function () {
                $(this).closest('.ns-hour-cell').toggleClass('checked', this.checked);
            });

            $('#ns-select-all-hours').on('click', function () {
                $('.ns-hour-cell input[type="checkbox"]').prop('checked', true);
                $('.ns-hour-cell').addClass('checked');
            });

            $('#ns-clear-all-hours').on('click', function () {
                $('.ns-hour-cell input[type="checkbox"]').prop('checked', false);
                $('.ns-hour-cell').removeClass('checked');
            });
        },

        /* ═══════════════════════════════════════════════════
           Manual Sync
           ═══════════════════════════════════════════════════ */
        async startSync() {
            if (this.syncInProgress) {
                alert('یک سینک در حال اجرا است!');
                return;
            }

            const mode = $('#sync-mode').val() || 'quick';
            this.syncInProgress = true;

            const $btn = $('#start-news-sync');
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال شروع...');

            $('#terminal-container').show();
            this.clearTerminal();
            this.log('🚀 شروع همگام‌سازی اخبار...', 'info');
            this.log('حالت: ' + (mode === 'ai' ? '🤖 با AI' : '⚡ سریع'), 'info');
            this.log('─────────────────────────────────────', 'process');

            try {
                const response = await this.ajax('nextsafar_news_batch_start', { mode }, 30000);

                if (!response || !response.success) {
                    this.log('❌ ' + this.msg(response), 'error');
                    this.resetSyncButton();
                    return;
                }

                this.log('📡 صف دریافت ساخته شد؛ در حال دریافت از منابع…', 'info');
                $btn.html('<span class="spinner-inline"></span> در حال دریافت...');
                setTimeout(() => this.runFetchQueue(), 300);
            } catch (error) {
                this.log('❌ خطا: ' + error.message, 'error');
                this.resetSyncButton();
            }
        },

        // ✅ هر درخواست = یک منبع؛ progress واقعی per-source
        async runFetchQueue() {
            let guard = 0;

            while (guard++ < 80) {
                let data = null;
                try {
                    const res = await this.ajax('nextsafar_news_fetch_step', {}, 120000);
                    if (!res || !res.success) {
                        this.log('❌ ' + this.msg(res), 'error');
                        this.resetSyncButton();
                        return;
                    }
                    data = res.data || {};
                } catch (e) {
                    this.log('❌ ' + e.message, 'error');
                    this.resetSyncButton();
                    return;
                }

                if (data.phase === 'fetching') {
                    const label = data.source_name || 'منبع';
                    const idx = data.source_cursor || 0;
                    const tot = data.source_total || 0;

                    if (data.source_error) {
                        this.log(`⚠️ [${idx}/${tot}] ${label}: ${data.source_error}`, 'warning');
                    } else {
                        this.log(`📡 [${idx}/${tot}] ${label} — ${data.source_count || 0} خبر`, 'info');
                    }

                    $('#progress-bar').css('width', (data.progress_percent || 0) + '%');
                    await new Promise((r) => setTimeout(r, 250));
                    continue;
                }

                this.onFetchFinished(data);
                return;
            }

            this.log('❌ دریافت منابع بیش از حد طول کشید.', 'error');
            this.resetSyncButton();
        },

        onFetchFinished(data) {
            if (data.phase === 'failed') {
                this.log('❌ ' + (data.message || 'خطا در دریافت اخبار'), 'error');
                this.resetSyncButton();
                return;
            }

            const st = data.stats || {};
            this.log(`📦 RSS: ${st.rss || 0} | API: ${st.api || 0}`, 'info');
            this.log(`🔁 تکراری حذف شد: ${st.duplicates || 0}`, 'info');
            this.log(`🗑️ فیلتر شد: ${st.filtered || 0}`, 'info');

            if (!data.total) {
                this.log('ℹ️ ' + (data.message || 'هیچ خبری برای پردازش یافت نشد'), 'warning');
                this.resetSyncButton();
                return;
            }

            this.log(`✅ ${data.total} خبر آماده پردازش است`, 'success');
            this.log(`📦 ${data.total_batches} batch (هر ${data.batch_size} خبر)`, 'info');
            this.log('─────────────────────────────────────', 'process');

            $('#start-news-sync').html('<span class="spinner-inline"></span> در حال پردازش...');
            setTimeout(() => this.processNextBatch(), 500);
        },

        async processNextBatch() {
            try {
                const response = await this.ajax('nextsafar_news_batch_process', {}, 300000);

                if (!response || !response.success) {
                    this.log('❌ ' + this.msg(response), 'error');
                    this.resetSyncButton();
                    return;
                }

                const data = response.data || {};

                if (data.phase === 'fetching') {
                    setTimeout(() => this.runFetchQueue(), 500);
                    return;
                }

                $('#progress-bar').css('width', (data.progress_percent || 0) + '%');

                $('#stats-row').show();
                $('#stat-total').text(data.total || 0);
                $('#stat-published').text(data.published || 0);
                $('#stat-drafts').text(data.drafts || 0);
                $('#stat-failed').text(data.failed || 0);
                $('#stat-ai').text(data.ai_used || 0);

                this.log(`📦 Batch ${data.current_batch}/${data.total_batches} (${data.progress_percent || 0}%)`, 'process');

                if (Array.isArray(data.batch_details)) {
                    data.batch_details.forEach((item) => {
                        const icon = {
                            published: '✅',
                            draft: '📝',
                            failed: '❌',
                            skipped: '⏭️'
                        }[item.status] || '❓';

                        const type = {
                            published: 'success',
                            draft: 'warning',
                            failed: 'error',
                            skipped: 'info'
                        }[item.status] || 'info';

                        this.log(`${icon} ${item.name} [${item.source || '?'}]`, type);
                    });
                }

                if (data.completed) {
                    this.showCompletion(data);
                } else {
                    setTimeout(() => this.processNextBatch(), 500);
                }
            } catch (error) {
                this.log('❌ خطا: ' + error.message, 'error');
                this.resetSyncButton();
            }
        },

        showCompletion(data) {
            this.resetSyncButton();
            $('#progress-bar').css('width', '100%');
            this.log('─────────────────────────────────────', 'process');
            this.log('🎉 همگام‌سازی با موفقیت کامل شد!', 'success');
            this.log('', 'info');
            this.log('📊 خلاصه نهایی:', 'info');
            this.log(`  • کل: ${data.total || 0}`, 'info');
            this.log(`  • ✅ منتشر شده: ${data.published || 0}`, 'success');
            this.log(`  • 📝 پیش‌نویس: ${data.drafts || 0}`, 'warning');
            this.log(`  • ⏭️ رد شده: ${data.skipped || 0}`, 'info');
            this.log(`  • ❌ ناموفق: ${data.failed || 0}`, 'error');
            if ((data.ai_used || 0) > 0) {
                this.log(`  • 🤖 با AI: ${data.ai_used}`, 'process');
            }
        },

        resetSyncButton() {
            this.syncInProgress = false;
            $('#start-news-sync').prop('disabled', false).html('🚀 شروع همگام‌سازی');
        },

        /* ═══════════════════════════════════════════════════
           AI Management
           ═══════════════════════════════════════════════════ */

        async resetAi() {
            if (!confirm('آیا مطمئن هستید که می‌خواهید وضعیت AI را ریست کنید؟')) return;
            const $btn = $('#reset-ai-btn');
            $btn.prop('disabled', true);
            try {
                const response = await this.ajax('nextsafar_news_reset_ai');
                if (response && response.success) {
                    alert('✅ وضعیت AI ریست شد');
                    location.reload();
                } else {
                    alert('❌ ' + this.msg(response));
                    $btn.prop('disabled', false);
                }
            } catch (error) {
                alert('❌ ' + error.message);
                $btn.prop('disabled', false);
            }
        },

        async testGemini() {
            const $btn = $('#test-gemini-btn');
            const $result = $('#gemini-test-result');
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال تست...');
            $result.html('<p>در حال ارتباط با Gemini API...</p>');

            try {
                const response = await this.ajax('nextsafar_news_test_api', {}, 60000);
                if (response && response.success) {
                    const d = response.data || {};
                    $result.html(
                        '<div class="ns-info-box" style="border-color:#00a32a;background:#e8f5e9;">' +
                        '✅ ' + this.escapeHtml(d.message || 'اتصال برقرار است') + '<br>' +
                        '<small>پاسخ: ' + this.escapeHtml(d.response || '') + '<br>' +
                        'توکن مصرفی: ' + (d.tokens || 0) + '</small></div>'
                    );
                } else {
                    $result.html(
                        '<div class="ns-info-box" style="border-color:#d63638;background:#ffebee;">❌ ' +
                        this.escapeHtml(this.msg(response)) + '</div>'
                    );
                }
            } catch (error) {
                $result.html('<div class="ns-info-box" style="border-color:#d63638;">❌ ' + this.escapeHtml(error.message) + '</div>');
            } finally {
                $btn.prop('disabled', false).html('🧪 تست اتصال');
            }
        },

        /* ═══════════════════════════════════════════════════
           Sources
           ═══════════════════════════════════════════════════ */

        // ✅ قبلاً یک AJAX می‌زد و جوابش را دور می‌ریخت، بعد reload می‌کرد.
        // داده‌ها سمت سرور رندر می‌شوند؛ فقط reload کافی است.
        loadSources() {
            $('#sources-table-container').html('<p>در حال بارگذاری...</p>');
            location.reload();
        },

        async resetSources() {
            if (!confirm('همه منابع فعلی حذف و منابع پیش‌فرض جایگزین می‌شوند. ادامه می‌دهید؟')) return;
            const $btn = $('#reset-sources-btn');
            $btn.prop('disabled', true);
            try {
                const response = await this.ajax('nextsafar_news_reset_sources');
                alert((response && response.success ? '✅ ' : '❌ ') + this.msg(response));
                location.reload();
            } catch (error) {
                alert('❌ ' + error.message);
                $btn.prop('disabled', false);
            }
        },

        /* ═══════════════════════════════════════════════════
           Keywords
           ═══════════════════════════════════════════════════ */

        async addKeyword() {
            const $btn = $('#add-keyword-btn');
            const keyword = String($('#new-keyword').val() || '').trim();
            const type = $('#new-keyword-type').val() || 'positive';

            if (!keyword) {
                alert('لطفاً یک کلمه کلیدی وارد کنید');
                return;
            }

            let weight = parseInt($('#new-keyword-weight').val(), 10);
            if (!Number.isFinite(weight) || weight === 0) weight = 15;
            weight = Math.min(100, Math.abs(weight));
            if (type === 'negative') weight = -weight; // ✅ هماهنگ با نوع

            $btn.prop('disabled', true);
            try {
                const response = await this.ajax('nextsafar_news_add_keyword', { keyword, type, weight });
                if (response && response.success) {
                    $('#new-keyword').val('');
                    location.reload();
                } else {
                    alert('❌ ' + this.msg(response));
                    $btn.prop('disabled', false);
                }
            } catch (error) {
                alert('❌ ' + error.message);
                $btn.prop('disabled', false);
            }
        },

        async deleteKeyword(e) {
            // ✅ currentTarget باید قبل از await گرفته شود (بعد از await برابر null می‌شود)
            const $btn = $(e.currentTarget);
            const id = $btn.data('id');
            if (!id) return;
            if (!confirm('این کلمه کلیدی حذف شود؟')) return;

            $btn.prop('disabled', true);
            try {
                const response = await this.ajax('nextsafar_news_delete_keyword', { id: id });
                if (response && response.success) {
                    const $item = $btn.closest('.ns-keyword-item');
                    if ($item.length) {
                        $item.fadeOut(180, () => location.reload());
                    } else {
                        location.reload();
                    }
                } else {
                    alert('❌ ' + this.msg(response));
                    $btn.prop('disabled', false);
                }
            } catch (error) {
                alert('❌ ' + error.message);
                $btn.prop('disabled', false);
            }
        },

        loadHistory() {
            $('#history-container').html('<p>در حال بارگذاری...</p>');
            location.reload();
        },

        /* ═══════════════════════════════════════════════════
           Helpers
           ═══════════════════════════════════════════════════ */

        ajax(action, data = {}, timeout = 60000) {
            const c = this.cfg();
            if (!c.ajaxUrl || !c.nonce) {
                return Promise.reject(new Error('تنظیمات AJAX بارگذاری نشده است؛ صفحه را رفرش کنید.'));
            }

            return new Promise((resolve, reject) => {
                $.ajax({
                    url: c.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    timeout: timeout,
                    data: Object.assign({ action: action, nonce: c.nonce }, data),
                    success: resolve,
                    error: (xhr, status, error) => {
                        // ✅ پاسخ JSON خطا (مثل nonce منقضی) هم به مسیر معمول برود
                        if (xhr && xhr.responseJSON) {
                            resolve(xhr.responseJSON);
                            return;
                        }
                        if (status === 'timeout') {
                            reject(new Error('زمان درخواست به پایان رسید'));
                            return;
                        }
                        reject(new Error(error || 'خطای ارتباط با سرور'));
                    }
                });
            });
        },

        msg(response, fallback) {
            if (response && response.data && response.data.message) {
                return response.data.message;
            }
            return fallback || 'خطای نامشخص';
        },

        log(message, type = 'info') {
            const $terminal = $('#terminal-body');
            if (!$terminal.length) return; // ✅ در صفحاتی که ترمینال ندارند خطا ندهد

            const time = new Date().toLocaleTimeString('fa-IR', { hour12: false });
            $terminal.append(
                `<div class="terminal-line ${type}"><span style="color:#484f58;margin-right:8px;font-size:11px;">[${time}]</span>${this.escapeHtml(message)}</div>`
            );
            $terminal.scrollTop($terminal[0].scrollHeight);
        },

        clearTerminal() {
            $('#terminal-body').empty();
        },

        escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    };

    $(function () {
        NewsAdmin.init();
    });
})(jQuery);