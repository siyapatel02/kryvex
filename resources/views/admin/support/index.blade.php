@extends('admin.layouts.app')

@section('title', 'Support Tickets - Kryvex Admin')

@section('content')
<div class="admin-page">
    <section class="page-head" style="margin-bottom: 20px;">
        <div>
            <p class="eyebrow" style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">ADMIN</p>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: #ffffff; margin: 0 0 4px;">Support Tickets</h1>
            <span style="color: #94a3b8; font-size: 0.9rem;">User issues &amp; replies</span>
        </div>
    </section>

    <div style="display: grid; grid-template-columns: minmax(340px, 1.1fr) minmax(360px, 1.2fr); gap: 24px; align-items: stretch;">
        
        <!-- LEFT COLUMN: SUPPORT TICKETS LIST -->
        <div class="admin-panel hover-panel" style="background: #0c1017; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 20px; min-height: 520px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin: 0;">Tickets</h3>
                <span id="ticketCountBadge" style="font-size: 0.78rem; color: #94a3b8;">0 total</span>
            </div>

            <div style="width: 100%; overflow: hidden;">
                <table class="support-tickets-table" style="width: 100% !important; min-width: 0 !important; max-width: 100% !important; table-layout: fixed !important; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 10px 4px; width: 42%;">SUBJECT</th>
                            <th style="padding: 10px 4px; width: 36%;">USER</th>
                            <th style="padding: 10px 4px; width: 22%; text-align: right;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody id="adminTicketsTableBody">
                        <tr>
                            <td colspan="3" style="padding: 24px; text-align: center; color: #64748b; font-size: 0.88rem;">
                                Loading tickets...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT COLUMN: TICKET THREAD & REPLY PANEL -->
        <div class="admin-panel hover-panel" id="adminTicketDetailPanel" style="background: #0c1017; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 20px; min-height: 520px; display: flex; flex-direction: column;">
            
            <div id="adminEmptySelectionView" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 440px; color: #64748b; text-align: center; margin: auto 0;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 12px; opacity: 0.5;">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <h4 style="color: #94a3b8; font-size: 1rem; margin: 0 0 4px;">Select a Ticket</h4>
                <p style="font-size: 0.85rem; margin: 0;">Click any support ticket from the list on the left to view thread details and reply.</p>
            </div>

            <div id="adminActiveTicketView" style="display: none; flex-direction: column; justify-content: space-between; height: 100%;">
                <div>
                    <!-- Ticket Header -->
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 14px; margin-bottom: 16px;">
                        <div>
                            <h2 id="activeTicketSubject" style="font-size: 1.15rem; font-weight: 800; color: #ffffff; margin: 0 0 4px;">--</h2>
                            <span id="activeTicketMeta" style="font-size: 0.78rem; color: #94a3b8;">--</span>
                        </div>
                        <button type="button" id="btnCloseTicket" style="background: none; border: none; color: #ef4444; font-size: 0.82rem; font-weight: 700; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'" onmouseout="this.style.background='none'">
                            Close ticket
                        </button>
                    </div>

                    <!-- Messages Thread List -->
                    <div id="adminMessageThreadContainer" style="display: flex; flex-direction: column; gap: 12px; max-height: 320px; overflow-y: auto; padding-right: 4px; margin-bottom: 16px;">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <!-- Admin Reply Input Form -->
                <form id="adminReplyForm" style="margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 16px;">
                    <div style="margin-bottom: 12px;">
                        <textarea id="adminReplyMessage" placeholder="Reply to user..." rows="3" required style="width: 100%; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 12px; color: #ffffff; padding: 12px 14px; font-size: 0.9rem; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-start;">
                        <button type="submit" id="btnAdminSendReply" style="background: #8b5cf6; color: #ffffff; border: none; font-weight: 700; padding: 10px 22px; border-radius: 10px; font-size: 0.88rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#7c3aed'" onmouseout="this.style.background='#8b5cf6'">
                            Send reply
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let allTickets = [];
    let activeTicketId = null;

    const tableBody = document.getElementById('adminTicketsTableBody');
    const ticketCountBadge = document.getElementById('ticketCountBadge');
    const emptyView = document.getElementById('adminEmptySelectionView');
    const activeView = document.getElementById('adminActiveTicketView');
    const subjectEl = document.getElementById('activeTicketSubject');
    const metaEl = document.getElementById('activeTicketMeta');
    const threadContainer = document.getElementById('adminMessageThreadContainer');
    const replyForm = document.getElementById('adminReplyForm');
    const replyMessageInput = document.getElementById('adminReplyMessage');
    const btnSendReply = document.getElementById('btnAdminSendReply');
    const btnCloseTicket = document.getElementById('btnCloseTicket');

    function getTicketId(t) {
        if (!t) return '';
        if (typeof t._id === 'string') return t._id;
        if (t._id && typeof t._id === 'object' && t._id.$oid) return t._id.$oid;
        if (t.id) return String(t.id);
        return t.ticket_uid || '';
    }

    async function loadTickets() {
        try {
            const res = await fetch('/admin/api/support/tickets');
            const data = await res.json();
            if (data.success) {
                allTickets = data.tickets || [];
                renderTicketsList();
                if (activeTicketId) {
                    const found = allTickets.find(t => getTicketId(t) === activeTicketId || t.ticket_uid === activeTicketId);
                    if (found) selectTicket(found);
                }
            }
        } catch (e) {
            console.error('Failed to load tickets', e);
        }
    }

    function renderTicketsList() {
        if (ticketCountBadge) ticketCountBadge.textContent = `${allTickets.length} total`;

        if (!allTickets || allTickets.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" style="padding: 24px; text-align: center; color: #64748b; font-size: 0.88rem;">
                        No support tickets found.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = allTickets.map(t => {
            const tId = getTicketId(t);
            const isSelected = activeTicketId && (tId === activeTicketId || t.ticket_uid === activeTicketId);
            const statusUpper = (t.status || 'OPEN').toUpperCase();
            
            let statusBadge = '<span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.35); padding: 2px 7px; border-radius: 12px; font-size: 0.68rem; font-weight: 700; display: inline-block;">OPEN</span>';
            if (statusUpper === 'ANSWERED') {
                statusBadge = '<span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); padding: 2px 7px; border-radius: 12px; font-size: 0.68rem; font-weight: 700; display: inline-block;">ANSWERED</span>';
            } else if (statusUpper === 'CLOSED') {
                statusBadge = '<span style="background: rgba(148, 163, 184, 0.2); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.35); padding: 2px 7px; border-radius: 12px; font-size: 0.68rem; font-weight: 700; display: inline-block;">CLOSED</span>';
            }

            return `
                <tr class="ticket-row-item" data-id="${tId}" style="border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.15s; ${isSelected ? 'background: rgba(139, 92, 246, 0.15);' : ''}">
                    <td style="padding: 10px 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <strong style="color: #ffffff; font-size: 0.85rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(t.subject || 'No Subject')}</strong>
                        <span style="font-size: 0.72rem; color: #64748b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(t.topic || 'General')}</span>
                    </td>
                    <td style="padding: 10px 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <span style="color: #cbd5e1; font-size: 0.82rem; font-weight: 600; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(t.user_name || t.user_email || 'User')}</span>
                    </td>
                    <td style="padding: 10px 6px; text-align: right; white-space: nowrap;">
                        ${statusBadge}
                    </td>
                </tr>
            `;
        }).join('');

        document.querySelectorAll('.ticket-row-item').forEach(row => {
            row.addEventListener('click', () => {
                const id = row.getAttribute('data-id');
                const ticket = allTickets.find(t => getTicketId(t) === id || t.ticket_uid === id);
                if (ticket) selectTicket(ticket);
            });
        });
    }

    function selectTicket(ticket) {
        activeTicketId = getTicketId(ticket);
        renderTicketsList();

        emptyView.style.setProperty('display', 'none', 'important');
        activeView.style.setProperty('display', 'flex', 'important');

        subjectEl.textContent = ticket.subject || 'No Subject';
        metaEl.textContent = `User: ${ticket.user_name || ticket.user_email} • ID: ${ticket.ticket_uid || '#--'} • Status: ${ticket.status}`;

        const replies = ticket.replies || [];
        if (replies.length === 0) {
            threadContainer.innerHTML = `
                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-bottom: 6px;">
                        <span style="font-weight: 700; color: #ffffff;">USER</span>
                    </div>
                    <p style="margin: 0; color: #e2e8f0; font-size: 0.9rem; line-height: 1.4;">${escapeHtml(ticket.description || '')}</p>
                </div>
            `;
        } else {
            threadContainer.innerHTML = replies.map(r => {
                const isAdmin = r.sender_type === 'admin';
                const senderLabel = isAdmin ? 'ADMIN' : 'USER';
                const bgStyle = isAdmin 
                    ? 'background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25);' 
                    : 'background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.06);';
                const labelColor = isAdmin ? 'color: #a78bfa;' : 'color: #cbd5e1;';
                const dateStr = r.created_at ? new Date(r.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

                return `
                    <div style="${bgStyle} border-radius: 12px; padding: 14px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-bottom: 6px;">
                            <span style="font-weight: 800; ${labelColor}">${senderLabel}</span>
                            <span>${dateStr}</span>
                        </div>
                        <p style="margin: 0; color: #ffffff; font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap;">${escapeHtml(r.message || '')}</p>
                    </div>
                `;
            }).join('');
        }

        threadContainer.scrollTop = threadContainer.scrollHeight;
    }

    replyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeTicketId) return;

        const message = replyMessageInput.value.trim();
        if (!message) return;

        btnSendReply.disabled = true;
        btnSendReply.textContent = 'Sending...';

        try {
            const targetId = encodeURIComponent(activeTicketId);
            const res = await fetch(`/admin/api/support/tickets/${targetId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ message })
            });

            const data = await res.json();
            if (data.success) {
                replyMessageInput.value = '';
                await loadTickets();
            } else {
                alert(data.message || 'Failed to send reply');
            }
        } catch (e) {
            alert('Failed to send reply');
        } finally {
            btnSendReply.disabled = false;
            btnSendReply.textContent = 'Send reply';
        }
    });

    btnCloseTicket.addEventListener('click', async () => {
        if (!activeTicketId) return;
        if (!confirm('Are you sure you want to mark this ticket as CLOSED?')) return;

        try {
            const targetId = encodeURIComponent(activeTicketId);
            const res = await fetch(`/admin/api/support/tickets/${targetId}/close`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            const data = await res.json();
            if (data.success) {
                await loadTickets();
            }
        } catch (e) {
            console.error(e);
        }
    });

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    loadTickets();
    setInterval(loadTickets, 10000);
});
</script>
@endsection
