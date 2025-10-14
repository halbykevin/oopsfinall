<?php
require __DIR__.'/db.php';
echo "✅ Connected as {$conn->host_info} | DB=".($conn->query("SELECT DATABASE() d")->fetch_assoc()['d']);
