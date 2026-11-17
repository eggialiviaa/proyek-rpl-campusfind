<?php
/**
 * app/Views/chats/room.php
 * Antarmuka Ruang Percakapan Internal Split-Pane (PAGE-01.2C.2)
 */
$currentUserId = (int) ($user['id'] ?? 0);
$isStaff = in_array($user['role_name'] ?? '', ['petugas', 'admin'], true);

if (!isset($rooms)) {
    try {
        $chatModel = new \App\Models\ChatModel();
        $rooms = $chatModel->getUserRooms($currentUserId, $user['role_name'] ?? 'civitas');
    } catch (\Throwable $e) {
        $rooms = [];
    }
}
?>

<style>
.message-item-optimistic {
    animation: cfBubbleFadeIn 160ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes cfBubbleFadeIn {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (prefers-reduced-motion: reduce) {
    .message-item-optimistic {
        animation: none !important;
        opacity: 1;
    }
}
.btn-retry-msg {
    cursor: pointer;
    text-decoration: underline;
}
.btn-retry-msg:hover {
    filter: brightness(0.85);
}
</style>

<div class="container py-3 cf-chat-workspace">
    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $roomActions = '<a href="/chats" class="btn btn-outline-secondary btn-sm rounded-pill"><i data-lucide="arrow-left" class="me-1" style="width: 15px; height: 15px;"></i>Daftar Pesan</a>'
                 . (!empty($room['context_url']) ? '<a href="' . htmlspecialchars($room['context_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-light btn-sm border rounded-pill text-dark shadow-sm ms-2"><i data-lucide="external-link" class="me-1 text-primary" style="width: 14px; height: 14px;"></i>Detail Terkait</a>' : '');
    $headerConfig = [
        'eyebrow'     => 'Ruang Diskusi',
        'title'       => $room['context_title'] ?? 'Percakapan Internal',
        'icon'        => 'bi bi-chat-dots',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Pesan & Diskusi', 'url' => '/chats'],
            ['label' => 'Ruang Diskusi'],
        ],
        'actions'     => $roomActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Main Split-Pane Glass Container (PAGE-01.2C.2) -->
    <div class="cf-chat-split-container shadow-sm">
        
        <!-- Left Master Pane: Conversation List (Visible on Desktop >=992px) -->
        <div class="cf-chat-pane-left is-room-view d-none d-lg-flex">
            <div class="cf-chat-pane-left-header">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-dark" style="font-size: 0.88rem;">Daftar Percakapan</span>
                    <span class="badge bg-secondary bg-opacity-25 text-dark rounded-pill" style="font-size: 0.72rem;"><?= count($rooms) ?> Sesi</span>
                </div>
                <div class="position-relative">
                    <input type="text" 
                           id="chatListSearchRoom" 
                           class="cf-chat-pane-left-search" 
                           placeholder="Cari ruang percakapan..." 
                           aria-label="Cari percakapan">
                </div>
            </div>

            <div class="cf-chat-pane-left-list" id="chatListContainerRoom">
                <?php foreach ($rooms as $r): ?>
                    <?php
                        $isCurrentRoom = ((int)$r['id'] === (int)$room['id']);
                        $isUnread = ($r['unread_count'] ?? 0) > 0;
                    ?>
                    <a href="/chats/<?= (int) $r['id'] ?>" 
                       class="cf-chat-room-item<?= $isCurrentRoom ? ' is-active' : '' ?>" 
                       data-search-text="<?= htmlspecialchars(strtolower(($r['display_title'] ?? '') . ' ' . ($r['display_subtitle'] ?? '') . ' ' . ($r['latest_message'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 42px; height: 42px; background: <?= $isCurrentRoom ? 'linear-gradient(135deg, #14283C, #0C1926)' : 'rgba(255, 255, 255, 0.75)' ?>; color: <?= $isCurrentRoom ? '#ffffff' : '#14283C' ?>; border: 1px solid rgba(255, 255, 255, 0.6);">
                            <i data-lucide="message-square" style="width: 20px; height: 20px;"></i>
                        </div>

                        <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                            <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                <span class="fw-bold text-dark text-truncate" style="font-size: 0.84rem; max-width: 170px;">
                                    <?= htmlspecialchars($r['display_title'] ?? 'Diskusi', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($r['latest_message_time'])): ?>
                                    <span class="text-muted flex-shrink-0" style="font-size: 0.70rem;">
                                        <?= date('d M, H:i', strtotime($r['latest_message_time'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="text-muted small text-truncate" style="font-size: 0.76rem;">
                                <?= htmlspecialchars($r['display_subtitle'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <p class="text-muted small mb-0 text-truncate opacity-75" style="font-size: 0.74rem;">
                                <?php if (!empty($r['latest_message'])): ?>
                                    <?= htmlspecialchars($r['latest_message'], ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="fst-italic">Percakapan dimulai...</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($isUnread && !$isCurrentRoom): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1 flex-shrink-0" style="font-size: 0.70rem;">
                                <?= (int) $r['unread_count'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Detail Pane: Active Chat Room Stream & Composer -->
        <div class="cf-chat-pane-right">
            <!-- Chat Room Header -->
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" 
                 style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px);">
                <div class="d-flex align-items-center gap-2">
                    <!-- Mobile Back to List Button (<992px) -->
                    <a href="/chats" class="btn btn-sm btn-outline-secondary rounded-pill d-lg-none" title="Kembali ke Daftar Pesan">
                        <i data-lucide="chevron-left" class="me-1" style="width: 14px; height: 14px;"></i>Daftar
                    </a>
                    
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm flex-shrink-0"
                         style="width: 40px; height: 40px; background: linear-gradient(135deg, #2563EB, #1D4ED8);">
                        <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="h6 fw-bold text-dark mb-0">
                                <?= htmlspecialchars($room['context_title'] ?? 'Percakapan Internal', ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <span class="cf-badge cf-badge-info">
                                <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $room['context_status'] ?? 'Aktif')), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.78rem;">
                            <?= htmlspecialchars($room['context_subtitle'] ?? '', ENT_QUOTES, 'UTF-8') ?> &bull; Kode: <span class="font-monospace fw-semibold"><?= htmlspecialchars($room['room_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($room['context_url'])): ?>
                        <a href="<?= htmlspecialchars($room['context_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill text-dark shadow-xs" style="font-size: 0.76rem;">
                            <i data-lucide="external-link" class="me-1 text-primary" style="width: 14px; height: 14px;"></i>Detail Terkait
                        </a>
                    <?php endif; ?>
                    <div class="d-none d-md-flex align-items-center gap-1 text-muted small bg-light px-2 py-1 rounded-pill border" style="font-size: 0.72rem;">
                        <i data-lucide="lock" class="text-success" style="width: 13px; height: 13px;"></i>
                        <span>Privat Terenkripsi</span>
                    </div>
                </div>
            </div>

            <!-- Privacy Alert Strip -->
            <div class="py-1 px-3 bg-light border-bottom text-muted small text-center" style="font-size: 0.74rem; background: rgba(241, 245, 249, 0.8) !important;">
                <i data-lucide="info" class="me-1 text-primary" style="width: 14px; height: 14px;"></i>Komunikasi resmi Lost &amp; Found Center. Jangan membagikan kata sandi atau informasi perbankan.
            </div>

            <!-- Messages Stream Area -->
            <div id="chatMessagesContainer" class="flex-grow-1 p-3 p-md-4 overflow-auto d-flex flex-column gap-3" 
                 style="background: rgba(248, 250, 252, 0.45);">
                
                <?php 
                $lastId = 0;
                foreach ($messages as $msg): 
                    $msgId = (int) $msg['id'];
                    if ($msgId > $lastId) {
                        $lastId = $msgId;
                    }
                    $isMine = ((int) $msg['sender_user_id'] === $currentUserId);
                    $isSystem = ($msg['message_type'] === 'system_event');
                ?>
                    <?php if ($isSystem): ?>
                        <!-- System Event Notification -->
                        <div class="d-flex justify-content-center my-2 message-item" data-msg-id="<?= $msgId ?>">
                            <div class="bg-white text-muted px-3 py-1 rounded-pill shadow-xs border small" style="font-size: 0.75rem;">
                                <i data-lucide="bell" class="text-warning me-1" style="width: 13px; height: 13px;"></i><?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="opacity-50 ms-1"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php elseif ($isMine): ?>
                        <!-- Pesan Pengirim (Saya - Kanan) -->
                        <div class="d-flex justify-content-end message-item" data-msg-id="<?= $msgId ?>">
                            <div class="d-flex flex-column align-items-end" style="max-width: 75%;">
                                <div class="p-3 text-white shadow-sm" 
                                     style="background: linear-gradient(135deg, #14283C 0%, #0C1926 100%); border-radius: 16px 16px 4px 16px; word-break: break-word; font-size: 0.90rem; line-height: 1.5; border: 1px solid rgba(255, 255, 255, 0.15);">
                                    <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                                <div class="msg-status-row text-muted small mt-1 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    <span class="msg-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                    <span class="msg-status-indicator text-muted d-inline-flex align-items-center">
                                        <?php if (!empty($msg['is_read'])): ?>
                                            <i data-lucide="check-check" class="text-primary" style="width: 14px; height: 14px;" title="Dibaca"></i>
                                        <?php else: ?>
                                            <i data-lucide="check" class="text-muted" style="width: 14px; height: 14px;" title="Terkirim"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Pesan Lawan Bicara (Kiri) -->
                        <div class="d-flex justify-content-start gap-2 message-item" data-msg-id="<?= $msgId ?>">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 mt-1 shadow-xs"
                                 style="width: 32px; height: 32px; font-size: 0.75rem; background: <?= in_array($msg['sender_role'] ?? '', ['petugas', 'admin'], true) ? '#059669' : '#475569' ?>;">
                                <i data-lucide="<?= in_array($msg['sender_role'] ?? '', ['petugas', 'admin'], true) ? 'shield-check' : 'user' ?>" style="width: 16px; height: 16px;"></i>
                            </div>
                            <div class="d-flex flex-column align-items-start" style="max-width: 75%;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold text-dark" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if (in_array($msg['sender_role'] ?? '', ['petugas', 'admin'], true)): ?>
                                        <span class="cf-badge cf-badge-found">
                                            <i data-lucide="shield-check" class="me-1" style="width: 13px; height: 13px;"></i>Petugas L&amp;F
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 bg-white text-dark shadow-sm border" 
                                     style="border-radius: 16px 16px 16px 4px; word-break: break-word; font-size: 0.90rem; line-height: 1.5; background: rgba(255, 255, 255, 0.90) !important;">
                                    <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div id="messagesEnd"></div>
            </div>

            <!-- Chat Input Area -->
            <div class="p-3 border-top bg-white" style="background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(12px);">
                <form id="chatForm" action="/chats/<?= (int) $room['id'] ?>/messages" method="POST" class="d-flex align-items-end gap-2">
                    <input type="hidden" name="_csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="flex-grow-1 position-relative">
                        <textarea id="messageInput" 
                                  name="message" 
                                  rows="1" 
                                  class="form-control bg-light border-0 shadow-none px-3 py-2" 
                                  placeholder="Tulis pesan resmi Anda di sini... (Enter untuk kirim, Shift+Enter untuk baris baru)" 
                                  style="resize: none; max-height: 120px; font-size: 0.90rem; border-radius: 18px;" 
                                  required></textarea>
                    </div>

                    <button type="submit" id="sendBtn" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 42px; height: 42px;" title="Kirim Pesan">
                        <i data-lucide="send" id="sendIcon" style="width: 17px; height: 17px;"></i>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomId = <?= (int) $room['id'] ?>;
    const currentUserId = <?= (int) $currentUserId ?>;
    let lastMessageId = <?= (int) $lastId ?>;

    const messagesContainer = document.getElementById('chatMessagesContainer');
    const messagesEnd = document.getElementById('messagesEnd');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const csrfToken = document.getElementById('csrfToken').value;

    // Search filter di pane kiri
    const searchInput = document.getElementById('chatListSearchRoom');
    const roomItems = document.querySelectorAll('.cf-chat-room-item');
    if (searchInput && roomItems.length) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            roomItems.forEach(function(item) {
                const text = item.getAttribute('data-search-text') || '';
                item.style.display = (!query || text.includes(query)) ? 'flex' : 'none';
            });
        });
    }

    // Scroll otomatis ke dasar percakapan
    function scrollToBottom(smooth = true) {
        messagesEnd.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' });
    }
    scrollToBottom(false);

    // Auto expand textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Enter untuk kirim (kecuali Shift+Enter)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    // Cegah double submit cepat
    let isSubmitting = false;

    // Submit form via AJAX dengan Optimistic UI
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (isSubmitting) return;

        const text = messageInput.value.trim();
        if (!text) return;

        isSubmitting = true;
        setTimeout(() => { isSubmitting = false; }, 120);

        // Bersihkan input secara instan dan kembalikan tinggi semula
        messageInput.value = '';
        messageInput.style.height = 'auto';
        messageInput.focus();

        // Buat temporary ID unik
        const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 7);

        // Render bubble pesan optimis secara instan ke dalam container
        renderOptimisticBubble(tempId, text);
        scrollToBottom(true);

        // Kirim request ke server di background
        sendMessagePayload(tempId, text);
    });

    // Render bubble pesan lokal (Optimistic)
    function renderOptimisticBubble(tempId, text) {
        const msgDiv = document.createElement('div');
        msgDiv.setAttribute('data-msg-id', tempId);
        msgDiv.setAttribute('data-temp-id', tempId);
        msgDiv.className = 'd-flex justify-content-end message-item message-item-optimistic';

        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        msgDiv.innerHTML = `
            <div class="d-flex flex-column align-items-end" style="max-width: 75%;">
                <div class="p-3 text-white shadow-sm" 
                     style="background: linear-gradient(135deg, #14283C 0%, #0C1926 100%); border-radius: 16px 16px 4px 16px; word-break: break-word; font-size: 0.90rem; line-height: 1.5; border: 1px solid rgba(255, 255, 255, 0.15);">
                    ${nl2br(escapeHtml(text))}
                </div>
                <div class="msg-status-row text-muted small mt-1 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                    <span class="msg-time">${timeStr}</span>
                    <span class="msg-status-indicator text-muted d-inline-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm" style="width: 0.68rem; height: 0.68rem; border-width: 1.5px;" role="status"></span>
                        <span style="font-size: 0.7rem;">Mengirim...</span>
                    </span>
                </div>
            </div>
        `;

        messagesContainer.insertBefore(msgDiv, messagesEnd);
    }

    // Kirim pesan ke server via fetch background
    async function sendMessagePayload(tempId, text) {
        const bubble = document.querySelector(`[data-temp-id="${tempId}"]`);
        if (!bubble) return;

        const indicator = bubble.querySelector('.msg-status-indicator');
        if (indicator) {
            indicator.className = 'msg-status-indicator text-muted d-inline-flex align-items-center gap-1';
            indicator.innerHTML = `
                <span class="spinner-border spinner-border-sm" style="width: 0.68rem; height: 0.68rem; border-width: 1.5px;" role="status"></span>
                <span style="font-size: 0.7rem;">Mengirim...</span>
            `;
        }

        try {
            const res = await fetch(`/chats/${roomId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: text,
                    client_msg_id: tempId,
                    _csrf_token: csrfToken
                })
            });

            const data = await res.json().catch(() => null);

            if (res.ok && data && data.success && data.message) {
                reconcileOptimisticBubble(tempId, data.message);
            } else {
                const errMsg = (data && data.error) ? data.error : 'Gagal mengirim.';
                markBubbleFailed(tempId, text, errMsg);
            }
        } catch (err) {
            console.error('Send error:', err);
            markBubbleFailed(tempId, text, 'Terjadi kesalahan jaringan.');
        }
    }

    // Rekonsiliasi bubble optimis dengan respons server
    function reconcileOptimisticBubble(tempId, serverMsg) {
        const bubble = document.querySelector(`[data-temp-id="${tempId}"]`);
        if (!bubble) return;

        const realId = parseInt(serverMsg.id, 10);

        const existingReal = document.querySelector(`.message-item[data-msg-id="${realId}"]`);
        if (existingReal && existingReal !== bubble) {
            bubble.remove();
            return;
        }

        bubble.setAttribute('data-msg-id', realId);
        bubble.removeAttribute('data-temp-id');
        bubble.removeAttribute('data-send-status');
        bubble.classList.remove('message-item-optimistic');

        const timeEl = bubble.querySelector('.msg-time');
        if (timeEl && serverMsg.created_at) {
            timeEl.textContent = new Date(serverMsg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        const indicator = bubble.querySelector('.msg-status-indicator');
        if (indicator) {
            indicator.className = 'msg-status-indicator text-muted d-inline-flex align-items-center';
            indicator.innerHTML = '<i class="bi bi-check2 text-muted" title="Terkirim"></i>';
        }

        lastMessageId = Math.max(lastMessageId, realId);
    }

    // Tandai bubble gagal terkirim dan sertakan tombol coba lagi
    function markBubbleFailed(tempId, text, errorReason) {
        const bubble = document.querySelector(`[data-temp-id="${tempId}"]`);
        if (!bubble) return;

        bubble.setAttribute('data-send-status', 'failed');
        const indicator = bubble.querySelector('.msg-status-indicator');
        if (indicator) {
            indicator.className = 'msg-status-indicator text-danger d-inline-flex align-items-center gap-1 flex-wrap justify-content-end';
            indicator.innerHTML = `
                <i class="bi bi-exclamation-circle-fill text-danger" title="${escapeHtml(errorReason)}"></i>
                <span class="small" style="font-size: 0.72rem;">Gagal mengirim</span>
                <a href="javascript:void(0)" class="btn-retry-msg text-primary fw-semibold ms-1" style="font-size: 0.72rem;">Coba lagi</a>
            `;

            const retryBtn = indicator.querySelector('.btn-retry-msg');
            if (retryBtn) {
                retryBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sendMessagePayload(tempId, text);
                });
            }
        }
    }

    // Append bubble HTML standar
    function appendMessage(msg, isMine) {
        const msgId = parseInt(msg.id, 10);
        if (document.querySelector(`[data-msg-id="${msgId}"]`)) {
            return;
        }

        const msgDiv = document.createElement('div');
        msgDiv.setAttribute('data-msg-id', msgId);
        msgDiv.className = 'message-item';

        const timeStr = new Date(msg.created_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (msg.message_type === 'system_event') {
            msgDiv.className += ' d-flex justify-content-center my-2';
            msgDiv.innerHTML = `
                <div class="bg-white text-muted px-3 py-1 rounded-pill shadow-xs border small" style="font-size: 0.75rem;">
                    <i class="bi bi-bell-fill text-warning me-1"></i>${escapeHtml(msg.message)}
                    <span class="opacity-50 ms-1">${timeStr}</span>
                </div>
            `;
        } else if (isMine) {
            msgDiv.className += ' d-flex justify-content-end';
            msgDiv.innerHTML = `
                <div class="d-flex flex-column align-items-end" style="max-width: 75%;">
                    <div class="p-3 text-white shadow-sm" 
                         style="background: linear-gradient(135deg, #14283C 0%, #0C1926 100%); border-radius: 16px 16px 4px 16px; word-break: break-word; font-size: 0.90rem; line-height: 1.5; border: 1px solid rgba(255, 255, 255, 0.15);">
                        ${nl2br(escapeHtml(msg.message))}
                    </div>
                    <div class="msg-status-row text-muted small mt-1 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                        <span class="msg-time">${timeStr}</span>
                        <span class="msg-status-indicator text-muted d-inline-flex align-items-center">
                            ${msg.is_read ? '<i class="bi bi-check2-all text-primary" title="Dibaca"></i>' : '<i class="bi bi-check2 text-muted" title="Terkirim"></i>'}
                        </span>
                    </div>
                </div>
            `;
        } else {
            msgDiv.className += ' d-flex justify-content-start gap-2';
            const isStaffMsg = (msg.sender_role === 'petugas' || msg.sender_role === 'admin');
            const roleBadge = isStaffMsg ? '<span class="cf-badge cf-badge-found"><i class="bi bi-patch-check me-1"></i>Petugas L&F</span>' : '';
            const avatarBg = isStaffMsg ? '#059669' : '#475569';
            const avatarIcon = isStaffMsg ? 'bi-shield-check' : 'bi-person-fill';

            msgDiv.innerHTML = `
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 mt-1 shadow-xs"
                     style="width: 32px; height: 32px; font-size: 0.75rem; background: ${avatarBg};">
                    <i class="bi ${avatarIcon}"></i>
                </div>
                <div class="d-flex flex-column align-items-start" style="max-width: 75%;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold text-dark" style="font-size: 0.8rem;">
                            ${escapeHtml(msg.sender_name || 'User')}
                        </span>
                        ${roleBadge}
                    </div>
                    <div class="p-3 bg-white text-dark shadow-sm border" 
                         style="border-radius: 16px 16px 16px 4px; word-break: break-word; font-size: 0.90rem; line-height: 1.5; background: rgba(255, 255, 255, 0.90) !important;">
                        ${nl2br(escapeHtml(msg.message))}
                    </div>
                    <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                        ${timeStr}
                    </div>
                </div>
            `;
        }

        messagesContainer.insertBefore(msgDiv, messagesEnd);
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function(m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
        });
    }

    function nl2br(str) {
        return (str || '').replace(/\n/g, '<br>');
    }

    // Polling berkala (setiap 3,5 detik)
    let isPolling = false;
    async function pollNewMessages() {
        if (isPolling) return;
        isPolling = true;

        try {
            const res = await fetch(`/chats/${roomId}/poll?after_id=${lastMessageId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    let hasNew = false;
                    data.messages.forEach(msg => {
                        const msgId = parseInt(msg.id, 10);
                        const isMine = (parseInt(msg.sender_user_id) === currentUserId);

                        // 1. Cek apakah pesan dengan ID ini sudah ada di DOM
                        if (document.querySelector(`[data-msg-id="${msgId}"]`)) {
                            lastMessageId = Math.max(lastMessageId, msgId);
                            return;
                        }

                        // 2. Jika pesan saya dan ada bubble dengan data-temp-id yang teksnya persis sama
                        if (isMine) {
                            const pendingBubbles = document.querySelectorAll('.message-item[data-temp-id]');
                            let matched = false;
                            for (const pb of pendingBubbles) {
                                const textDiv = pb.querySelector('.p-3');
                                if (textDiv && textDiv.textContent.trim() === msg.message.trim()) {
                                    const tempId = pb.getAttribute('data-temp-id');
                                    reconcileOptimisticBubble(tempId, msg);
                                    matched = true;
                                    break;
                                }
                            }
                            if (matched) {
                                lastMessageId = Math.max(lastMessageId, msgId);
                                return;
                            }
                        }

                        // 3. Tambahkan pesan baru
                        appendMessage(msg, isMine);
                        lastMessageId = Math.max(lastMessageId, msgId);
                        hasNew = true;
                    });
                    if (hasNew) {
                        scrollToBottom(true);
                    }
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        } finally {
            isPolling = false;
        }
    }

    // Jalankan interval polling
    const pollInterval = setInterval(pollNewMessages, 3500);

    window.addEventListener('beforeunload', () => {
        clearInterval(pollInterval);
    });
});
</script>
