<?php
$cfg = require __DIR__ . "/../config/config.php";
$db  = $cfg["db"]["mysql"];

$conn = mysqli_init();
mysqli_real_connect(
    $conn,
    $db["host"],
    $db["user"],
    $db["pass"],
    $db["dbname"],
    $db["port"]
);

if (mysqli_connect_errno()) {
    fwrite(STDERR, "MySQL connect error: " . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

$migrations = require __DIR__ . "/../config/database.php";

foreach ($migrations as $k => $migs) {
    foreach ($migs as $m) {
        $sql = file_get_contents($m);
        if (!mysqli_multi_query($conn, $sql)) {
            fwrite(STDERR, "Error running $m: " . mysqli_error($conn) . PHP_EOL);
            exit(1);
        }
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
    }
}

echo "migrated\n";
