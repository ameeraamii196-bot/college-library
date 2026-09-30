<?php

function cleanInput($data)
{
return htmlspecialchars(trim($data));
}

function redirect($url)
{
header("Location: $url");
exit;
}

function recordActivity($conn, $user_id, $activity_type)
{
	$today = date("Y-m-d");

	$sql = "INSERT INTO user_activity
			(user_id, activity_date, activity_type)
			VALUES (?, ?, ?)
			ON DUPLICATE KEY UPDATE
			activity_type = VALUES(activity_type)";

	$stmt = $conn->prepare($sql);

	$stmt->bind_param(
		"iss",
		$user_id,
		$today,
		$activity_type
	);

	$stmt->execute();

	$stmt->close();
}

function getLibraryStreak($conn, $user_id)
{
	$sql = "SELECT activity_date
			FROM user_activity
			WHERE user_id=?
			ORDER BY activity_date DESC";

	$stmt = $conn->prepare($sql);
	$stmt->bind_param("i", $user_id);
	$stmt->execute();

	$result = $stmt->get_result();
	$dates = [];

	while ($row = $result->fetch_assoc()) {
		$dates[] = $row["activity_date"];
	}

	$stmt->close();

	if (empty($dates)) {
		return 0;
	}

	$streak = 0;
	$expected_date = new DateTime(date("Y-m-d"));

	foreach ($dates as $date) {
		$activity_date = new DateTime($date);

		if ($activity_date->format("Y-m-d") === $expected_date->format("Y-m-d")) {
			$streak++;
			$expected_date->modify("-1 day");
		} else {
			break;
		}
	}

	return $streak;
}

?>