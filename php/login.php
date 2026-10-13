<?php

session_start();

require_once "db.php";


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];


    // =========================
    // VALIDATION
    // =========================

    if (empty($email) || empty($password)) {

        header("Location: ../pages/login.html?status=empty");
        exit();

    }


    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        header("Location: ../pages/login.html?status=invalid");
        exit();

    }


    // =========================
    // FIND USER
    // =========================

    $stmt = $conn->prepare(
        "SELECT id, name, email, password, role
         FROM users
         WHERE email = ?"
    );

    $stmt->bind_param("s", $email);

    $stmt->execute();

    $result = $stmt->get_result();


    // User not found
    if ($result->num_rows === 0) {

        header("Location: ../pages/login.html?status=invalid");
        exit();

    }


    $user = $result->fetch_assoc();


    // =========================
    // VERIFY PASSWORD
    // =========================

    if (!password_verify($password, $user["password"])) {

        header("Location: ../pages/login.html?status=invalid");
        exit();

    }


    // =========================
    // REGENERATE SESSION ID
    // =========================

    session_regenerate_id(true);


    // =========================
    // CREATE SESSION
    // =========================

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["name"] = $user["name"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["last_activity"] = time();


    // =========================
    // ROLE-BASED REDIRECTION
    // =========================

    if ($user["role"] === "admin") {

        header("Location: ../pages/admin.php");
        exit();

    } else {

        header("Location: ../pages/dashboard.php");
        exit();

    }

}

?>