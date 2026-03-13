<?php
session_start();

// Check if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// Assuming we have a function to get user profile data
function getUserProfile($userId) {
    // Here you would typically query the database to get user info
    // For demonstration, we’re returning a sample data array
    return [
        'id' => $userId,
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'created_at' => '2021-01-01',
    ];
}

$userId = $_SESSION['user_id'];
$userProfile = getUserProfile($userId);

header('Content-Type: application/json');
echo json_encode($userProfile);
?>