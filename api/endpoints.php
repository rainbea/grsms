<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$currentUserName = $_SESSION['staff']['full_name'] ?? 'Camille Torres';
$sessionUserId   = trim((string)($_SESSION['staff']['user_id'] ?? ''));

if ($sessionUserId !== '') {
    $currentUserId = $sessionUserId;
} else {
    $chkUser = $pdo->prepare('SELECT user_id FROM system_user WHERE user_id = ? LIMIT 1');
    $chkUser->execute(['USR-009']);
    $foundDefault = $chkUser->fetchColumn();
    if ($foundDefault) {
        $currentUserId = (string)$foundDefault;
    } else {
        $anyUser = $pdo->query('SELECT user_id FROM system_user ORDER BY user_id ASC LIMIT 1')->fetchColumn();
        $currentUserId = $anyUser ? (string)$anyUser : 'USR-009';
    }
}

function nextPrefixedId(PDO $pdo, string $table, string $col, string $prefix, int $pad = 3): string {
    $stmt = $pdo->query("SELECT $col FROM $table");
    $max = 0;
    while ($row = $stmt->fetchColumn()) {
        if (preg_match('/(\d+)$/', (string)$row, $m)) {
            $num = (int)$m[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    return $prefix . '-' . str_pad((string)($max + 1), $pad, '0', STR_PAD_LEFT);
}

function normalizeBoardStatus(?string $rawStatus): string {
    $s = trim((string)$rawStatus);
    return match ($s) {
        'Received', 'Handed to Technician' => 'Received',
        'Diagnosing', 'Under Diagnosis' => 'Diagnosing',
        'Repairing', 'In Progress' => 'Repairing',
        'Ready for pickup' => 'Ready for pickup',
        'Picked up', 'Completed' => 'Picked up',
        '' => 'Received',
        default => $s,
    };
}

function recalculateInvoiceStatus(PDO $pdo, string $invoiceId): string {
    $totStmt = $pdo->prepare('
        SELECT COALESCE(SUM(rs.quantity * rs.actual_price), 0)
        FROM invoice i
        LEFT JOIN repair_service rs ON rs.repair_request_ID = i.repair_request_ID
        WHERE i.invoice_ID = ?
    ');
    $totStmt->execute([$invoiceId]);
    $serviceTotal = (float)$totStmt->fetchColumn();

    $invTotStmt = $pdo->prepare('SELECT COALESCE(total, 0) FROM invoice WHERE invoice_ID = ? LIMIT 1');
    $invTotStmt->execute([$invoiceId]);
    $storedTotal = (float)$invTotStmt->fetchColumn();
    $total = max($serviceTotal, $storedTotal);

    $paidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM payment
        WHERE invoice_ID = ? AND payment_status = 'Completed'
    ");
    $paidStmt->execute([$invoiceId]);
    $completedPaid = (float)$paidStmt->fetchColumn();

    $pendStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM payment
        WHERE invoice_ID = ? AND payment_status = 'Pending'
    ");
    $pendStmt->execute([$invoiceId]);
    $hasPending = ((int)$pendStmt->fetchColumn()) > 0;

    if ($completedPaid > 0 && ($total <= 0 || $completedPaid >= $total)) {
        $newStatus = 'Paid';
    } elseif ($hasPending) {
        $newStatus = 'Pending';
    } elseif ($completedPaid > 0) {
        $newStatus = 'Partially Paid';
    } else {
        $newStatus = 'Unpaid';
    }

    $upd = $pdo->prepare('UPDATE invoice SET status = ? WHERE invoice_ID = ?');
    $upd->execute([$newStatus, $invoiceId]);

    return $newStatus;
}

try {
    switch ($action) {
        case 'login':
            $username = trim($input['username'] ?? $input['email'] ?? '');
            $password = (string)($input['password'] ?? '');

            $stmt = $pdo->prepare('
                SELECT user_id, first_name, middle_name, last_name, username, password_hash, role, specialization, account_status
                FROM system_user
                WHERE username = ?
                LIMIT 1
            ');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $validPass = $user && (
                password_verify($password, $user['password_hash'])
                || hash_equals($user['password_hash'], $password)
            );

            if (!$user || !$validPass) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'message' => 'Invalid username or password.']);
                exit;
            }

            if ($user['account_status'] !== 'Active') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Account is Inactive. Ask an admin to reactivate yours.']);
                exit;
            }

            $fullName = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
            $_SESSION['staff'] = [
                'user_id'        => $user['user_id'],
                'full_name'      => $fullName,
                'username'       => $user['username'],
                'role'           => $user['role'],
                'specialization' => $user['specialization'],
            ];
            echo json_encode(['ok' => true, 'staff' => $_SESSION['staff']]);
            break;

        case 'create_staff':
        case 'signup':
            $firstName  = trim($input['first_name'] ?? '');
            $middleName = trim($input['middle_name'] ?? '');
            $lastName   = trim($input['last_name'] ?? '');
            $username   = trim($input['username'] ?? '');
            $password   = (string)($input['password'] ?? '');
            $contact    = trim($input['contact_number'] ?? '');
            $role       = trim($input['role'] ?? 'Technician');
            $rawSpec    = $input['specialization'] ?? null;
            $spec       = ($rawSpec === null || trim((string)$rawSpec) === '' || strtoupper(trim((string)$rawSpec)) === 'NULL')
                ? null
                : trim((string)$rawSpec);
            $status     = 'Active';

            $allowedRoles = ['Technician', 'Receptionist', 'Admin', 'Cashier', 'Manager'];
            if (!in_array($role, $allowedRoles, true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invalid role selected.']);
                exit;
            }

            if ($firstName === '' || $lastName === '' || $username === '' || $password === '' || $contact === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Please complete all required fields.']);
                exit;
            }

            $userCheck = $pdo->prepare('SELECT COUNT(*) FROM system_user WHERE username = ?');
            $userCheck->execute([$username]);
            if ((int)$userCheck->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'message' => 'That username is already taken.']);
                exit;
            }

            if ($role === 'Manager') {
                $mgrCheck = $pdo->query("SELECT COUNT(*) FROM system_user WHERE role = 'Manager'")->fetchColumn();
                if ((int)$mgrCheck > 0) {
                    http_response_code(409);
                    echo json_encode(['ok' => false, 'message' => 'A Manager account already exists. Only one Manager is allowed.']);
                    exit;
                }
            }

            $newUserId = nextPrefixedId($pdo, 'system_user', 'user_id', 'USR', 3);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO system_user
                (user_id, first_name, last_name, middle_name, username, password_hash, contact_number, role, specialization, account_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $newUserId,
                $firstName,
                $lastName,
                $middleName !== '' ? $middleName : null,
                $username,
                $hash,
                $contact,
                $role,
                $spec,
                $status
            ]);

            echo json_encode(['ok' => true, 'user_id' => $newUserId]);
            break;

        case 'staff_list':
        case 'staff':
            $stmt = $pdo->query("
                SELECT user_id,
                       TRIM(CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name)) AS full_name,
                       username, contact_number, role,
                       specialization,
                       COALESCE(account_status, 'Active') AS account_status
                FROM system_user
                ORDER BY user_id ASC
            ");
            echo json_encode($stmt->fetchAll());
            break;

        case 'toggle_staff_status':
            if (($_SESSION['staff']['role'] ?? '') !== 'Manager') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Only the Manager can update worker account status.']);
                exit;
            }
            $targetId = trim((string)($input['user_id'] ?? ''));
            $newStatus = ($input['account_status'] ?? '') === 'Inactive' ? 'Inactive' : 'Active';

            $stmt = $pdo->prepare('UPDATE system_user SET account_status = ? WHERE user_id = ?');
            $stmt->execute([$newStatus, $targetId]);
            echo json_encode(['ok' => true]);
            break;

        case 'manager_overview':
            $stalledStmt = $pdo->query("
                SELECT r.repair_request_ID AS request_id,
                       r.repair_request_ID AS request_code,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       CONCAT(g.brand, ' ', g.model) AS gadget_name,
                       COALESCE(TRIM(CONCAT(u.first_name, ' ', u.last_name)), 'Unassigned') AS technician_name,
                       DATEDIFF(NOW(), r.date_submitted) AS days_open,
                       DATE_FORMAT(r.date_submitted, '%b %e, %Y') AS submitted_date,
                       COALESCE((
                           SELECT sh.status
                           FROM status_history sh
                           WHERE sh.repair_request_ID = r.repair_request_ID
                           ORDER BY sh.date_updated DESC, sh.status_history_ID DESC
                           LIMIT 1
                       ), 'Received') AS raw_status
                FROM repair_request r
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                LEFT JOIN system_user u ON r.assigned_tech_ID = u.user_id
                HAVING raw_status NOT IN ('Picked up', 'Completed') AND days_open >= 3
                ORDER BY days_open DESC, r.repair_request_ID ASC
            ");

            $unclaimedStmt = $pdo->query("
                SELECT r.repair_request_ID AS request_id,
                       r.repair_request_ID AS request_code,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       c.contact_number,
                       CONCAT(g.brand, ' ', g.model) AS gadget_name,
                       COALESCE(i.invoice_ID, 'None') AS invoice_code,
                       COALESCE(i.status, 'Unpaid') AS invoice_status,
                       COALESCE(i.total, 0) AS total_amount,
                       COALESCE((
                           SELECT SUM(p.amount_paid)
                           FROM payment p
                           WHERE p.invoice_ID = i.invoice_ID AND p.payment_status = 'Completed'
                       ), 0) AS paid_amount
                FROM repair_request r
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                LEFT JOIN invoice i ON i.repair_request_ID = r.repair_request_ID
                WHERE (
                    SELECT sh.status
                    FROM status_history sh
                    WHERE sh.repair_request_ID = r.repair_request_ID
                    ORDER BY sh.date_updated DESC, sh.status_history_ID DESC
                    LIMIT 1
                ) = 'Ready for pickup'
                ORDER BY r.repair_request_ID DESC
            ");

            $techLoadStmt = $pdo->query("
                SELECT u.user_id,
                       TRIM(CONCAT(u.first_name, ' ', IFNULL(CONCAT(u.middle_name, ' '), ''), u.last_name)) AS full_name,
                       COALESCE(u.specialization, 'General') AS specialization,
                       COALESCE(u.account_status, 'Active') AS account_status,
                       COUNT(r.repair_request_ID) AS active_jobs
                FROM system_user u
                LEFT JOIN repair_request r
                  ON r.assigned_tech_ID = u.user_id
                 AND COALESCE((
                     SELECT sh.status
                     FROM status_history sh
                     WHERE sh.repair_request_ID = r.repair_request_ID
                     ORDER BY sh.date_updated DESC, sh.status_history_ID DESC
                     LIMIT 1
                 ), 'Received') NOT IN ('Picked up', 'Completed')
                WHERE u.role = 'Technician'
                GROUP BY u.user_id, u.first_name, u.middle_name, u.last_name, u.specialization, u.account_status
                ORDER BY u.first_name ASC
            ");

            $pendingPayCount = (int)$pdo->query("SELECT COUNT(*) FROM payment WHERE payment_status = 'Pending'")->fetchColumn();

            echo json_encode([
                'stalled_repairs'     => $stalledStmt->fetchAll(),
                'unclaimed_pickups'   => $unclaimedStmt->fetchAll(),
                'technician_workload' => $techLoadStmt->fetchAll(),
                'pending_payments'    => $pendingPayCount,
            ]);
            break;

        case 'reassign_technician':
            $role = $_SESSION['staff']['role'] ?? '';
            if (!in_array($role, ['Manager', 'Admin'], true)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Only a Manager or Admin can reassign technicians.']);
                exit;
            }

            $reqId = trim((string)($input['request_id'] ?? ''));
            $techId = trim((string)($input['assigned_tech_id'] ?? ''));

            if ($reqId === '' || $techId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Request ID and technician are required.']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE repair_request SET assigned_tech_ID = ? WHERE repair_request_ID = ?');
            $stmt->execute([$techId, $reqId]);

            echo json_encode(['ok' => true]);
            break;

        case 'repair_summary':
        case 'repair_requests':
            $staffRole = $_SESSION['staff']['role'] ?? null;
            $staffSpec = $_SESSION['staff']['specialization'] ?? null;

            $whereSql = '';
            $params = [];

            if ($staffRole === 'Technician' && !empty($staffSpec)) {
                $specFilters = [
                    'Apple & Tablet Devices' => " (LOWER(g.category) = 'tablet' OR LOWER(g.brand) = 'apple' OR LOWER(g.model) LIKE '%ipad%' OR LOWER(g.model) LIKE '%iphone%' OR LOWER(g.model) LIKE '%macbook%') ",
                    'Mobile & Micro-soldering' => " (LOWER(g.category) IN ('phone', 'smartphone') OR LOWER(r.reported_issue) LIKE '%port%' OR LOWER(r.reported_issue) LIKE '%battery%' OR LOWER(r.reported_issue) LIKE '%screen%' OR LOWER(r.reported_issue) LIKE '%water%') ",
                    'Laptop & Desktop Hardware' => " (LOWER(g.category) IN ('laptop', 'desktop') OR LOWER(r.reported_issue) LIKE '%ssd%' OR LOWER(r.reported_issue) LIKE '%keyboard%' OR LOWER(r.reported_issue) LIKE '%boot%') ",
                    'Console & Peripherals' => " (LOWER(g.category) IN ('game console', 'console', 'accessory', 'gaming mouse', 'wireless keyboard', 'gaming headset') OR LOWER(g.model) LIKE '%ps4%' OR LOWER(g.model) LIKE '%ps5%' OR LOWER(g.model) LIKE '%controller%' OR LOWER(r.reported_issue) LIKE '%stick%') ",
                    'Motherboard & IC Repair' => " (LOWER(r.reported_issue) LIKE '%power%' OR LOWER(r.reported_issue) LIKE '%motherboard%' OR LOWER(r.reported_issue) LIKE '%ic%' OR LOWER(r.reported_issue) LIKE '%short%') ",
                ];
                if (isset($specFilters[$staffSpec])) {
                    $whereSql = ' WHERE ' . $specFilters[$staffSpec];
                }
            }

            $sql = "
                SELECT r.repair_request_ID AS request_id,
                       r.repair_request_ID AS request_code,
                       r.reported_issue,
                       r.date_submitted,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       g.category,
                       CONCAT(g.brand, ' ', g.model) AS gadget_name,
                       COALESCE(TRIM(CONCAT(u.first_name, ' ', u.last_name)), 'Unassigned') AS technician_name,
                       DATE_FORMAT(r.date_submitted, '%b %e') AS date_label,
                       (
                           SELECT sh.status
                           FROM status_history sh
                           WHERE sh.repair_request_ID = r.repair_request_ID
                           ORDER BY sh.date_updated DESC, sh.status_history_ID DESC
                           LIMIT 1
                       ) AS raw_status
                FROM repair_request r
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                LEFT JOIN system_user u ON r.assigned_tech_ID = u.user_id
                $whereSql
                ORDER BY r.repair_request_ID DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $counts = [
                'Received'         => 0,
                'Diagnosing'       => 0,
                'Repairing'        => 0,
                'Ready for pickup' => 0,
                'Picked up'        => 0,
            ];

            foreach ($rows as &$row) {
                $norm = normalizeBoardStatus($row['raw_status'] ?? 'Received');
                $row['status'] = $norm;
                if (isset($counts[$norm])) {
                    $counts[$norm]++;
                }
            }
            unset($row);

            if ($action === 'repair_summary') {
                echo json_encode($counts);
                break;
            }

            echo json_encode($rows);
            break;

        case 'create_request_full':
            $pdo->beginTransaction();

            $existingCustomerId = trim((string)($input['existing_customer_id'] ?? ''));
            $firstName     = trim($input['first_name'] ?? '');
            $middleName    = trim($input['middle_name'] ?? '');
            $lastName      = trim($input['last_name'] ?? '');
            $contactNumber = trim($input['contact_number'] ?? '');
            $email         = trim($input['email'] ?? '');
            $barangay      = trim($input['barangay'] ?? '');
            $city          = trim($input['city'] ?? '');
            $province      = trim($input['province'] ?? '');
            $zipCode       = trim($input['zip_code'] ?? '');

            if ($existingCustomerId !== '') {
                $customerId = $existingCustomerId;
                $updCust = $pdo->prepare('
                    UPDATE customer
                    SET first_name = COALESCE(NULLIF(?, ""), first_name),
                        middle_name = COALESCE(NULLIF(?, ""), middle_name),
                        last_name = COALESCE(NULLIF(?, ""), last_name),
                        contact_number = COALESCE(NULLIF(?, ""), contact_number),
                        email = COALESCE(NULLIF(?, ""), email),
                        barangay = COALESCE(NULLIF(?, ""), barangay),
                        city = COALESCE(NULLIF(?, ""), city),
                        province = COALESCE(NULLIF(?, ""), province),
                        zip_code = COALESCE(NULLIF(?, ""), zip_code)
                    WHERE customer_ID = ?
                ');
                $updCust->execute([
                    $firstName, $middleName, $lastName, $contactNumber,
                    $email, $barangay, $city, $province, $zipCode, $customerId
                ]);
            } else {
                if ($firstName === '' || $lastName === '' || $contactNumber === '') {
                    throw new Exception('Please enter customer first name, last name, and contact number.');
                }
                $customerId = nextPrefixedId($pdo, 'customer', 'customer_ID', 'CUS', 3);
                $insCust = $pdo->prepare('
                    INSERT INTO customer
                    (customer_ID, first_name, last_name, middle_name, contact_number, email, date_registered, barangay, city, province, zip_code)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
                ');
                $insCust->execute([
                    $customerId,
                    $firstName,
                    $lastName,
                    $middleName !== '' ? $middleName : null,
                    $contactNumber,
                    $email !== '' ? $email : null,
                    $barangay !== '' ? $barangay : 'N/A',
                    $city !== '' ? $city : 'N/A',
                    $province !== '' ? $province : 'N/A',
                    $zipCode !== '' ? $zipCode : '0000'
                ]);
            }

            $category     = trim($input['category'] ?? 'Smartphone');
            $brand        = trim($input['brand'] ?? '');
            $model        = trim($input['model'] ?? '');
            $serialNumber = trim($input['serial_number'] ?? '');

            if ($brand === '' || $model === '' || $serialNumber === '') {
                throw new Exception('Please fill in Gadget Brand, Model, and Serial number or IMEI.');
            }

            $gadgetId = nextPrefixedId($pdo, 'gadget', 'gadget_ID', 'GAD', 3);
            $insGadget = $pdo->prepare('
                INSERT INTO gadget (gadget_ID, customer_ID, category, brand, model, serial_number, date_registered)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');
            $insGadget->execute([$gadgetId, $customerId, $category, $brand, $model, $serialNumber]);

            $reportedIssue       = trim($input['reported_issue'] ?? '');
            $initialCondition    = trim($input['initial_condition'] ?? '');
            $accessoriesIncluded = trim($input['accessories_included'] ?? '');
            $assignedTechId      = trim((string)($input['assigned_tech_id'] ?? ''));

            if ($reportedIssue === '') {
                throw new Exception('Please enter the Reported issue.');
            }

            if ($assignedTechId === '') {
                $assignedTechId = (string)$pdo->query("SELECT user_id FROM system_user WHERE role = 'Technician' AND account_status = 'Active' LIMIT 1")->fetchColumn();
            }

            $requestId = nextPrefixedId($pdo, 'repair_request', 'repair_request_ID', 'REQ', 3);
            $insReq = $pdo->prepare('
                INSERT INTO repair_request
                (repair_request_ID, gadget_ID, received_by, assigned_tech_ID, date_submitted, reported_issue, initial_condition, accessories_included)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
            ');
            $insReq->execute([
                $requestId,
                $gadgetId,
                $currentUserId,
                $assignedTechId,
                $reportedIssue,
                $initialCondition !== '' ? $initialCondition : 'Checked upon intake',
                $accessoriesIncluded !== '' ? $accessoriesIncluded : 'None'
            ]);

            $histId = nextPrefixedId($pdo, 'status_history', 'status_history_ID', 'SHS', 3);
            $insHist = $pdo->prepare('
                INSERT INTO status_history (status_history_ID, repair_request_ID, updated_by, status, date_updated)
                VALUES (?, ?, ?, "Received", NOW())
            ');
            $insHist->execute([$histId, $requestId, $currentUserId]);

            $invId = nextPrefixedId($pdo, 'invoice', 'invoice_ID', 'INV', 3);
            $insInv = $pdo->prepare('
                INSERT INTO invoice (invoice_ID, repair_request_ID, date, total, status)
                VALUES (?, ?, NOW(), 0.00, "Unpaid")
            ');
            $insInv->execute([$invId, $requestId]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'request_id' => $requestId, 'request_code' => $requestId]);
            break;

        case 'request_detail':
            $id = trim((string)($_GET['id'] ?? 'REQ-001'));
            $stmt = $pdo->prepare("
                SELECT r.repair_request_ID AS request_id,
                       r.repair_request_ID AS request_code,
                       r.reported_issue,
                       r.initial_condition,
                       r.accessories_included,
                       DATE_FORMAT(r.date_submitted, '%b %e, %Y') AS submitted_date,
                       c.customer_ID AS customer_id,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       c.contact_number,
                       COALESCE(c.email, 'None') AS email,
                       CONCAT_WS(', ', NULLIF(c.barangay, ''), NULLIF(c.city, ''), NULLIF(c.province, ''), NULLIF(c.zip_code, '')) AS address,
                       DATE_FORMAT(c.date_registered, '%b %e, %Y') AS customer_reg_date,
                       g.category, g.brand, g.model, COALESCE(g.serial_number, 'None') AS serial_number,
                       DATE_FORMAT(g.date_registered, '%b %e, %Y') AS gadget_reg_date,
                       COALESCE(TRIM(CONCAT(t.first_name, ' ', t.last_name)), 'Unassigned') AS technician_name,
                       COALESCE(TRIM(CONCAT(rc.first_name, ' ', rc.last_name)), 'Reception') AS received_by_name,
                       i.invoice_ID AS invoice_id,
                       i.invoice_ID AS invoice_code,
                       i.status AS invoice_status
                FROM repair_request r
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                LEFT JOIN system_user t ON r.assigned_tech_ID = t.user_id
                LEFT JOIN system_user rc ON r.received_by = rc.user_id
                LEFT JOIN invoice i ON i.repair_request_ID = r.repair_request_ID
                WHERE r.repair_request_ID = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $req = $stmt->fetch();
            if (!$req) {
                echo json_encode(['error' => 'Not found']);
                exit;
            }

            $histStmt = $pdo->prepare("
                SELECT sh.status,
                       COALESCE(TRIM(CONCAT(u.first_name, ' ', u.last_name)), sh.updated_by) AS updated_by,
                       DATE_FORMAT(sh.date_updated, '%b %e, %l:%i %p') AS formatted_date
                FROM status_history sh
                LEFT JOIN system_user u ON sh.updated_by = u.user_id
                WHERE sh.repair_request_ID = ?
                ORDER BY sh.date_updated DESC, sh.status_history_ID DESC
            ");
            $histStmt->execute([$id]);
            $history = $histStmt->fetchAll();

            $latestStatus = !empty($history) ? normalizeBoardStatus($history[0]['status']) : 'Received';
            $req['status'] = $latestStatus;

            $itemStmt = $pdo->prepare('
                SELECT rs.repair_service_ID AS item_id,
                       s.service_name AS service_label,
                       rs.quantity,
                       rs.actual_price,
                       (rs.quantity * rs.actual_price) AS subtotal
                FROM repair_service rs
                JOIN service s ON rs.service_ID = s.service_ID
                WHERE rs.repair_request_ID = ?
                ORDER BY rs.repair_service_ID ASC
            ');
            $itemStmt->execute([$id]);
            $items = $itemStmt->fetchAll();

            echo json_encode([
                'request' => $req,
                'history' => $history,
                'items'   => $items,
            ]);
            break;

        case 'update_request_status':
        case 'update_status':
            $reqId = trim((string)($input['request_id'] ?? ''));
            $newStatus = trim($input['status'] ?? 'Received');

            $pdo->beginTransaction();
            $histId = nextPrefixedId($pdo, 'status_history', 'status_history_ID', 'SHS', 3);
            $hist = $pdo->prepare('
                INSERT INTO status_history (status_history_ID, repair_request_ID, updated_by, status, date_updated)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $hist->execute([$histId, $reqId, $currentUserId, $newStatus]);
            $pdo->commit();

            echo json_encode(['ok' => true]);
            break;

        case 'add_request_service':
            $reqId = trim((string)($input['request_id'] ?? ''));
            $serviceId = trim((string)($input['service_id'] ?? ''));
            $qty = max(1, (int)($input['quantity'] ?? 1));
            $price = max(0.0, (float)($input['actual_price'] ?? 0));

            if ($reqId === '' || $serviceId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Request and service are required.']);
                exit;
            }

            $pdo->beginTransaction();

            $resId = nextPrefixedId($pdo, 'repair_service', 'repair_service_ID', 'RES', 3);
            $ins = $pdo->prepare('
                INSERT INTO repair_service (repair_service_ID, repair_request_ID, service_ID, quantity, actual_price)
                VALUES (?, ?, ?, ?, ?)
            ');
            $ins->execute([$resId, $reqId, $serviceId, $qty, $price]);

            $totStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity * actual_price), 0) FROM repair_service WHERE repair_request_ID = ?');
            $totStmt->execute([$reqId]);
            $newTotal = (float)$totStmt->fetchColumn();

            $invStmt = $pdo->prepare('SELECT invoice_ID FROM invoice WHERE repair_request_ID = ? LIMIT 1');
            $invStmt->execute([$reqId]);
            $invId = $invStmt->fetchColumn();

            if (!$invId) {
                $invId = nextPrefixedId($pdo, 'invoice', 'invoice_ID', 'INV', 3);
                $createInv = $pdo->prepare('INSERT INTO invoice (invoice_ID, repair_request_ID, date, total, status) VALUES (?, ?, NOW(), ?, "Unpaid")');
                $createInv->execute([$invId, $reqId, $newTotal]);
            } else {
                $updTot = $pdo->prepare('UPDATE invoice SET total = ? WHERE invoice_ID = ?');
                $updTot->execute([$newTotal, $invId]);
            }

            recalculateInvoiceStatus($pdo, (string)$invId);
            $pdo->commit();

            echo json_encode(['ok' => true]);
            break;

        case 'invoices_list':
        case 'invoices':
            $stmt = $pdo->query("
                SELECT i.invoice_ID AS invoice_id,
                       i.invoice_ID AS invoice_code,
                       i.repair_request_ID AS request_id,
                       i.repair_request_ID AS request_code,
                       i.status AS payment_status,
                       DATE_FORMAT(i.date, '%b %e, %Y') AS formatted_date,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       CONCAT(g.brand, ' ', g.model) AS gadget_name,
                       GREATEST(
                           i.total,
                           COALESCE((SELECT SUM(rs.quantity * rs.actual_price) FROM repair_service rs WHERE rs.repair_request_ID = i.repair_request_ID), 0)
                       ) AS total_amount,
                       COALESCE((SELECT SUM(p.amount_paid) FROM payment p WHERE p.invoice_ID = i.invoice_ID AND p.payment_status = 'Completed'), 0) AS paid_amount,
                       COALESCE((SELECT SUM(p.amount_paid) FROM payment p WHERE p.invoice_ID = i.invoice_ID AND p.payment_status = 'Pending'), 0) AS pending_amount
                FROM invoice i
                JOIN repair_request r ON i.repair_request_ID = r.repair_request_ID
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                ORDER BY i.invoice_ID DESC
            ");
            echo json_encode($stmt->fetchAll());
            break;

        case 'invoice_detail':
            $id = trim((string)($_GET['id'] ?? ''));
            $stmt = $pdo->prepare("
                SELECT i.invoice_ID AS invoice_id,
                       i.invoice_ID AS invoice_code,
                       i.repair_request_ID AS request_id,
                       i.repair_request_ID AS request_code,
                       i.status AS payment_status,
                       i.total AS stored_total,
                       DATE_FORMAT(i.date, '%b %e, %Y') AS formatted_date,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS customer_name,
                       c.contact_number,
                       CONCAT(g.brand, ' ', g.model) AS gadget_name,
                       COALESCE(g.serial_number, 'None') AS serial_number
                FROM invoice i
                JOIN repair_request r ON i.repair_request_ID = r.repair_request_ID
                JOIN gadget g ON r.gadget_ID = g.gadget_ID
                JOIN customer c ON g.customer_ID = c.customer_ID
                WHERE i.invoice_ID = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $inv = $stmt->fetch();
            if (!$inv) {
                echo json_encode(['error' => 'Not found']);
                exit;
            }

            $itemsStmt = $pdo->prepare('
                SELECT s.service_name AS service_label,
                       rs.quantity,
                       rs.actual_price,
                       (rs.quantity * rs.actual_price) AS subtotal
                FROM repair_service rs
                JOIN service s ON rs.service_ID = s.service_ID
                WHERE rs.repair_request_ID = ?
                ORDER BY rs.repair_service_ID ASC
            ');
            $itemsStmt->execute([$inv['request_id']]);
            $items = $itemsStmt->fetchAll();

            $payStmt = $pdo->prepare("
                SELECT p.payment_ID AS payment_id,
                       p.payment_ID AS payment_code,
                       p.reference_number,
                       DATE_FORMAT(p.payment_date, '%b %e, %Y') AS formatted_date,
                       p.amount_paid,
                       p.payment_status AS status,
                       COALESCE(TRIM(CONCAT(u.first_name, ' ', u.last_name)), p.user_ID) AS recorded_by
                FROM payment p
                LEFT JOIN system_user u ON p.user_ID = u.user_id
                WHERE p.invoice_ID = ?
                ORDER BY p.payment_ID DESC
            ");
            $payStmt->execute([$id]);
            $payments = $payStmt->fetchAll();

            $itemsSum = (float)array_sum(array_column($items, 'subtotal'));
            $total = max((float)($inv['stored_total'] ?? 0), $itemsSum);

            $completedPaid = 0.0;
            $pendingPaid = 0.0;
            foreach ($payments as $p) {
                if ($p['status'] === 'Completed') {
                    $completedPaid += (float)$p['amount_paid'];
                } elseif ($p['status'] === 'Pending') {
                    $pendingPaid += (float)$p['amount_paid'];
                }
            }

            echo json_encode([
                'invoice'      => $inv,
                'items'        => $items,
                'payments'     => $payments,
                'total'        => $total,
                'paid'         => $completedPaid,
                'pending_paid' => $pendingPaid,
                'balance'      => max(0.0, $total - $completedPaid),
            ]);
            break;

        case 'record_payment':
            $invoiceId  = trim((string)($input['invoice_id'] ?? ''));
            if ($invoiceId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invoice ID is required.']);
                exit;
            }

            $chkInv = $pdo->prepare('SELECT invoice_ID FROM invoice WHERE invoice_ID = ? LIMIT 1');
            $chkInv->execute([$invoiceId]);
            if (!$chkInv->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Invoice not found.']);
                exit;
            }

            $method     = trim($input['payment_method'] ?? '');
            $rawRef     = trim($input['reference_number'] ?? '');
            $ref        = ($method !== '' && $rawRef !== '')
                ? ($method . ' · ' . $rawRef)
                : ($rawRef !== '' ? $rawRef : ($method !== '' ? $method : null));
            $payDateRaw = trim($input['payment_date'] ?? date('Y-m-d'));
            $payDate    = strlen($payDateRaw) === 10 ? ($payDateRaw . ' ' . date('H:i:s')) : $payDateRaw;
            $amount     = max(0.0, (float)($input['amount_paid'] ?? $input['amount'] ?? 0));
            $payStatus  = trim($input['status'] ?? 'Completed');

            $allowedPayStatuses = ['Completed', 'Pending', 'Unpaid'];
            if (!in_array($payStatus, $allowedPayStatuses, true)) {
                $payStatus = 'Completed';
            }

            $pdo->beginTransaction();

            if ($payStatus === 'Unpaid') {
                $upd = $pdo->prepare("UPDATE invoice SET status = 'Unpaid' WHERE invoice_ID = ?");
                $upd->execute([$invoiceId]);
                $pdo->commit();
                echo json_encode(['ok' => true, 'payment_status' => 'Unpaid']);
                break;
            }

            if ($amount <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Amount paid must be greater than 0 for Completed or Pending payments.']);
                exit;
            }

            $payId = nextPrefixedId($pdo, 'payment', 'payment_ID', 'PAY', 3);
            $ins = $pdo->prepare('
                INSERT INTO payment (payment_ID, user_ID, invoice_ID, reference_number, payment_date, amount_paid, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([$payId, $currentUserId, $invoiceId, $ref, $payDate, $amount, $payStatus]);

            $newInvStatus = recalculateInvoiceStatus($pdo, $invoiceId);
            $pdo->commit();

            echo json_encode(['ok' => true, 'payment_status' => $newInvStatus]);
            break;

        case 'complete_payment':
            $paymentId = trim((string)($input['payment_id'] ?? ''));
            $invoiceId = trim((string)($input['invoice_id'] ?? ''));
            if ($paymentId === '' || $invoiceId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Payment ID and Invoice ID are required.']);
                exit;
            }

            $pdo->beginTransaction();
            $updPay = $pdo->prepare("UPDATE payment SET payment_status = 'Completed' WHERE payment_ID = ? AND invoice_ID = ?");
            $updPay->execute([$paymentId, $invoiceId]);

            $newInvStatus = recalculateInvoiceStatus($pdo, $invoiceId);
            $pdo->commit();

            echo json_encode(['ok' => true, 'payment_status' => $newInvStatus]);
            break;

        case 'update_invoice_status':
            $invoiceId = trim((string)($input['invoice_id'] ?? ''));
            $status = trim($input['payment_status'] ?? 'Unpaid');
            $allowed = ['Unpaid', 'Pending', 'Partially Paid', 'Paid'];
            if ($invoiceId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invoice ID is required.']);
                exit;
            }
            if (!in_array($status, $allowed, true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invalid invoice status.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE invoice SET status = ? WHERE invoice_ID = ?');
            $stmt->execute([$status, $invoiceId]);
            if ($stmt->rowCount() === 0) {
                $chk = $pdo->prepare('SELECT invoice_ID FROM invoice WHERE invoice_ID = ? LIMIT 1');
                $chk->execute([$invoiceId]);
                if (!$chk->fetchColumn()) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'message' => 'Invoice not found.']);
                    exit;
                }
            }
            echo json_encode(['ok' => true, 'payment_status' => $status]);
            break;

        case 'delete_customer':
            if (($_SESSION['staff']['role'] ?? '') !== 'Admin') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Only an Admin can delete a customer.']);
                exit;
            }

            $customerId = trim((string)($input['customer_id'] ?? ''));
            if ($customerId === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invalid customer ID.']);
                exit;
            }

            $pdo->beginTransaction();
            $cStmt = $pdo->prepare('SELECT * FROM customer WHERE customer_ID = ? LIMIT 1');
            $cStmt->execute([$customerId]);
            $cust = $cStmt->fetch();

            if (!$cust) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Customer not found.']);
                exit;
            }

            $archStmt = $pdo->prepare('
                INSERT INTO deleted_customer
                (original_customer_ID, first_name, last_name, middle_name, contact_number, email, barangay, city, province, zip_code, date_registered, deleted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $archStmt->execute([
                $cust['customer_ID'], $cust['first_name'], $cust['last_name'], $cust['middle_name'],
                $cust['contact_number'], $cust['email'], $cust['barangay'], $cust['city'],
                $cust['province'], $cust['zip_code'], $cust['date_registered'], $currentUserName
            ]);

            $gStmt = $pdo->prepare('SELECT gadget_ID FROM gadget WHERE customer_ID = ?');
            $gStmt->execute([$customerId]);
            $gadgetIds = $gStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($gadgetIds)) {
                $gPlaceholders = implode(',', array_fill(0, count($gadgetIds), '?'));

                $rStmt = $pdo->prepare("SELECT repair_request_ID FROM repair_request WHERE gadget_ID IN ($gPlaceholders)");
                $rStmt->execute($gadgetIds);
                $reqIds = $rStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($reqIds)) {
                    $rPlaceholders = implode(',', array_fill(0, count($reqIds), '?'));

                    $iStmt = $pdo->prepare("SELECT invoice_ID FROM invoice WHERE repair_request_ID IN ($rPlaceholders)");
                    $iStmt->execute($reqIds);
                    $invIds = $iStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($invIds)) {
                        $iPlaceholders = implode(',', array_fill(0, count($invIds), '?'));
                        $pdo->prepare("DELETE FROM payment WHERE invoice_ID IN ($iPlaceholders)")->execute($invIds);
                        $pdo->prepare("DELETE FROM invoice WHERE invoice_ID IN ($iPlaceholders)")->execute($invIds);
                    }

                    $pdo->prepare("DELETE FROM repair_service WHERE repair_request_ID IN ($rPlaceholders)")->execute($reqIds);
                    $pdo->prepare("DELETE FROM status_history WHERE repair_request_ID IN ($rPlaceholders)")->execute($reqIds);
                    $pdo->prepare("DELETE FROM repair_request WHERE repair_request_ID IN ($rPlaceholders)")->execute($reqIds);
                }

                $pdo->prepare("DELETE FROM gadget WHERE gadget_ID IN ($gPlaceholders)")->execute($gadgetIds);
            }

            $delStmt = $pdo->prepare('DELETE FROM customer WHERE customer_ID = ?');
            $delStmt->execute([$customerId]);
            $pdo->commit();

            echo json_encode(['ok' => true]);
            break;

        case 'customers_and_gadgets':
        case 'customers_gadgets':
            $q = '%' . trim($input['q'] ?? ($_GET['q'] ?? '')) . '%';
            $cStmt = $pdo->prepare("
                SELECT customer_ID AS customer_id,
                       TRIM(CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name)) AS full_name,
                       contact_number,
                       COALESCE(email, 'None') AS email,
                       CONCAT_WS(', ', NULLIF(barangay, ''), NULLIF(city, ''), NULLIF(province, ''), NULLIF(zip_code, '')) AS address,
                       DATE_FORMAT(date_registered, '%b %e, %Y') AS reg_date
                FROM customer
                WHERE first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ? OR COALESCE(email, '') LIKE ? OR city LIKE ? OR barangay LIKE ?
                ORDER BY customer_ID ASC
            ");
            $cStmt->execute([$q, $q, $q, $q, $q, $q]);

            $gStmt = $pdo->prepare("
                SELECT g.gadget_ID AS gadget_id,
                       g.category, g.brand, g.model,
                       COALESCE(g.serial_number, 'None') AS serial_number,
                       TRIM(CONCAT(c.first_name, ' ', IFNULL(CONCAT(c.middle_name, ' '), ''), c.last_name)) AS owner_name,
                       DATE_FORMAT(g.date_registered, '%b %e, %Y') AS reg_date
                FROM gadget g
                JOIN customer c ON g.customer_ID = c.customer_ID
                WHERE g.brand LIKE ? OR g.model LIKE ? OR COALESCE(g.serial_number, '') LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?
                ORDER BY g.gadget_ID ASC
            ");
            $gStmt->execute([$q, $q, $q, $q, $q]);

            $deletedCustomers = [];
            if (($_SESSION['staff']['role'] ?? '') === 'Admin') {
                try {
                    $dStmt = $pdo->prepare("
                        SELECT deleted_id, original_customer_ID AS original_customer_id,
                               TRIM(CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name)) AS full_name,
                               contact_number,
                               COALESCE(email, 'None') AS email,
                               CONCAT_WS(', ', NULLIF(barangay, ''), NULLIF(city, ''), NULLIF(province, ''), NULLIF(zip_code, '')) AS address,
                               deleted_by,
                               DATE_FORMAT(deleted_at, '%b %e, %Y %l:%i %p') AS deleted_date
                        FROM deleted_customer
                        WHERE first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ?
                        ORDER BY deleted_id DESC
                    ");
                    $dStmt->execute([$q, $q, $q]);
                    $deletedCustomers = $dStmt->fetchAll();
                } catch (Throwable $ignore) {
                    $deletedCustomers = [];
                }
            }

            echo json_encode([
                'customers'         => $cStmt->fetchAll(),
                'gadgets'           => $gStmt->fetchAll(),
                'deleted_customers' => $deletedCustomers,
            ]);
            break;

        case 'services_list':
        case 'services':
            $stmt = $pdo->query('SELECT service_ID AS service_id, service_name, description, price AS default_price FROM service ORDER BY service_ID ASC');
            echo json_encode($stmt->fetchAll());
            break;

        case 'save_service':
            $id = trim((string)($input['service_id'] ?? ''));
            $name = trim($input['service_name'] ?? '');
            $desc = trim($input['description'] ?? '');
            $price = max(0.0, (float)($input['default_price'] ?? 0));

            if ($name === '' || $desc === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Service name and description are required.']);
                exit;
            }

            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE service SET service_name = ?, description = ?, price = ? WHERE service_ID = ?');
                $stmt->execute([$name, $desc, $price, $id]);
            } else {
                $newSrvId = nextPrefixedId($pdo, 'service', 'service_ID', 'SER', 3);
                $stmt = $pdo->prepare('INSERT INTO service (service_ID, service_name, description, price) VALUES (?, ?, ?, ?)');
                $stmt->execute([$newSrvId, $name, $desc, $price]);
            }
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}