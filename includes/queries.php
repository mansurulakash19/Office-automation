<?php
// includes/queries.php — queries that more than one page needs.
require_once __DIR__ . '/auth.php';

// Everything that is due (or already late) within the next $days days.
// Admins see the whole company. Staff only see their own tasks and approval requests.
function upcoming_deadlines(int $days = 7, int $limit = 8): array {
    $horizon = date('Y-m-d', strtotime('+' . $days . ' days'));
    $items = [];

    if (is_admin()) {
        $rows = db_all("SELECT i.invoice_no, i.due_date, c.name AS client FROM invoices i
                        JOIN clients c ON c.id = i.client_id
                        WHERE i.status IN ('Pending','Overdue') AND i.due_date <= ?
                        ORDER BY i.due_date LIMIT 10", [$horizon]);
        foreach ($rows as $r) {
            $items[] = ['type' => 'Invoice', 'icon' => 'receipt', 'title' => $r['invoice_no'] . ' · ' . $r['client'],
                        'date' => $r['due_date'], 'link' => 'accounts.php?tab=invoices'];
        }

        $rows = db_all("SELECT name, next_followup FROM clients
                        WHERE next_followup IS NOT NULL AND next_followup <= ?
                        ORDER BY next_followup LIMIT 10", [$horizon]);
        foreach ($rows as $r) {
            $items[] = ['type' => 'Follow-up', 'icon' => 'users', 'title' => 'Call ' . $r['name'],
                        'date' => $r['next_followup'], 'link' => 'clients.php'];
        }
    }

    $sql = "SELECT title, due_date FROM approvals
            WHERE status IN ('Pending','Overdue') AND due_date IS NOT NULL AND due_date <= ?";
    $params = [$horizon];
    if (!is_admin()) {
        $sql .= ' AND requested_by = ?';
        $params[] = user_id();
    }
    foreach (db_all($sql . ' ORDER BY due_date LIMIT 10', $params) as $r) {
        $items[] = ['type' => 'Approval', 'icon' => 'shield', 'title' => $r['title'],
                    'date' => $r['due_date'], 'link' => 'approvals.php'];
    }

    $sql = "SELECT t.title, t.due_date, p.title AS project FROM tasks t
            JOIN projects p ON p.id = t.project_id
            WHERE t.status <> 'Done' AND t.due_date IS NOT NULL AND t.due_date <= ?";
    $params = [$horizon];
    if (!is_admin()) {
        $sql .= ' AND t.assigned_to = ?';
        $params[] = user_id();
    }
    foreach (db_all($sql . ' ORDER BY t.due_date LIMIT 10', $params) as $r) {
        $items[] = ['type' => 'Task', 'icon' => 'kanban', 'title' => $r['title'] . ' · ' . $r['project'],
                    'date' => $r['due_date'], 'link' => 'projects.php?tab=board'];
    }

    usort($items, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    return array_slice($items, 0, $limit);
}
