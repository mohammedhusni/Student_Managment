
<?php
session_start();

// Database connection
$host = "localhost";
$dbname = "student_management";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    exit("Database connection failed. Check XAMPP and database settings.");
}

// Escape output before displaying it in HTML
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$message = "";
$error = "";
$editStudent = null;

// Create a session token for form protection
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Invalid form token. Refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";

    try {
        if ($action === "create" || $action === "update") {
            $studentId = trim($_POST["student_id"] ?? "");
            $fullName = trim($_POST["full_name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $course = trim($_POST["course"] ?? "");

            if (
                $studentId === "" ||
                $fullName === "" ||
                $email === "" ||
                $course === ""
            ) {
                throw new RuntimeException(
                    "Please fill in every field."
                );
            }

            if (
                strlen($studentId) > 30 ||
                strlen($fullName) > 100 ||
                strlen($email) > 120 ||
                strlen($course) > 100
            ) {
                throw new RuntimeException(
                    "One or more fields exceed the allowed length."
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    "Please enter a valid email address."
                );
            }

            if ($action === "create") {
                $sql = "INSERT INTO students
                        (student_id, full_name, email, course)
                        VALUES (?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $studentId, $fullName, $email, $course
                ]);

                $_SESSION["flash"] = "Student added successfully!";
            } else {
                $id = filter_var(
                    $_POST["id"] ?? "",
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 1]]
                );

                if (!$id) {
                    throw new RuntimeException(
                        "Invalid student record."
                    );
                }

                $sql = "UPDATE students
                        SET student_id = ?,
                            full_name = ?,
                            email = ?,
                            course = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $studentId, $fullName, $email, $course, $id
                ]);

                $_SESSION["flash"] = "Student updated successfully!";
            }

        } elseif ($action === "delete") {
            $id = filter_var(
                $_POST["id"] ?? "",
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            if (!$id) {
                throw new RuntimeException(
                    "Invalid student record."
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM students WHERE id = ?"
            );
            $stmt->execute([$id]);

            $_SESSION["flash"] = $stmt->rowCount()
                ? "Student deleted successfully!"
                : "Student record was not found.";

        } else {
            throw new RuntimeException("Invalid action.");
        }

        // Prevent duplicate submissions when refreshing
        header("Location: index.php");
        exit;

    } catch (PDOException $ex) {
        if ($ex->getCode() === "23000") {
            $error = "That student registration number already exists.";
        } else {
            $error = "A database error occurred. Please try again.";
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

// Display one-time success message
if (isset($_SESSION["flash"])) {
    $message = $_SESSION["flash"];
    unset($_SESSION["flash"]);
}

// Read a record for editing
if (isset($_GET["edit"])) {
    $editId = filter_var(
        $_GET["edit"],
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($editId) {
        $stmt = $pdo->prepare(
            "SELECT * FROM students WHERE id = ?"
        );
        $stmt->execute([$editId]);
        $editStudent = $stmt->fetch();

        if (!$editStudent) {
            $error = "Student record not found.";
        }
    }
}

// Read all student records
$stmt = $pdo->query(
    "SELECT * FROM students ORDER BY id DESC"
);
$students = $stmt->fetchAll();
$totalStudents = count($students);

$isEditing = is_array($editStudent);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Management System</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="topbar">
        <div class="brand">
            <span class="brand-icon">S</span>
            <span>Student<span class="brand-light">Hub</span></span>
        </div>

        <span class="topbar-label">ADMIN DASHBOARD</span>
    </header>

    <main class="container">
        <section class="hero">
            <p class="eyebrow">STUDENT RECORDS MANAGEMENT</p>

            <h1>
                Manage your students
                <span class="animated-text" id="animatedText">
                    efficiently.
                </span>
            </h1>

            <p class="subtitle">
                Add, view, edit, and manage student information
                in one simple dashboard.
            </p>
        </section>

        <section class="stats">
            <div class="stat-card">
                <span class="stat-label">TOTAL STUDENTS</span>
                <strong><?php echo $totalStudents; ?></strong>
                <span class="stat-note">Registered records</span>
            </div>

            <div class="stat-card">
                <span class="stat-label">DATABASE</span>
                <strong class="online">Connected</strong>
                <span class="stat-note">MySQL database</span>
            </div>
        </section>

        <?php if ($message !== ""): ?>
            <div class="alert success">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>
                        <?php echo $isEditing
                            ? "Edit Student"
                            : "Add New Student"; ?>
                    </h2>

                    <p>
                        Enter the student information below.
                    </p>
                </div>

                <?php if ($isEditing): ?>
                    <a class="button button-secondary"
                       href="index.php">
                        Cancel Edit
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="index.php" id="studentForm">
                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo e($_SESSION["csrf_token"]); ?>">

                <input type="hidden"
                       name="action"
                       value="<?php echo $isEditing ? "update" : "create"; ?>">

                <?php if ($isEditing): ?>
                    <input type="hidden"
                           name="id"
                           value="<?php echo e($editStudent["id"]); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">
                            Student ID
                        </label>

                        <input type="text"
                               id="student_id"
                               name="student_id"
                               maxlength="30"
                               placeholder="e.g. STU001"
                               value="<?php echo e(
                                   $editStudent["student_id"] ?? ""
                               ); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="full_name">
                            Full Name
                        </label>

                        <input type="text"
                               id="full_name"
                               name="full_name"
                               maxlength="100"
                               placeholder="Enter full name"
                               value="<?php echo e(
                                   $editStudent["full_name"] ?? ""
                               ); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            Email Address
                        </label>

                        <input type="email"
                               id="email"
                               name="email"
                               maxlength="120"
                               placeholder="student@example.com"
                               value="<?php echo e(
                                   $editStudent["email"] ?? ""
                               ); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="course">
                            Course
                        </label>

                        <input type="text"
                               id="course"
                               name="course"
                               maxlength="100"
                               placeholder="e.g. Facilities Management"
                               value="<?php echo e(
                                   $editStudent["course"] ?? ""
                               ); ?>"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php echo $isEditing
                            ? "Save Changes"
                            : "Add Student"; ?>
                    </button>

                    <?php if (!$isEditing): ?>
                        <button type="reset"
                                class="button button-secondary">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel records-panel">
            <div class="panel-heading">
                <div>
                    <h2>Student Records</h2>
                    <p>All students saved in your database.</p>
                </div>

                <span class="record-count">
                    <?php echo $totalStudents; ?> records
                </span>
            </div>

            <div class="search-wrap">
                <input type="search"
                       id="searchInput"
                       placeholder="Search by ID, name, email, or course..."
                       aria-label="Search student records">
            </div>

            <div class="table-wrap">
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo e($student["id"]); ?></td>
                            <td><?php echo e($student["student_id"]); ?></td>
                            <td class="student-name">
                                <?php echo e($student["full_name"]); ?>
                            </td>
                            <td><?php echo e($student["email"]); ?></td>
                            <td><?php echo e($student["course"]); ?></td>
                            <td class="actions">
                                <a class="button button-edit"
                                   href="index.php?edit=<?php
                                       echo e($student["id"]);
                                   ?>">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="index.php"
                                      class="delete-form">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?php echo e(
                                               $_SESSION["csrf_token"]
                                           ); ?>">

                                    <input type="hidden"
                                           name="action"
                                           value="delete">

                                    <input type="hidden"
                                           name="id"
                                           value="<?php echo e(
                                               $student["id"]
                                           ); ?>">

                                    <button type="submit"
                                            class="button button-delete">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($students)): ?>
                        <tr id="emptyRow">
                            <td colspan="6" class="empty-state">
                                No students found. Add your first student above.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <p id="noSearchResults" class="empty-state" hidden>
                    No matching students found.
                </p>
            </div>
        </section>

        <footer>
            StudentHub · Student Management System
            <br>
            Built with HTML, CSS, JavaScript, PHP and MySQL
        </footer>
    </main>

    <script src="script.js"></script>
</body>
</html>
