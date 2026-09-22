/**
 * FYP Complaint Portal - Frontend Application Script
 */

document.addEventListener('DOMContentLoaded', () => {
    // ─── 1. Public Ticket Quick Tracker ─────────────────────────────────────
    const trackerForm = document.getElementById('publicTrackerForm');
    if (trackerForm) {
        trackerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('trackerInput');
            const resultBox = document.getElementById('trackerResult');
            const ticketNo = input.value.trim();

            if (!ticketNo) return;

            resultBox.innerHTML = `<div class="alert alert-info">Searching for ticket ${escapeHtml(ticketNo)}...</div>`;
            resultBox.style.display = 'block';

            try {
                const res = await fetch(`api/complaints.php?ticket_no=${encodeURIComponent(ticketNo)}`);
                const data = await res.json();

                if (data.status === 'success') {
                    const c = data.complaint;
                    let badgeClass = 'badge-pending';
                    if (c.status === 'In Progress') badgeClass = 'badge-progress';
                    else if (c.status === 'Resolved') badgeClass = 'badge-resolved';
                    else if (c.status === 'Rejected') badgeClass = 'badge-rejected';

                    resultBox.innerHTML = `
                        <div class="card" style="margin-top: 1rem; border-left: 4px solid var(--primary-light);">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                                <div>
                                    <span style="font-size:1.1rem; font-weight:700; color:var(--primary);">${escapeHtml(c.ticket_no)}</span>
                                    <span style="color:var(--text-muted); font-size:0.85rem; margin-left:0.5rem;">(${escapeHtml(c.department)})</span>
                                </div>
                                <span class="badge ${badgeClass}">${escapeHtml(c.status)}</span>
                            </div>
                            <h4 style="margin-bottom:0.5rem; font-size:1.05rem;">${escapeHtml(c.title)}</h4>
                            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">${escapeHtml(c.description)}</p>
                            <div style="font-size:0.82rem; color:var(--text-muted); border-top:1px solid var(--border-color); padding-top:0.5rem; display:flex; justify-content:space-between;">
                                <span>Student: ${escapeHtml(c.student_name)} (${escapeHtml(c.roll_no || 'N/A')})</span>
                                <span>Submitted: ${escapeHtml(c.created_at)}</span>
                            </div>
                            ${c.comments && c.comments.length > 0 ? `
                                <div style="margin-top:0.75rem; padding:0.75rem; background:#f1f5f9; border-radius:6px;">
                                    <strong style="font-size:0.85rem; color:var(--text-main);">Latest Official Remark:</strong>
                                    <p style="font-size:0.85rem; margin-top:0.25rem; color:#334155;">${escapeHtml(c.comments[c.comments.length - 1].message)}</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    resultBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Ticket not found.')}</div>`;
                }
            } catch (err) {
                resultBox.innerHTML = `<div class="alert alert-danger">Error connecting to the server. Please try again.</div>`;
            }
        });
    }

    // ─── 2. Filter complaints in student/admin dashboard ───────────────────
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#complaintsTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            const val = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#complaintsTable tbody tr');
            rows.forEach(row => {
                const statusBadge = row.querySelector('.badge');
                if (!statusBadge || val === 'all') {
                    row.style.display = '';
                } else {
                    const rowStatus = statusBadge.innerText.trim().toLowerCase();
                    row.style.display = rowStatus === val ? '' : 'none';
                }
            });
        });
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
