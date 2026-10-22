document.addEventListener("DOMContentLoaded", () => {

    const marketsPage = document.querySelector('[data-markets-page]');

    if (!marketsPage) {
        return;
    }

    const overlay = document.getElementById("marketModalOverlay");
    const openAddBtn = document.getElementById("openAddMarketModal");
    const closeBtn = document.getElementById("closeMarketModal");
    const cancelBtn = document.getElementById("cancelMarketModal");

    const form = document.getElementById("marketForm");
    const methodInput = document.getElementById("marketFormMethod");
    const title = document.getElementById("marketModalTitle");
    const subtitle = document.getElementById("marketModalSubtitle");
    const submitBtn = document.getElementById("marketSubmitBtn");

    const symbol = document.getElementById("marketSymbol");
    const base = document.getElementById("marketBase");
    const quote = document.getElementById("marketQuote");
    const status = document.getElementById("marketStatus");
    const minQty = document.getElementById("marketMinQty");
    const leverage = document.getElementById("marketLeverage");
    const maker = document.getElementById("marketMakerFee");
    const taker = document.getElementById("marketTakerFee");
    const order = document.getElementById("marketOrder");
    const futures = document.getElementById("marketFutures");
    const options = document.getElementById("marketOptions");
    const maintenance = document.getElementById("marketMaintenance");

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    if (!form) {
        return;
    }

    const storeUrl = form.getAttribute("action") || "";

    function openModal() {
        overlay.classList.add("show");
    }

    function closeModal() {
        overlay.classList.remove("show");
    }

    function resetFormForAdd() {
        form.action = storeUrl;
        methodInput.value = "POST";

        title.textContent = "Add Market";
        subtitle.textContent = "Create a new trading pair setting.";
        submitBtn.textContent = "Save Market";

        symbol.readOnly = false;
        base.readOnly = false;
        quote.readOnly = false;

        form.reset();

        futures.checked = true;
        options.checked = true;
        maintenance.checked = false;
        status.value = "active";
    }

    openAddBtn?.addEventListener("click", () => {
        resetFormForAdd();
        openModal();
    });

    document.querySelectorAll(".market-edit-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            form.action = btn.dataset.updateUrl;
            methodInput.value = "PATCH";

            title.textContent = "Edit Market";
            subtitle.textContent = "Update trading pair settings.";
            submitBtn.textContent = "Update Market";

            symbol.value = btn.dataset.symbol || "";
            base.value = btn.dataset.base || "";
            quote.value = btn.dataset.quote || "";
            status.value = btn.dataset.status || "active";
            minQty.value = btn.dataset.minqty || "0";
            leverage.value = btn.dataset.leverage || "1";
            maker.value = btn.dataset.maker || "0";
            taker.value = btn.dataset.taker || "0";
            order.value = btn.dataset.order || "99";

            futures.checked = btn.dataset.futures === "1";
            options.checked = btn.dataset.options === "1";
            maintenance.checked = btn.dataset.maintenance === "1";

            symbol.readOnly = true;
            base.readOnly = true;
            quote.readOnly = true;

            openModal();
        });
    });

    closeBtn?.addEventListener("click", closeModal);
    cancelBtn?.addEventListener("click", closeModal);

    overlay?.addEventListener("click", (e) => {
        if (e.target === overlay) {
            closeModal();
        }
    });
});