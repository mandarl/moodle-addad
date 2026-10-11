<?php
require(__DIR__ . '/../../config.php');
require_login();
echo $OUTPUT->header();
echo "Hello from my plugin!";
echo $OUTPUT->footer();
