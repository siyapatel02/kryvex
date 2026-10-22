document.addEventListener("DOMContentLoaded", () => {
    const overlay = document.getElementById("auditModalOverlay");
    const closeBtn = document.getElementById("closeAuditModal");

    const admin = document.getElementById("auditAdmin");
    const module = document.getElementById("auditModule");
    const action = document.getElementById("auditAction");
    const ip = document.getElementById("auditIp");
    const date = document.getElementById("auditDate");
    const message = document.getElementById("auditMessage");
    const agent = document.getElementById("auditAgent");
    const meta = document.getElementById("auditMeta");

    document.querySelectorAll(".audit-detail-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            admin.textContent = btn.dataset.admin || "-";
            module.textContent = btn.dataset.module || "-";
            action.textContent = btn.dataset.action || "-";
            ip.textContent = btn.dataset.ip || "-";
            date.textContent = btn.dataset.date || "-";
            message.textContent = btn.dataset.message || "-";
            agent.textContent = btn.dataset.agent || "-";
            meta.textContent = btn.dataset.meta || "{}";

            overlay.classList.add("show");
        });
    });

    closeBtn?.addEventListener("click", () => {
        overlay.classList.remove("show");
    });

    overlay?.addEventListener("click", (e) => {
        if (e.target === overlay) {
            overlay.classList.remove("show");
        }
    });
});