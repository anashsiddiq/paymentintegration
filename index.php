<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Seamless Payment Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fc;
        }
        .card-payment {
            max-width: 880px;
            margin: 28px auto;
        }
        .qr-box {
            width: 320px;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
        }
        .loader {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid #ddd;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        .copy-btn {
            cursor: pointer;
        }
        .small-muted {
            color: #6c757d;
            font-size: .9rem;
        }
        @media (max-width: 767px) {
            .qr-box {
                width: 240px;
                height: 240px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card card-payment shadow-sm">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="mb-1">Demo: Pay ₹<span id="display-amount">10</span></h4>
                        <p class="small-muted">Product: <strong>Deposit</strong></p>
                        <div class="mt-3">
                            <label class="form-label">Amount (INR)</label>
                            <input id="amount" class="form-control mb-2" type="number" min="1" value="10">
                            <label class="form-label">Invoice ID</label>
                            <input id="invoice_id" class="form-control mb-2" type="text" value="<?php echo time(); ?>">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="mockMode" checked>
                                <label class="form-check-label" for="mockMode">Use mock responses (demo)</label>
                            </div>
                            <button id="btn-create" class="btn btn-primary me-2"><i class="fa fa-credit-card"></i> Pay / Create Transaction</button>
                            <button id="btn-check" class="btn btn-outline-secondary d-none"><i class="fa fa-sync"></i> Check status</button>
                            <button id="btn-retry" class="btn btn-outline-warning d-none"><i class="fa fa-redo"></i> Retry</button>
                            <div id="feedback" class="mt-3"></div>
                        </div>
                        <hr class="my-3" />
                        <div id="info-panel" class="d-none">
                            <h6>Transaction</h6>
                            <p><strong>Token:</strong> <span id="token-text" class="word-break"></span></p>
                            <p><strong>Amount:</strong> ₹<span id="amount-text"></span></p>
                            <p><strong>Status:</strong> <span id="status-badge" class="badge bg-warning text-dark">Pending</span>
                                <small id="status-msg" class="ms-2 small-muted">Awaiting payment — click "Check status" to refresh.</small></p>
                            <div class="mb-2">
                                <strong>UPI Link:</strong>
                                <div class="input-group">
                                    <input id="upi-link" class="form-control" readonly>
                                    <button id="copy-upi" class="btn btn-outline-secondary copy-btn"><i class="fa fa-copy"></i></button>
                                    <button id="open-upi" class="btn btn-outline-primary"><i class="fa fa-external-link-alt"></i></button>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>QR Code</strong>
                                <div class="mt-2 qr-box" id="qr-box"><div class="small-muted">QR not loaded</div></div>
                                <div class="mt-2 small-muted">
                                    Scan the QR code with your UPI app to pay.<br>
                                    Alternatively, click the UPI link to open your payment app or copy it to share.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 border-start d-none d-md-block">
                        <div class="px-4">
                            <h6>How it works</h6>
                            <ol>
                                <li>Click <em>Pay / Create Transaction</em> — returns a token.</li>
                                <li>Fetch UPI deposit details (QR + link).</li>
                                <li>Status will be <strong>Pending</strong> — click <em>Check status</em> to refresh or wait for auto-refresh.</li>
                            </ol>
                            <hr />
                            <h6>Logs</h6>
                            <pre id="log" style="height:240px; overflow:auto; background:#fff; padding:12px; border-radius:6px; border:1px solid #eee;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function($) {
            let pollingInterval = null;

            function log(msg) {
                $('#log').prepend('[' + new Date().toLocaleTimeString() + '] ' + msg + "\n");
            }

            function showFeedback(html, type = 'info') {
                const cls = type === 'error' ? 'alert-danger' : (type === 'success' ? 'alert-success' : 'alert-info');
                $('#feedback').html('<div class="alert ' + cls + ' py-2">' + html + '</div>');
            }

            function clearFeedback() {
                $('#feedback').html('');
            }

            function setLoading(button, loading = true) {
                if (loading) {
                    button.prop('disabled', true);
                    if (!button.data('orig')) button.data('orig', button.html());
                    button.html('<span class="loader"></span> ' + (button.data('orig') || 'Please wait'));
                } else {
                    button.prop('disabled', false);
                    if (button.data('orig')) button.html(button.data('orig'));
                }
            }

            function mapErrorMessage(xhr, defaultMsg) {
                const status = xhr.status;
                if (status === 400) return 'Bad request: Invalid or missing parameters.';
                if (status === 403) return 'Unauthorized: Invalid merchant key.';
                if (status === 404) return 'Merchant not found.';
                if (status === 500) return 'Server error: Please try again later.';
                return defaultMsg;
            }

            $('#copy-upi').on('click', function() {
                const link = $('#upi-link').val();
                if (!link) return;
                navigator.clipboard.writeText(link).then(
                    () => showFeedback('UPI link copied.', 'success'),
                    () => showFeedback('Unable to copy.', 'error')
                );
            });

            $('#open-upi').on('click', function() {
                const link = $('#upi-link').val();
                if (link) window.open(link, '_blank');
            });

            $('#amount').on('change keyup', function() {
                $('#display-amount').text($(this).val());
            });

            $('#btn-create').on('click', function() {
                clearFeedback();
                stopPolling();
                const $btn = $(this);
                const amount = parseFloat($('#amount').val());
                const invoice_id = $('#invoice_id').val().trim();
                const mock = $('#mockMode').is(':checked') ? 1 : 0;
                if (!amount || amount <= 0) {
                    showFeedback('Please enter a valid amount (greater than 0).', 'error');
                    return;
                }
                if (!invoice_id) {
                    showFeedback('Invoice ID is required.', 'error');
                    return;
                }

                setLoading($btn, true);
                $('#btn-retry').addClass('d-none').data('action', 'create');
                $.ajax({
                    url: 'api.php',
                    method: 'POST',
                    data: JSON.stringify({ action: 'create_transaction', amount: amount, invoice_id: invoice_id, mock: mock }),
                    contentType: 'application/json',
                    success: function(res) {
                        log('Create: ' + JSON.stringify(res));
                        setLoading($btn, false);
                        if (!res || !res.status) {
                            showFeedback(res?.error_message ?? 'Unable to create transaction.', 'error');
                            $('#btn-retry').removeClass('d-none');
                            return;
                        }
                        showFeedback('Transaction created. Fetching deposit...', 'success');
                        $('#info-panel').removeClass('d-none');
                        $('#token-text').text(res.data.token);
                        $('#amount-text').text(amount);
                        $('#upi-link').val('');
                        $('#qr-box').html('<div class="small-muted">Loading QR...</div>');
                        $('#btn-check').removeClass('d-none');
                        fetchDeposit(res.data.token, mock);
                    },
                    error: function(xhr) {
                        setLoading($btn, false);
                        showFeedback(mapErrorMessage(xhr, 'Network error while creating transaction.'), 'error');
                        $('#btn-retry').removeClass('d-none');
                    }
                });
            });

            function fetchDeposit(token, mock) {
                log('Fetching deposit for ' + token);
                $('#btn-retry').data('action', 'deposit').data('token', token).data('mock', mock);
                $.ajax({
                    url: 'api.php',
                    method: 'POST',
                    data: JSON.stringify({ action: 'get_deposit', token: token, mock: mock }),
                    contentType: 'application/json',
                    success: function(res) {
                        log('Deposit: ' + JSON.stringify(res));
                        if (!res || !res.status) {
                            showFeedback(res?.error_message ?? 'Unable to fetch deposit details.', 'error');
                            $('#qr-box').html('<div class="small-muted">QR unavailable</div>');
                            $('#btn-retry').removeClass('d-none');
                            return;
                        }
                        $('#upi-link').val(res.data.link || '');
                        if (res.data.qr) {
                            $('#qr-box').html('<img src="' + res.data.qr + '" style="max-width:100%;max-height:100%;border-radius:8px">');
                        }
                        showFeedback('UPI deposit details loaded.', 'success');
                        startPolling(token, mock);
                    },
                    error: function(xhr) {
                        showFeedback(mapErrorMessage(xhr, 'Network error fetching deposit details.'), 'error');
                        $('#qr-box').html('<div class="small-muted">QR unavailable</div>');
                        $('#btn-retry').removeClass('d-none');
                    }
                });
            }

            function validateTransaction(token, mock) {
                log('Validating transaction for ' + token);
                $.ajax({
                    url: 'api.php',
                    method: 'POST',
                    data: JSON.stringify({ action: 'validate', token: token, mock: mock }),
                    contentType: 'application/json',
                    success: function(res) {
                        setLoading($('#btn-check'), false);
                        log('Validate: ' + JSON.stringify(res));
                        if (!res || !res.status) {
                            showFeedback(res?.error_message ?? 'Unable to fetch transaction status.', 'error');
                            $('#btn-retry').removeClass('d-none').data('action', 'validate').data('token', token).data('mock', mock);
                            return;
                        }
                        const st = res.transaction_status || 'Pending';
                        $('#status-badge').removeClass('bg-warning bg-success bg-danger text-dark text-white');
                        if (st.toLowerCase() === 'pending') {
                            $('#status-badge').addClass('badge bg-warning text-dark').text('Pending');
                            $('#status-msg').text('Awaiting payment — click "Check status" to refresh or wait for auto-refresh.');
                        } else if (st.toLowerCase() === 'completed') {
                            $('#status-badge').addClass('badge bg-success').text('Completed');
                            $('#status-msg').text('Payment completed.');
                            stopPolling();
                        } else {
                            $('#status-badge').addClass('badge bg-danger').text(st);
                            $('#status-msg').text('Transaction status: ' + st);
                            stopPolling();
                        }
                    },
                    error: function(xhr) {
                        setLoading($('#btn-check'), false);
                        showFeedback(mapErrorMessage(xhr, 'Network error checking transaction status.'), 'error');
                        $('#btn-retry').removeClass('d-none').data('action', 'validate').data('token', token).data('mock', mock);
                    }
                });
            }

            function startPolling(token, mock) {
                if (pollingInterval) return;
                pollingInterval = setInterval(() => {
                    validateTransaction(token, mock);
                }, 10000);
            }

            function stopPolling() {
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
            }

            $('#btn-check').on('click', function() {
                clearFeedback();
                stopPolling();
                const token = $('#token-text').text();
                if (!token) {
                    showFeedback('Create transaction first.', 'error');
                    return;
                }
                const mock = $('#mockMode').is(':checked') ? 1 : 0;
                setLoading($(this), true);
                validateTransaction(token, mock);
                startPolling(token, mock);
            });

            $('#btn-retry').on('click', function() {
                clearFeedback();
                const action = $(this).data('action');
                const token = $(this).data('token');
                const mock = $(this).data('mock');
                $(this).addClass('d-none');
                if (action === 'create') {
                    $('#btn-create').trigger('click');
                } else if (action === 'deposit') {
                    fetchDeposit(token, mock);
                } else if (action === 'validate') {
                    setLoading($('#btn-check'), true);
                    validateTransaction(token, mock);
                }
            });
        })(jQuery);
    </script>
</body>
</html>