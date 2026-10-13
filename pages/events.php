<?php
session_start();

require_once "../php/auth.php";
require_once "../php/db.php";

// Simple admin protection
if ($_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit();
}
$message = "";
$error = "";

/* =========================
   DELETE EVENT
========================= */

if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    // Get poster name before deleting
    $stmt = $conn->prepare("SELECT poster FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $event = $result->fetch_assoc();

    if ($event) {

        // Delete poster file
        if (!empty($event['poster'])) {

            $posterPath = "../images/events/" . $event['poster'];

            if (file_exists($posterPath)) {
                unlink($posterPath);
            }
        }

        // Delete database record
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Event deleted successfully!";
        } else {
            $error = "Unable to delete event.";
        }
    }
}

/* =========================
   ADD EVENT
========================= */

if (isset($_POST['add_event'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);

    $posterName = "";

    /* Poster validation */

    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {

        $fileName = $_FILES['poster']['name'];
        $fileSize = $_FILES['poster']['size'];
        $tmpName = $_FILES['poster']['tmp_name'];

        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];

        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        // Maximum 2 MB
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($extension, $allowedTypes)) {

            $error = "Only JPG, JPEG, PNG and WEBP files are allowed.";

        } elseif ($fileSize > $maxSize) {

            $error = "Poster size must be less than 2 MB.";

        } else {

            // Generate unique file name
            $posterName = time() . "_" . uniqid() . "." . $extension;

            $uploadPath = "../images/events/" . $posterName;

            if (!move_uploaded_file($tmpName, $uploadPath)) {
                $error = "Failed to upload poster.";
            }
        }
    }

    if (empty($error)) {

        $stmt = $conn->prepare(
            "INSERT INTO events
            (title, description, event_date, event_time, venue, poster)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $title,
            $description,
            $event_date,
            $event_time,
            $venue,
            $posterName
        );

        if ($stmt->execute()) {
            $message = "Event added successfully!";
        } else {
            $error = "Failed to add event.";
        }
    }
}

/* =========================
   UPDATE EVENT
========================= */

if (isset($_POST['update_event'])) {

    $id = intval($_POST['id']);

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);

    // Get old poster
    $stmt = $conn->prepare(
        "SELECT poster FROM events WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $oldEvent = $result->fetch_assoc();

    $posterName = $oldEvent['poster'];

    /* New poster uploaded? */

    if (isset($_FILES['poster']) &&
        $_FILES['poster']['error'] == 0) {

        $fileName = $_FILES['poster']['name'];
        $fileSize = $_FILES['poster']['size'];
        $tmpName = $_FILES['poster']['tmp_name'];

        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];

        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        $maxSize = 2 * 1024 * 1024;

        if (!in_array($extension, $allowedTypes)) {

            $error = "Only JPG, JPEG, PNG and WEBP files are allowed.";

        } elseif ($fileSize > $maxSize) {

            $error = "Poster size must be less than 2 MB.";

        } else {

            // Delete old poster
            if (!empty($oldEvent['poster'])) {

                $oldPath =
                    "../images/events/" .
                    $oldEvent['poster'];

                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $posterName =
                time() . "_" .
                uniqid() .
                "." .
                $extension;

            $uploadPath =
                "../images/events/" .
                $posterName;

            if (!move_uploaded_file($tmpName, $uploadPath)) {
                $error = "Failed to upload new poster.";
            }
        }
    }

    if (empty($error)) {

        $stmt = $conn->prepare(
            "UPDATE events
             SET title = ?,
                 description = ?,
                 event_date = ?,
                 event_time = ?,
                 venue = ?,
                 poster = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssssssi",
            $title,
            $description,
            $event_date,
            $event_time,
            $venue,
            $posterName,
            $id
        );

        if ($stmt->execute()) {
            $message = "Event updated successfully!";
        } else {
            $error = "Failed to update event.";
        }
    }
}

/* =========================
   GET EVENT FOR EDIT
========================= */

$editEvent = null;

if (isset($_GET['edit'])) {

    $id = intval($_GET['edit']);

    $stmt = $conn->prepare(
        "SELECT * FROM events WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    $editEvent = $result->fetch_assoc();
}

/* =========================
   FETCH ALL EVENTS
========================= */

$result = $conn->query(
    "SELECT * FROM events ORDER BY event_date DESC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Event Management</title>

    <link rel="stylesheet"
          href="../css/admin.css">

    <style>

        .event-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        .event-form {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .event-form h2 {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            padding: 11px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #ff4f9a;
            color: white;
        }

        .btn-edit {
            background: #4f7cff;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .event-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .event-table th,
        .event-table td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .event-table th {
            background: #ff4f9a;
            color: white;
        }

        .poster {
            width: 90px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .message {
            padding: 12px;
            background: #d4edda;
            color: #155724;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .error {
            padding: 12px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 8px;
            margin-bottom: 15px;
        }

    </style>

</head>

<body>

<div class="event-container">

    <h1>🎉 Event Management</h1>

    <?php if (!empty($message)): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =========================
         ADD / EDIT FORM
    ========================== -->

    <div class="event-form">

        <?php if ($editEvent): ?>

            <h2>✏️ Update Event</h2>

            <form method="POST"
                  enctype="multipart/form-data">

                <input type="hidden"
                       name="id"
                       value="<?= $editEvent['id'] ?>">

        <?php else: ?>

            <h2>➕ Add New Event</h2>

            <form method="POST"
                  enctype="multipart/form-data">

        <?php endif; ?>


            <div class="form-group">

                <label>Event Title</label>

                <input type="text"
                       name="title"
                       required
                       value="<?= $editEvent
                           ? htmlspecialchars($editEvent['title'])
                           : '' ?>">

            </div>


            <div class="form-group">

                <label>Description</label>

                <textarea name="description"
                          required><?= $editEvent
                          ? htmlspecialchars($editEvent['description'])
                          : '' ?></textarea>

            </div>


            <div class="form-group">

                <label>Event Date</label>

                <input type="date"
                       name="event_date"
                       required
                       value="<?= $editEvent
                           ? $editEvent['event_date']
                           : '' ?>">

            </div>


            <div class="form-group">

                <label>Event Time</label>

                <input type="time"
                       name="event_time"
                       required
                       value="<?= $editEvent
                           ? $editEvent['event_time']
                           : '' ?>">

            </div>


            <div class="form-group">

                <label>Venue</label>

                <input type="text"
                       name="venue"
                       required
                       value="<?= $editEvent
                           ? htmlspecialchars($editEvent['venue'])
                           : '' ?>">

            </div>


            <div class="form-group">

                <label>Event Poster</label>

                <input type="file"
                       name="poster"
                       accept=".jpg,.jpeg,.png,.webp">

                <small>
                    Allowed: JPG, JPEG, PNG, WEBP | Maximum: 2 MB
                </small>

            </div>


            <?php if ($editEvent): ?>

                <button type="submit"
                        name="update_event"
                        class="btn btn-primary">
                    Update Event
                </button>

                <a href="events.php"
                   class="btn btn-delete">
                    Cancel
                </a>

            <?php else: ?>

                <button type="submit"
                        name="add_event"
                        class="btn btn-primary">
                    Add Event
                </button>

            <?php endif; ?>

        </form>

    </div>


    <!-- =========================
         EVENT LIST
    ========================== -->

    <h2>📋 All Events</h2>

    <table class="event-table">

        <thead>

            <tr>

                <th>ID</th>
                <th>Poster</th>
                <th>Title</th>
                <th>Date</th>
                <th>Time</th>
                <th>Venue</th>
                <th>Actions</th>

            </tr>

        </thead>

        <tbody>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= $row['id'] ?>
                    </td>


                    <td>

                        <?php if (!empty($row['poster'])): ?>

                            <img
                                src="../images/events/<?= htmlspecialchars($row['poster']) ?>"
                                class="poster"
                                alt="Event Poster">

                        <?php else: ?>

                            No Poster

                        <?php endif; ?>

                    </td>


                    <td>
                        <?= htmlspecialchars($row['title']) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['event_date']) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['event_time']) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['venue']) ?>
                    </td>


                    <td>

                        <a
                            href="events.php?edit=<?= $row['id'] ?>"
                            class="btn btn-edit">
                            Edit
                        </a>

                        <a
                            href="events.php?delete=<?= $row['id'] ?>"
                            class="btn btn-delete"
                            onclick="return confirm('Are you sure you want to delete this event?');">
                            Delete
                        </a>

                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>

                <td colspan="7"
                    style="text-align:center;">

                    No events found.

                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

</body>

</html>