<?php
// Content list is scoped to the currently selected view.
$view = (($_GET['view'] ?? 'personal') === 'shared') ? 'shared' : 'personal';
$user_id = (int) $_SESSION['user_id'];

if ($view === 'shared') {
    // Shared tasks are visible to authenticated members under the current project model.
    $stmt = $pdo->prepare('
        SELECT t.*, u.nama_lengkap AS pembuat
        FROM todos t
        JOIN users u ON t.user_id = u.id
        WHERE t.tipe = "shared"
        ORDER BY t.selesai ASC, t.created_at DESC
    ');
    $stmt->execute();
} else {
    $stmt = $pdo->prepare('
        SELECT t.*, u.nama_lengkap AS pembuat
        FROM todos t
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ? AND t.tipe = "personal"
        ORDER BY t.selesai ASC, t.created_at DESC
    ');
    $stmt->execute([$user_id]);
}

$todos = $stmt->fetchAll();
$pendingCount = 0;
$completedCount = 0;
$overdueCount = 0;
$now = time();

foreach ($todos as $task) {
    if ((int) $task['selesai'] === 1) {
        $completedCount++;
        continue;
    }

    $pendingCount++;
    if (!empty($task['tenggat'])) {
        $deadline = strtotime($task['tenggat']);
        if ($deadline !== false && $deadline <= $now) {
            $overdueCount++;
        }
    }
}

$viewTitle = $view === 'shared' ? 'Shared tasks' : 'Personal tasks';
$viewDescription = $view === 'shared'
    ? 'Coordinate work with everyone in your shared workspace.'
    : 'Plan and track the tasks that belong to you.';
?>
<main class="dashboard-content" id="dashboard-main">
    <div class="page-heading">
        <div>
            <p class="eyebrow"><?= $view === 'shared' ? 'COLLABORATION' : 'MY WORKSPACE' ?></p>
            <h2><?= htmlspecialchars($viewTitle, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="page-description"><?= htmlspecialchars($viewDescription, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="primary-button add-task-button" href="#todo-form">
            <span aria-hidden="true">+</span>
            Add New Task
        </a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="flash-success" role="status"><?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="flash-error" role="alert"><?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <section class="statistics-grid" aria-label="Task statistics">
        <article class="stat-card">
            <span class="stat-icon stat-icon--blue" aria-hidden="true">[ ]</span>
            <div><p>Total Tasks</p><strong id="stat-total"><?= $pendingCount + $completedCount ?></strong></div>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon--amber" aria-hidden="true">~</span>
            <div><p>Pending Tasks</p><strong id="stat-pending"><?= $pendingCount ?></strong></div>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon--green" aria-hidden="true">OK</span>
            <div><p>Completed Tasks</p><strong id="stat-completed"><?= $completedCount ?></strong></div>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon--red" aria-hidden="true">!</span>
            <div><p>Overdue Tasks</p><strong id="stat-overdue"><?= $overdueCount ?></strong></div>
        </article>
    </section>

    <section class="task-panel" aria-labelledby="task-list-heading">
        <div class="task-panel-heading">
            <div>
                <h3 id="task-list-heading">Task list</h3>
                <p>Search and filter tasks in this workspace.</p>
            </div>
        </div>

        <div class="task-toolbar">
            <label class="search-control">
                <span class="visually-hidden">Search by task title or description</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-4-4"></path>
                </svg>
                <input type="search" id="task-search" placeholder="Search tasks..." autocomplete="off">
            </label>
            <label class="filter-control">
                <span class="visually-hidden">Filter tasks by status</span>
                <select id="task-filter">
                    <option value="all">All status</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="overdue">Overdue</option>
                </select>
            </label>
        </div>

        <div class="table-wrap" role="region" aria-label="Tasks table" tabindex="0">
            <table class="task-table" id="task-table">
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden">Toggle status</span></th>
                        <th scope="col">Task</th>
                        <th scope="col">Category</th>
                        <th scope="col">Deadline</th>
                        <th scope="col">Reminder</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="task-table-body">
                    <?php foreach ($todos as $todo): ?>
                        <?php include 'todo-item.php'; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="task-empty-state" class="empty-state" role="status" <?= $todos ? 'hidden' : '' ?>>
            No tasks match this search or filter.
        </p>
        <p id="task-result-count" class="task-result-count" aria-live="polite">
            Showing <?= count($todos) ?> <?= count($todos) === 1 ? 'task' : 'tasks' ?>
        </p>
    </section>
</main>