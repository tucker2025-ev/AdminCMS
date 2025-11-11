<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $to = "support@tuckermotors.com"; // Replace with your common support email
    $subject = "New Support Request";

    $name = htmlspecialchars($_POST['name'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');

    $message = "Support Request Details:\n\n";
    $message .= "Name: $name\n";
    $message .= "Phone: $phone\n";
    $message .= "Description:\n$description\n";

    $headers = "From: support@tuckermotors.com";

    if (mail($to, $subject, $message, $headers)) {
        echo "Your request has been sent successfully.";
    } else {
        echo "Failed to send the request. Please try again.";
    }
} else {
    echo "Invalid request.";
}
