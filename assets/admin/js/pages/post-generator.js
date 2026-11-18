(function($) {
    'use strict';

    const PG = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $('#nextsafar-pg-form').on('submit', (e) => this.handleFormSubmit(e));
            $('#test-api-btn').on('click', () => this.testApi());
        },

        async handleFormSubmit(e) {
            e.preventDefault();

            const formData = {
                action: 'nextsafar_pg_start',
                nonce: nextsafarPG.nonce,
                sync_type: $('#sync_type').val(),
                location: $('#location').val(),
                limit: $('#limit').val()
            };

            $('#terminal-container').show();
            this.clearTerminal();
            this.log('شروع تولید پست...', 'info');
            this.log('نوع: ' + $('#sync_type option:selected').text(), 'info');
            this.log('مقصد: ' + formData.location, 'info');
            this.log('تعداد: ' + formData.limit + ' پست', 'info');

            const $btn = $('#start-sync-btn');
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال پردازش...');

            try {
                const response = await this.ajaxRequest(formData);

                if (response.success) {
                    this.log(response.data.total + ' مورد یافت شد', 'success');
                    await this.processBatches();
                } else {
                    this.log('خطا: ' + response.data.message, 'error');
                }
            } catch (error) {
                this.log('خطا: ' + error.message, 'error');
            } finally {
                $btn.prop('disabled', false).html('شروع تولید');
            }
        },

        async processBatches() {
            let completed = false;

            while (!completed) {
                try {
                    const response = await this.ajaxRequest({
                        action: 'nextsafar_pg_batch',
                        nonce: nextsafarPG.nonce
                    });

                    if (response.success) {
                        const data = response.data;
                        completed = data.completed;

                        this.updateProgress(data.progress_percent);
                        this.updateStats(data);

                        if (data.batch_details) {
                            data.batch_details.forEach(item => {
                                const icon = item.status === 'created' ? '[OK]' : item.status === 'updated' ? '[UP]' : '[FAIL]';
                                const type = item.status === 'created' ? 'success' : item.status === 'updated' ? 'info' : 'error';
                                this.log(icon + ' ' + item.name, type);
                            });
                        }

                        if (completed) {
                            this.showCompletion(data);
                        } else {
                            await this.delay(500);
                        }
                    } else {
                        this.log('خطا: ' + response.data.message, 'error');
                        break;
                    }
                } catch (error) {
                    this.log('خطا: ' + error.message, 'error');
                    break;
                }
            }
        },

        async testApi() {
            const $btn = $('#test-api-btn');
            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> در حال تست...');
            $('#api-test-results').show().html('<p>در حال تست...</p>');

            try {
                const response = await this.ajaxRequest({
                    action: 'nextsafar_pg_test_api',
                    nonce: nextsafarPG.nonce
                });

                if (response.success) {
                    let html = '<div class="nextsafar-card"><h2>نتایج تست API</h2><table class="widefat">';
                    html += '<thead><tr><th>سرویس</th><th>وضعیت</th><th>پیام</th></tr></thead><tbody>';

                    Object.entries(response.data).forEach(([service, result]) => {
                        const icon = result.status === 'success' ? '[OK]' : result.status === 'warning' ? '[WARN]' : '[FAIL]';
                        const color = result.status === 'success' ? '#46b450' : result.status === 'warning' ? '#ffb900' : '#dc3232';
                        html += '<tr><td><strong>' + service + '</strong></td><td style="color:' + color + '">' + icon + '</td><td>' + result.message + '</td></tr>';
                    });

                    html += '</tbody></table></div>';
                    $('#api-test-results').html(html);
                } else {
                    $('#api-test-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            } catch (error) {
                $('#api-test-results').html('<div class="notice notice-error"><p>خطا: ' + error.message + '</p></div>');
            } finally {
                $btn.prop('disabled', false).html('تست API');
            }
        },

        ajaxRequest(data) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: nextsafarPG.ajaxUrl,
                    type: 'POST',
                    data: data,
                    success: resolve,
                    error: (xhr, status, error) => reject(new Error(error || 'Unknown error'))
                });
            });
        },

        log(message, type) {
            const $terminal = $('#terminal-body');
            $terminal.append($('<div class="terminal-line"></div>').addClass(type || 'info').text(message));
            $terminal.scrollTop($terminal[0].scrollHeight);
        },

        clearTerminal() { $('#terminal-body').empty(); },

        updateProgress(percent) {
            $('#progress-bar').css('width', percent + '%');
        },

        updateStats(data) {
            $('#stats-row').show();
            $('#stat-total').text(data.total);
            $('#stat-created').text(data.created);
            $('#stat-updated').text(data.updated);
            $('#stat-failed').text(data.failed);
        },

        showCompletion(data) {
            this.log('تولید پست کامل شد!', 'success');
            this.log('کل: ' + data.total + ' | ایجاد: ' + data.created + ' | بروزرسانی: ' + data.updated + ' | ناموفق: ' + data.failed, 'info');
        },

        delay(ms) { return new Promise(r => setTimeout(r, ms)); }
    };

    $(document).ready(() => PG.init());
})(jQuery);