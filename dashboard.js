(function () {
    var tableBody = document.getElementById("task-table-body");
    var searchInput = document.getElementById("task-search");
    var filterSelect = document.getElementById("task-filter");
    if (!tableBody || !searchInput || !filterSelect) return;

    var rows = Array.prototype.slice.call(tableBody.querySelectorAll(".task-item"));
    var emptyState = document.getElementById("task-empty-state");
    var resultCount = document.getElementById("task-result-count");

    function updateStat(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = String(value);
    }

    function applyFilters() {
        var query = searchInput.value.trim().toLocaleLowerCase();
        var selectedStatus = filterSelect.value;
        var counts = { total: 0, pending: 0, completed: 0, overdue: 0 };

        rows.forEach(function (row) {
            var matchesText = (row.dataset.search || "").toLocaleLowerCase().indexOf(query) !== -1;
            var status = row.dataset.status;
            var overdue = row.dataset.overdue === "true";
            var matchesStatus = selectedStatus === "all"
                || (selectedStatus === "pending" && status === "pending")
                || (selectedStatus === "completed" && status === "completed")
                || (selectedStatus === "overdue" && overdue);
            var visible = matchesText && matchesStatus;

            row.hidden = !visible;
            var editRow = document.getElementById("edit-row-" + row.dataset.todoId);
            if (editRow) editRow.hidden = !visible;

            if (visible) {
                counts.total++;
                if (status === "completed") counts.completed++;
                else {
                    counts.pending++;
                    if (overdue) counts.overdue++;
                }
            }
        });

        updateStat("stat-total", counts.total);
        updateStat("stat-pending", counts.pending);
        updateStat("stat-completed", counts.completed);
        updateStat("stat-overdue", counts.overdue);
        if (emptyState) emptyState.hidden = counts.total !== 0;
        if (resultCount) {
            resultCount.textContent = "Showing " + counts.total + (counts.total === 1 ? " task" : " tasks");
        }
    }

    searchInput.addEventListener("input", applyFilters);
    filterSelect.addEventListener("change", applyFilters);
    applyFilters();
}());