<?php



require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';



lb_secure_session_start();



/*
|--------------------------------------------------------------------------
| Administrator authentication
|--------------------------------------------------------------------------
*/



requireLogin();
requireAdmin();



/*
|--------------------------------------------------------------------------
| Validate entity type
|--------------------------------------------------------------------------
*/



$allowedTypes = [
    'user',
    'client',
    'lawyer',
    'specialty'
];



$type = $_GET['type'] ?? '';



if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid entity type.');
}



/*
|--------------------------------------------------------------------------
| Validate entity ID
|--------------------------------------------------------------------------
*/



$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);



if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    http_response_code(400);
    exit('Invalid record ID.');
}



$id = (int)$id;



$error = '';



$user        = null;
$specialties = [];



/*
|--------------------------------------------------------------------------
| Load record
|--------------------------------------------------------------------------
*/



if ($type === 'specialty') {
    $stmt = dbSelect(
        "SELECT
            id,
            name,
            active
         FROM specialties
         WHERE id = :id
         LIMIT 1",
        [
            ':id' => $id
        ]
    );



    $user = $stmt->fetch();
} else {
    /*
    |--------------------------------------------------------------------------
    | Protect administrator accounts
    |--------------------------------------------------------------------------
    |
    | Administrator accounts are excluded directly in SQL. Even if someone
    | manually enters an administrator ID in the URL, the record will not
    | be returned.
    |
    */



    if ($type === 'lawyer') {
        $stmt = dbSelect(
            "SELECT
                id,
                name,
                email,
                role,
                active
             FROM users
             WHERE id = :id
               AND role = 'lawyer'
             LIMIT 1",
            [
                ':id' => $id
            ]
        );
    } elseif ($type === 'client') {
        $stmt = dbSelect(
            "SELECT
                id,
                name,
                email,
                role,
                active
             FROM users
             WHERE id = :id
               AND role = 'client'
             LIMIT 1",
            [
                ':id' => $id
            ]
        );
    } else {
        /*
         * Generic user editing is allowed only for clients and lawyers.
         * Administrator accounts remain excluded.
         */
        $stmt = dbSelect(
            "SELECT
                id,
                name,
                email,
                role,
                active
             FROM users
             WHERE id = :id
               AND role IN ('client', 'lawyer')
             LIMIT 1",
            [
                ':id' => $id
            ]
        );
    }



    $user = $stmt->fetch();



    /*
    |--------------------------------------------------------------------------
    | Load lawyer-specific information
    |--------------------------------------------------------------------------
    */



    if ($user && $type === 'lawyer') {
        $lawyer = dbSelect(
            "SELECT specialty_id
             FROM lawyers
             WHERE user_id = :user_id
             LIMIT 1",
            [
                ':user_id' => $id
            ]
        )->fetch();



        if (!$lawyer) {
            http_response_code(404);
            exit('Lawyer profile not found.');
        }



        $user['specialty_id'] =
            (int)$lawyer['specialty_id'];



        $specialties = dbSelect(
            "SELECT id, name
             FROM specialties
             WHERE active = 1
             ORDER BY name ASC"
        )->fetchAll();
    }
}



/*
|--------------------------------------------------------------------------
| Record existence and administrator protection
|--------------------------------------------------------------------------
*/



if (!$user) {
    http_response_code(404);
    exit('Record not found or protected.');
}



/*
 * This is a second defensive check. The database queries above already
 * exclude administrators, but this prevents accidental exposure if those
 * queries are changed later.
 */
if (
    $type !== 'specialty' &&
    ($user['role'] ?? '') === 'admin'
) {
    http_response_code(403);
    exit('Administrator accounts cannot be modified.');
}



/*
|--------------------------------------------------------------------------
| Save changes
|--------------------------------------------------------------------------
*/



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
    |--------------------------------------------------------------------------
    | CSRF validation
    |--------------------------------------------------------------------------
    */



    if (!csrfValidate($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request.');
    }



    /*
    |--------------------------------------------------------------------------
    | Specialty update
    |--------------------------------------------------------------------------
    */



    if ($type === 'specialty') {
        $name = trim($_POST['name'] ?? '');



        if ($name === '') {
            $error = 'Specialty name is required.';
        } elseif (mb_strlen($name) > 100) {
            $error =
                'Specialty name cannot exceed 100 characters.';
        } else {
            $duplicate = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE LOWER(name) = LOWER(:name)
                   AND id <> :id
                 LIMIT 1",
                [
                    ':name' => $name,
                    ':id'   => $id
                ]
            )->fetch();



            if ($duplicate) {
                $error =
                    'A specialty with that name already exists.';
            }
        }



        if ($error === '') {
            try {
                $updated = dbExecute(
                    "UPDATE specialties
                     SET name = :name
                     WHERE id = :id",
                    [
                        ':name' => $name,
                        ':id'   => $id
                    ]
                );



                if (!$updated) {
                    throw new RuntimeException(
                        'Specialty update failed.'
                    );
                }



                header(
                    'Location: admin_specialties.php?updated=1'
                );
                exit;
            } catch (Throwable $exception) {
                lb_log_error(
                    'Specialty update failed for specialty ID '
                        . $id
                        . ': '
                        . $exception->getMessage()
                );



                $error =
                    'The specialty could not be updated.';
            }
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Client, lawyer, or general user update
    |--------------------------------------------------------------------------
    */ else {
        /*
         * Role is intentionally not read from POST.
         *
         * A forged value such as role=admin has no effect because this
         * handler never accepts or updates the role column.
         */
        $name = trim($_POST['name'] ?? '');



        $email =
            strtolower(trim($_POST['email'] ?? ''));



        /*
        |--------------------------------------------------------------------------
        | Confirm target remains a non-administrator account
        |--------------------------------------------------------------------------
        */



        $targetStmt = dbSelect(
            "SELECT id, role
             FROM users
             WHERE id = :id
             LIMIT 1",
            [
                ':id' => $id
            ]
        );



        $targetUser = $targetStmt->fetch();



        if (!$targetUser) {
            http_response_code(404);
            exit('User not found.');
        }



        if (($targetUser['role'] ?? '') === 'admin') {
            http_response_code(403);
            exit('Administrator accounts cannot be modified.');
        }



        if (
            !in_array(
                $targetUser['role'] ?? '',
                ['client', 'lawyer'],
                true
            )
        ) {
            http_response_code(403);
            exit('Protected user role.');
        }



        /*
        |--------------------------------------------------------------------------
        | Confirm URL type matches the stored account role
        |--------------------------------------------------------------------------
        */



        if (
            $type === 'lawyer' &&
            $targetUser['role'] !== 'lawyer'
        ) {
            http_response_code(403);
            exit('The selected account is not a lawyer.');
        }



        if (
            $type === 'client' &&
            $targetUser['role'] !== 'client'
        ) {
            http_response_code(403);
            exit('The selected account is not a client.');
        }



        /*
        |--------------------------------------------------------------------------
        | Name and email validation
        |--------------------------------------------------------------------------
        */



        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        } elseif (mb_strlen($name) < 2) {
            $error =
                'Name must contain at least 2 characters.';
        } elseif (mb_strlen($name) > 100) {
            $error =
                'Name cannot exceed 100 characters.';
        } elseif (
            !preg_match(
                "/^[\p{L}\s'.\-]+$/u",
                $name
            )
        ) {
            $error = 'Please enter a valid name.';
        } elseif (strlen($email) > 255) {
            $error =
                'Email address cannot exceed 255 characters.';
        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $error = 'Invalid email address.';
        }



        /*
        |--------------------------------------------------------------------------
        | Duplicate email validation
        |--------------------------------------------------------------------------
        */



        if ($error === '') {
            $duplicateEmail = dbSelect(
                "SELECT id
                 FROM users
                 WHERE email = :email
                   AND id <> :id
                 LIMIT 1",
                [
                    ':email' => $email,
                    ':id'    => $id
                ]
            )->fetch();



            if ($duplicateEmail) {
                $error =
                    'That email address is already registered.';
            }
        }



        /*
        |--------------------------------------------------------------------------
        | Lawyer specialty validation
        |--------------------------------------------------------------------------
        */



        $specialtyId = 0;



        if ($error === '' && $type === 'lawyer') {
            $specialtyId =
                (int)($_POST['specialty_id'] ?? 0);



            if ($specialtyId <= 0) {
                $error =
                    'Please select a specialty.';
            } else {
                $specialtyStmt = dbSelect(
                    "SELECT id
                     FROM specialties
                     WHERE id = :specialty_id
                       AND active = 1
                     LIMIT 1",
                    [
                        ':specialty_id' => $specialtyId
                    ]
                );



                if (!$specialtyStmt->fetch()) {
                    $error =
                        'Please select a valid active specialty.';
                }
            }
        }



        /*
        |--------------------------------------------------------------------------
        | Update database
        |--------------------------------------------------------------------------
        */



        if ($error === '') {
            try {
                global $pdo;



                $pdo->beginTransaction();



                /*
                 * The role restriction is repeated in the UPDATE query.
                 * Administrator accounts cannot be updated even if an earlier
                 * validation check is accidentally removed.
                 */
                $updated = dbExecute(
                    "UPDATE users
                     SET name = :name,
                         email = :email
                     WHERE id = :id
                       AND role IN ('client', 'lawyer')",
                    [
                        ':name'  => $name,
                        ':email' => $email,
                        ':id'    => $id
                    ]
                );



                if (!$updated) {
                    throw new RuntimeException(
                        'User update failed.'
                    );
                }



                if ($type === 'lawyer') {
                    $lawyerUpdated = dbExecute(
                        "UPDATE lawyers
                         SET specialty_id = :specialty_id
                         WHERE user_id = :user_id",
                        [
                            ':specialty_id' => $specialtyId,
                            ':user_id'      => $id
                        ]
                    );



                    if (!$lawyerUpdated) {
                        throw new RuntimeException(
                            'Lawyer profile update failed.'
                        );
                    }
                }



                $pdo->commit();



                $redirectPage = match ($type) {
                    'lawyer' => 'admin_lawyers.php',
                    'client' => 'admin_clients.php',
                    default  => 'admin_users.php'
                };



                header(
                    'Location: '
                        . $redirectPage
                        . '?updated=1'
                );
                exit;
            } catch (Throwable $exception) {
                if (
                    isset($pdo) &&
                    $pdo->inTransaction()
                ) {
                    $pdo->rollBack();
                }



                lb_log_error(
                    'Administrative user update failed for user ID '
                        . $id
                        . ': '
                        . $exception->getMessage()
                );



                $error =
                    'The account could not be updated. Please try again.';
            }
        }
    }
}



/*
|--------------------------------------------------------------------------
| Values displayed in the form
|--------------------------------------------------------------------------
*/



$displayName =
    $_POST['name']
    ?? ($user['name'] ?? '');



$displayEmail =
    $_POST['email']
    ?? ($user['email'] ?? '');



$selectedSpecialtyId =
    isset($_POST['specialty_id'])
    ? (int)$_POST['specialty_id']
    : (int)($user['specialty_id'] ?? 0);



/*
|--------------------------------------------------------------------------
| Load page header after security checks and POST processing
|--------------------------------------------------------------------------
*/



require_once __DIR__ . '/includes/header.php';



?>



<h2 class="lb-title center">
    Edit <?= e(ucfirst($type)) ?>
</h2>



<div class="lb-form-container">



    <?php if ($error !== ''): ?>



        <div class="lb-error">
            <?= e($error) ?>
        </div>



    <?php endif; ?>



    <form
        method="post"
        action="admin_entity_edit.php?type=<?= e($type) ?>&amp;id=<?= $id ?>"
        class="lb-form">



        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">



        <?php if ($type === 'specialty'): ?>



            <label for="name">
                Specialty Name
            </label>



            <input
                type="text"
                id="name"
                name="name"
                minlength="2"
                maxlength="100"
                value="<?= e($displayName) ?>"
                required>



        <?php else: ?>



            <label for="name">
                Name
            </label>



            <input
                type="text"
                id="name"
                name="name"
                minlength="2"
                maxlength="100"
                value="<?= e($displayName) ?>"
                required>



            <label for="email">
                Email
            </label>



            <input
                type="email"
                id="email"
                name="email"
                maxlength="255"
                value="<?= e($displayEmail) ?>"
                required>



            <label>
                Role
            </label>



            <input
                type="text"
                value="<?= e(ucfirst($user['role'] ?? '')) ?>"
                readonly>



            <small>
                Account roles cannot be changed from this page.
            </small>



            <?php if ($type === 'lawyer'): ?>



                <label for="specialty_id">
                    Specialty
                </label>



                <select
                    id="specialty_id"
                    name="specialty_id"
                    required>



                    <option value="">
                        Select Specialty
                    </option>



                    <?php foreach ($specialties as $specialty): ?>



                        <?php
                        $currentSpecialtyId =
                            (int)$specialty['id'];
                        ?>



                        <option
                            value="<?= $currentSpecialtyId ?>"
                            <?= (
                                $selectedSpecialtyId ===
                                $currentSpecialtyId
                            ) ? 'selected' : '' ?>>



                            <?= e($specialty['name']) ?>



                        </option>



                    <?php endforeach; ?>



                </select>



            <?php endif; ?>



        <?php endif; ?>



        <div style="margin-top: 20px;">



            <button
                type="submit"
                class="lb-btn">
                Save Changes
            </button>



            <a
                class="lb-btn secondary"
                href="<?=
                        match ($type) {
                            'lawyer' =>
                            'admin_lawyers.php',



                            'client' =>
                            'admin_clients.php',



                            'specialty' =>
                            'admin_specialties.php',



                            default =>
                            'admin_users.php'
                        }
                        ?>">
                Cancel
            </a>



        </div>



    </form>



</div>



</main>
</body>



</html>