/**
 * NextSafar News Admin JavaScript
 * 
 * Handles:
 * - Terminal UI for manual sync
 * - AJAX interactions for all news pages
 * - Keyword management
 * - Gemini API testing
 */

(function($) {
    'use strict';

    const NewsAdmin = {
        syncInProgress: false,

        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Manual sync
            $('#start-news-sync').on('click', () => this.startSync());
            
            // AI reset
            $('#reset-ai-btn').on('click', () => this.resetAi());
            
            // Gemini test
            $('#test-gemini-btn').on('click', () => this.testGemini());
            
            // Sources
            $('#refresh-sources-btn').on('click', () => this.loadSources());
            $('#reset-sources-btn').on('click', () => this.resetSources());
            
            // Keywords
            $('#add-keyword-btn').on('click', () => this.addKeyword());
            $(document).on('click', '.delete-keyword-btn', (e) => this.deleteKeyword(e));
            
            // History
            $('#refresh-history-btn').on('click', () => this.loadHistory());
            
            // Hours grid toggle - Fixed version
            $(document).on('click', '.ns-hour-cell', function(e) {
                e.preventDefault(); // ❗ جلوگیری از toggle خودکار مرورگر
                e.stopPropagation();
                
                const $checkbox = $(this).find('input[type="checkbox"]');
                const isChecked = $checkbox.prop('checked');
                
                $checkbox.prop('checked', !isChecked);
                $(this).toggleClass('checked', !isChecked);
            });

            // جلوگیری از bubble شدن event خود checkbox
            $(document).on('click', '.ns-hour-cell input[type="checkbox"]', function(e) {
                e.stopPropagation();
            });

            // Select All
            $('#ns-select-all-hours').on('click', function() {
                $('.ns-hour-cell input[type="checkbox"]').prop('checked', true);
                $('.ns-hour-cell').addClass('checked');
            });

            // Clear All
            $('#ns-clear-all-hours').on('click', function() {
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

            const mode = $('#sync-mode').val();

            this.syncInProgress = true;
            const $btn = $('#start-news-sync');
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال شروع...');

            $('#terminal-container').show();
            this.clearTerminal();
            this.log('🚀 شروع همگام‌سازی اخبار...', 'info');
            this.log('حالت: ' + (mode === 'ai' ? '🤖 با AI' : '⚡ سریع'), 'info');
            this.log('─────────────────────────────────────', 'process');

            try {
                const response = await this.ajax('nextsafar_news_batch_start', { mode });

                if (!response.success) {
                    this.log('❌ ' + (response.data.message || 'خطا'), 'error');
                    this.resetSyncButton();
                    return;
                }

                const data = response.data;

                if (data.total === 0) {
                    this.log('ℹ️ ' + (data.message || 'هیچ خبری یافت نشد'), 'warning');
                    this.resetSyncButton();
                    return;
                }

                this.log(`✅ ${data.total} خبر یافت شد`, 'success');
                this.log(`📦 RSS: ${data.stats.rss} | API: ${data.stats.api}`, 'info');
                this.log(`🔁 تکراری حذف شد: ${data.stats.duplicates}`, 'info');
                this.log(`🗑️ فیلتر شد: ${data.stats.filtered}`, 'info');
                this.log(`📦 تقسیم به ${data.total_batches} batch (هر ${data.batch_size} خبر)`, 'info');
                this.log('─────────────────────────────────────', 'process');

                setTimeout(() => this.processNextBatch(), 500);
            } catch (error) {
                this.log('❌ خطا: ' + error.message, 'error');
                this.resetSyncButton();
            }
        },

        async processNextBatch() {
            try {
                const response = await this.ajax('nextsafar_news_batch_process');

                if (!response.success) {
                    this.log('❌ ' + (response.data.message || 'خطا'), 'error');
                    this.resetSyncButton();
                    return;
                }

                const data = response.data;

                // Update progress
                $('#progress-bar').css('width', data.progress_percent + '%');

                // Update stats
                $('#stats-row').show();
                $('#stat-total').text(data.total);
                $('#stat-published').text(data.published);
                $('#stat-drafts').text(data.drafts);
                $('#stat-failed').text(data.failed);
                $('#stat-ai').text(data.ai_used);

                // Log batch
                this.log(`📦 Batch ${data.current_batch}/${data.total_batches} (${data.progress_percent}%)`, 'process');

                if (data.batch_details) {
                    data.batch_details.forEach(item => {
                        const icon = {
                            'published': '✅',
                            'draft':     '📝',
                            'failed':    '❌',
                            'skipped':   '⏭️'
                        }[item.status] || '❓';
                        
                        const type = {
                            'published': 'success',
                            'draft':     'warning',
                            'failed':    'error',
                            'skipped':   'info'
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
            this.log(`  • کل: ${data.total}`, 'info');
            this.log(`  • ✅ منتشر شده: ${data.published}`, 'success');
            this.log(`  • 📝 پیش‌نویس: ${data.drafts}`, 'warning');
            this.log(`  • ⏭️ رد شده: ${data.skipped}`, 'info');
            this.log(`  • ❌ ناموفق: ${data.failed}`, 'error');
            
            if (data.ai_used > 0) {
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

            try {
                const response = await this.ajax('nextsafar_news_reset_ai');
                if (response.success) {
                    alert('✅ وضعیت AI ریست شد');
                    location.reload();
                } else {
                    alert('❌ ' + response.data.message);
                }
            } catch (error) {
                alert('❌ خطا: ' + error.message);
            }
        },

        async testGemini() {
            const $btn = $('#test-gemini-btn');
            const $result = $('#gemini-test-result');
            
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال تست...');
            $result.html('<p>در حال ارتباط با Gemini API...</p>');

            try {
                const response = await this.ajax('nextsafar_news_test_api');
                
                if (response.success) {
                    $result.html(`
                        <div class="ns-info-box" style="border-color:#00a32a;background:#e8f5e9;">
                            ✅ ${response.data.message}<br>
                            <small>پاسخ: ${this.escapeHtml(response.data.response)}<br>
                            توکن مصرفی: ${response.data.tokens}</small>
                        </div>
                    `);
                } else {
                    $result.html(`
                        <div class="ns-info-box" style="border-color:#d63638;background:#ffebee;">
                            ❌ ${response.data.message}
                        </div>
                    `);
                }
            } catch (error) {
                $result.html(`<div class="ns-info-box" style="border-color:#d63638;">❌ ${error.message}</div>`);
            } finally {
                $btn.prop('disabled', false).html('🧪 تست اتصال');
            }
        },

        /* ═══════════════════════════════════════════════════
           Sources
           ═══════════════════════════════════════════════════ */

        async loadSources() {
            $('#sources-table-container').html('<p>در حال بارگذاری...</p>');
            try {
                const response = await this.ajax('nextsafar_news_get_sources');
                location.reload();
            } catch (error) {
                $('#sources-table-container').html('<p style="color:red;">❌ ' + error.message + '</p>');
            }
        },

        async resetSources() {
            if (!confirm('همه منابع فعلی حذف و منابع پیش‌فرض جایگزین می‌شوند. ادامه می‌دهید؟')) return;

            try {
                const response = await this.ajax('nextsafar_news_reset_sources');
                alert(response.success ? '✅ ' + response.data.message : '❌ ' + response.data.message);
                location.reload();
            } catch (error) {
                alert('❌ ' + error.message);
            }
        },

        /* ═══════════════════════════════════════════════════
           Keywords
           ═══════════════════════════════════════════════════ */

        async addKeyword() {
            const keyword = $('#new-keyword').val().trim();
            const type = $('#new-keyword-type').val();
            const weight = $('#new-keyword-weight').val();

            if (!keyword) {
                alert('لطفاً یک کلمه کلیدی وارد کنید');
                return;
            }

            try {
                const response = await this.ajax('nextsafar_news_add_keyword', {
                    keyword,
                    type,
                    weight: parseInt(weight) || 10,
                });

                if (response.success) {
                    $('#new-keyword').val('');
                    location.reload();
                } else {
                    alert('❌ ' + response.data.message);
                }
            } catch (error) {
                alert('❌ ' + error.message);
            }
        },

        async deleteKeyword(e) {
            const id = $(e.currentTarget).data('id');
            if (!confirm('این کلمه کلیدی حذف شود؟')) return;

            try {
                const response = await this.ajax('nextsafar_news_delete_keyword', { id });
                if (response.success) {
                    $(e.currentTarget).closest('.ns-keyword-item').fadeOut(() => location.reload());
                } else {
                    alert('❌ ' + response.data.message);
                }
            } catch (error) {
                alert('❌ ' + error.message);
            }
        },

        async loadHistory() {
            $('#history-container').html('<p>در حال بارگذاری...</p>');
            try {
                const response = await this.ajax('nextsafar_news_get_history', { limit: 50 });
                location.reload();
            } catch (error) {
                $('#history-container').html('<p style="color:red;">❌ ' + error.message + '</p>');
            }
        },

        /* ═══════════════════════════════════════════════════
           Helpers
           ═══════════════════════════════════════════════════ */

        ajax(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: nextsafarNews.ajaxUrl,
                    type: 'POST',
                    data: { action, nonce: nextsafarNews.nonce, ...data },
                    success: resolve,
                    error: (xhr, status, error) => reject(new Error(error || 'خطای ارتباط'))
                });
            });
        },

        log(message, type = 'info') {
            const $terminal = $('#terminal-body');
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
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    $(document).ready(() => NewsAdmin.init());
})(jQuery);